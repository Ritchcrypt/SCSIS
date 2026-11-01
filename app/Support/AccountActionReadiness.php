<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Non-mutating checks for account action prerequisites.
 * Never expose mail credentials, reset tokens or raw SQL in responses/logs.
 */
final class AccountActionReadiness
{
    public static function passwordResetError(): ?string
    {
        $broker = (string) config('auth.defaults.passwords', 'users');
        $table = (string) config("auth.passwords.{$broker}.table", 'password_reset_tokens');
        if ($table === '' || ! Schema::hasTable($table)) {
            return 'reset_token_table_missing';
        }

        // Password resets use BrevoPasswordResetNotification, not mail.default.
        // A log/array SMTP fallback would falsely report successful delivery.
        if (trim((string) config('services.brevo.key', '')) === '') {
            return 'brevo_api_key_missing';
        }

        $from = trim((string) config('mail.from.address', ''));
        if (! filter_var($from, FILTER_VALIDATE_EMAIL)
            || strtolower($from) === 'hello@example.com') {
            return 'mail_from_not_configured';
        }

        return null;
    }

    public static function missingDatabaseObject(\Throwable $exception): ?string
    {
        $message = $exception->getMessage();
        // MySQL SQLSTATE 42S02 text, e.g. Table 'database.table_name' doesn't exist.
        if (preg_match("/Table ['`][^'`]*?\.([a-zA-Z0-9_]+)['`] (?:doesn't exist|does not exist)/i", $message, $match)) {
            return $match[1];
        }
        // DB-agnostic fallback for explicit 'no such table' error messages.
        if (preg_match('/no such table:\s*([a-zA-Z0-9_]+)/i', $message, $match)) {
            return $match[1];
        }
        return null;
    }
}
