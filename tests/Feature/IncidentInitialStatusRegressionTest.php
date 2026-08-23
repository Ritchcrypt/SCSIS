<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentCategory;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncidentInitialStatusRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_uses_reported_as_the_initial_incident_status(): void
    {
        $this->seed(FoundationSeeder::class);

        $reported = Status::query()
            ->whereRaw(
                'LOWER(status_name) = ?',
                ['reported']
            )
            ->first();

        $this->assertNotNull($reported);
        $this->assertTrue((bool) $reported->is_active);

        $this->assertFalse(
            Status::query()
                ->whereRaw(
                    'LOWER(status_name) = ?',
                    ['pending']
                )
                ->exists()
        );
    }

    public function test_website_incident_form_displays_reported_as_initial_status(): void
    {
        $this->seed(FoundationSeeder::class);

        $admin = $this->activeAdmin();

        $this->actingAs($admin)
            ->get(route('admin.incidents.create'))
            ->assertOk()
            ->assertSee('Initial Status')
            ->assertSee('Reported');
    }

    public function test_website_incident_submission_succeeds_without_a_pending_status_record(): void
    {
        $this->seed(FoundationSeeder::class);

        $admin = $this->activeAdmin();
        $reported = $this->reportedStatus();

        $this->assertFalse(
            Status::query()
                ->whereRaw(
                    'LOWER(status_name) = ?',
                    ['pending']
                )
                ->exists()
        );

        $response = $this->actingAs($admin)
            ->post(
                route('admin.incidents.store'),
                $this->incidentPayload(
                    'Website reported-status regression'
                )
            );

        $response->assertSessionHasNoErrors();

        $incident = Incident::query()
            ->where(
                'incident_title',
                'Website reported-status regression'
            )
            ->firstOrFail();

        $this->assertSame(
            (int) $reported->id,
            (int) $incident->status_id
        );

        $this->assertDatabaseHas(
            'incident_status_history',
            [
                'incident_id' => $incident->id,
                'status_id' => $reported->id,
                'updated_by' => $admin->id,
                'remarks' => 'Incident report submitted.',
            ]
        );
    }

    public function test_mobile_incident_options_and_submission_use_reported_without_pending(): void
    {
        $this->seed(FoundationSeeder::class);

        $admin = $this->activeAdmin();
        $reported = $this->reportedStatus();

        Sanctum::actingAs(
            $admin,
            ['mobile']
        );

        $this->getJson(
            route('api.v1.incidents.options')
        )
            ->assertOk()
            ->assertJsonPath(
                'data.submission_info.initial_status',
                'Reported'
            );

        $response = $this->postJson(
            route('api.v1.incidents.store'),
            $this->incidentPayload(
                'Mobile reported-status regression'
            )
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'message',
                'Incident report submitted successfully.'
            );

        $incident = Incident::query()
            ->where(
                'incident_title',
                'Mobile reported-status regression'
            )
            ->firstOrFail();

        $this->assertSame(
            (int) $reported->id,
            (int) $incident->status_id
        );

        $this->assertDatabaseHas(
            'incident_status_history',
            [
                'incident_id' => $incident->id,
                'status_id' => $reported->id,
                'updated_by' => $admin->id,
                'remarks' => 'Incident report submitted.',
            ]
        );
    }

    private function activeAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'status' => true,
        ]);
    }

    private function reportedStatus(): Status
    {
        return Status::query()
            ->active()
            ->whereRaw(
                'LOWER(status_name) = ?',
                ['reported']
            )
            ->firstOrFail();
    }

    private function incidentPayload(
        string $title
    ): array {
        $categoryId = IncidentCategory::query()
            ->active()
            ->orderBy('id')
            ->value('id');

        $barangayId = DB::table('barangays')
            ->orderBy('id')
            ->value('id');

        $this->assertNotNull($categoryId);
        $this->assertNotNull($barangayId);

        return [
            'incident_title' => $title,
            'incident_description' =>
                'Regression test incident report.',
            'category_id' => (int) $categoryId,
            'barangay_id' => (int) $barangayId,
            'priority' => 'low',
            'location_address' =>
                'Dao, Capiz regression test location',
        ];
    }
}