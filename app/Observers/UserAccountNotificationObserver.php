<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SystemNotificationService;

class UserAccountNotificationObserver
{
    public function __construct(
        private readonly SystemNotificationService $notifications
    ) {
    }

    public function updated(User $user): void
    {
        if (
            ! $user->wasChanged('is_active')
            && ! $user->wasChanged('status')
        ) {
            return;
        }

        $this->notifications->notifyAccountStatus(
            $user,
            $user->isActive()
        );
    }
}
