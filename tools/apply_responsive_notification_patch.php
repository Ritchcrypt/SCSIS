<?php

/**
 * Safe, UTF-8 preserving patch for the remaining notification routing and
 * complaint-event distinctions. Run from the Laravel project root:
 *
 *   php tools/apply_responsive_notification_patch.php
 */

$files = [
    'app/Services/NotificationBellService.php',
    'app/Http/Controllers/NotificationOpenController.php',
    'app/Http/Controllers/Api/V1/NotificationCenterController.php',
    'app/Http/Controllers/ResidentComplaintController.php',
    'app/Http/Controllers/Api/V1/ResidentComplaintController.php',
];

foreach ($files as $file) {
    if (! is_file($file)) {
        throw new RuntimeException("Missing required file: {$file}");
    }
}

$contents = [];
foreach ($files as $file) {
    $contents[$file] = file_get_contents($file);

    if ($contents[$file] === false) {
        throw new RuntimeException("Unable to read {$file}");
    }
}

function replaceExactOnce(string $text, string $old, string $new, string $label): string
{
    $count = substr_count($text, $old);

    if ($count === 0 && str_contains($text, $new)) {
        return $text;
    }

    if ($count !== 1) {
        throw new RuntimeException("{$label}: expected exactly one match, found {$count}. Nothing was written.");
    }

    return str_replace($old, $new, $text);
}

function replaceInsideMethod(
    string $text,
    string $methodName,
    callable $transform,
    string $label
): string {
    $pattern = '/(\n\s*private function ' . preg_quote($methodName, '/') . '\b.*?)(?=\n\s*private function |\n}\s*$)/s';

    $count = preg_match_all($pattern, $text, $matches);

    if ($count !== 1) {
        throw new RuntimeException("{$label}: expected exactly one {$methodName} method, found {$count}. Nothing was written.");
    }

    $original = $matches[0][0];
    $updated = $transform($original);

    if ($updated === $original) {
        return $text;
    }

    return substr_replace(
        $text,
        $updated,
        strpos($text, $original),
        strlen($original)
    );
}

// -------------------------------------------------------------------------
// Notification labels and routes.
// -------------------------------------------------------------------------

$file = 'app/Services/NotificationBellService.php';
$text = $contents[$file];

$text = replaceExactOnce(
    $text,
    "            'system' => 'System',\n",
    "            'user_registration' => 'New Registration',\n"
        . "            'account_activated' => 'Account Activated',\n"
        . "            'account_deactivated' => 'Account Deactivated',\n"
        . "            'incident_message' => 'Incident Message',\n"
        . "            'resident_complaint_status_update' => 'Complaint Status Update',\n"
        . "            'resident_complaint_proof' => 'Complaint Proof',\n"
        . "            'system' => 'System',\n",
    'notification type labels'
);

$text = replaceExactOnce(
    $text,
    "        if (in_array(\$type, ['resident_complaint', 'resident_complaint_update'], true)) {\n",
    "        if (in_array(\$type, [\n"
        . "            'resident_complaint',\n"
        . "            'resident_complaint_update',\n"
        . "            'resident_complaint_status_update',\n"
        . "            'resident_complaint_proof',\n"
        . "        ], true)) {\n",
    'complaint fallback routing'
);

$text = replaceExactOnce(
    $text,
    "            'incident_update',\n            'incident_updated',\n",
    "            'incident_update',\n            'incident_message',\n            'incident_updated',\n",
    'incident message fallback routing'
);

$contents[$file] = $text;

$file = 'app/Http/Controllers/NotificationOpenController.php';
$text = $contents[$file];

$text = replaceExactOnce(
    $text,
    "        if (in_array(\$type, ['resident_complaint', 'resident_complaint_update'], true)) {\n",
    "        if (in_array(\$type, [\n"
        . "            'resident_complaint',\n"
        . "            'resident_complaint_update',\n"
        . "            'resident_complaint_status_update',\n"
        . "            'resident_complaint_proof',\n"
        . "        ], true)) {\n",
    'website complaint open routing'
);

$text = replaceExactOnce(
    $text,
    "            'incident_update',\n            'incident_updated',\n",
    "            'incident_update',\n            'incident_message',\n            'incident_updated',\n",
    'website incident message open routing'
);

$contents[$file] = $text;

$file = 'app/Http/Controllers/Api/V1/NotificationCenterController.php';
$text = $contents[$file];

