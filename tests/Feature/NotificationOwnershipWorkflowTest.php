<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\NotificationBellService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationOwnershipWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_bell_returns_all_unread_notifications_without_twenty_item_cap(): void
    {
        $user = $this->activeUser('resident');

        foreach (range(1, 25) as $index) {
            $this->notification(
                $user,
                'announcement',
                1000 + $index
            );
        }

        $bell = app(NotificationBellService::class)
            ->forUser($user);

        $this->assertSame(25, $bell['unread_count']);
        $this->assertCount(25, $bell['notifications']);
    }

    public function test_read_all_changes_only_the_authenticated_users_notifications(): void
    {
        $first = $this->activeUser('resident');
        $second = $this->activeUser('resident');

        $this->notification($first, 'announcement', 2001);
        $this->notification($first, 'announcement', 2002);
        $secondNotification = $this->notification(
            $second,
            'announcement',
            2003
        );

        $updated = app(NotificationBellService::class)
            ->markAllUnreadAsRead($first);

        $this->assertSame(2, $updated);

        $this->assertSame(
            0,
            UserNotification::query()
                ->where('user_id', $first->id)
                ->where('is_read', false)
                ->count()
        );

        $this->assertFalse(
            (bool) $secondNotification->fresh()->is_read
        );
    }

    public function test_delete_removes_only_the_current_users_copy(): void
    {
        $first = $this->activeUser('resident');
        $second = $this->activeUser('resident');

        $firstCopy = $this->notification(
            $first,
            'announcement',
            3001
        );

        $this->notification(
            $first,
            'announcement',
            3001
        );

        $secondCopy = $this->notification(
            $second,
            'announcement',
            3001
        );

        app(NotificationBellService::class)
            ->deleteOwnedNotificationGroup(
                $first,
                $firstCopy
            );

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $first->id)
                ->where('type', 'announcement')
                ->where('source_id', 3001)
                ->exists()
        );

        $this->assertTrue(
            UserNotification::query()
                ->whereKey($secondCopy->id)
                ->exists()
        );
    }

    private function activeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
        ]);
    }

    private function notification(
        User $user,
        string $type,
        int $sourceId
    ): UserNotification {
        return UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'source_id' => $sourceId,
            'title' => 'Test notification',
            'message' => 'Recipient-owned test notification.',
            'is_read' => false,
            'read_at' => null,
        ]);
    }
}