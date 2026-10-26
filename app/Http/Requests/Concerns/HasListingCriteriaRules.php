<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PropertyType;
use Illuminate\Validation\Rules\Enum;

trait HasListingCriteriaRules
{
    /**
     * @return array<string, mixed>
     */
    protected function listingCriteriaRules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
        ];
    }
}
