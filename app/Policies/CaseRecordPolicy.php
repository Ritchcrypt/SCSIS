<?php

namespace App\Policies;

use App\Models\CaseRecord;
use App\Models\User;

class CaseRecordPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isActive() ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, CaseRecord $caseRecord): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, CaseRecord $caseRecord): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, CaseRecord $caseRecord): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, CaseRecord $caseRecord): bool
    {
        return false;
    }

    public function forceDelete(User $user, CaseRecord $caseRecord): bool
    {
        return false;
    }
}
