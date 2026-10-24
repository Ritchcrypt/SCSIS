<?php

namespace Tests\Feature\Api;

use App\Models\Announcement;
use App\Models\Barangay;
use App\Models\Employee;
use App\Models\Incident;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Capstone18MobileParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_registration_rejects_underage_resident(): void
    {
        RateLimiter::clear('registration|127.0.0.1');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Underage Resident',
            'email' => 'underage@example.com',
            'contact_number' => '09123456789',
            'address' => 'Dao, Capiz',
            'date_of_birth' => now()->subYears(17)->toDateString(),
            'age_confirmation' => true,
            'password' => 'TabangNow#2026Secure',
            'password_confirmation' => 'TabangNow#2026Secure',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_of_birth');

        $this->assertDatabaseMissing('users', [
            'email' => 'underage@example.com',
        ]);
    }

    public function test_resident_api_incident_cannot_self_assign_critical_severity(): void
    {
        $barangay = $this->barangay('Agtambi');
        $resident = $this->activeUser('resident', $barangay);

        $categoryId = DB::table('incident_categories')->insertGetId([
            'category_name' => 'Parity Test',
            'description' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('statuses')->insert([
            'status_name' => 'Reported',
            'description' => null,
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($resident, ['mobile']);

        $this->postJson('/api/v1/incidents', [
            'incident_title' => 'Resident severity test',
            'incident_description' => 'Resident submitted incident.',
            'category_id' => $categoryId,
            'barangay_id' => $barangay->id,
            'priority' => 'critical',
            'location_address' => 'Agtambi, Dao, Capiz',
        ])->assertCreated();

        $incident = Incident::query()
            ->where('reporter_id', $resident->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('low', $incident->priority);
        $this->assertTrue((bool) $incident->severity_needs_assessment);
        $this->assertNull($incident->severity_assessed_by);
        $this->assertNull($incident->severity_assessed_at);
    }

    public function test_mobile_announcement_targets_only_selected_barangays(): void
    {
        $agtambi = $this->barangay('Agtambi');
        $duyoc = $this->barangay('Duyoc');
        $admin = $this->activeUser('admin');
        $targetResident = $this->activeUser('resident', $agtambi);
        $otherResident = $this->activeUser('resident', $duyoc);

        Sanctum::actingAs($admin, ['mobile']);

        $response = $this->postJson('/api/v1/announcements', [
            'title' => 'Agtambi only',
            'content' => 'Targeted announcement.',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => 'residents',
            'barangay_ids' => [$agtambi->id],
        ])->assertCreated();

        $announcementId = (int) $response->json('data.id');

        $this->assertDatabaseHas('announcement_barangay', [
            'announcement_id' => $announcementId,
            'barangay_id' => $agtambi->id,
        ]);

        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $targetResident->id)
                ->where('source_id', $announcementId)
                ->whereIn('type', ['announcement', 'calamity'])
                ->exists()
        );

        $this->assertFalse(
            UserNotification::query()
                ->where('user_id', $otherResident->id)
                ->where('source_id', $announcementId)
                ->whereIn('type', ['announcement', 'calamity'])
                ->exists()
        );
    }

    public function test_resident_dashboard_applies_announcement_audience_and_barangay_visibility(): void
    {
        $agtambi = $this->barangay('Agtambi');
        $duyoc = $this->barangay('Duyoc');
        $poster = $this->activeUser('admin');
        $resident = $this->activeUser('resident', $agtambi);

        $visible = $this->announcement(
            $poster,
            'Visible Agtambi notice',
            'residents',
            $agtambi
        );

        $this->announcement(
            $poster,
            'Hidden Duyoc notice',
            'residents',
            $duyoc
        );

        $this->announcement(
            $poster,
            'Hidden admin notice',
            'admin',
            $agtambi
        );

        Sanctum::actingAs($resident, ['mobile']);

        $response = $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.summary.announcements', 1)
            ->assertJsonPath('data.latest_announcements.0.id', $visible->id);

        $this->assertStringNotContainsString(
            'Hidden Duyoc notice',
            $response->getContent()
        );
        $this->assertStringNotContainsString(
            'Hidden admin notice',
            $response->getContent()
        );
    }

    public function test_tanod_dashboard_only_returns_incidents_assigned_to_that_tanod(): void
    {
        $barangay = $this->barangay('Agtambi');
        $tanod = $this->tanod($barangay);
        $otherTanod = $this->tanod($barangay);
        $reporter = $this->activeUser('resident', $barangay);

        $assigned = Incident::query()->create([
            'reporter_id' => $reporter->id,
            'assigned_to' => $tanod->id,
            'title' => 'Assigned incident',
            'incident_title' => 'Assigned incident',
            'description' => 'Assigned to current tanod.',
            'incident_description' => 'Assigned to current tanod.',
            'priority' => 'low',
        ]);

        Incident::query()->create([
            'reporter_id' => $reporter->id,
            'assigned_to' => $otherTanod->id,
            'title' => 'Other tanod incident',
            'incident_title' => 'Other tanod incident',
            'description' => 'Must remain private from current tanod.',
            'incident_description' => 'Must remain private from current tanod.',
            'priority' => 'high',
        ]);

        Sanctum::actingAs($tanod->user, ['mobile']);

        $response = $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.recent_incidents.0.id', $assigned->id);

        $this->assertStringNotContainsString(
            'Other tanod incident',
            $response->getContent()
        );
    }

    public function test_manual_mobile_tanod_task_uses_same_barangay_first(): void
    {
        $agtambi = $this->barangay('Agtambi');
        $duyoc = $this->barangay('Duyoc');
        $admin = $this->activeUser('admin');
        $local = $this->tanod($agtambi);
        $far = $this->tanod($duyoc);

        Sanctum::actingAs($admin, ['mobile']);

        $response = $this->postJson('/api/v1/tanod-tasks', [
            'barangay_id' => $agtambi->id,
            'title' => 'Agtambi manual task',
            'description' => 'Local response first.',
            'priority' => 'normal',
        ])->assertCreated();

        $taskId = (int) $response->json('data.id');

        $this->assertDatabaseHas('tanod_tasks', [
            'id' => $taskId,
            'barangay_id' => $agtambi->id,
        ]);
        $this->assertDatabaseHas('tanod_task_responses', [
            'tanod_task_id' => $taskId,
            'employee_id' => $local->id,
            'response_status' => 'pending',
        ]);
        $this->assertDatabaseMissing('tanod_task_responses', [
            'tanod_task_id' => $taskId,
            'employee_id' => $far->id,
        ]);
    }

    public function test_mobile_tanod_roster_uses_requested_official_dao_barangay(): void
    {
        $agtambi = $this->barangay('Agtambi');
        $duyoc = $this->barangay('Duyoc');
        $admin = $this->activeUser('admin');

        $this->assertNotSame($agtambi->id, $duyoc->id);

        Sanctum::actingAs($admin, ['mobile']);

        $response = $this->postJson('/api/v1/tanod-roster', [
            'full_name' => 'Mobile Routed Tanod',
            'barangay_id' => $duyoc->id,
            'shift' => 'day',
            'status' => 'active',
        ])->assertCreated();

        $userId = (int) $response->json('data.user_id');
        $employeeId = (int) $response->json('data.employee_id');

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'barangay_id' => $duyoc->id,
            'role' => 'tanod',
        ]);
        $this->assertDatabaseHas('employees', [
            'id' => $employeeId,
            'barangay_id' => $duyoc->id,
            'employee_type' => 'tanod',
        ]);
    }

    private function barangay(string $name): Barangay
    {
        return Barangay::query()
            ->where('barangay_name', $name)
            ->firstOrFail();
    }

    private function activeUser(
        string $role,
        ?Barangay $barangay = null
    ): User {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'status' => true,
            'barangay_id' => $barangay?->id,
        ]);
    }

    private function tanod(Barangay $barangay): Employee
    {
        $user = $this->activeUser('tanod', $barangay);

        return Employee::query()->create([
            'user_id' => $user->id,
            'barangay_id' => $barangay->id,
            'employee_type' => 'tanod',
            'position' => 'Barangay Tanod',
            'department' => 'Public Safety',
            'is_active' => true,
        ]);
    }

    private function announcement(
        User $poster,
        string $title,
        string $audience,
        Barangay $barangay
    ): Announcement {
        $announcement = Announcement::query()->create([
            'title' => $title,
            'content' => $title . ' content',
            'category' => 'general',
            'priority' => 'normal',
            'audience' => $audience,
            'is_active' => true,
            'activate_calamity_mode' => false,
            'posted_by' => $poster->id,
            'published_at' => now(),
        ]);

        $announcement->targetBarangays()->sync([$barangay->id]);

        return $announcement;
    }
}
