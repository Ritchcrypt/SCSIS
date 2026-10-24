<?php

namespace Tests\Feature;

use App\Mail\RegistrationDecisionMail;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Database-free, network-free checks for the already configured Brevo API mailer.
 */
class BrevoHttpsTransportTest extends TestCase
{
    public function test_brevo_mailer_uses_https_api_instead_of_smtp(): void
    {
        config()->set('services.brevo.key', 'local-fake-key-never-sent');
        Mail::purge('brevo');

        try {
            $transport = Mail::mailer('brevo')->getSymfonyTransport();

            $this->assertStringContainsString(
                'BrevoApiTransport',
                get_class($transport)
            );
        } finally {
            Mail::purge('brevo');
        }
    }

    public function test_missing_api_key_fails_closed_without_smtp_fallback(): void
    {
        config()->set('services.brevo.key', '');
        Mail::purge('brevo');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('BREVO_API_KEY is not configured.');

            Mail::mailer('brevo');
        } finally {
            Mail::purge('brevo');
        }
    }

    public function test_approval_and_decline_email_subjects_remain_unchanged(): void
    {
        $approved = new RegistrationDecisionMail('Sample Resident', 'approved');
        $declined = new RegistrationDecisionMail('Sample Resident', 'declined');

        $this->assertSame('TabangNow registration approved', $approved->envelope()->subject);
        $this->assertSame('TabangNow registration declined', $declined->envelope()->subject);
    }
}
