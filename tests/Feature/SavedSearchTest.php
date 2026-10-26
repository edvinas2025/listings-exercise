<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_criteria_are_persisted_for_the_owner(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $search = $user->savedSearches()->create([
            'max_price' => 250000,
            'min_bedrooms' => 2,
            'property_type' => PropertyType::Flat,
            'region' => 'North West',
        ])->refresh();

        $this->assertSame(250000, $search->max_price);
        $this->assertSame(2, $search->min_bedrooms);
        $this->assertSame(PropertyType::Flat, $search->property_type);
        $this->assertSame('North West', $search->region);
        $this->assertSame($user->id, $search->user_id);
        $this->assertTrue($search->user->is($user));
        $this->assertTrue($search->created_at->equalTo(now()));
        $this->assertTrue($search->updated_at->equalTo(now()));
    }

    #[DataProvider('zeroCriteria')]
    public function test_omitted_criteria_remain_null_and_zero_is_preserved(string $field): void
    {
        $search = User::factory()->create()->savedSearches()->create([
            $field => 0,
        ])->refresh();

        $this->assertSame($field === 'min_bedrooms' ? 0 : null, $search->min_bedrooms);
        $this->assertSame($field === 'max_price' ? 0 : null, $search->max_price);
        $this->assertNull($search->property_type);
        $this->assertNull($search->region);
    }

    public static function zeroCriteria(): array
    {
        return ['price' => ['max_price'], 'bedrooms' => ['min_bedrooms']];
    }

    public function test_user_relationship_only_returns_their_searches(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $search = $user->savedSearches()->create(['region' => 'North West']);
        $otherUser->savedSearches()->create(['region' => 'North West']);

        $this->assertSame([$search->id], $user->savedSearches()->pluck('id')->all());
    }

    public function test_deleting_a_user_removes_only_their_searches(): void
    {
        $user = User::factory()->create();
        $search = $user->savedSearches()->create(['region' => 'North West']);
        $otherSearch = User::factory()->create()->savedSearches()->create(['region' => 'North West']);

        $user->delete();

        $this->assertDatabaseMissing('saved_searches', ['id' => $search->id]);
        $this->assertDatabaseHas('saved_searches', ['id' => $otherSearch->id]);
    }
}
