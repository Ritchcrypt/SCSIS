<?php

namespace App\Observers;

use App\Models\MobileEmergencyAlert;
use App\Services\SystemNotificationService;

class MobileEmergencyAlertNotificationObserver
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {
    }

    public function created(MobileEmergencyAlert $alert): void
    {
        $this->notifications->notifyMobileEmergencyReporter(
            $alert,
            'received'
        );
    }

    public function updated(MobileEmergencyAlert $alert): void
    {
        if (! $alert->wasChanged('status')) {
            return;
        }

        $previous = strtolower(
            trim((string) $alert->getOriginal('status'))
        );
        $current = strtolower(trim((string) $alert->status));

        if ($current === 'acknowledged') {
            $this->notifications->notifyMobileEmergencyReporter(
                $alert,
                'acknowledged'
            );

            return;
        }

        if ($current !== 'resolved') {
            return;
        }

        // The existing resolve action implicitly fills acknowledged_by/at.
        // If a responder resolves directly from Active, surface both lifecycle
        // transitions to the sender instead of silently skipping Acknowledged.
        if (! in_array($previous, ['acknowledged', 'resolved'], true)) {
            $this->notifications->notifyMobileEmergencyReporter(
                $alert,
                'acknowledged'
            );
        }

        $this->notifications->notifyMobileEmergencyReporter(
            $alert,
            'resolved'
        );
    }
}