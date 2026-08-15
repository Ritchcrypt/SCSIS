<?php

namespace App\Services;

use App\Models\IncidentMessage;
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
}
