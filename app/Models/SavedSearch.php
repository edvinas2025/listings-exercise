<?php

namespace App\Models;

use App\Enums\PropertyType;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property PropertyType|null $property_type
 * @property string|null $region
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    protected $fillable = ['max_price', 'min_bedrooms', 'property_type', 'region'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
            'property_type' => PropertyType::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Null criteria are unrestricted. Every supplied criterion must match.
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeMatchingListing(Builder $query, Listing $listing): void
    {
        $region = $listing->branch()->value('region');

        $query
            ->where(fn (Builder $query) => $query
                ->whereNull('max_price')->orWhere('max_price', '>=', $listing->price))
            ->where(fn (Builder $query) => $query
                ->whereNull('min_bedrooms')->orWhere('min_bedrooms', '<=', $listing->bedrooms))
            ->where(fn (Builder $query) => $query
                ->whereNull('property_type')->orWhere('property_type', $listing->property_type))
            ->where(fn (Builder $query) => $query
                ->whereNull('region')->orWhere('region', $region));
    }
}
