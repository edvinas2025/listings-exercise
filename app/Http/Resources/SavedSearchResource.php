<?php

namespace App\Http\Resources;

use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $criteria = [
            'max_price' => $this->max_price,
            'min_bedrooms' => $this->min_bedrooms,
            'property_type' => $this->property_type?->value,
            'region' => $this->region,
        ];

        return [
            'id' => $this->id,
            'criteria' => $criteria,
            'property_type_label' => $this->property_type?->label(),
            'listing_url' => route('listings.index', array_filter($criteria, fn ($value) => $value !== null)),
        ];
    }
}
