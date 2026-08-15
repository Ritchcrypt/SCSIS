<?php

declare(strict_types=1);

/**
 * Transactional Flutter pagination-capacity patch.
 *
 * Policy:
 * - User Management default: 25 rows/page
 * - Activity Logs default: 50 rows/page
 * - Allowed/selectable page sizes: 10, 25, 50, 100, 250
 *
 * Run from Flutter project root:
 *   php tool/apply_user_activity_pagination_capacity.php
 *
 * All expected patterns are validated before any file is written.
 */

$root = dirname(__DIR__);

$paths = [
    'users_screen' => $root . '/lib/screens/user_management_screen.dart',
    'users_service' => $root . '/lib/services/user_management_service.dart',
    'logs_screen' => $root . '/lib/screens/activity_logs_screen.dart',
    'logs_service' => $root . '/lib/services/activity_log_service.dart',
];

foreach ($paths as $label => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Required file missing ({$label}): {$path}\n");
        exit(1);
    }
}

function readSource(string $path): string
{
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$path}\n");
        exit(1);
    }

    return str_replace(["\r\n", "\r"], "\n", $source);
}

function replaceRequired(string $source, string $search, string $replace, int $expected, string $label): string
{
    $count = substr_count($source, $search);

    if ($count !== $expected) {
        fwrite(STDERR, "Patch stopped: {$label} expected {$expected} match(es), found {$count}. No files were written.\n");
        exit(2);
    }

    return str_replace($search, $replace, $source);
}

$before = [];
$after = [];

foreach ($paths as $key => $path) {
    $before[$key] = readSource($path);
    $after[$key] = $before[$key];
}

// User Management screen: default/fallback 25 and 250 in fallback selector.
$after['users_screen'] = replaceRequired(
    $after['users_screen'],
    'int _perPage = 10;',
    'int _perPage = 25;',
    1,
    'User Management screen default'
);
$after['users_screen'] = replaceRequired(
    $after['users_screen'],
    'const <Object?>[10, 25, 50, 100]',
    'const <Object?>[10, 25, 50, 100, 250]',
    1,
    'User Management filter fallback options'
);
$after['users_screen'] = replaceRequired(
    $after['users_screen'],
    'const <int>[10, 25, 50, 100]',
    'const <int>[10, 25, 50, 100, 250]',
    1,
    'User Management dropdown fallback options'
);
$after['users_screen'] = replaceRequired(
    $after['users_screen'],
    "_perPage = _int(result['per_page'], fallback: 10);",
    "_perPage = _int(result['per_page'], fallback: 25);",
    1,
    'User Management applied-filter fallback'
);

// User Management service default.
$after['users_service'] = replaceRequired(
    $after['users_service'],
    'int perPage = 10,',
    'int perPage = 25,',
    1,
    'User Management service default'
);

// Activity Logs screen: default/reset/fallback 50 and max 250.
$after['logs_screen'] = replaceRequired(
    $after['logs_screen'],
    'int _perPage = 25;',
    'int _perPage = 50;',
    1,
    'Activity Logs screen default'
);
$after['logs_screen'] = replaceRequired(
    $after['logs_screen'],
    "_perPage = _int(filters['per_page'], fallback: 25);",
    "_perPage = _int(filters['per_page'], fallback: 50);",
    1,
    'Activity Logs response fallback'
);
$after['logs_screen'] = replaceRequired(
    $after['logs_screen'],
    '_perPage = 25;',
    '_perPage = 50;',
    1,
    'Activity Logs clear-filters default'
);
$after['logs_screen'] = replaceRequired(
    $after['logs_screen'],
    'fallback: const <int>[10, 25, 50, 100],',
    'fallback: const <int>[10, 25, 50, 100, 250],',
    1,
    'Activity Logs filter fallback options'
);
$after['logs_screen'] = replaceRequired(
    $after['logs_screen'],
    "_perPage = _int(result['per_page'], fallback: 25);",
    "_perPage = _int(result['per_page'], fallback: 50);",
    1,
    'Activity Logs applied-filter fallback'
);

// Activity Logs service default.
$after['logs_service'] = replaceRequired(
    $after['logs_service'],
    'int perPage = 25,',
    'int perPage = 50,',
    1,
    'Activity Logs service default'
);

// Validate complete target state before writes.
$checks = [
    [str_contains($after['users_screen'], 'int _perPage = 25;'), 'User Management screen default 25'],
    [str_contains($after['users_screen'], '[10, 25, 50, 100, 250]'), 'User Management fallback includes 250'],
    [str_contains($after['users_service'], 'int perPage = 25,'), 'User Management service default 25'],
    [str_contains($after['logs_screen'], 'int _perPage = 50;'), 'Activity Logs screen default 50'],
    [str_contains($after['logs_screen'], '[10, 25, 50, 100, 250]'), 'Activity Logs fallback includes 250'],
    [str_contains($after['logs_service'], 'int perPage = 50,'), 'Activity Logs service default 50'],
];

foreach ($checks as [$passed, $label]) {
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

echo "Flutter User Management / Activity Logs pagination capacity patch applied.\n";
echo "User Management: default 25; options 10, 25, 50, 100, 250.\n";
echo "Activity Logs: default 50; options 10, 25, 50, 100, 250.\n";

if ($changed !== []) {
    echo "Changed:\n";
    foreach ($changed as $path) {
        echo " - {$path}\n";
    }
}
