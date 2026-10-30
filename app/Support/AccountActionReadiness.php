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

        $mailer = strtolower(trim((string) config('mail.default', '')));
        if ($mailer === '' || in_array($mailer, ['log', 'array'], true)) {
            return 'mail_transport_not_deliverable';
        }
        if (in_array($mailer, ['failover', 'roundrobin'], true)) {
            // These transports can fall back to log/array and report false delivery success.
            return 'mail_transport_needs_verification';
        }
        if ($mailer === 'brevo' && trim((string) config('services.brevo.key', '')) === '') {
            return 'brevo_api_key_missing';
        }
        $from = trim((string) config('mail.from.address', ''));
        if ($from === '' || $from === 'hello@example.com') {
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
