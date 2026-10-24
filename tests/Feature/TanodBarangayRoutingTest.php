<?php

namespace Tests\Feature;

use App\Models\Barangay;
use App\Models\Employee;
use App\Models\TanodProfile;
use App\Models\TanodTask;
use App\Models\User;
use App\Services\TanodTaskAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TanodBarangayRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_barangay_tanods_receive_task_first(): void
    {
        $agtambi = $this->barangay('Agtambi');
        $duyoc = $this->barangay('Duyoc');

        $local = $this->tanod($agtambi);
        $far = $this->tanod($duyoc);

        $task = TanodTask::query()->create([
            'created_by' => null,
            'barangay_id' => $agtambi->id,
            'title' => 'Agtambi incident response',
            'priority' => 'urgent',
            'status' => 'open',
        ]);

        app(TanodTaskAssignmentService::class)
            ->assignInitialResponses($task);

        $this->assertDatabaseHas(
            'tanod_task_responses',
            [
                'tanod_task_id' => $task->id,
                'employee_id' => $local->id,
                'response_status' => 'pending',
            ]
        );

        $this->assertDatabaseMissing(
            'tanod_task_responses',
            [
                'tanod_task_id' => $task->id,
                'employee_id' => $far->id,
            ]
        );
    }

    public function test_other_barangays_are_used_when_no_local_tanod_is_available(): void
    {
        $agtambi = $this->barangay('Agtambi');
        $duyoc = $this->barangay('Duyoc');

        $fallback = $this->tanod($duyoc);

        $task = TanodTask::query()->create([
            'created_by' => null,
            'barangay_id' => $agtambi->id,
            'title' => 'Agtambi incident without local responders',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $result = app(
            TanodTaskAssignmentService::class
        )->assignInitialResponses($task);

        $this->assertTrue(
            $result['fallback_used']
        );

        $this->assertDatabaseHas(
            'tanod_task_responses',
            [
                'tanod_task_id' => $task->id,
                'employee_id' => $fallback->id,
                'response_status' => 'pending',
            ]
        );

        $this->assertDatabaseHas(
            'tanod_tasks',
            [
                'id' => $task->id,
                'fallback_used' => 1,
            ]
        );
    }

    public function test_fallback_opens_after_all_local_tanods_decline(): void
    {
        $agtambi = $this->barangay('Agtambi');
        $duyoc = $this->barangay('Duyoc');

        $local = $this->tanod($agtambi);
        $fallback = $this->tanod($duyoc);

        $task = TanodTask::query()->create([
            'created_by' => null,
            'barangay_id' => $agtambi->id,
            'title' => 'Escalating response task',
            'priority' => 'urgent',
            'status' => 'open',
        ]);

        $service = app(
            TanodTaskAssignmentService::class
        );

        $service->assignInitialResponses($task);

        $task->responses()
            ->where('employee_id', $local->id)
            ->update([
                'response_status' => 'declined',
                'responded_at' => now(),
            ]);

        $expanded = $service
            ->expandFallbackIfNeeded($task);

        $this->assertTrue(
            $expanded->contains(
                fn (Employee $employee): bool =>
                    $employee->id === $fallback->id
            )
        );

        $this->assertDatabaseHas(
            'tanod_task_responses',
            [
                'tanod_task_id' => $task->id,
                'employee_id' => $fallback->id,
                'response_status' => 'pending',
            ]
        );
    }

    public function test_roster_page_contains_barangay_assignment_selector(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tanods.index'))
            ->assertOk()
            ->assertSee('Assigned Barangay')
            ->assertSee('Agtambi')
            ->assertSee('Duyoc');
    }

    private function barangay(
        string $name
    ): Barangay {
        return Barangay::query()
            ->where(
                'barangay_name',
                $name
            )
            ->firstOrFail();
    }

    private function tanod(
        Barangay $barangay
    ): Employee {
        $user = User::factory()->create([
            'role' => 'tanod',
            'barangay_id' => $barangay->id,
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'barangay_id' => $barangay->id,
            'employee_type' => 'tanod',
            'position' => 'Barangay Tanod',
            'department' => 'Public Safety',
            'is_active' => true,
        ]);

        TanodProfile::query()->create([
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'shift' => 'day',
            'status' => 'on_duty',
        ]);

        return $employee;
    }
}
