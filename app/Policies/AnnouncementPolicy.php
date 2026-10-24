<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AnnouncementPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole([
            'admin',
            'official',
            'dao',
            'tanod',
            'resident',
        ]);
    }

    public function view(User $user, Announcement $announcement): bool
    {
        if ($user->isAdmin() || $user->isOfficial()) {
            return true;
        }

        if (! (bool) $announcement->is_active) {
            return false;
        }

        $audience = strtolower(
            trim((string) $announcement->audience)
        );

        $allowedAudiences = match (true) {
            $user->isTanod() => [
                'everyone',
                'public',
                'all',
                'tanod',
            ],
            $user->isResident() => [
                'everyone',
                'public',
                'all',
                'resident',
                'residents',
            ],
            default => [],
        };

        if (! in_array($audience, $allowedAudiences, true)) {
            return false;
        }

        if (! Schema::hasTable('announcement_barangay')) {
            return true;
        }

        $hasBarangayTargets = $announcement
            ->targetBarangays()
            ->exists();

        if (! $hasBarangayTargets) {
            // Existing announcements created before targeting stay visible.
            return true;
        }

        if (
            ! Schema::hasColumn('users', 'barangay_id')
            || empty($user->barangay_id)
        ) {
            return false;
        }

        return $announcement
            ->targetBarangays()
            ->where(
                'barangays.id',
                (int) $user->barangay_id
            )
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function restore(User $user, Announcement $announcement): bool
    {
        return false;
    }

    public function forceDelete(User $user, Announcement $announcement): bool
    {
        return false;
    }
}
