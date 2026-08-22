<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Tests\Concerns\SpaAuthentication;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase, SpaAuthentication;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function notifyUser(User $user, string $title, bool $read = false, ?string $createdAt = null): DatabaseNotification
    {
        $user->notify(new class($title) extends Notification
        {
            public function __construct(private readonly string $title) {}

            public function via($notifiable): array
            {
                return ['database'];
            }

            public function toArray($notifiable): array
            {
                return [
                    'type' => 'application.approved',
                    'title' => $this->title,
                    'message' => 'Test message.',
                    'action_url' => '/student/applications/1',
                    'application_id' => 1,
                ];
            }
        });

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()
            ->where('data->title', $title)
            ->firstOrFail();

        if ($createdAt !== null) {
            // Second-precision columns mean same-second rows need explicit
            // timestamps to exercise recency ordering deterministically.
            DatabaseNotification::query()->whereKey($notification->id)->update([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        if ($read) {
            $notification->markAsRead();
        }

        return $notification;
    }

    // ============================================================
    // AUTHENTICATION / AUTHORIZATION
    // ============================================================

    public function test_guest_cannot_list_notifications(): void
    {
        $this->fromSpa()->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_guest_cannot_fetch_unread_count(): void
    {
        $this->fromSpa()->getJson('/api/notifications/unread-count')->assertUnauthorized();
    }

    public function test_guest_cannot_mark_notifications_as_read(): void
    {
        $user = User::factory()->student()->create();
        $notification = $this->notifyUser($user, 'Guest attempt');

        $this->fromSpa()
            ->patchJson('/api/notifications/'.$notification->id.'/read')
            ->assertUnauthorized();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_retrieve_another_users_notification(): void
    {
        $owner = User::factory()->student()->create();
        $attacker = User::factory()->staff()->create();
        $foreign = $this->notifyUser($owner, 'Foreign notification');

        $this->loginAs($attacker)->assertOk();

        $this->fromSpa()
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->fromSpa()
            ->patchJson('/api/notifications/'.$foreign->id.'/read')
            ->assertNotFound();

        $this->fromSpa()
            ->deleteJson('/api/notifications/'.$foreign->id)
            ->assertNotFound();

        // Untouched: still unread and still stored.
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertDatabaseHas('notifications', ['id' => $foreign->id]);
    }

    public function test_marking_all_read_only_affects_the_authenticated_user(): void
    {
        $userA = User::factory()->student()->create();
        $userB = User::factory()->student()->create();

        $this->notifyUser($userA, 'A unread one');
        $this->notifyUser($userA, 'A unread two');
        $bForeign = $this->notifyUser($userB, 'B stays unread');

        $this->loginAs($userA)->assertOk();

        $this->fromSpa()
            ->patchJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('count', 0);

        foreach ($userA->notifications as $notification) {
            $this->assertNotNull($notification->fresh()->read_at);
        }

        $this->assertNull($bForeign->fresh()->read_at);
    }

    // ============================================================
    // LIST / PAGINATION
    // ============================================================

    public function test_user_sees_only_own_notifications_newest_first(): void
    {
        $user = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        $this->notifyUser($user, 'First', createdAt: now()->subMinute()->toDateTimeString());
        $this->notifyUser($user, 'Second');
        $this->notifyUser($other, 'Not mine');

        $this->loginAs($user)->assertOk();

        $response = $this->fromSpa()
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertSame(['Second', 'First'], $titles);
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_notification_list_is_paginated(): void
    {
        $user = User::factory()->student()->create();
        for ($i = 1; $i <= 25; $i++) {
            $this->notifyUser($user, "Notification {$i}");
        }

        $this->loginAs($user)->assertOk();

        $page = $this->fromSpa()
            ->getJson('/api/notifications?page=2&per_page=10')
            ->assertOk();

        $this->assertCount(10, $page->json('data'));
        $this->assertSame(2, $page->json('meta.current_page'));
        $this->assertSame(3, $page->json('meta.last_page'));
        $this->assertSame(10, $page->json('meta.per_page'));
        $this->assertSame(25, $page->json('meta.total'));
    }

    public function test_unread_filter_returns_only_unread(): void
    {
        $user = User::factory()->student()->create();
        $this->notifyUser($user, 'Unread one');
        $this->notifyUser($user, 'Already read', read: true);
        $this->notifyUser($user, 'Unread two');

        $this->loginAs($user)->assertOk();

        $response = $this->fromSpa()
            ->getJson('/api/notifications?unread=1')
            ->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->values()->all();
        $this->assertEqualsCanonicalizing(['Unread one', 'Unread two'], $titles);
    }

    public function test_read_notifications_remain_in_history(): void
    {
        $user = User::factory()->student()->create();
        $notification = $this->notifyUser($user, 'Read but kept');
        $notification->markAsRead();

        $this->loginAs($user)->assertOk();

        $this->fromSpa()
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    // ============================================================
    // UNREAD COUNT
    // ============================================================

    public function test_unread_count_is_accurate_and_scoped(): void
    {
        $user = User::factory()->student()->create();
        $other = User::factory()->student()->create();

        $this->notifyUser($user, 'U1');
        $this->notifyUser($user, 'U2');
        $this->notifyUser($user, 'R1', read: true);
        $this->notifyUser($other, 'Other user unread');

        $this->loginAs($user)->assertOk();

        $this->fromSpa()
            ->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_marking_as_read_decrements_the_unread_count(): void
    {
        $user = User::factory()->student()->create();
        $n1 = $this->notifyUser($user, 'One');
        $n2 = $this->notifyUser($user, 'Two');

        $this->loginAs($user)->assertOk();

        $this->fromSpa()->getJson('/api/notifications/unread-count')->assertJsonPath('count', 2);

        $this->fromSpa()
            ->patchJson('/api/notifications/'.$n1->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value) => $value !== null);

        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNull($n2->fresh()->read_at);

        $this->fromSpa()->getJson('/api/notifications/unread-count')->assertJsonPath('count', 1);

        // Marking the same notification read twice is harmless.
        $this->fromSpa()->patchJson('/api/notifications/'.$n1->id.'/read')->assertOk();
        $this->assertNotNull($n1->fresh()->read_at);
        $this->fromSpa()->getJson('/api/notifications/unread-count')->assertJsonPath('count', 1);
    }

    // ============================================================
    // MARK READ / DELETE (OWNER)
    // ============================================================

    public function test_owner_can_mark_a_notification_as_read(): void
    {
        $user = User::factory()->student()->create();
        $notification = $this->notifyUser($user, 'Mine to read');

        $this->loginAs($user)->assertOk();

        $this->assertNull($notification->fresh()->read_at);

        $this->fromSpa()
            ->patchJson('/api/notifications/'.$notification->id.'/read')
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);

        // Still present in history.
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_owner_can_delete_a_notification(): void
    {
        $user = User::factory()->student()->create();
        $kept = $this->notifyUser($user, 'Keep me');
        $deleted = $this->notifyUser($user, 'Delete me');

        $this->loginAs($user)->assertOk();

        $this->fromSpa()
            ->deleteJson('/api/notifications/'.$deleted->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('notifications', ['id' => $deleted->id]);
        $this->assertDatabaseHas('notifications', ['id' => $kept->id]);
    }

    // ============================================================
    // PRIVACY
    // ============================================================

    public function test_notification_payload_contains_no_sensitive_data(): void
    {
        $user = User::factory()->student()->create();
        $notification = $this->notifyUser($user, 'Privacy check');

        $raw = json_encode($notification->fresh()->toArray(), JSON_THROW_ON_ERROR);

        foreach (['password', 'remember_token', 'access_token', 'file_path', 'file_contents'] as $needle) {
            $this->assertStringNotContainsString($needle, $raw);
        }

        $payloadKeys = array_keys($notification->fresh()->data);
        $this->assertEqualsCanonicalizing(
            ['type', 'title', 'message', 'action_url', 'application_id'],
            $payloadKeys,
        );
    }
}
