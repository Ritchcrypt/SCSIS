<?php

namespace App\Observers;

use App\Models\IncidentMessage;
use App\Services\SystemNotificationService;

class IncidentMessageNotificationObserver
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {
    }

    public function created(IncidentMessage $incidentMessage): void
    {
        $this->notifications->notifyIncidentMessage($incidentMessage);
    }
}
