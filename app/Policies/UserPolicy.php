<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private const DELETED_USER_EMAIL = 'deleted-user@tabangnow.local';

    /**
     * Reject inactive actors even if route middleware changes later.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isActive()) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin() && $this->isManageable($target);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin() && $this->isManageable($target);
    }

    public function activate(User $user, User $target): bool
    {
        return $user->isAdmin() && $this->isManageable($target);
    }

    public function deactivate(User $user, User $target): bool
    {
        return $user->isAdmin()
            && (int) $user->id !== (int) $target->id
            && $this->isManageable($target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->isAdmin() && $this->isManageable($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin()
            && (int) $user->id !== (int) $target->id
            && $this->isManageable($target);
    }

    public function export(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Preserve the established privacy rule: users may view their own profile
     * photo, while administrators may view profile photos for user management.
     */
    public function viewProfilePhoto(User $user, User $target): bool
    {
        return (int) $user->id === (int) $target->id
            || $user->isAdmin();
    }

    private function isManageable(User $target): bool
    {
        return strcasecmp(
            (string) $target->email,
            self::DELETED_USER_EMAIL
        ) !== 0;
    }
}
