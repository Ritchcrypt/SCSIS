<?php

namespace App\Console\Commands;

use App\Support\AccountActionReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CheckPasswordResetMail extends Command
{
    protected $signature = 'tabangnow:password-reset-check';

    protected $description = 'Inspect password-reset Brevo readiness without emailing or displaying secrets';

    public function handle(): int
    {
        $this->info('TabangNow password reset / Brevo readiness (no email sent)');
        $reason = AccountActionReadiness::passwordResetError();
        $this->line('Brevo key: '.(trim((string) config('services.brevo.key', '')) !== '' ? 'configured' : 'missing'));
        $this->line('Sender address: '.(filter_var((string) config('mail.from.address'), FILTER_VALIDATE_EMAIL) ? 'configured (verify it in Brevo)' : 'invalid'));
        $appUrl = (string) config('app.url');
        $this->line('Password reset URL: '.(str_starts_with(strtolower($appUrl), 'https://') ? 'HTTPS' : 'non-HTTPS; local links cannot open on remote phones'));
        if ($reason !== null) {
            $this->error('Configuration/database check failed: '.$reason);
            return self::FAILURE;
        }
        try {
            $transport = Mail::mailer('brevo')->getSymfonyTransport();
            if (! str_contains(get_class($transport), 'BrevoApiTransport')) {
                $this->error('Brevo transport is not the expected HTTPS API transport.');
                return self::FAILURE;
            }
            $this->info('Brevo HTTPS API transport: available');
            $this->warn('External delivery is NOT verified. Use the admin reset button and inspect logs, Brevo sender and authorized IP settings.');
            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('Brevo transport could not initialize (reason type: '.get_class($error).').');
            return self::FAILURE;
        }
    }
}
