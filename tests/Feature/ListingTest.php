<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_scope_returns_only_live_listings(): void
    {
        Listing::factory(2)->live()->create();
        Listing::factory()->create();                 // draft
        Listing::factory()->sold()->create();
        Listing::factory()->underOffer()->create();

        $live = Listing::query()->live()->get();

        $this->assertCount(2, $live);
        $this->assertTrue($live->every(fn (Listing $l) => $l->status === ListingStatus::Live));
    }

    public function test_property_type_and_status_are_cast_to_enums(): void
    {
        $listing = Listing::factory()->live()->create(['property_type' => 'semi_detached'])->refresh();

        $this->assertInstanceOf(ListingStatus::class, $listing->status);
        $this->assertSame(ListingStatus::Live, $listing->status);
        $this->assertSame(PropertyType::SemiDetached, $listing->property_type);
    }
}
