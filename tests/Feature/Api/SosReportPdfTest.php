<?php

namespace Tests\Feature\Api;

use App\Models\MobileEmergencyAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SosReportPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reports_expose_and_generate_structured_sos_pdf_on_web_and_mobile_api(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);

        $alert = MobileEmergencyAlert::query()->create([
            'alert_code' => 'SOS-REPORT-000001',
            'installation_id' => 'report-test-installation',
            'request_id' => 'report-test-request',
            'status' => 'acknowledged',
            'emergency_details' => 'Test SOS report requiring immediate assistance.',
            'contact_number' => '09171234567',
            'latitude' => 11.3900000,
            'longitude' => 122.6800000,
            'accuracy_meters' => 12.5,
            'location_source' => 'current',
            'source' => 'mobile',
            'triggered_at' => now(),
            'acknowledged_by' => $admin->id,
            'acknowledged_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports')
            ->assertOk()
            ->assertSee('SOS PDF')
            ->assertSee($alert->alert_code);

        $webPdf = $this->actingAs($admin)
            ->get("/admin/reports/sos/{$alert->id}/pdf");

        $webPdf->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            strtolower((string) $webPdf->headers->get('content-type'))
        );

        Sanctum::actingAs($admin, ['mobile']);

        $this->getJson('/api/v1/reports?period=week')
            ->assertOk()
            ->assertJsonPath(
                'data.report_options.sos.0.id',
                $alert->id
            )
            ->assertJsonPath(
                'permissions.can_download_sos_pdf',
                true
            );

        $apiPdf = $this->get(
            "/api/v1/reports/sos/{$alert->id}/pdf",
            ['Accept' => 'application/pdf']
        );

        $apiPdf->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            strtolower((string) $apiPdf->headers->get('content-type'))
        );
    }

    public function test_non_admin_cannot_access_sos_report_pdf(): void
    {
        $resident = User::factory()->create([
            'role' => 'resident',
            'is_active' => true,
            'status' => true,
        ]);

        $alert = MobileEmergencyAlert::query()->create([
            'alert_code' => 'SOS-REPORT-000002',
            'installation_id' => 'report-test-resident-installation',
            'request_id' => 'report-test-resident-request',
            'status' => 'active',
            'emergency_details' => 'Authorization test.',
            'contact_number' => '09170000001',
            'latitude' => 11.3900000,
            'longitude' => 122.6800000,
            'location_source' => 'current',
            'source' => 'mobile',
            'triggered_at' => now(),
        ]);

        Sanctum::actingAs($resident, ['mobile']);

        $this->get("/api/v1/reports/sos/{$alert->id}/pdf")
            ->assertForbidden();
    }
}