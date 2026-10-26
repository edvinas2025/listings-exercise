<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AlertPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_exposes_only_the_current_users_alerts(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $alert = Alert::factory()->for($user)->create();
        Alert::factory()->create();

        $this->get('/alerts')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 1)
                ->where('alerts.data.0.id', $alert->id)
                ->where('alerts.data.0.created_at', now()->toIso8601String())
                ->where('alerts.data.0.listing.id', $alert->listing_id)
                ->where('alerts.data.0.listing.branch.name', $alert->listing->branch->name)
                ->missing('alerts.data.0.user_id')
            );
    }

    public function test_alerts_are_paginated_newest_first_even_when_timestamps_tie(): void
    {
        $user = User::factory()->create();
        $alerts = Alert::factory(16)->for($user)->create(['created_at' => now()]);

        $this->get('/alerts')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 15)
                ->where('alerts.data.0.id', $alerts->last()->id)
                ->where('alerts.meta.total', 16)
            );

        $this->get('/alerts?page=2')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.id', $alerts->first()->id)
            );
    }

    public function test_the_index_handles_an_empty_feed(): void
    {
        User::factory()->create();

        $this->get('/alerts')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')->has('alerts.data', 0)
            );
    }

    public function test_the_index_requires_a_resolved_user(): void
    {
        $this->get('/alerts')->assertForbidden();
    }

    #[DataProvider('nonLiveStatuses')]
    public function test_alerts_do_not_expose_details_of_a_listing_that_is_no_longer_live(ListingStatus $status): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->for($user)->create();
        $alert->listing->update(['status' => $status]);

        $this->get('/alerts')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.listing', null)
            );
        $this->get("/listings/{$alert->listing_id}")->assertNotFound();
    }

    public static function nonLiveStatuses(): array
    {
        return [[ListingStatus::Draft], [ListingStatus::UnderOffer], [ListingStatus::Sold]];
    }

    public function test_deleted_listings_remove_their_alerts(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->for($user)->create();

        $alert->listing->delete();

        $this->assertModelMissing($alert);
        $this->get('/alerts')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 0));
    }

    public function test_listing_and_branch_query_counts_do_not_grow_with_the_feed(): void
    {
        $user = User::factory()->create();
        Alert::factory()->for($user)->create();

        $small = $this->feedQueries();
        Alert::factory(14)->for($user)->create();
        $large = $this->feedQueries();

        $this->assertCount(count($small), $large);
        $this->assertCount(1, array_filter($large, fn ($query) => str_contains($query, 'from "listings"')));
        $this->assertCount(1, array_filter($large, fn ($query) => str_contains($query, 'from "branches"')));
    }

    public function test_a_buyer_can_save_a_search_and_see_a_new_matching_property_once(): void
    {
        User::factory()->create();
        $criteria = [
            'region' => 'Manchester', 'max_price' => 300_000,
            'min_bedrooms' => 2, 'property_type' => 'flat',
        ];
        $this->post('/saved-searches', $criteria)->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/saved-searches', ['region' => 'Manchester'])->assertSessionHasNoErrors()->assertRedirect();

        $listing = Listing::factory()->draft()
            ->for(Branch::factory()->create(['region' => 'Manchester']))
            ->create(['price' => 250_000, 'bedrooms' => 3, 'property_type' => PropertyType::Flat]);
        $this->get('/alerts')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 0));

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        $this->get('/alerts')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.listing.id', $listing->id)
            );
        $this->get("/listings/{$listing->id}")->assertOk();
    }

    private function feedQueries(): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/alerts')->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return $queries;
    }
}
