<?php

namespace Tests\Unit;

use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Tests\TestCase;

class SavedSearchResourceTest extends TestCase
{
    public function test_serializes_all_criteria_and_links_to_the_same_search(): void
    {
        $search = new SavedSearch;
        $search->forceFill([
            'id' => 7, 'user_id' => 12,
            'max_price' => '255000', 'min_bedrooms' => '2',
            'property_type' => 'semi_detached', 'region' => 'North West & Wales',
        ]);

        $data = (new SavedSearchResource($search))->resolve(Request::create('/saved-searches'));

        $this->assertSame([
            'id' => 7,
            'criteria' => [
                'max_price' => 255000, 'min_bedrooms' => 2,
                'property_type' => 'semi_detached', 'region' => 'North West & Wales',
            ],
            'property_type_label' => 'Semi-detached',
            'listing_url' => route('listings.index').'?max_price=255000&min_bedrooms=2'
                .'&property_type=semi_detached&region=North%20West%20%26%20Wales',
        ], $data);
    }

    public function test_search_link_preserves_zero_values_and_omits_null_criteria(): void
    {
        $search = new SavedSearch;
        $search->forceFill([
            'id' => 8, 'max_price' => 0, 'min_bedrooms' => 0,
            'property_type' => null, 'region' => null,
        ]);

        $data = (new SavedSearchResource($search))->resolve(Request::create('/saved-searches'));

        $this->assertSame([
            'id' => 8,
            'criteria' => [
                'max_price' => 0, 'min_bedrooms' => 0,
                'property_type' => null, 'region' => null,
            ],
            'property_type_label' => null,
            'listing_url' => route('listings.index').'?max_price=0&min_bedrooms=0',
        ], $data);
    }
}