$text = replaceExactOnce(
    $text,
    "                ['resident_complaint', 'resident_complaint_update'],\n",
    "                [\n"
        . "                    'resident_complaint',\n"
        . "                    'resident_complaint_update',\n"
        . "                    'resident_complaint_status_update',\n"
        . "                    'resident_complaint_proof',\n"
        . "                ],\n",
    'mobile complaint target routing'
);

$text = replaceExactOnce(
    $text,
    "                    'incident_update',\n                    'incident_updated',\n",
    "                    'incident_update',\n                    'incident_message',\n                    'incident_updated',\n",
    'mobile incident message target routing'
);

$contents[$file] = $text;

// -------------------------------------------------------------------------
// Complaint event separation and active management recipients.
// -------------------------------------------------------------------------

foreach ([
    'app/Http/Controllers/ResidentComplaintController.php',
    'app/Http/Controllers/Api/V1/ResidentComplaintController.php',
] as $file) {
    $text = $contents[$file];

    $text = replaceInsideMethod(
        $text,
        'notifyResidentStatusUpdated',
        function (string $method): string {
            return str_replace(
                "'resident_complaint_update'",
                "'resident_complaint_status_update'",
                $method
            );
        },
        "{$file} complaint status notification"
    );

    $text = replaceInsideMethod(
        $text,
        'notifyResidentProofUploaded',
        function (string $method): string {
            return str_replace(
                "'resident_complaint_update'",
                "'resident_complaint_proof'",
                $method
            );
        },
        "{$file} complaint proof notification"
    );

    $text = replaceInsideMethod(
        $text,
        'deleteComplaintNotifications',
        function (string $method): string {
            if (str_contains($method, "'resident_complaint_status_update'")) {
                return $method;
            }

            return str_replace(
                "                'resident_complaint_update',\n",
                "                'resident_complaint_update',\n"
                    . "                'resident_complaint_status_update',\n"
                    . "                'resident_complaint_proof',\n",
                $method
            );
        },
        "{$file} complaint notification cleanup"
    );

    $text = replaceInsideMethod(
        $text,
        'notifyAdminsAndOfficials',
        function (string $method): string {
            if (str_contains($method, "Schema::hasColumn('users', 'is_active')")) {
                return $method;
            }

            $pattern = "/(->whereIn\(\s*'role',\s*(?:\[.*?\]|\n\s*\[.*?\]\s*)\)\s*)\n(\s*->select)/s";
            $replacement = "$1\n"
                . "            ->when(Schema::hasColumn('users', 'is_active'), function (\$query) {\n"
                . "                \$query->where('is_active', true);\n"
                . "            })\n"
                . "$2";

            $updated = preg_replace($pattern, $replacement, $method, 1, $count);

            if ($updated === null || $count !== 1) {
                throw new RuntimeException('Unable to add active-recipient filter to complaint notifications. Nothing was written.');
            }

            return $updated;
        },
        "{$file} active complaint recipients"
    );

    $contents[$file] = $text;
}

// -------------------------------------------------------------------------
// Validation before any write.
// -------------------------------------------------------------------------

$requiredMarkers = [
    'app/Services/NotificationBellService.php' => [
        "'user_registration' => 'New Registration'",
        "'account_activated' => 'Account Activated'",
        "'incident_message' => 'Incident Message'",
        "'resident_complaint_status_update' => 'Complaint Status Update'",
        "'resident_complaint_proof' => 'Complaint Proof'",
    ],
    'app/Http/Controllers/NotificationOpenController.php' => [
        "'incident_message'",
        "'resident_complaint_status_update'",
        "'resident_complaint_proof'",
    ],
    'app/Http/Controllers/Api/V1/NotificationCenterController.php' => [
        "'incident_message'",
        "'resident_complaint_status_update'",
        "'resident_complaint_proof'",
    ],
    'app/Http/Controllers/ResidentComplaintController.php' => [
        "'resident_complaint_status_update'",
        "'resident_complaint_proof'",
        "Schema::hasColumn('users', 'is_active')",
    ],
    'app/Http/Controllers/Api/V1/ResidentComplaintController.php' => [
        "'resident_complaint_status_update'",
        "'resident_complaint_proof'",
        "Schema::hasColumn('users', 'is_active')",
    ],
];

foreach ($requiredMarkers as $file => $markers) {
    foreach ($markers as $marker) {
        if (! str_contains($contents[$file], $marker)) {
            throw new RuntimeException("Validation failed for {$file}: missing {$marker}. Nothing was written.");
        }
    }
}

foreach ($contents as $file => $text) {
    file_put_contents($file, $text, LOCK_EX);
}

echo "Responsive notification routing patch applied successfully.\n";
echo "Added: account-state labels, incident-message routing, distinct complaint events, active management recipients.\n";
