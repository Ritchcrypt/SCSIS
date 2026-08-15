<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Auth\Events\Registered;

class NotifyPendingResidentRegistration
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {
    }

    public function handle(Registered $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $this->notifications->notifyPendingRegistration($user);
    }
}
