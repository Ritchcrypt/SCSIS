<?php

namespace App\Policies;

use App\Models\ResidentComplaint;
use App\Models\User;

class ResidentComplaintPolicy
{
    /**
     * Determine whether the user may view the complaint list.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || $user->isOfficial()
            || $user->isResident();
    }

    /**
     * Determine whether the user may view a specific complaint.
     *
     * Administrators and barangay officials may view all complaints.
     * Residents may view only complaints they submitted.
     */
    public function view(
        User $user,
        ResidentComplaint $residentComplaint
    ): bool {
        if ($user->isAdmin() || $user->isOfficial()) {
            return true;
        }

        return $user->isResident()
            && (int) $residentComplaint->resident_id === (int) $user->id;
    }

    /**
     * Only residents may submit resident complaints.
     */
    public function create(User $user): bool
    {
        return $user->isResident();
    }

    /**
     * Administrators and barangay officials may update complaint status
     * and upload resolution proof.
     */
    public function update(
        User $user,
        ResidentComplaint $residentComplaint
    ): bool {
        return $user->isAdmin() || $user->isOfficial();
    }

    /**
     * Complaint deletion is administrator-only.
     *
     * Officials may process complaints but may not permanently delete them.
     */
    public function delete(
        User $user,
        ResidentComplaint $residentComplaint
    ): bool {
        return $user->isAdmin();
    }

    /**
     * Restoring deleted complaints is not currently supported.
     */
    public function restore(
        User $user,
        ResidentComplaint $residentComplaint
    ): bool {
        return false;
    }

    /**
     * Permanent force deletion is not currently supported.
     */
    public function forceDelete(
        User $user,
        ResidentComplaint $residentComplaint
    ): bool {
        return false;
    }
}