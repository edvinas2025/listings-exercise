<?php

namespace App\Http\Resources;

use App\Enums\ListingStatus;
use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toIso8601String(),
            // Preserve the existing rule that non-live listing details are not public.
            'listing' => $this->listing->status === ListingStatus::Live
                ? new ListingResource($this->listing)
                : null,
        ];
    }
}
