<?php

namespace App\Policies;

use App\Models\TanodProfile;
use App\Models\User;

class TanodProfilePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function view(User $user, TanodProfile $tanodProfile): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function update(User $user, TanodProfile $tanodProfile): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function delete(User $user, TanodProfile $tanodProfile): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function restore(User $user, TanodProfile $tanodProfile): bool
    {
        return false;
    }

    public function forceDelete(User $user, TanodProfile $tanodProfile): bool
    {
        return false;
    }
}
