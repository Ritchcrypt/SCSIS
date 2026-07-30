<?php

namespace App\Policies;

use App\Models\EmergencyHotline;
use App\Models\User;

class EmergencyHotlinePolicy
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

    public function view(User $user, EmergencyHotline $emergencyHotline): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function update(User $user, EmergencyHotline $emergencyHotline): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function delete(User $user, EmergencyHotline $emergencyHotline): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function restore(User $user, EmergencyHotline $emergencyHotline): bool
    {
        return false;
    }

    public function forceDelete(User $user, EmergencyHotline $emergencyHotline): bool
    {
        return false;
    }
}
