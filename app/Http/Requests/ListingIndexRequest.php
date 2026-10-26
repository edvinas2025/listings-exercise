<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasListingCriteriaRules;
use Illuminate\Foundation\Http\FormRequest;

class ListingIndexRequest extends FormRequest
{
    use HasListingCriteriaRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->listingCriteriaRules(),
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
