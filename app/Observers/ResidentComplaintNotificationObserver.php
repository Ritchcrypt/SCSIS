<?php

namespace App\Observers;

use App\Models\ResidentComplaint;
use App\Services\SystemNotificationService;

class ResidentComplaintNotificationObserver
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {
    }

    public function deleting(ResidentComplaint $complaint): void
    {
        $this->notifications->notifyComplaintDeleted($complaint);
    }
}
