<?php

namespace Tests\Feature;

use App\Models\IncidentCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentFormRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_incident_form_has_no_manual_title_and_contains_dao_barangays(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.incidents.create'))
            ->assertOk()
            ->assertDontSee('Incident Title')
            ->assertSee('Incident Category')
            ->assertSee('Aganan')
            ->assertSee('Poblacion Ilawod')
            ->assertSee('Poblacion Ilaya')
            ->assertSee('San Agustin')
            ->assertDontSee('Search Location on Map')
            ->assertSee('Add Category')
            ->assertSee('Remove Category');
    }

    public function test_admin_can_add_and_remove_incident_category_without_deleting_history(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(
                route(
                    'admin.incident-categories.quick-store'
                ),
                [
                    'category_name' =>
                        'Electrical Hazard',
                ]
            )
            ->assertRedirect();

        $category = IncidentCategory::query()
            ->where(
                'category_name',
                'Electrical Hazard'
            )
            ->firstOrFail();

        $this->assertTrue(
            (bool) $category->is_active
        );

        $this->actingAs($admin)
            ->delete(
                route(
                    'admin.incident-categories.quick-delete',
                    $category->id
                )
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'incident_categories',
            [
                'id' => $category->id,
                'is_active' => 0,
            ]
        );
    }
}
