<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\GenerateListingAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GenerateListingAlertsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('criteria')]
    public function test_alert_matching_agrees_with_browsing(array $criteria, bool $matches): void
    {
        $search = SavedSearch::factory()->create($criteria);
        $listing = $this->listing();

        $count = app(GenerateListingAlerts::class)->generate($listing, now());

        $this->assertSame($matches ? 1 : 0, $count);
        $this->assertDatabaseCount('alerts', $matches ? 1 : 0);
        if ($matches) {
            $this->assertDatabaseHas('alerts', ['user_id' => $search->user_id, 'listing_id' => $listing->id]);
        }

        $this->get('/?'.http_build_query($criteria))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('listings.data', $matches ? 1 : 0));
    }

    public static function criteria(): array
    {
        return [
            'null criteria' => [['max_price' => null, 'min_bedrooms' => null, 'region' => 'Manchester'], true],
            'price boundary' => [['max_price' => 300_000], true],
            'within budget' => [['max_price' => 300_001], true],
            'over budget' => [['max_price' => 299_999], false],
            'zero budget' => [['max_price' => 0], false],
            'bedroom boundary' => [['min_bedrooms' => 2], true],
            'more bedrooms' => [['min_bedrooms' => 1], true],
            'zero bedrooms' => [['min_bedrooms' => 0], true],
            'too few bedrooms' => [['min_bedrooms' => 3], false],
            'matching type' => [['property_type' => 'flat'], true],
            'wrong type' => [['property_type' => 'detached'], false],
            'matching region' => [['region' => 'Manchester'], true],
            'wrong region' => [['region' => 'Leeds'], false],
            'all criteria' => [[
                'max_price' => 300_000, 'min_bedrooms' => 2, 'property_type' => 'flat', 'region' => 'Manchester',
            ], true],
            'only price fails' => [[
                'max_price' => 299_999, 'min_bedrooms' => 2, 'property_type' => 'flat', 'region' => 'Manchester',
            ], false],
            'only bedrooms fail' => [[
                'max_price' => 300_000, 'min_bedrooms' => 3, 'property_type' => 'flat', 'region' => 'Manchester',
            ], false],
            'only type fails' => [[
                'max_price' => 300_000, 'min_bedrooms' => 2, 'property_type' => 'detached', 'region' => 'Manchester',
            ], false],
            'only region fails' => [[
                'max_price' => 300_000, 'min_bedrooms' => 2, 'property_type' => 'flat', 'region' => 'Leeds',
            ], false],
        ];
    }

    public function test_overlapping_searches_create_one_alert_per_user_without_sending_messages(): void
    {
        Mail::fake();
        Notification::fake();
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['max_price' => 300_000]);
        SavedSearch::factory()->for($user)->create(['region' => 'Manchester']);
        SavedSearch::factory()->for($user)->create(['min_bedrooms' => 2]);
        $other = SavedSearch::factory()->create(['region' => 'Manchester']);
        SavedSearch::factory()->create(['region' => 'Leeds']);
        $listing = $this->listing();

        $this->assertSame(2, app(GenerateListingAlerts::class)->generate($listing, now()));
        $this->assertDatabaseCount('alerts', 2);
        $this->assertDatabaseHas('alerts', ['user_id' => $user->id, 'listing_id' => $listing->id]);
        $this->assertDatabaseHas('alerts', ['user_id' => $other->user_id, 'listing_id' => $listing->id]);
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    public function test_generated_alert_starts_unsent_with_both_timestamps(): void
    {
        $this->freezeSecond();
        $search = SavedSearch::factory()->create();
        $listing = $this->listing();

        $this->assertSame(1, app(GenerateListingAlerts::class)->generate($listing, now()));

        $alert = Alert::query()->sole();
        $this->assertSame($search->user_id, $alert->user_id);
        $this->assertSame($listing->id, $alert->listing_id);
        $this->assertTrue($alert->created_at->equalTo(now()));
        $this->assertTrue($alert->updated_at->equalTo(now()));
        $this->assertNull($alert->sent_at);
    }

    #[DataProvider('deliveryStates')]
    public function test_reprocessing_does_not_duplicate_or_change_an_alert(bool $sent): void
    {
        $this->freezeSecond();
        SavedSearch::factory()->create();
        $listing = $this->listing();
        $publishedAt = now();
        $action = app(GenerateListingAlerts::class);
        $action->generate($listing, $publishedAt);

        if ($sent) {
            $this->travel(1)->hour();
            Alert::query()->sole()->update(['sent_at' => now()]);
        }

        $original = Alert::query()->sole()->getAttributes();

        $this->travel(1)->day();

        $this->assertSame(0, $action->generate($listing, $publishedAt));
        $this->assertSame($original, Alert::query()->sole()->getAttributes());
    }

    public static function deliveryStates(): array
    {
        return ['unsent' => [false], 'sent' => [true]];
    }

    public function test_publication_cutoff_includes_equal_timestamps_and_excludes_later_searches(): void
    {
        $this->freezeSecond();
        $publishedAt = now()->toImmutable();
        $earlier = SavedSearch::factory()->create(['created_at' => $publishedAt->subSecond()]);
        $sameTime = SavedSearch::factory()->create(['created_at' => $publishedAt]);
        SavedSearch::factory()->create(['created_at' => $publishedAt->addSecond()]);
        $listing = $this->listing();

        $this->travel(1)->hour();

        $this->assertSame(2, app(GenerateListingAlerts::class)->generate($listing, $publishedAt));
        $this->assertEqualsCanonicalizing(
            [$earlier->user_id, $sameTime->user_id],
            Alert::query()->pluck('user_id')->all(),
        );
    }

    public function test_searches_created_after_publication_are_excluded_when_reprocessing(): void
    {
        SavedSearch::factory()->create();
        $listing = $this->listing();
        $publishedAt = now();
        $action = app(GenerateListingAlerts::class);
        $action->generate($listing, $publishedAt);

        $this->travel(1)->minute();
        $later = SavedSearch::factory()->create();

        $this->assertSame(0, $action->generate($listing, $publishedAt));
        $this->assertDatabaseMissing('alerts', ['user_id' => $later->user_id]);
    }

    #[DataProvider('nonLiveStatuses')]
    public function test_non_live_listings_do_not_generate_alerts(ListingStatus $status): void
    {
        SavedSearch::factory()->create();

        $this->assertSame(0, app(GenerateListingAlerts::class)->generate($this->listing(['status' => $status]), now()));
        $this->assertDatabaseCount('alerts', 0);
    }

    public static function nonLiveStatuses(): array
    {
        return [[ListingStatus::Draft], [ListingStatus::UnderOffer], [ListingStatus::Sold]];
    }

    public function test_deleting_a_search_retains_existing_alerts(): void
    {
        $search = SavedSearch::factory()->create();
        app(GenerateListingAlerts::class)->generate($this->listing(), now());

        $search->delete();

        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_query_count_stays_bounded_as_searches_and_recipients_increase(): void
    {
        SavedSearch::factory()->create();
        $listing = $this->listing();
        $action = app(GenerateListingAlerts::class);
        DB::enableQueryLog();
        $this->assertSame(1, $action->generate($listing, now()));
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $users = User::factory(30)->create();
        foreach ($users as $user) {
            SavedSearch::factory(3)->for($user)->create();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->assertSame(30, $action->generate($listing, now()));
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($smallCount, $largeCount);
        $this->assertLessThanOrEqual(2, $largeCount);
        $this->assertDatabaseCount('alerts', 31);
    }

    private function listing(array $attributes = []): Listing
    {
        // These tests exercise the service directly, independently of publication hooks.
        return Listing::withoutEvents(fn () => Listing::factory()->live()
            ->for(Branch::factory()->create(['region' => 'Manchester']))
            ->create([
                'price' => 300_000, 'bedrooms' => 2, 'property_type' => PropertyType::Flat,
                'city' => 'Leeds',
                ...$attributes,
            ]));
    }
}
