<?php

namespace App\Support;

use Throwable;

/** Safe operational labels: never log raw provider error bodies or credentials. */
final class PasswordResetFailure
{
    public static function classify(Throwable $error): string
    {
        $message = strtolower($error->getMessage());

        if (str_contains($message, '403') || str_contains($message, 'unauthorized ip')
            || str_contains($message, 'not allowed ip') || str_contains($message, 'whitelist')) {
            return 'brevo_ip_or_permission';
        }
        if (str_contains($message, '401') || str_contains($message, 'invalid api key')) {
            return 'brevo_api_key_rejected';
        }
        if (str_contains($message, '429') || str_contains($message, 'rate limit')) {
            return 'brevo_rate_limit';
        }
        if (str_contains($message, 'sender') || str_contains($message, 'domain')) {
            return 'sender_or_domain_not_verified';
        }
        if (str_contains($message, 'connection') || str_contains($message, 'timeout')
            || str_contains($message, 'network')) {
            return 'network_or_timeout';
        }
        if (str_contains($message, 'password_reset_tokens') || str_contains($message, 'no such table')) {
            return 'password_reset_table_error';
        }
        return 'provider_or_broker_error';
    }
}
