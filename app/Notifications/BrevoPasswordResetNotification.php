<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Reset links deliberately use Brevo's HTTPS mailer even when the application's
 * other mail notifications use another provider or a local log transport.
 */
final class BrevoPasswordResetNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return parent::toMail($notifiable)->mailer('brevo');
    }
}
