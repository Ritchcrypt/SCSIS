<?php

declare(strict_types=1);

/**
 * Transactional integration patch for TabangNow mobile photo cropping.
 *
 * This script patches every current Flutter photo-selection entry point while
 * leaving non-image attachments such as PDFs untouched.
 *
 * It validates every source transformation in memory before writing any file,
 * so a source mismatch cannot leave a half-patched project.
 *
 * Run from the Flutter project root:
 *   php tool/apply_global_image_crop_integration.php
 */

$root = dirname(__DIR__);

function readUtf8(string $path): string
{
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException("Unable to read {$path}.");
    }

    return str_replace(["\r\n", "\r"], "\n", $source);
}

function addImport(string $source, string $afterImport, string $newImport, string $label): string
{
    if (str_contains($source, $newImport)) {
        return $source;
    }

    if (! str_contains($source, $afterImport)) {
        throw new RuntimeException("Expected import anchor not found for {$label}.");
    }

    return str_replace(
        $afterImport,
        $afterImport . "\n" . $newImport,
        $source
    );
}

function replaceBlock(
    string $source,
    string $old,
    string $new,
    string $alreadyMarker,
    string $label
): string {
    if (str_contains($source, $alreadyMarker)) {
        return $source;
    }

    if (! str_contains($source, $old)) {
        throw new RuntimeException("Expected source block not found for {$label}.");
    }

    return str_replace($old, $new, $source);
}

$targets = [
    'system_branding' => $root . '/lib/screens/system_branding_screen.dart',
    'user_management' => $root . '/lib/screens/user_management_form_screen.dart',
    'complaint_create' => $root . '/lib/screens/resident_complaint_create_screen.dart',
    'complaint_detail' => $root . '/lib/screens/resident_complaint_detail_screen.dart',
    'incident_report' => $root . '/lib/screens/report_incident_screen.dart',
];

foreach ($targets as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Required source file not found: {$path}\n");
        exit(1);
    }
}

