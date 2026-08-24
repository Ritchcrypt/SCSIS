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
        $this->schedulePush($notification);
    }

    public function updated(UserNotification $notification): void
    {
        /*
         * Several established TabangNow workflows intentionally use
         * updateOrCreate() for one notification row per user/type/source.
         *
         * A created-only observer therefore misses later real updates such as
         * a complaint/status notification being refreshed. Deliver another
         * native push only when user-visible notification content changed or
         * the row was explicitly made unread again.
         *
         * Read/acknowledge-only updates are deliberately excluded so opening
         * or acknowledging a notification can never generate another push.
         */
        if ((bool) $notification->is_read) {
            return;
        }

        if (! $notification->wasChanged([
            'type',
            'source_id',
            'title',
            'message',
            'is_read',
        ])) {
            return;
        }

        $this->schedulePush($notification);
    }

    private function schedulePush(UserNotification $notification): void
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

                /*
                 * Re-check the final persisted state. If another operation
                 * marked the notification read before request termination,
                 * do not surface a stale push.
                 */
                if ((bool) $notification->is_read) {
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
