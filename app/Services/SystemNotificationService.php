<?php

namespace App\Services;

use App\Models\IncidentMessage;
use App\Models\MobileEmergencyAlert;
use App\Models\CaseRecord;
use App\Models\ResidentComplaint;
use App\Models\TanodTask;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SystemNotificationService
{
    public function activeUserIdsForRoles(array $roles): Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        $query = User::query()
            ->whereIn('role', array_values(array_unique($roles)));

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', true);
        } elseif (Schema::hasColumn('users', 'status')) {
            $query->where('status', true);
        }

        return $query
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    public function send(
        int $userId,
        string $type,
        ?int $sourceId,
        string $title,
        string $message,
        bool $replaceExisting = false
    ): ?UserNotification {
        if (
            $userId <= 0
            || ! Schema::hasTable('notifications')
            || ! Schema::hasColumn('notifications', 'user_id')
        ) {
            return null;
        }

        $payload = [
            'user_id' => $userId,
            'type' => trim($type),
            'source_id' => $sourceId,
            'title' => trim($title),
            'message' => trim($message),
            'is_read' => false,
        ];

        if (Schema::hasColumn('notifications', 'read_at')) {
            $payload['read_at'] = null;
        }

        if (Schema::hasColumn('notifications', 'acknowledged_by')) {
            $payload['acknowledged_by'] = null;
        }

        if (Schema::hasColumn('notifications', 'acknowledged_at')) {
            $payload['acknowledged_at'] = null;
        }

        if ($replaceExisting && $sourceId !== null) {
            return UserNotification::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'type' => trim($type),
                    'source_id' => $sourceId,
                ],
                $payload
            );
        }

        return UserNotification::query()->create($payload);
    }


    public function notifyCaseRecord(
        CaseRecord $caseRecord,
        string $event
    ): void {
        $event = strtolower(trim($event));

        if (! in_array($event, ['created', 'updated', 'deleted'], true)) {
            return;
        }

        $caseNumber = trim((string) $caseRecord->case_number);
        $caseLabel = $caseNumber !== ''
            ? 'Case #' . $caseNumber
            : 'Case record #' . (int) $caseRecord->id;

        $title = match ($event) {
            'created' => 'Case record created',
            'updated' => 'Case record updated',
            'deleted' => 'Case record deleted',
        };

        $message = match ($event) {
            'created' => $caseLabel . ' was created in Case Management.',
            'updated' => $caseLabel . ' was updated in Case Management.',
            'deleted' => $caseLabel . ' was deleted from Case Management.',
        };

        foreach ($this->activeUserIdsForRoles(['admin']) as $adminId) {
            $this->send(
                userId: $adminId,
                type: 'case_' . $event,
                sourceId: (int) $caseRecord->id,
                title: $title,
                message: $message,
                replaceExisting: false
            );
        }
    }

    public function notifyMobileEmergencyReporter(
        MobileEmergencyAlert $alert,
        string $event
    ): void {
        $userId = (int) $alert->user_id;

        if ($userId <= 0) {
            return;
        }

        $event = strtolower(trim($event));

        if (! in_array(
            $event,
            ['received', 'acknowledged', 'resolved'],
            true
        )) {
            return;
        }

        $alertCode = trim((string) $alert->alert_code);
        $safeCode = $alertCode !== '' && ! str_starts_with($alertCode, 'TMP-')
            ? $alertCode
            : 'Your distress signal';

        $responderName = null;

        if ($event === 'acknowledged') {
            $alert->loadMissing('acknowledgedBy:id,name');
            $responderName = $alert->acknowledgedBy?->name;
        } elseif ($event === 'resolved') {
            $alert->loadMissing('resolvedBy:id,name');
            $responderName = $alert->resolvedBy?->name;
        }

        $title = match ($event) {
            'received' => 'Distress signal received',
            'acknowledged' => 'Distress signal acknowledged',
            'resolved' => 'Distress signal resolved',
        };

        $message = match ($event) {
            'received' => 'Your distress signal was received successfully. TabangNow administrators and officials have been notified.',
            'acknowledged' => $safeCode
                . ' has been acknowledged'
                . ($responderName ? ' by ' . $responderName : ' by a TabangNow responder')
                . '. Responders are handling your report.',
            'resolved' => $safeCode
                . ' has been marked resolved'
                . ($responderName ? ' by ' . $responderName : ' by a TabangNow responder')
                . '.',
        };

        $this->send(
            userId: $userId,
            type: 'mobile_emergency_' . $event,
            sourceId: (int) $alert->id,
            title: $title,
            message: $message,
            replaceExisting: true
        );
    }
    public function notifyPendingRegistration(User $registeredUser): void
    {
        if (
            strtolower(trim((string) $registeredUser->role)) !== 'resident'
            || $registeredUser->isActive()
        ) {
            return;
        }

        foreach ($this->activeUserIdsForRoles(['admin']) as $adminId) {
            $this->send(
                userId: $adminId,
                type: 'user_registration',
                sourceId: (int) $registeredUser->id,
                title: 'New resident registration',
                message: $registeredUser->name
                    . ' registered a resident account and is awaiting administrator approval.',
                replaceExisting: true
            );
        }
    }

    public function notifyAccountStatus(User $user, bool $active): void
    {
        $this->send(
            userId: (int) $user->id,
            type: $active ? 'account_activated' : 'account_deactivated',
            sourceId: (int) $user->id,
            title: $active ? 'Account activated' : 'Account deactivated',
            message: $active
                ? 'Your TabangNow account has been activated. You can now sign in and use the services available to your role.'
                : 'Your TabangNow account has been deactivated. Access is disabled until an administrator reactivates the account.',
            replaceExisting: false
        );
    }

    public function notifyIncidentMessage(IncidentMessage $incidentMessage): void
    {
        $incidentMessage->loadMissing(['incident.assignedTanod.user', 'user']);

        $incident = $incidentMessage->incident;

        if (! $incident) {
            return;
        }

        $recipientIds = collect()
            ->merge(
                $this->activeUserIdsForRoles([
                    'admin',
                    'official',
                    'dao',
                ])
            )
            ->push($incident->reporter_id)
            ->push($incident->assignedTanod?->user_id)
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->reject(
                fn (int $id): bool =>
                    $id === (int) $incidentMessage->user_id
            )
            ->unique()
            ->values();

        $incidentTitle = $incident->incident_title
            ?? $incident->title
            ?? $incident->display_title
            ?? 'Untitled Incident';

        $senderName = $incidentMessage->user?->name ?: 'A participant';

        foreach ($recipientIds as $userId) {
            $this->send(
                userId: $userId,
                type: 'incident_message',
                sourceId: (int) $incident->id,
                title: 'New incident message',
                message: $senderName
                    . ' added a new message to incident: '
                    . $incidentTitle
                    . '.',
                replaceExisting: false
            );
        }
    }

    public function notifyTanodTaskStatus(TanodTask $task): void
    {
        $status = strtolower(trim((string) $task->status));

        if (! in_array($status, ['closed', 'cancelled'], true)) {
            return;
        }

        $task->loadMissing('responses');

        $recipientIds = $task->responses
            ->pluck('user_id')
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $statusLabel = $status === 'closed' ? 'closed' : 'cancelled';

        foreach ($recipientIds as $userId) {
            $this->send(
                userId: $userId,
                type: 'tanod_task_update',
                sourceId: (int) $task->id,
                title: 'Tanod task ' . $statusLabel,
                message: 'Tanod task "'
                    . $task->title
                    . '" has been '
                    . $statusLabel
                    . ' by the administrator.',
                replaceExisting: false
            );
        }
    }

    public function notifyComplaintDeleted(ResidentComplaint $complaint): void
    {
        if ((int) $complaint->resident_id <= 0) {
            return;
        }

        $this->send(
            userId: (int) $complaint->resident_id,
            type: 'resident_complaint_deleted',
            sourceId: (int) $complaint->id,
            title: 'Complaint removed',
            message: 'Your complaint record has been removed by an authorized administrator or official.',
            replaceExisting: false
        );
    }
}
