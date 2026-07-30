<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

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
        if ($user->isAdmin()) {
            return true;
        }

        if (! (bool) $announcement->is_active) {
            return false;
        }

        $audience = strtolower(trim((string) $announcement->audience));

        $allowedAudiences = match (true) {
            $user->isOfficial() => [
                'everyone',
                'public',
                'all',
                'official',
                'officials',
                'dao',
            ],
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

        return in_array($audience, $allowedAudiences, true);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
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
