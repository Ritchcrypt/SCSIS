<?php

namespace Tests\Feature;

use App\Models\Incident;
use Tests\TestCase;

class IncidentSeverityAuthorityV2ContractTest extends TestCase
{
    public function test_incident_model_has_pending_assessment_map_state(): void
    {
        $incident = new Incident();

        $incident->priority = 'low';
        $incident->severity_needs_assessment = true;

        $this->assertSame(
            'Pending Assessment',
            $incident->severity_label
        );

        $this->assertSame(
            'unassessed',
            $incident->map_severity_value
        );

        $this->assertSame(
            '#64748b',
            $incident->map_pin_color
        );

        $this->assertSame(
            0.0,
            $incident->map_heat_intensity
        );
    }

    public function test_incident_model_maps_assessed_severity_to_existing_color_legend(): void
    {
        $incident = new Incident();

        $incident->priority = 'critical';
        $incident->map_severity = 'critical';
        $incident->severity_needs_assessment = false;

        $this->assertSame(
            'Critical',
            $incident->severity_label
        );

        $this->assertSame(
            'critical',
            $incident->map_severity_value
        );

        $this->assertSame(
            '#ef4444',
            $incident->map_pin_color
        );

        $this->assertSame(
            1.0,
            $incident->map_heat_intensity
        );
    }

    public function test_create_page_hides_severity_input_from_resident_and_tanod(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/incidents/create.blade.php'
            )
        );

        $this->assertStringContainsString(
            '$canAssessSeverity',
            $source
        );

        $this->assertStringContainsString(
            'Severity will be assigned by an administrator or barangay official',
            $source
        );

        $this->assertStringContainsString(
            'You can still pinpoint the exact incident location',
            $source
        );
    }

    public function test_controller_enforces_severity_authority_server_side(): void
    {
        $source = (string) file_get_contents(
            app_path(
                'Http/Controllers/IncidentController.php'
            )
        );

        foreach ([
            '$canAssessSeverity = $user->isAdmin() || $user->isOfficial();',
            "['prohibited']",
            "'severity_needs_assessment'",
            "'severity_assessed_by'",
            "'severity_assessed_at'",
            "'map_severity'",
        ] as $marker) {
            $this->assertStringContainsString(
                $marker,
                $source
            );
        }
    }

    public function test_map_has_pending_assessment_and_four_assessed_severity_legends(): void
    {
        $source = (string) file_get_contents(
            resource_path(
                'views/map/index.blade.php'
            )
        );

        foreach ([
            'Pending assessment',
            'Low',
            'Moderate',
            'High',
            'Critical',
        ] as $label) {
            $this->assertStringContainsString(
                $label,
                $source
            );
        }

        $this->assertStringContainsString(
            "incident.severity !== 'unassessed'",
            $source
        );
    }
}
