<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Non-mutating provider access check. Never sends email or records credentials.
 */
final class CheckBrevoAccountHealth extends Command
{
    protected $signature = 'tabangnow:brevo-health {--live : Query the Brevo Account API (read-only)}';

    protected $description = 'Audit password reset readiness and optionally test Brevo API authorization';

    public function handle(): int
    {
        $errors = [];
        $warnings = [];

        $broker = (string) config('auth.defaults.passwords', 'users');
        $table = (string) config("auth.passwords.{$broker}.table", 'password_reset_tokens');
        $expires = (int) config("auth.passwords.{$broker}.expire", 0);
        $throttle = (int) config("auth.passwords.{$broker}.throttle", 0);
        $appUrl = trim((string) config('app.url', ''));
        $host = strtolower((string) (parse_url($appUrl, PHP_URL_HOST) ?: ''));
        $scheme = strtolower((string) (parse_url($appUrl, PHP_URL_SCHEME) ?: ''));
        $production = app()->environment('production');
        $key = trim((string) config('services.brevo.key', ''));
        $sender = trim((string) config('mail.from.address', ''));

        if ($key === '') {
            $errors[] = 'brevo_api_key_missing';
        }
        if (! filter_var($sender, FILTER_VALIDATE_EMAIL) || strtolower($sender) === 'hello@example.com') {
            $errors[] = 'mail_sender_not_configured';
        }
        if ($table === '' || ! Schema::hasTable($table)) {
            $errors[] = 'password_reset_token_table_missing';
        }
        if ($expires < 1 || $expires > 60) {
            $errors[] = 'token_expiry_outside_1_to_60_minutes';
        }
        if ($throttle < 60) {
            $errors[] = 'reset_throttle_under_60_seconds';
        }
        if ((string) config('session.driver') !== 'database') {
            $warnings[] = 'session_driver_not_database_check_cross_device_logout';
        }
        if ($scheme !== 'https' || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true) || $host === '') {
            if ($production) {
                $errors[] = 'production_reset_url_must_be_public_https';
            } else {
                $warnings[] = 'local_reset_url_unreachable_from_other_devices';
            }
        }

        $this->line('Password reset health check (no emails sent, no secrets printed)');
        $this->line('Token lifetime: '.$expires.' minutes; throttle: '.$throttle.' seconds');
        foreach ($warnings as $code) {
            $this->warn('WARNING: '.$code);
        }
        foreach ($errors as $code) {
            $this->error('FAILED: '.$code);
        }
        if ($errors !== []) {
            // No external request if configuration is incomplete.
            Log::warning('Password reset health check failed.', ['checks' => $errors]);
            return self::FAILURE;
        }

        if (! $this->option('live')) {
            $this->info('Local checks passed. Use --live for Brevo IP/key access verification.');
            return self::SUCCESS;
        }

        try {
            $response = Http::withHeaders([
                'api-key' => $key,
                'Accept' => 'application/json',
            ])->connectTimeout(5)->timeout(12)->get('https://api.brevo.com/v3/account');
            $status = $response->status();
            if ($status === 200) {
                $this->info('Brevo Account API: 200 OK. Account authorization verified; email delivery is not tested.');
                return self::SUCCESS;
            }

            $reason = match ($status) {
                401, 403 => 'brevo_key_or_authorized_ip_rejected',
                429 => 'brevo_rate_limit',
                default => 'brevo_api_http_failure',
            };
            $this->error('Brevo Account API: HTTP '.$status.' ('.$reason.').');
            Log::warning('Password reset Brevo monitor failed.', [
                'reason' => $reason,
                'http_status' => $status,
            ]);
            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Brevo account access unreachable (network or TLS error).');
            Log::warning('Password reset Brevo monitor failed.', [
                'reason' => 'brevo_account_network_failure',
                'exception_type' => get_class($exception),
            ]);
            return self::FAILURE;
        }
    }
}
