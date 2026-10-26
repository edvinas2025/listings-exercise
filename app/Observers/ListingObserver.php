<?php

namespace App\Observers;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Services\GenerateListingAlerts;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ListingObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private GenerateListingAlerts $generateAlerts) {}

    public function created(Listing $listing): void
    {
        if ($listing->exists && $listing->status === ListingStatus::Live) {
            $this->generateAlerts->generate($listing, now());
        }
    }

    public function updated(Listing $listing): void
    {
        if ($listing->exists && $listing->wasChanged('status') && $listing->status === ListingStatus::Live) {
            $this->generateAlerts->generate($listing, now());
        }
    }
}
