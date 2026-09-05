<?php

namespace Tests\Feature;

use App\Models\MobileEmergencyAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistressSignalWebDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_one_distress_signal_through_browser_post_route(): void
    {
        $admin = $this->admin();
        $alert = $this->alert('single');

        $this->actingAs($admin)
            ->post("/emergency-alerts/{$alert->id}/delete")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('mobile_emergency_alerts', [
            'id' => $alert->id,
        ]);
    }

    public function test_admin_can_delete_all_distress_signals_through_browser_post_route(): void
    {
        $admin = $this->admin();
        $this->alert('first');
        $this->alert('second');

        $this->actingAs($admin)
            ->post('/emergency-alerts/delete-all')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('mobile_emergency_alerts', 0);
    }

    public function test_distress_signal_delete_confirmations_are_scoped_to_submit_buttons(): void
    {
        $index = file_get_contents(
            resource_path('views/emergency-alerts/index.blade.php')
        );
        $show = file_get_contents(
            resource_path('views/emergency-alerts/show.blade.php')
        );

        $this->assertStringNotContainsString(
            'onsubmit="return confirm(',
            $index
        );
        $this->assertStringNotContainsString(
            'onsubmit="return confirm(',
            $show
        );
        $this->assertStringContainsString(
            'data-confirm="Delete ALL distress signals?',
            $index
        );
        $this->assertStringContainsString(
            'data-confirm="Delete {{ $alert->alert_code }}?',
            $index
        );
        $this->assertStringContainsString(
            'data-confirm="Delete {{ $alert->alert_code }}?',
            $show
        );
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);
    }

    private function alert(string $suffix): MobileEmergencyAlert
    {
        return MobileEmergencyAlert::query()->create([
            'alert_code' => 'SOS-TEST-' . strtoupper($suffix),
            'installation_id' => 'installation-' . $suffix,
            'request_id' => 'request-' . $suffix,
            'status' => 'active',
            'triggered_at' => now(),
        ]);
    }
}
