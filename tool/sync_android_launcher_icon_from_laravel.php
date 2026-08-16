<?php

declare(strict_types=1);

$flutterRoot = dirname(__DIR__);

$laravelRoot = $argv[1]
    ?? 'C:\xampp\htdocs\TabangNow_Laravel';

$laravelRoot = rtrim(
    $laravelRoot,
    "\\/"
);

$autoload = $laravelRoot
    . DIRECTORY_SEPARATOR
    . 'vendor'
    . DIRECTORY_SEPARATOR
    . 'autoload.php';

$bootstrap = $laravelRoot
    . DIRECTORY_SEPARATOR
    . 'bootstrap'
    . DIRECTORY_SEPARATOR
    . 'app.php';

if (!is_file($autoload) || !is_file($bootstrap)) {
    fwrite(
        STDERR,
        "ERROR: Laravel project was not found or is not installed:\n"
        . "{$laravelRoot}\n"
    );
    exit(1);
}

if (!extension_loaded('gd')) {
    fwrite(
        STDERR,
        "ERROR: PHP GD extension is required to generate Android launcher icons.\n"
    );
    exit(1);
}

require $autoload;

/** @var \Illuminate\Foundation\Application $app */
$app = require $bootstrap;

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(
    \Illuminate\Contracts\Console\Kernel::class
);

$kernel->bootstrap();

$setting = \App\Models\SystemSetting::query()->first();

if (!$setting) {
    fwrite(
        STDERR,
        "ERROR: No System Branding settings were found in Laravel.\n"
    );
    exit(1);
}

$logoPath = trim(
    (string) $setting->system_logo_path
);

$logoPath = str_replace('\\', '/', $logoPath);
$logoPath = ltrim($logoPath, '/');

if (
    $logoPath === ''
    || str_contains($logoPath, '..')
    || !str_starts_with($logoPath, 'system-branding/')
) {
    fwrite(
        STDERR,
        "ERROR: Laravel does not currently have a safe uploaded System Branding logo.\n"
        . "Upload the real TabangNow logo in System Branding first.\n"
    );
    exit(1);
}

$disk = \Illuminate\Support\Facades\Storage::disk('public');

if (!$disk->exists($logoPath)) {
    fwrite(
        STDERR,
        "ERROR: The configured Laravel System Branding logo file is missing:\n"
        . "{$logoPath}\n"
    );
    exit(1);
}

$sourcePath = $disk->path($logoPath);
$sourceBytes = file_get_contents($sourcePath);

if ($sourceBytes === false) {
    fwrite(STDERR, "ERROR: Unable to read Laravel System Branding logo.\n");
    exit(1);
}

$source = @imagecreatefromstring($sourceBytes);

if (!$source) {
    fwrite(
        STDERR,
        "ERROR: The Laravel System Branding logo could not be decoded by GD.\n"
    );
    exit(1);
}

$sourceWidth = imagesx($source);
$sourceHeight = imagesy($source);

if ($sourceWidth < 1 || $sourceHeight < 1) {
    imagedestroy($source);
    fwrite(STDERR, "ERROR: Invalid System Branding logo dimensions.\n");
    exit(1);
}

$sizes = [
    'mipmap-mdpi' => 48,
    'mipmap-hdpi' => 72,
    'mipmap-xhdpi' => 96,
    'mipmap-xxhdpi' => 144,
    'mipmap-xxxhdpi' => 192,
];

$backupDir = rtrim(
    sys_get_temp_dir(),
    DIRECTORY_SEPARATOR
) . DIRECTORY_SEPARATOR
    . 'tabangnow_flutter_launcher_backups'
    . DIRECTORY_SEPARATOR
    . date('Ymd_His');

if (
    !is_dir($backupDir)
    && !mkdir($backupDir, 0777, true)
    && !is_dir($backupDir)
) {
    imagedestroy($source);
    fwrite(STDERR, "ERROR: Unable to create icon backup directory.\n");
    exit(1);
}

foreach ($sizes as $directory => $size) {
    $targetDir = $flutterRoot
        . DIRECTORY_SEPARATOR
        . 'android'
        . DIRECTORY_SEPARATOR
        . 'app'
        . DIRECTORY_SEPARATOR
        . 'src'
        . DIRECTORY_SEPARATOR
        . 'main'
        . DIRECTORY_SEPARATOR
        . 'res'
        . DIRECTORY_SEPARATOR
        . $directory;

    $target = $targetDir
        . DIRECTORY_SEPARATOR
        . 'ic_launcher.png';

    if (!is_dir($targetDir)) {
        imagedestroy($source);
        fwrite(
            STDERR,
            "ERROR: Android launcher directory missing:\n{$targetDir}\n"
        );
        exit(1);
    }

    if (is_file($target)) {
        $backup = $backupDir
            . DIRECTORY_SEPARATOR
            . $directory
            . '-ic_launcher.png';

        if (!copy($target, $backup)) {
            imagedestroy($source);
            fwrite(
                STDERR,
                "ERROR: Unable to back up existing launcher icon:\n{$target}\n"
            );
            exit(1);
        }
    }

    $canvas = imagecreatetruecolor($size, $size);

    if (!$canvas) {
        imagedestroy($source);
        fwrite(STDERR, "ERROR: Unable to create {$size}px launcher icon canvas.\n");
        exit(1);
    }

    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    $transparent = imagecolorallocatealpha(
        $canvas,
        0,
        0,
        0,
        127
    );

    imagefilledrectangle(
        $canvas,
        0,
        0,
        $size,
        $size,
        $transparent
    );

    /*
     * Keep a safe margin so Android launchers do not visually clip
     * the barangay/system logo.
     */
    $padding = max(
        2,
        (int) round($size * 0.10)
    );

    $available = $size - ($padding * 2);

    $ratio = min(
        $available / $sourceWidth,
        $available / $sourceHeight
    );

    $targetWidth = max(
        1,
        (int) round($sourceWidth * $ratio)
    );

    $targetHeight = max(
        1,
        (int) round($sourceHeight * $ratio)
    );

    $targetX = (int) floor(
        ($size - $targetWidth) / 2
    );

    $targetY = (int) floor(
        ($size - $targetHeight) / 2
    );

    $copied = imagecopyresampled(
        $canvas,
        $source,
        $targetX,
        $targetY,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight
    );

    if (!$copied) {
        imagedestroy($canvas);
        imagedestroy($source);
        fwrite(STDERR, "ERROR: Unable to resize launcher icon.\n");
        exit(1);
    }

    $written = imagepng(
        $canvas,
        $target,
        6
    );

    imagedestroy($canvas);

    if (!$written || !is_file($target)) {
        imagedestroy($source);
        fwrite(
            STDERR,
            "ERROR: Unable to save Android launcher icon:\n{$target}\n"
        );
        exit(1);
    }
}

imagedestroy($source);

echo "SUCCESS: Android launcher icons now use the real Laravel System Branding logo.\n";
echo "Source: {$logoPath}\n";
echo "Backup directory: {$backupDir}\n";
