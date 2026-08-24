<?php

namespace App\Observers;

use App\Models\UserNotification;
use App\Services\FirebasePushService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserNotificationPushObserver implements ShouldHandleEventsAfterCommit
{
    public function created(UserNotification $notification): void
    {
        $push = app(FirebasePushService::class);

        if (! $push->isConfigured()) {
            return;
        }

        $notificationId = (int) $notification->id;

        app()->terminating(function () use ($notificationId): void {
            try {
                $notification = UserNotification::query()->find($notificationId);

                if (! $notification) {
                    return;
                }

                app(FirebasePushService::class)->sendForNotification($notification);
            } catch (Throwable $exception) {
                Log::warning('Deferred Firebase push delivery failed.', [
                    'notification_id' => $notificationId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
