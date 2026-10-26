<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\GenerateListingAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingAlertPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_live_listing_generates_an_in_app_alert_only(): void
    {
        Mail::fake();
        Notification::fake();
        $search = SavedSearch::factory()->create();

        $listing = Listing::factory()->live()->create();

        $this->assertDatabaseHas('alerts', ['user_id' => $search->user_id, 'listing_id' => $listing->id]);
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    #[DataProvider('nonLiveStates')]
    public function test_transitioning_to_live_generates_an_alert(string $state): void
    {
        SavedSearch::factory()->create();
        $listing = Listing::factory()->{$state}()->create();
        $this->assertDatabaseCount('alerts', 0);

        $listing->update(['status' => ListingStatus::Live]);

        $this->assertDatabaseCount('alerts', 1);
    }

    public static function nonLiveStates(): array
    {
        return [['draft'], ['underOffer'], ['sold']];
    }

    public function test_non_matching_publication_generates_no_alert(): void
    {
        SavedSearch::factory()->create(['max_price' => 300_000]);

        Listing::factory()->live()->create(['price' => 300_001]);

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_a_draft_created_before_the_search_can_match_when_published(): void
    {
        $listing = Listing::factory()->draft()->create();
        $this->travel(1)->day();
        $search = SavedSearch::factory()->create();

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()->subMonth()]);

        $this->assertDatabaseHas('alerts', ['user_id' => $search->user_id, 'listing_id' => $listing->id]);
    }

    public function test_ordinary_live_edits_do_not_invoke_alert_generation(): void
    {
        $listing = Listing::factory()->live()->create(['price' => 500_000]);
        SavedSearch::factory()->create(['max_price' => 300_000]);
        $this->mock(GenerateListingAlerts::class)->shouldNotReceive('generate');

        $listing->update(['price' => 250_000, 'listed_at' => now()]);
        $listing->update(['status' => ListingStatus::Live]);
        $listing->update(['status' => ListingStatus::Sold]);

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_repeat_publication_and_overlapping_searches_do_not_repeat_alerts(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory()->for($user)->create(['min_bedrooms' => 0]);
        SavedSearch::factory()->for($user)->create(['max_price' => 1_000_000]);
        $listing = Listing::factory()->live()->create();
        $original = Alert::query()->sole()->getAttributes();

        $this->travel(1)->day();
        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->update(['status' => ListingStatus::Live]);

        $this->assertSame($original, Alert::query()->sole()->getAttributes());
    }

    public function test_saving_a_search_does_not_backfill_existing_live_listings(): void
    {
        User::factory()->create();
        Listing::factory()->live()->create();

        $this->post('/saved-searches', ['min_bedrooms' => 0])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('saved_searches', 1);

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_deleting_a_search_stops_future_alerts_and_preserves_existing_ones(): void
    {
        $search = SavedSearch::factory()->create();
        $first = Listing::factory()->live()->create();

        $this->delete("/saved-searches/{$search->id}")->assertRedirect();
        $second = Listing::factory()->live()->create();

        $this->assertDatabaseCount('alerts', 1);
        $this->assertDatabaseHas('alerts', ['listing_id' => $first->id]);
        $this->assertDatabaseMissing('alerts', ['listing_id' => $second->id]);
    }

    public function test_alerts_wait_for_publication_to_commit(): void
    {
        SavedSearch::factory()->create(['max_price' => 300_000]);
        $listing = Listing::factory()->draft()->create(['price' => 250_000]);

        DB::transaction(function () use ($listing): void {
            $listing->update(['status' => ListingStatus::Live]);
            $this->assertDatabaseCount('alerts', 0);
        });

        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_rolling_back_publication_discards_alert_generation(): void
    {
        SavedSearch::factory()->create();
        $listing = Listing::factory()->draft()->create();

        DB::beginTransaction();
        $listing->update(['status' => ListingStatus::Live]);
        $this->assertDatabaseCount('alerts', 0);
        DB::rollBack();

        $this->assertSame(ListingStatus::Draft, $listing->fresh()->status);
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_rolling_back_a_live_creation_discards_alert_generation(): void
    {
        SavedSearch::factory()->create();

        DB::beginTransaction();
        $listing = Listing::factory()->live()->create();
        $this->assertDatabaseCount('alerts', 0);
        DB::rollBack();

        $this->assertModelMissing($listing);
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_a_listing_that_is_no_longer_live_at_commit_is_not_alerted(): void
    {
        SavedSearch::factory()->create();

        DB::transaction(function (): void {
            $listing = Listing::factory()->live()->create();
            $listing->update(['status' => ListingStatus::Sold]);
        });

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_a_listing_deleted_before_commit_is_not_alerted(): void
    {
        SavedSearch::factory()->create();

        DB::transaction(function (): void {
            Listing::factory()->live()->create()->delete();
        });

        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_live_creation_waits_for_commit_and_matches_searches_existing_at_commit(): void
    {
        SavedSearch::factory()->create();

        DB::transaction(function (): void {
            Listing::factory()->live()->create();
            $this->travel(1)->minute();
            SavedSearch::factory()->create();
            $this->assertDatabaseCount('alerts', 0);
        });

        $this->assertEqualsCanonicalizing(
            SavedSearch::query()->pluck('user_id')->all(),
            Alert::query()->pluck('user_id')->all(),
        );
    }
}
