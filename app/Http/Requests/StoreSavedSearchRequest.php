<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasListingCriteriaRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSavedSearchRequest extends FormRequest
{
    use HasListingCriteriaRules;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->listingCriteriaRules();
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->anyFilled(['max_price', 'min_bedrooms', 'property_type', 'region'])) {
                $validator->errors()->add('criteria', 'Choose at least one search criterion.');
            }
        }];
    }
}
