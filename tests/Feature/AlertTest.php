<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_links_a_user_to_a_listing_and_starts_unsent_with_timestamps(): void
    {
        $this->freezeSecond();
        $user = User::factory()->create();
        $listing = Listing::factory()->live()->create();

        $alert = $user->alerts()->create(['listing_id' => $listing->id])->refresh();

        $this->assertSame($user->id, $alert->user_id);
        $this->assertSame($listing->id, $alert->listing_id);
        $this->assertTrue($alert->user->is($user));
        $this->assertTrue($alert->listing->is($listing));
        $this->assertTrue($alert->created_at->equalTo(now()));
        $this->assertTrue($alert->updated_at->equalTo(now()));
        $this->assertNull($alert->sent_at);
    }

    public function test_sending_time_is_persisted_as_a_datetime_without_changing_creation_time(): void
    {
        $this->freezeSecond();
        $alert = Alert::factory()->create()->refresh();
        $createdAt = $alert->created_at->copy();

        $this->travel(1)->day();
        $sentAt = now();
        $alert->update(['sent_at' => $sentAt]);
        $alert->refresh();

        $this->assertInstanceOf(Carbon::class, $alert->sent_at);
        $this->assertTrue($alert->sent_at->equalTo($sentAt));
        $this->assertTrue($alert->created_at->equalTo($createdAt));
        $this->assertTrue($alert->updated_at->equalTo($sentAt));
    }

    public function test_database_rejects_another_alert_for_the_same_user_and_listing(): void
    {
        $alert = Alert::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Alert::query()->create(['user_id' => $alert->user_id, 'listing_id' => $alert->listing_id]);
    }

    public function test_a_listing_can_alert_different_users_and_a_user_can_have_different_listings(): void
    {
        $alert = Alert::factory()->create();
        $second = Alert::factory()->for($alert->user)->create();
        Alert::factory()->for($alert->listing)->create();

        $this->assertDatabaseCount('alerts', 3);
        $this->assertSame([$alert->id, $second->id], $alert->user->alerts()->orderBy('id')->pluck('id')->all());
    }

    public function test_deleting_a_user_removes_only_their_alerts(): void
    {
        $alert = Alert::factory()->create();
        $other = Alert::factory()->for($alert->listing)->create();

        $alert->user->delete();

        $this->assertModelMissing($alert);
        $this->assertModelExists($other);
        $this->assertModelExists($other->listing);
    }

    public function test_deleting_a_listing_removes_only_its_alerts(): void
    {
        $alert = Alert::factory()->create();
        $other = Alert::factory()->for($alert->user)->create();

        $alert->listing->delete();

        $this->assertModelMissing($alert);
        $this->assertModelExists($other);
        $this->assertModelExists($other->user);
    }
}
