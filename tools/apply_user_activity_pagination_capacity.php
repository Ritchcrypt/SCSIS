<?php

declare(strict_types=1);

/**
 * Transactional pagination-capacity patch for User Management and Activity Logs.
 *
 * Policy:
 * - User Management default: 25 rows/page
 * - Activity Logs default: 50 rows/page
 * - Allowed/selectable page sizes: 10, 25, 50, 100, 250
 * - Pagination remains server-side; there is no total-record cap.
 *
 * Run from the Laravel project root:
 *   php tools/apply_user_activity_pagination_capacity.php
 *
 * This script validates all expected source patterns before writing any file.
 */

$root = dirname(__DIR__);

$paths = [
    'web_users' => $root . '/app/Http/Controllers/UserManagementController.php',
    'api_users' => $root . '/app/Http/Controllers/Api/V1/UserManagementController.php',
    'web_logs' => $root . '/app/Http/Controllers/Admin/ActivityLogController.php',
    'api_logs' => $root . '/app/Http/Controllers/Api/V1/ActivityLogController.php',
    'logs_view' => $root . '/resources/views/admin/activity-logs/index.blade.php',
];

foreach ($paths as $label => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Required file missing ({$label}): {$path}\n");
        exit(1);
    }
}

function readFileUtf8(string $path): string
{
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$path}\n");
        exit(1);
    }

    return str_replace(["\r\n", "\r"], "\n", $source);
}

function replaceExactly(string $source, string $search, string $replace, int $expectedCount, string $label): string
{
    $count = substr_count($source, $search);

    if ($count !== $expectedCount) {
        fwrite(STDERR, "Patch stopped: {$label} expected {$expectedCount} match(es), found {$count}. No files were written.\n");
        exit(2);
    }

    return str_replace($search, $replace, $source);
}

$before = [];
$after = [];

foreach ($paths as $key => $path) {
    $before[$key] = readFileUtf8($path);
    $after[$key] = $before[$key];
}

// -------------------------------------------------------------------------
// Website User Management: default 25, retain 10, add max 250.
// -------------------------------------------------------------------------
$after['web_users'] = replaceExactly(
    $after['web_users'],
    "\$perPage = (int) \$request->query('per_page', 10);",
    "\$perPage = (int) \$request->query('per_page', 25);",
    1,
    'website User Management default page size'
);
$after['web_users'] = replaceExactly(
    $after['web_users'],
    "if (! in_array(\$perPage, [10, 25, 50, 100], true)) {\n            \$perPage = 10;\n        }",
    "if (! in_array(\$perPage, [10, 25, 50, 100, 250], true)) {\n            \$perPage = 25;\n        }",
    1,
    'website User Management allowed page sizes'
);
$after['web_users'] = replaceExactly(
    $after['web_users'],
    "'perPageOptions' => [10, 25, 50, 100],",
    "'perPageOptions' => [10, 25, 50, 100, 250],",
    1,
    'website User Management selector options'
);

// -------------------------------------------------------------------------
// API User Management: same contract for Flutter/mobile.
// -------------------------------------------------------------------------
$after['api_users'] = replaceExactly(
    $after['api_users'],
    "\$perPage = (int) \$request->query('per_page', 10);",
    "\$perPage = (int) \$request->query('per_page', 25);",
    1,
    'API User Management default page size'
);
$after['api_users'] = replaceExactly(
    $after['api_users'],
    "if (! in_array(\$perPage, [10, 25, 50, 100], true)) {\n            \$perPage = 10;\n        }",
    "if (! in_array(\$perPage, [10, 25, 50, 100, 250], true)) {\n            \$perPage = 25;\n        }",
    1,
    'API User Management allowed page sizes'
);
$after['api_users'] = replaceExactly(
    $after['api_users'],
    "'per_page' => [10, 25, 50, 100],",
    "'per_page' => [10, 25, 50, 100, 250],",
    1,
    'API User Management selector options'
);

