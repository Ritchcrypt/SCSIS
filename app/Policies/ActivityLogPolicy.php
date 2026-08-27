<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy
{
    /**
     * Inactive users are denied before any ability-specific decision.
     */
    public function before(
        User $user,
        string $ability
    ): ?bool {
        if (! $user->isActive()) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(
        User $user,
        ActivityLog $activityLog
    ): bool {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(
        User $user,
        ActivityLog $activityLog
    ): bool {
        return false;
    }


    /**
     * Administrators may intentionally reset the complete audit trail.
     * Individual log records remain immutable and non-deletable.
     */
    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
    public function delete(
        User $user,
        ActivityLog $activityLog
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        ActivityLog $activityLog
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        ActivityLog $activityLog
    ): bool {
        return false;
    }
}
