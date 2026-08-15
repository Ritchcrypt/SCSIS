<?php

namespace App\Observers;

use App\Models\TanodTask;
use App\Services\SystemNotificationService;

class TanodTaskNotificationObserver
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {
    }

    public function updated(TanodTask $task): void
    {
        if (! $task->wasChanged('status')) {
            return;
        }

        $this->notifications->notifyTanodTaskStatus($task);
    }
}