// -------------------------------------------------------------------------
// Website Activity Logs: default 50, retain existing options, add 250.
// -------------------------------------------------------------------------
$after['web_logs'] = replaceExactly(
    $after['web_logs'],
    "Rule::in([\n                    10,\n                    25,\n                    50,\n                    100,\n                ]),",
    "Rule::in([\n                    10,\n                    25,\n                    50,\n                    100,\n                    250,\n                ]),",
    1,
    'website Activity Logs validation page sizes'
);
$after['web_logs'] = replaceExactly(
    $after['web_logs'],
    "\$validated['per_page']\n            ?? 25",
    "\$validated['per_page']\n            ?? 50",
    1,
    'website Activity Logs default page size'
);

// -------------------------------------------------------------------------
// API Activity Logs: same validation/default/options for mobile.
// -------------------------------------------------------------------------
$after['api_logs'] = replaceExactly(
    $after['api_logs'],
    "Rule::in([\n                    10,\n                    25,\n                    50,\n                    100,\n                ]),",
    "Rule::in([\n                    10,\n                    25,\n                    50,\n                    100,\n                    250,\n                ]),",
    1,
    'API Activity Logs validation page sizes'
);
$after['api_logs'] = replaceExactly(
    $after['api_logs'],
    "'per_page' => [\n                        10,\n                        25,\n                        50,\n                        100,\n                    ],",
    "'per_page' => [\n                        10,\n                        25,\n                        50,\n                        100,\n                        250,\n                    ],",
    1,
    'API Activity Logs selector options'
);
$after['api_logs'] = replaceExactly(
    $after['api_logs'],
    "?? 25\n            ),",
    "?? 50\n            ),",
    1,
    'API Activity Logs default page size'
);

// -------------------------------------------------------------------------
// Website Activity Logs selector is hard-coded in Blade.
// -------------------------------------------------------------------------
$after['logs_view'] = replaceExactly(
    $after['logs_view'],
    "@foreach ([10, 25, 50, 100] as \$size)",
    "@foreach ([10, 25, 50, 100, 250] as \$size)",
    1,
    'website Activity Logs selector'
);

// Final in-memory validation before any write.
$assertions = [
    [str_contains($after['web_users'], "query('per_page', 25)"), 'website User Management default 25'],
    [str_contains($after['web_users'], '[10, 25, 50, 100, 250]'), 'website User Management max 250'],
    [str_contains($after['api_users'], "query('per_page', 25)"), 'API User Management default 25'],
    [str_contains($after['api_users'], "'per_page' => [10, 25, 50, 100, 250]"), 'API User Management max 250'],
    [str_contains($after['web_logs'], '250,'), 'website Activity Logs allows 250'],
    [str_contains($after['web_logs'], '?? 50'), 'website Activity Logs default 50'],
    [str_contains($after['api_logs'], '250,'), 'API Activity Logs allows 250'],
    [str_contains($after['api_logs'], '?? 50'), 'API Activity Logs default 50'],
    [str_contains($after['logs_view'], '[10, 25, 50, 100, 250]'), 'website Activity Logs selector includes 250'],
];

foreach ($assertions as [$passed, $label]) {
    if (! $passed) {
        fwrite(STDERR, "Final validation failed: {$label}. No files were written.\n");
        exit(3);
    }
}

$changed = [];
foreach ($paths as $key => $path) {
    if ($after[$key] === $before[$key]) {
        continue;
    }

    if (file_put_contents($path, $after[$key], LOCK_EX) === false) {
        fwrite(STDERR, "Unable to write {$path}\n");
        exit(4);
    }

    $changed[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
}

echo "User Management / Activity Logs pagination capacity patch applied.\n";
echo "User Management: default 25; options 10, 25, 50, 100, 250.\n";
echo "Activity Logs: default 50; options 10, 25, 50, 100, 250.\n";
echo "Server-side pagination remains enabled; total stored records are not capped by this change.\n";

if ($changed !== []) {
    echo "Changed:\n";
    foreach ($changed as $path) {
        echo " - {$path}\n";
    }
}
