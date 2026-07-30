<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    /**
     * Reject inactive accounts even if route middleware changes later.
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
        return $user->isAdmin()
            || $user->isOfficial()
            || $user->isTanod()
            || $user->isResident();
    }

    public function view(User $user, Incident $incident): bool
    {
        if ($user->isAdmin() || $user->isOfficial()) {
            return true;
        }

        if ($this->isAssignedTanod($user, $incident)) {
            return true;
        }

        return $this->residentOwnsIncident($user, $incident);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->isOfficial()
            || $user->isResident();
    }

    public function update(User $user, Incident $incident): bool
    {
        if ($user->isAdmin() || $user->isOfficial()) {
            return true;
        }

        return $this->isAssignedTanod($user, $incident);
    }

    public function assign(User $user, Incident $incident): bool
    {
        return $user->isAdmin();
    }

    public function escalate(User $user, Incident $incident): bool
    {
        return $user->isAdmin() || $user->isOfficial();
    }

    public function addMessage(User $user, Incident $incident): bool
    {
        return $this->view($user, $incident);
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Incident $incident): bool
    {
        return false;
    }

    public function forceDelete(User $user, Incident $incident): bool
    {
        return false;
    }

    private function isAssignedTanod(User $user, Incident $incident): bool
    {
        if (! $user->isTanod()) {
            return false;
        }

        $employee = $user->employee;

        if (! $employee) {
            return false;
        }

        $employeeAttributes = $employee->getAttributes();

        if (
            array_key_exists('is_active', $employeeAttributes)
            && ! (bool) $employee->is_active
        ) {
            return false;
        }

        $assignedEmployeeId = (int) ($incident->assigned_to ?? 0);

        return $assignedEmployeeId > 0
            && $assignedEmployeeId === (int) $employee->id;
    }

    private function residentOwnsIncident(User $user, Incident $incident): bool
    {
        if (! $user->isResident()) {
            return false;
        }

        $userId = (int) $user->id;
        $incidentAttributes = $incident->getAttributes();

        foreach ([
            'reporter_id',
            'user_id',
            'created_by',
            'submitted_by',
            'reported_by',
            'resident_user_id',
        ] as $column) {
            if (
                array_key_exists($column, $incidentAttributes)
                && (int) ($incidentAttributes[$column] ?? 0) === $userId
            ) {
                return true;
            }
        }

        if (! array_key_exists('resident_id', $incidentAttributes)) {
            return false;
        }

        $allowedResidentIds = [$userId];
        $residentProfileId = $user->resident?->id;

        if ($residentProfileId) {
            $allowedResidentIds[] = (int) $residentProfileId;
        }

        $allowedResidentIds = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $allowedResidentIds)
                )
            )
        );

        return in_array(
            (int) ($incidentAttributes['resident_id'] ?? 0),
            $allowedResidentIds,
            true
        );
    }
}
