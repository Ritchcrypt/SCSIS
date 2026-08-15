<?php

/**
 * Safe UTF-8 preserving patch for the two large complaint controllers.
 * The shared notification service and routing files are already committed on
 * feature/mobile-registration-sos; this script only makes the surgical
 * complaint-event changes without replacing either controller wholesale.
 *
 * Run from the Laravel project root:
 *
 *   php tools/apply_responsive_notification_patch.php
 */

$files = [
    'app/Http/Controllers/ResidentComplaintController.php',
    'app/Http/Controllers/Api/V1/ResidentComplaintController.php',
];

$contents = [];

foreach ($files as $file) {
    if (! is_file($file)) {
        throw new RuntimeException("Missing required file: {$file}");
    }

    $contents[$file] = file_get_contents($file);

    if ($contents[$file] === false) {
        throw new RuntimeException("Unable to read {$file}");
    }
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
        throw new RuntimeException(
            "{$label}: expected exactly one {$methodName} method, found {$count}. Nothing was written."
        );
    }

    $original = $matches[0][0];
    $updated = $transform($original);

    if ($updated === $original) {
        return $text;
    }

    $position = strpos($text, $original);

    if ($position === false) {
        throw new RuntimeException("{$label}: method position could not be resolved.");
    }

    return substr_replace($text, $updated, $position, strlen($original));
}

foreach ($files as $file) {
    $text = $contents[$file];

    $text = replaceInsideMethod(
        $text,
        'notifyResidentStatusUpdated',
        static function (string $method): string {
            if (str_contains($method, "'resident_complaint_status_update'")) {
                return $method;
            }

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
        static function (string $method): string {
            if (str_contains($method, "'resident_complaint_proof'")) {
                return $method;
            }

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
        static function (string $method): string {
            if (
                str_contains($method, "'resident_complaint_status_update'")
                && str_contains($method, "'resident_complaint_proof'")
            ) {
                return $method;
            }

            $needle = "                    'resident_complaint_update',\n";

            if (! str_contains($method, $needle)) {
                $needle = "                'resident_complaint_update',\n";
            }

            if (! str_contains($method, $needle)) {
                throw new RuntimeException(
                    'Unable to locate resident_complaint_update in notification cleanup.'
                );
            }

            $indent = str_starts_with($needle, '                    ')
                ? '                    '
                : '                ';

            return str_replace(
                $needle,
                $needle
                    . $indent . "'resident_complaint_status_update',\n"
                    . $indent . "'resident_complaint_proof',\n",
                $method
            );
        },
        "{$file} complaint notification cleanup"
    );

    $text = replaceInsideMethod(
        $text,
        'notifyAdminsAndOfficials',
        static function (string $method): string {
            if (str_contains($method, "->where('is_active', true)")) {
                return $method;
            }

            $pattern = '/(->whereIn\(.*?\)\s*)\n(\s*->select)/s';
            $replacement = '$1' . "\n"
                . "            ->when(Schema::hasColumn('users', 'is_active'), function (\$query): void {\n"
                . "                \$query->where('is_active', true);\n"
                . "            })\n"
                . '$2';

            $updated = preg_replace($pattern, $replacement, $method, 1, $count);

            if ($updated === null || $count !== 1) {
                throw new RuntimeException(
                    'Unable to add the active-recipient filter to complaint notifications.'
                );
            }

            return $updated;
        },
        "{$file} active complaint recipients"
    );

    foreach ([
        "'resident_complaint_status_update'",
        "'resident_complaint_proof'",
        "->where('is_active', true)",
    ] as $marker) {
        if (! str_contains($text, $marker)) {
            throw new RuntimeException(
                "Validation failed for {$file}: missing {$marker}. Nothing was written."
            );
        }
    }

    $contents[$file] = $text;
}

// Nothing is written until both controllers pass all transformations above.
foreach ($contents as $file => $text) {
    if (file_put_contents($file, $text, LOCK_EX) === false) {
        throw new RuntimeException("Unable to write {$file}");
    }
}

echo "Responsive complaint notifications patched successfully.\n";
echo "Status updates and proof uploads are now separate notification events.\n";
echo "Inactive Admin/Official accounts are excluded from new complaint alerts.\n";
