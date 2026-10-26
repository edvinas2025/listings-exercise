<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\SavedSearch;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class GenerateListingAlerts
{
    public function generate(Listing $listing, CarbonInterface $publishedAt): int
    {
        if ($listing->status !== ListingStatus::Live) {
            return 0;
        }

        $createdAt = now()->toDateTimeString();

        $recipients = SavedSearch::query()
            ->matchingListing($listing)
            ->where('created_at', '<=', $publishedAt)
            ->select('user_id')
            ->selectRaw('? as listing_id, ? as created_at, ? as updated_at', [
                $listing->id, $createdAt, $createdAt,
            ])
            ->distinct();

        // Insert directly from the query without loading recipients into PHP.
        // The unique constraint protects against retries and overlapping searches.
        return DB::table('alerts')->insertOrIgnoreUsing(
            ['user_id', 'listing_id', 'created_at', 'updated_at'],
            $recipients,
        );
    }
}
