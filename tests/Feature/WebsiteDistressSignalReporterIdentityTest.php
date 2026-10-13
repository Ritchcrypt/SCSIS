<?php

namespace Tests\Feature;

use App\Models\MobileEmergencyAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteDistressSignalReporterIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_distress_signal_page_requires_reporter_full_name(): void
    {
        $this->get(
            route(
                'distress-signal.create'
            )
        )
            ->assertOk()
            ->assertSee(
                'Reporter full name'
            )
            ->assertSee(
                'Unregistered reporters must provide a complete name'
            );
    }

    public function test_registered_user_sees_account_name_automatically(): void
    {
        $resident =
            User::factory()
                ->create([
                    'name' =>
                        'Maria Santos',

                    'role' =>
                        'resident',

                    'is_active' =>
                        true,

                    'status' =>
                        true,

                    'contact_number' =>
                        '09123456789',
                ]);

        $this->actingAs(
            $resident
        )
            ->get(
                route(
                    'distress-signal.create'
                )
            )
            ->assertOk()
            ->assertSee(
                'Maria Santos'
            )
            ->assertSee(
                'Filled automatically from your TabangNow account'
            );
    }

    public function test_guest_single_name_is_rejected_before_sos_record_is_created(): void
    {
        $this->post(
            route(
                'distress-signal.store'
            ),
            [
                'reporter_name' =>
                    'Juan',

                'contact_number' =>
                    '09123456789',

                'emergency_details' =>
                    'Need immediate medical help.',

                'latitude' =>
                    11.3889,

                'longitude' =>
                    122.6850,

                'accuracy_meters' =>
                    12.5,
            ]
        )
            ->assertSessionHasErrors([
                'reporter_name',
            ]);

        $this->assertDatabaseCount(
            'mobile_emergency_alerts',
            0
        );
    }

    public function test_guest_complete_name_is_stored_on_website_sos(): void
    {
        $this->post(
            route(
                'distress-signal.store'
            ),
            [
                'reporter_name' =>
                    'Juan Dela Cruz',

                'contact_number' =>
                    '09123456789',

                'emergency_details' =>
                    'Need immediate medical help.',

                'latitude' =>
                    11.3889,

                'longitude' =>
                    122.6850,

                'accuracy_meters' =>
                    12.5,
            ]
        )
            ->assertRedirect(
                route(
                    'distress-signal.create'
                )
            )
            ->assertSessionHas(
                'success'
            );

        $alert =
            MobileEmergencyAlert::query()
                ->firstOrFail();

        $this->assertNull(
            $alert->user_id
        );

        $this->assertSame(
            'Juan Dela Cruz',
            $alert->reporter_name
        );

        $this->assertSame(
            'Juan Dela Cruz',
            $alert->display_name
        );

        $this->assertSame(
            'website',
            $alert->source
        );
    }

    public function test_registered_user_name_cannot_be_overridden_by_form_input(): void
    {
        $resident =
            User::factory()
                ->create([
                    'name' =>
                        'Maria Santos',

                    'role' =>
                        'resident',

                    'is_active' =>
                        true,

                    'status' =>
                        true,

                    'contact_number' =>
                        '09123456789',
                ]);

        $this->actingAs(
            $resident
        )
            ->post(
                route(
                    'distress-signal.store'
                ),
                [
                    'reporter_name' =>
                        'Fake Reporter Name',

                    'contact_number' =>
                        '09123456789',

                    'emergency_details' =>
                        'There is a fire nearby.',

                    'latitude' =>
                        11.3889,

                    'longitude' =>
                        122.6850,

                    'accuracy_meters' =>
                        8.2,
                ]
            )
            ->assertRedirect(
                route(
                    'distress-signal.create'
                )
            );

        $alert =
            MobileEmergencyAlert::query()
                ->firstOrFail();

        $this->assertSame(
            $resident->id,
            $alert->user_id
        );

        $this->assertSame(
            'Maria Santos',
            $alert->reporter_name
        );

        $this->assertSame(
            'Maria Santos',
            $alert->display_name
        );
    }

    public function test_responder_list_and_profile_show_guest_reporter_name(): void
    {
        $admin =
            User::factory()
                ->create([
                    'role' =>
                        'admin',

                    'is_active' =>
                        true,

                    'status' =>
                        true,
                ]);

        $alert =
            MobileEmergencyAlert::query()
                ->create([
                    'alert_code' =>
                        'SOS-WEB-IDENTITY-001',

                    'user_id' =>
                        null,

                    'reporter_name' =>
                        'Pedro Reyes',

                    'installation_id' =>
                        'website-test-installation',

                    'request_id' =>
                        'website-test-request',

                    'status' =>
                        'active',

                    'emergency_details' =>
                        'Emergency identity display test.',

                    'contact_number' =>
                        '09171234567',

                    'latitude' =>
                        11.3889,

                    'longitude' =>
                        122.6850,

                    'accuracy_meters' =>
                        10,

                    'location_source' =>
                        'current',

                    'source' =>
                        'website',

                    'triggered_at' =>
                        now(),
                ]);

        $this->actingAs(
            $admin
        )
            ->get(
                route(
                    'admin.mobile-sos.index'
                )
            )
            ->assertOk()
            ->assertSee(
                'Pedro Reyes'
            );

        $this->actingAs(
            $admin
        )
            ->get(
                route(
                    'emergency-alerts.show',
                    $alert
                )
            )
            ->assertOk()
            ->assertSee(
                'Reporter full name'
            )
            ->assertSee(
                'Pedro Reyes'
            );
    }
}
