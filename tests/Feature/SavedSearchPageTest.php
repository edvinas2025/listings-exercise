<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SavedSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_search_for_current_user_ignoring_supplied_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $criteria = [
            'max_price' => 255000, 'min_bedrooms' => 2,
            'property_type' => 'semi_detached', 'region' => 'Greater Manchester',
        ];

        $this->post('/saved-searches', [
            'max_price' => '255000', 'min_bedrooms' => '2',
            'property_type' => 'semi_detached', 'region' => ' Greater Manchester ',
            'user_id' => $other->id,
        ])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('saved_searches', ['user_id' => $user->id, ...$criteria]);
        $this->assertDatabaseCount('saved_searches', 1);

        $search = $user->savedSearches()->sole();
        $this->get('/saved-searches')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SavedSearches/Index')
            ->has('savedSearches.data', 1)
            ->where('savedSearches.data.0.id', $search->id)
            ->where('savedSearches.data.0.criteria', $criteria)
            ->where('savedSearches.data.0.property_type_label', 'Semi-detached')
            ->where('savedSearches.data.0.listing_url', route('listings.index', $criteria))
            ->missing('savedSearches.data.0.user_id'));
    }

    #[DataProvider('validCriteria')]
    public function test_each_criterion_can_be_saved_on_its_own(string $field, mixed $value): void
    {
        User::factory()->create();
        $this->post('/saved-searches', [$field => $value])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('saved_searches', [$field => $value]);
    }

    /** @return array<string, array{string, int|string}> */
    public static function validCriteria(): array
    {
        return [
            'price zero' => ['max_price', 0],
            'price outside ten-thousand increments' => ['max_price', 255000],
            'bedrooms zero' => ['min_bedrooms', 0],
            'maximum bedrooms' => ['min_bedrooms', 20],
            'type' => ['property_type', 'flat'],
            'detached' => ['property_type', 'detached'],
            'semi-detached' => ['property_type', 'semi_detached'],
            'terraced' => ['property_type', 'terraced'],
            'bungalow' => ['property_type', 'bungalow'],
            'region' => ['region', 'Manchester'],
            'maximum region length' => ['region', str_repeat('a', 100)],
        ];
    }

    public function test_empty_search_is_rejected(): void
    {
        User::factory()->create();
        $this->post('/saved-searches', ['region' => ' ', 'max_price' => null])
            ->assertSessionHasErrors('criteria');
        $this->assertDatabaseCount('saved_searches', 0);
    }

    #[DataProvider('invalidCriteria')]
    public function test_invalid_criteria_are_rejected(string $field, mixed $value): void
    {
        User::factory()->create();
        $this->post('/saved-searches', [$field => $value])->assertSessionHasErrors($field);
        $this->assertDatabaseCount('saved_searches', 0);
    }

    public static function invalidCriteria(): array
    {
        return [
            'negative price' => ['max_price', -1],
            'fractional price' => ['max_price', '250000.5'],
            'non-numeric price' => ['max_price', 'cheap'],
            'negative bedrooms' => ['min_bedrooms', -1],
            'too many bedrooms' => ['min_bedrooms', 21],
            'fractional bedrooms' => ['min_bedrooms', '1.5'],
            'non-numeric bedrooms' => ['min_bedrooms', 'two'],
            'unknown type' => ['property_type', 'castle'],
            'region too long' => ['region', str_repeat('a', 101)],
            'non-string region' => ['region', ['Manchester']],
        ];
    }

    public function test_duplicate_criteria_are_rejected_after_normalizing_blanks_and_numbers(): void
    {
        User::factory()->create()->savedSearches()->create(['max_price' => 250000, 'region' => 'Manchester']);
        $this->post('/saved-searches', [
            'max_price' => '250000', 'region' => ' Manchester ', 'property_type' => '', 'min_bedrooms' => null,
        ])->assertSessionHasErrors('criteria');
        $this->assertDatabaseCount('saved_searches', 1);
    }

    public function test_overlapping_searches_and_another_users_identical_search_are_allowed(): void
    {
        $user = User::factory()->create();
        User::factory()->create()->savedSearches()->create(['region' => 'Manchester']);
        $user->savedSearches()->create(['region' => 'Manchester', 'max_price' => 250000]);

        $this->post('/saved-searches', ['region' => 'Manchester'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('saved_searches', 3);
    }

    #[DataProvider('changedCriteria')]
    public function test_changing_any_criterion_makes_a_search_distinct(string $field, mixed $value): void
    {
        $criteria = [
            'max_price' => 300000, 'min_bedrooms' => 2,
            'property_type' => 'flat', 'region' => 'Manchester',
        ];
        User::factory()->create()->savedSearches()->create($criteria);

        $this->post('/saved-searches', $criteria)->assertSessionHasErrors('criteria');
        $this->assertDatabaseCount('saved_searches', 1);

        $criteria[$field] = $value;
        $this->post('/saved-searches', $criteria)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('saved_searches', 2);
        $this->assertDatabaseHas('saved_searches', $criteria);
    }

    public static function changedCriteria(): array
    {
        return [
            'price' => ['max_price', 300001],
            'bedrooms' => ['min_bedrooms', 3],
            'type' => ['property_type', 'detached'],
            'region' => ['region', 'Leeds'],
        ];
    }

    public function test_zero_is_distinct_from_an_omitted_criterion_when_checking_duplicates(): void
    {
        User::factory()->create()->savedSearches()->create(['region' => 'Manchester']);

        $criteria = ['region' => 'Manchester', 'min_bedrooms' => '0'];
        $this->post('/saved-searches', $criteria)->assertSessionHasNoErrors();
        $this->post('/saved-searches', $criteria)->assertSessionHasErrors('criteria');

        $this->assertDatabaseCount('saved_searches', 2);
    }

    public function test_page_only_shows_own_searches_and_paginates(): void
    {
        $user = User::factory()->create();
        User::factory()->create()->savedSearches()->create(['region' => 'Private']);
        for ($i = 1; $i <= 16; $i++) {
            $user->savedSearches()->create(['min_bedrooms' => $i]);
        }

        $this->get('/saved-searches')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SavedSearches/Index')->has('savedSearches.data', 15)
            ->where('savedSearches.meta.total', 16)
            ->where('savedSearches.data.0.criteria.min_bedrooms', 16));
        $this->get('/saved-searches?page=2')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('savedSearches.data', 1)->where('savedSearches.data.0.criteria.min_bedrooms', 1));
    }

    public function test_can_delete_own_search_but_not_another_users_search(): void
    {
        $user = User::factory()->create();
        $own = $user->savedSearches()->create(['region' => 'Manchester']);
        $other = User::factory()->create()->savedSearches()->create(['region' => 'Manchester']);

        $this->delete(route('saved-searches.delete', $other->id))->assertNotFound();
        $this->delete(route('saved-searches.delete', $own->id))->assertRedirect();
        $this->assertDatabaseMissing('saved_searches', ['id' => $own->id]);
        $this->assertDatabaseHas('saved_searches', ['id' => $other->id]);
    }

    public function test_index_handles_a_user_without_saved_searches(): void
    {
        User::factory()->create();

        $this->get('/saved-searches')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('SavedSearches/Index')->has('savedSearches.data', 0));
    }

    public function test_requests_without_a_user_are_forbidden(): void
    {
        $this->get('/saved-searches')->assertForbidden();
        $this->post('/saved-searches', ['region' => 'Manchester'])->assertForbidden();
        $this->delete('/saved-searches/1')->assertForbidden();
    }
}