try {
    $before = [];
    $after = [];

    foreach ($targets as $key => $path) {
        $before[$key] = readUtf8($path);
        $after[$key] = $before[$key];
    }

    // ------------------------------------------------------------------
    // System Branding: logo is always square so it can fill the SOS coin,
    // sidebar brand, and other logo surfaces without letterboxing.
    // ------------------------------------------------------------------
    $after['system_branding'] = addImport(
        $after['system_branding'],
        "import '../services/branding_service.dart';",
        "import '../services/global_image_crop_service.dart';",
        'System Branding crop service'
    );

    $after['system_branding'] = replaceBlock(
        $after['system_branding'],
        <<<'OLD'
    final file = result.files.single;

    if (file.size > 5 * 1024 * 1024) {
OLD,
        <<<'NEW'
    var file = result.files.single;

    try {
      final cropped = await GlobalImageCropService.crop(
        file: file,
        mode: GlobalImageCropMode.square,
        title: 'Adjust System Logo',
      );

      if (cropped == null || !mounted) {
        return;
      }

      file = cropped;
    } on GlobalImageCropException catch (exception) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(exception.message)),
        );
      }
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
NEW,
        "title: 'Adjust System Logo'",
        'System Branding logo crop'
    );

    $after['system_branding'] = str_replace(
        'JPG, JPEG, PNG, or WEBP. Maximum 5 MB.',
        'JPG, JPEG, PNG, or WEBP. Drag and zoom to crop after choosing. Maximum 5 MB.',
        $after['system_branding']
    );

    // ------------------------------------------------------------------
    // User Management: profile pictures are square, then rendered with
    // circular/rounded clipping depending on the surface.
    // ------------------------------------------------------------------
    $after['user_management'] = addImport(
        $after['user_management'],
        "import '../services/user_management_service.dart';",
        "import '../services/global_image_crop_service.dart';",
        'User Management crop service'
    );

    $after['user_management'] = replaceBlock(
        $after['user_management'],
        <<<'OLD'
    final file = result.files.single;

    if (file.path == null) {
      _show('The selected profile picture could not be accessed.');
      return;
    }

    const maxBytes = 5 * 1024 * 1024;
OLD,
        <<<'NEW'
    var file = result.files.single;

    if (file.path == null) {
      _show('The selected profile picture could not be accessed.');
      return;
    }

    try {
      final cropped = await GlobalImageCropService.crop(
        file: file,
        mode: GlobalImageCropMode.square,
        title: 'Adjust Profile Picture',
      );

      if (cropped == null || !mounted) {
        return;
      }

      file = cropped;
    } on GlobalImageCropException catch (exception) {
      if (mounted) {
        _show(exception.message);
      }
      return;
    }

    const maxBytes = 5 * 1024 * 1024;
NEW,
        "title: 'Adjust Profile Picture'",
        'User profile crop'
    );

    $after['user_management'] = str_replace(
        'JPG, PNG, or WEBP. Maximum size: 5 MB.',
        'JPG, PNG, or WEBP. After choosing, drag and zoom to crop. Maximum size: 5 MB.',
        $after['user_management']
    );

    // ------------------------------------------------------------------
    // Resident complaint evidence: free crop / aspect-ratio presets.
    // ------------------------------------------------------------------
    $after['complaint_create'] = addImport(
        $after['complaint_create'],
        "import '../services/resident_complaint_service.dart';",
        "import '../services/global_image_crop_service.dart';",
        'Complaint evidence crop service'
    );

    $after['complaint_create'] = replaceBlock(
        $after['complaint_create'],
        <<<'OLD'
    final file = result.files.single;

    if (file.path == null) {
      _show('The selected image could not be accessed.');
      return;
    }

    const maxBytes = 10 * 1024 * 1024;
OLD,
        <<<'NEW'
    var file = result.files.single;

    if (file.path == null) {
      _show('The selected image could not be accessed.');
      return;
    }

    try {
      final cropped = await GlobalImageCropService.crop(
        file: file,
        mode: GlobalImageCropMode.free,
        title: 'Adjust Evidence Photo',
      );

      if (cropped == null || !mounted) {
        return;
      }

      file = cropped;
    } on GlobalImageCropException catch (exception) {
      if (mounted) {
        _show(exception.message);
      }
      return;
    }

    const maxBytes = 10 * 1024 * 1024;
NEW,
        "title: 'Adjust Evidence Photo'",
        'Resident complaint evidence crop'
    );

    $after['complaint_create'] = str_replace(
        'Accepted: JPG, JPEG, PNG, WEBP. Maximum secure-upload size: 10MB.',
        'Accepted: JPG, JPEG, PNG, WEBP. After choosing, adjust the crop before attaching. Maximum secure-upload size: 10MB.',
        $after['complaint_create']
    );

    // ------------------------------------------------------------------
    // Complaint action proof: same free crop behavior for Admin/Official.
    // ------------------------------------------------------------------
    $after['complaint_detail'] = addImport(
        $after['complaint_detail'],
        "import '../services/resident_complaint_service.dart';",
        "import '../services/global_image_crop_service.dart';",
        'Complaint proof crop service'
    );

    $after['complaint_detail'] = replaceBlock(
        $after['complaint_detail'],
        <<<'OLD'
    final file = result.files.single;

    if (file.path == null) {
      _show('The selected proof image could not be accessed.');
      return;
    }

    const maxBytes = 10 * 1024 * 1024;
OLD,
        <<<'NEW'
    var file = result.files.single;

    if (file.path == null) {
      _show('The selected proof image could not be accessed.');
      return;
    }

    try {
      final cropped = await GlobalImageCropService.crop(
        file: file,
        mode: GlobalImageCropMode.free,
        title: 'Adjust Action Proof',
      );

      if (cropped == null || !mounted) {
        return;
      }

      file = cropped;
    } on GlobalImageCropException catch (exception) {
      if (mounted) {
        _show(exception.message);
      }
      return;
    }

    const maxBytes = 10 * 1024 * 1024;
NEW,
        "title: 'Adjust Action Proof'",
        'Complaint action proof crop'
    );

    // ------------------------------------------------------------------
    // Incident evidence supports photos + PDFs. Crop every selected image
    // sequentially; PDFs stay exactly as selected.
    // ------------------------------------------------------------------
    $after['incident_report'] = addImport(
        $after['incident_report'],
        "import '../services/incident_service.dart';",
        "import '../services/global_image_crop_service.dart';",
        'Incident evidence crop service'
    );

    $after['incident_report'] = replaceBlock(
        $after['incident_report'],
        <<<'OLD'
    final combined = <PlatformFile>[..._evidence, ...result.files];
OLD,
        <<<'NEW'
    final adjustedFiles = <PlatformFile>[];

    for (final selectedFile in result.files) {
      if (!GlobalImageCropService.isCroppableImage(selectedFile)) {
        adjustedFiles.add(selectedFile);
        continue;
      }

      try {
        final cropped = await GlobalImageCropService.crop(
          file: selectedFile,
          mode: GlobalImageCropMode.free,
          title: 'Adjust Incident Evidence',
        );

        if (cropped != null) {
          adjustedFiles.add(cropped);
        }
      } on GlobalImageCropException catch (exception) {
        if (mounted) {
          _showMessage(exception.message);
        }
        return;
      }
    }

    if (!mounted || adjustedFiles.isEmpty) {
      return;
    }

    final combined = <PlatformFile>[..._evidence, ...adjustedFiles];
NEW,
        "title: 'Adjust Incident Evidence'",
        'Incident evidence crop pipeline'
    );

    // ------------------------------------------------------------------
    // Validate the full result before any write occurs.
    // ------------------------------------------------------------------
    $requiredChecks = [
        'system_branding' => [
            "import '../services/global_image_crop_service.dart';",
            "title: 'Adjust System Logo'",
        ],
        'user_management' => [
            "import '../services/global_image_crop_service.dart';",
            "title: 'Adjust Profile Picture'",
        ],
        'complaint_create' => [
            "import '../services/global_image_crop_service.dart';",
            "title: 'Adjust Evidence Photo'",
        ],
        'complaint_detail' => [
            "import '../services/global_image_crop_service.dart';",
            "title: 'Adjust Action Proof'",
        ],
        'incident_report' => [
            "import '../services/global_image_crop_service.dart';",
            "title: 'Adjust Incident Evidence'",
            'adjustedFiles',
        ],
    ];

    foreach ($requiredChecks as $key => $markers) {
        foreach ($markers as $marker) {
            if (! str_contains($after[$key], $marker)) {
                throw new RuntimeException(
                    "Final validation failed for {$key}: missing {$marker}."
                );
            }
        }
    }

    $changed = [];

    foreach ($targets as $key => $path) {
        if ($after[$key] === $before[$key]) {
            continue;
        }

        if (file_put_contents($path, $after[$key], LOCK_EX) === false) {
            throw new RuntimeException("Unable to write {$path}.");
        }

        $changed[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
    }

    echo "Global image crop integration applied successfully.\n";

    if ($changed === []) {
        echo "All current photo picker screens were already integrated.\n";
    } else {
        echo "Changed:\n";
        foreach ($changed as $path) {
            echo " - {$path}\n";
        }
    }

    echo "Square crop: system logos and profile pictures.\n";
    echo "Adjustable crop: incident evidence, complaint evidence, and action proof.\n";
    echo "Non-image attachments such as PDFs remain unchanged.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Patch stopped safely: {$exception->getMessage()}\n");
    fwrite(STDERR, "No target source file was written unless all transformations validated first.\n");
    exit(2);
}
