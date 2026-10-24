<?php

namespace App\Policies;

use App\Models\DeletedDataArchive;
use App\Models\User;

class DeletedDataArchivePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveAdministrator($user);
    }

    public function view(User $user, DeletedDataArchive $archive): bool
    {
        return $this->isActiveAdministrator($user);
    }

    public function restore(User $user, DeletedDataArchive $archive): bool
    {
        return $this->isActiveAdministrator($user) && ! $archive->is_restored;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DeletedDataArchive $archive): bool
    {
        return false;
    }

    public function delete(User $user, DeletedDataArchive $archive): bool
    {
        return false;
    }

    public function forceDelete(User $user, DeletedDataArchive $archive): bool
    {
        return false;
    }

    private function isActiveAdministrator(User $user): bool
    {
        return $user->isActive() && $user->isAdmin();
    }
}
