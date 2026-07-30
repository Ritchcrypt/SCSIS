<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserNotification;

class UserNotificationPolicy
{
    /**
     * Reject inactive accounts even if route middleware is changed later.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return null;
    }

    /**
     * The Tanod Alerts module is available to administrators and tanods.
     * Queries inside the controller remain restricted to the current user.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTanod();
    }

    /**
     * A notification may be opened only by its owner.
     */
    public function view(User $user, UserNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    /**
     * A notification may be changed only by its owner.
     */
    public function update(User $user, UserNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    /**
     * A notification may be acknowledged only by its owner.
     */
    public function acknowledge(User $user, UserNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    /**
     * A notification may be deleted only by its owner.
     */
    public function delete(User $user, UserNotification $notification): bool
    {
        return $this->owns($user, $notification);
    }

    /**
     * Bulk mark-as-read is permitted only inside the alert module.
     * The controller still scopes the update to the authenticated user.
     */
    public function markAllRead(User $user): bool
    {
        return $user->isAdmin() || $user->isTanod();
    }

    /**
     * Bulk deletion is permitted only inside the alert module.
     * The controller still scopes the deletion to the authenticated user.
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdmin() || $user->isTanod();
    }

    private function owns(User $user, UserNotification $notification): bool
    {
        return (int) $notification->user_id === (int) $user->id;
    }
}
