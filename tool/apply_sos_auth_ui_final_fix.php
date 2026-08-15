<?php

/**
 * Final, UTF-8-preserving mobile SOS/auth UI repair.
 *
 * Run from the Flutter project root:
 *   php tool/apply_sos_auth_ui_final_fix.php
 *
 * This patch:
 * - switches normal startup from DevSessionGate to AuthGate;
 * - makes logout return to the real login screen;
 * - preserves the global SOS host and SOS form;
 * - removes the redundant SOS reminder card;
 * - keeps GPS status, but makes it compact and theme-safe.
 */

$paths = [
    'lib/main.dart',
    'lib/screens/home_screen.dart',
    'lib/widgets/global_sos_overlay.dart',
];

foreach ($paths as $path) {
    if (! is_file($path)) {
        throw new RuntimeException("Missing required file: {$path}");
    }
}

$originals = [];
foreach ($paths as $path) {
    $value = file_get_contents($path);
    if ($value === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    $originals[$path] = str_replace(["\r\n", "\r"], "\n", $value);
}

$patched = $originals;

// -------------------------------------------------------------------------
// main.dart: use the real authentication gate, never DevSessionGate.
// -------------------------------------------------------------------------

$main = $patched['lib/main.dart'];

$main = str_replace(
    "import 'screens/dev_session_gate.dart';",
    "import 'screens/auth_gate.dart';",
    $main
);

if (! str_contains($main, "import 'screens/auth_gate.dart';")) {
    $anchor = "import 'widgets/global_sos_overlay.dart';\n";
    if (! str_contains($main, $anchor)) {
        throw new RuntimeException('main.dart SOS overlay import anchor was not found.');
    }

    $main = str_replace(
        $anchor,
        $anchor . "\nimport 'screens/auth_gate.dart';\n",
        $main
    );
}

$main = str_replace(
    'home: const DevSessionGate(),',
    'home: const AuthGate(),',
    $main
);

if (! str_contains($main, 'home: const AuthGate(),')) {
    throw new RuntimeException('Unable to set AuthGate as the mobile home screen.');
}

if (! str_contains($main, "'/login': (_) => const AuthGate(),")) {
    $homeAnchor = "      home: const AuthGate(),\n";
    if (! str_contains($main, $homeAnchor)) {
        throw new RuntimeException('main.dart AuthGate home anchor was not found.');
    }

    $main = str_replace(
        $homeAnchor,
        "      routes: <String, WidgetBuilder>{\n"
            . "        '/login': (_) => const AuthGate(),\n"
            . "      },\n"
            . $homeAnchor,
        $main
    );
}

$patched['lib/main.dart'] = $main;

// -------------------------------------------------------------------------
// HomeScreen: logout must end the authenticated session and return to login.
// It must NOT silently create another development Admin session.
// -------------------------------------------------------------------------

$home = $patched['lib/screens/home_screen.dart'];

$logoutPattern = '~  Future<void> _logout\(\) async \{.*?\n  \}\n\n  void _selectModule~s';

if (preg_match($logoutPattern, $home) !== 1) {
    throw new RuntimeException('HomeScreen logout method boundary was not found.');
}

$logoutReplacement = <<<'DART'
  Future<void> _logout() async {
    if (_loggingOut) {
      return;
    }

    setState(() {
      _loggingOut = true;
    });

    try {
      await _authService.logout();

      if (!mounted) {
        return;
      }

      Navigator.of(context).pushNamedAndRemoveUntil(
        '/login',
        (_) => false,
      );
    } on AuthException catch (exception) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loggingOut = false;
      });

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(exception.message)));
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _loggingOut = false;
      });

      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          const SnackBar(
            content: Text('Unable to log out from TabangNow.'),
          ),
        );
    }
  }

  void _selectModule
DART;

$home = preg_replace($logoutPattern, $logoutReplacement, $home, 1, $logoutCount);

if ($home === null || $logoutCount !== 1) {
    throw new RuntimeException('Unable to replace HomeScreen logout behavior.');
}

$patched['lib/screens/home_screen.dart'] = $home;

// -------------------------------------------------------------------------
// Global SOS form: remove redundant safety reminder and replace only the
// location status widget with a compact theme-safe version.
// -------------------------------------------------------------------------

$overlay = $patched['lib/widgets/global_sos_overlay.dart'];

$redundantReminder = <<<'DART'
                const SizedBox(height: 22),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF7ED),
                    border: Border.all(color: const Color(0xFFFED7AA)),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Icon(
                        Icons.info_outline_rounded,
                        color: Color(0xFFC2410C),
                      ),
                      SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'You already confirmed the SOS before opening this form. Press Send only when the emergency details and callback number are correct.',
                          style: TextStyle(height: 1.4),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
DART;

if (str_contains($overlay, $redundantReminder)) {
    $overlay = str_replace(
        $redundantReminder,
        "                const SizedBox(height: 18),\n",
        $overlay
    );
}

$locationClassMarker = 'class _LocationStatusCard extends StatelessWidget {';
$locationStart = strpos($overlay, $locationClassMarker);

if ($locationStart === false) {
    throw new RuntimeException('Location status card class was not found.');
}

$newLocationClass = <<<'DART'
class _LocationStatusCard extends StatelessWidget {
  const _LocationStatusCard({
    required this.location,
    required this.locating,
    required this.error,
    required this.onRetry,
    required this.onLocationSettings,
    required this.onAppSettings,
  });

