<?php

declare(strict_types=1);

/**
 * Repairs the known UTF-8/PowerShell mojibake introduced into Flutter source,
 * then scans every Dart file under lib/ for suspicious corruption markers.
 *
 * Run from the Flutter project root:
 *   php tool/fix_flutter_mojibake.php
 *
 * The script never touches Git history and never touches Laravel or main.
 */

$libRoot = __DIR__ . '/../lib';

if (! is_dir($libRoot)) {
    fwrite(STDERR, "Flutter lib/ directory was not found. Run this from the Flutter project root.\n");
    exit(1);
}

$replacements = [
    // CP437-style corruption previously introduced by Windows PowerShell 5.1.
    'ΓÇö' => '\\u2014',     // em dash
    'ΓÇô' => '\\u2013',     // en dash
    'ΓÇó' => '\\u2022',     // bullet
    '≡ƒîñ∩╕Å' => '\\u{1F324}\\uFE0F', // sun behind small cloud + VS16

    // Common UTF-8-as-Windows-1252 mojibake variants.
    'â€”' => '\\u2014',
    'â€“' => '\\u2013',
    'â€¢' => '\\u2022',
    'Â°' => '\\u00B0',
    'Â±' => '\\u00B1',
];

$suspiciousMarkers = [
    'ΓÇ',
    '≡ƒ',
    '∩╕',
    'Ã',
    'Â',
    'â€',
    'ðŸ',
    'ï¸',
    "\xEF\xBF\xBD",
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($libRoot, FilesystemIterator::SKIP_DOTS)
);

$dartFiles = [];
foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'dart') {
        continue;
    }

    $dartFiles[] = $fileInfo->getPathname();
}

sort($dartFiles);

$changed = [];

foreach ($dartFiles as $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }

    $normalized = str_replace(["\r\n", "\r"], "\n", $source);
    $repaired = strtr($normalized, $replacements);

    if ($repaired !== $normalized) {
        if (file_put_contents($path, $repaired, LOCK_EX) === false) {
            fwrite(STDERR, "Unable to write {$path}.\n");
            exit(1);
        }

        $changed[] = str_replace('\\', '/', substr($path, strlen(dirname(__DIR__)) + 1));
    }
}

$problems = [];

foreach ($dartFiles as $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        continue;
    }

    $lines = preg_split('/\R/u', $source) ?: [];

    foreach ($lines as $index => $line) {
        foreach ($suspiciousMarkers as $marker) {
            if (str_contains($line, $marker)) {
                $relative = str_replace('\\', '/', substr($path, strlen(dirname(__DIR__)) + 1));
                $problems[] = sprintf(
                    '%s:%d contains suspicious marker %s',
                    $relative,
                    $index + 1,
                    json_encode($marker, JSON_UNESCAPED_UNICODE)
                );
            }
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, "Mojibake scan still found suspicious source text:\n");
    foreach (array_values(array_unique($problems)) as $problem) {
        fwrite(STDERR, " - {$problem}\n");
    }
    fwrite(STDERR, "No additional automatic guesses were made. Review these lines before committing.\n");
    exit(2);
}

if ($changed === []) {
    echo "No known mojibake replacements were needed.\n";
} else {
    echo "Repaired known mojibake in:\n";
    foreach ($changed as $path) {
        echo " - {$path}\n";
    }
}

echo "Global Flutter lib/**/*.dart mojibake scan passed.\n";