  final MobileSosLocation? location;
  final bool locating;
  final String? error;
  final Future<void> Function()? onRetry;
  final Future<void> Function() onLocationSettings;
  final Future<void> Function() onAppSettings;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final locationValue = location;

    BoxDecoration cardDecoration(Color borderColor) {
      return BoxDecoration(
        color: colors.surfaceContainerHighest,
        border: Border.all(color: borderColor),
        borderRadius: BorderRadius.circular(14),
      );
    }

    if (locating) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        decoration: cardDecoration(colors.outlineVariant),
        child: Row(
          children: <Widget>[
            SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2.4,
                color: colors.primary,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Text(
                'Getting current GPS location...',
                style: TextStyle(
                  color: colors.onSurface,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      );
    }

    if (locationValue != null) {
      final accuracy = locationValue.accuracyMeters;
      final accuracyText = accuracy == null
          ? 'accuracy unavailable'
          : '+/- ${accuracy.toStringAsFixed(1)} m';

      return Container(
        padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
        decoration: cardDecoration(const Color(0xFF10B981)),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: <Widget>[
            const Icon(
              Icons.location_on_rounded,
              color: Color(0xFF10B981),
              size: 24,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  Text(
                    locationValue.isLastKnown
                        ? 'Last-known location ready'
                        : 'Current GPS location ready',
                    style: TextStyle(
                      color: colors.onSurface,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${locationValue.latitude.toStringAsFixed(6)}, '
                    '${locationValue.longitude.toStringAsFixed(6)} | '
                    '$accuracyText',
                    style: TextStyle(
                      color: colors.onSurfaceVariant,
                      fontSize: 11,
                      height: 1.3,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'Refresh location',
              onPressed: onRetry,
              icon: Icon(Icons.refresh_rounded, color: colors.primary),
            ),
          ],
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: cardDecoration(const Color(0xFFEF4444)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              const Icon(
                Icons.location_off_rounded,
                color: Color(0xFFEF4444),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Location required',
                  style: TextStyle(
                    color: colors.onSurface,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            error ?? 'Unable to determine location.',
            style: TextStyle(
              color: colors.onSurfaceVariant,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: <Widget>[
              FilledButton.tonalIcon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Retry'),
              ),
              TextButton(
                onPressed: onLocationSettings,
                child: const Text('Location settings'),
              ),
              TextButton(
                onPressed: onAppSettings,
                child: const Text('App permissions'),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
DART;

$overlay = substr($overlay, 0, $locationStart) . $newLocationClass . "\n";
$patched['lib/widgets/global_sos_overlay.dart'] = $overlay;

// -------------------------------------------------------------------------
// Validate everything BEFORE writing any file.
// -------------------------------------------------------------------------

$checks = [
    'main uses AuthGate' => str_contains(
        $patched['lib/main.dart'],
        'home: const AuthGate(),'
    ),
    'main no longer imports DevSessionGate' => ! str_contains(
        $patched['lib/main.dart'],
        'dev_session_gate.dart'
    ),
    'login route exists' => str_contains(
        $patched['lib/main.dart'],
        "'/login': (_) => const AuthGate(),"
    ),
    'logout returns to login' => str_contains(
        $patched['lib/screens/home_screen.dart'],
        "pushNamedAndRemoveUntil(\n        '/login',"
    ),
    'logout no longer creates dev session' => ! str_contains(
        $patched['lib/screens/home_screen.dart'],
        'final devSession = await _authService.devSession();'
    ),
    'global SOS host preserved' => str_contains(
        $patched['lib/widgets/global_sos_overlay.dart'],
        'static _GlobalSosOverlayState? _hostState;'
    ),
    'SOS confirmation preserved' => str_contains(
        $patched['lib/widgets/global_sos_overlay.dart'],
        'Confirm Emergency SOS'
    ),
    'SOS send action preserved' => str_contains(
        $patched['lib/widgets/global_sos_overlay.dart'],
        'Send Distress Signal'
    ),
    'redundant reminder removed' => ! str_contains(
        $patched['lib/widgets/global_sos_overlay.dart'],
        'You already confirmed the SOS before opening this form.'
    ),
    'GPS status remains visible' => str_contains(
        $patched['lib/widgets/global_sos_overlay.dart'],
        'Current GPS location ready'
    ),
    'GPS status is theme aware' => str_contains(
        $patched['lib/widgets/global_sos_overlay.dart'],
        'colors.surfaceContainerHighest'
    ),
];

$failures = [];
foreach ($checks as $label => $passed) {
    if (! $passed) {
        $failures[] = $label;
    }
}

if ($failures !== []) {
    throw new RuntimeException(
        'Validation failed: ' . implode(', ', $failures) . '. Nothing was written.'
    );
}

// -------------------------------------------------------------------------
// Back up once, then write.
// -------------------------------------------------------------------------

foreach ($patched as $path => $contents) {
    if ($contents === $originals[$path]) {
        continue;
    }

    $backup = $path . '.before-auth-sos-ui-fix';
    if (! is_file($backup)) {
        file_put_contents($backup, $originals[$path], LOCK_EX);
    }
}

foreach ($patched as $path => $contents) {
    if ($contents !== $originals[$path]) {
        file_put_contents($path, $contents, LOCK_EX);
    }
}

echo "Final mobile SOS/auth UI fix applied successfully.\n";
echo "Startup/logout now use the real AuthGate login screen.\n";
echo "Login keeps the flipping SOS coin and Sign up.\n";
echo "Redundant SOS reminder card removed.\n";
echo "GPS status preserved and made compact/theme-safe.\n";
