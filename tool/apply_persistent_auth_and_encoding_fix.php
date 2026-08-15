<?php

/**
 * Persistent authentication + global Flutter source encoding repair.
 *
 * Run from the Flutter project root:
 *   php tool/apply_persistent_auth_and_encoding_fix.php
 *
 * Goals:
 * - keep exactly one root AuthGate for startup/login/logout;
 * - make logout rebuild the original login UI instead of navigating to a
 *   second login route;
 * - guarantee the login SOS coin is recreated with a fresh timer after logout;
 * - preserve the Sign up action after logout;
 * - remove old development-session wording/behavior;
 * - replace fragile Unicode UI punctuation/glyphs with ASCII Dart escapes;
 * - repair common mojibake sequences already present in Dart source;
 * - create a regression test that rejects mojibake markers in lib/**/*.dart.
 */

$required = [
    'lib/main.dart',
    'lib/screens/auth_gate.dart',
    'lib/screens/home_screen.dart',
    'lib/widgets/sos_flip_coin_button.dart',
    'lib/widgets/global_sos_overlay.dart',
];

foreach ($required as $path) {
    if (! is_file($path)) {
        throw new RuntimeException("Missing required file: {$path}");
    }
}

function readUtf8File(string $path): string
{
    $value = file_get_contents($path);
    if ($value === false) {
        throw new RuntimeException("Unable to read {$path}");
    }

    return str_replace(["\r\n", "\r"], "\n", $value);
}

function replaceExactlyOnce(
    string $text,
    string $old,
    string $new,
    string $label
): string {
    $count = substr_count($text, $old);

    if ($count === 0 && str_contains($text, $new)) {
        return $text;
    }

    if ($count !== 1) {
        throw new RuntimeException(
            "{$label}: expected exactly one match, found {$count}. Nothing was written."
        );
    }

    return str_replace($old, $new, $text);
}

$originals = [];
foreach ($required as $path) {
    $originals[$path] = readUtf8File($path);
}

$patched = $originals;

// -------------------------------------------------------------------------
// 1. main.dart: one real root AuthGate. DevSessionGate must never be normal UI.
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
        throw new RuntimeException('main.dart AuthGate import anchor was not found.');
    }

    $main = str_replace(
        $anchor,
        $anchor . "\nimport 'screens/auth_gate.dart';\n",
        $main
    );
}

$main = str_replace('home: const DevSessionGate(),', 'home: const AuthGate(),', $main);

if (! str_contains($main, 'home: const AuthGate(),')) {
    throw new RuntimeException('main.dart does not use AuthGate as home.');
}

// A named /login route is no longer needed for logout. Remove it when it is the
// exact route block created by the older repair. This avoids two login states.
$oldLoginRouteBlock = <<<'DART'
      routes: <String, WidgetBuilder>{
        '/login': (_) => const AuthGate(),
      },
DART;
$main = str_replace($oldLoginRouteBlock . "\n", '', $main);
$main = str_replace($oldLoginRouteBlock, '', $main);

$patched['lib/main.dart'] = $main;

// -------------------------------------------------------------------------
// 2. AuthGate: own authenticated/logged-out state permanently.
// -------------------------------------------------------------------------

$auth = $patched['lib/screens/auth_gate.dart'];

if (! str_contains($auth, 'Future<void> _handleLoggedOut() async')) {
    $anchor = "  Future<void> _openRegistration() async {\n";
    if (! str_contains($auth, $anchor)) {
        throw new RuntimeException('AuthGate registration method anchor was not found.');
    }

    $handler = <<<'DART'
  Future<void> _handleLoggedOut() async {
    if (!mounted) {
      return;
    }

    _passwordController.clear();

    setState(() {
      _user = null;
      _checkingSession = false;
      _loggingIn = false;
      _loginError = null;
      _obscurePassword = true;
    });

    // Let Flutter finish disposing HomeScreen before the newly inserted
    // SosFlipCoinButton starts its own timer on the login branch.
    await Future<void>.delayed(Duration.zero);
  }

DART;

    $auth = str_replace($anchor, $handler . $anchor, $auth);
}

$auth = replaceExactlyOnce(
    $auth,
    '      return HomeScreen(user: _user!);',
    "      return HomeScreen(\n        user: _user!,\n        onLoggedOut: _handleLoggedOut,\n      );",
    'AuthGate HomeScreen callback wiring'
);

// Give the login SOS widget an explicit identity. When AuthGate switches from
// HomeScreen back to login, this widget is inserted fresh and initState starts
// a new periodic flip timer.
$auth = str_replace(
    '                  const SosFlipCoinButton(size: 104),',
    "                  const SosFlipCoinButton(\n                    key: ValueKey<String>('auth-login-sos'),\n                    size: 104,\n                  ),",
    $auth
);

$patched['lib/screens/auth_gate.dart'] = $auth;

// -------------------------------------------------------------------------
// 3. HomeScreen: logout reports back to the root AuthGate, no login navigation.
// -------------------------------------------------------------------------

$home = $patched['lib/screens/home_screen.dart'];

if (! str_contains($home, 'final Future<void> Function()? onLoggedOut;')) {
    $home = replaceExactlyOnce(
        $home,
        '  const HomeScreen({super.key, required this.user});',
        "  const HomeScreen({\n    super.key,\n    required this.user,\n    this.onLoggedOut,\n  });",
        'HomeScreen constructor'
    );

    $home = replaceExactlyOnce(
        $home,
        "  final Map<String, dynamic> user;\n",
        "  final Map<String, dynamic> user;\n  final Future<void> Function()? onLoggedOut;\n",
        'HomeScreen logout callback field'
    );
}

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

      final onLoggedOut = widget.onLoggedOut;
      if (onLoggedOut != null) {
        await onLoggedOut();
        return;
      }

      // Defensive fallback for any legacy caller that constructs HomeScreen
      // outside AuthGate. Normal application startup never uses this path.
      await _authService.clearToken();

      if (mounted) {
        Navigator.of(context).popUntil((route) => route.isFirst);
      }
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
    throw new RuntimeException('Unable to replace HomeScreen logout method.');
}

$home = str_replace(
    "sessionActionLabel: 'Restart Dev Session',",
    "sessionActionLabel: 'Log out',",
    $home
);

$patched['lib/screens/home_screen.dart'] = $home;

// -------------------------------------------------------------------------
// 4. Global Dart source encoding repair.
// -------------------------------------------------------------------------

$dartFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        'lib',
        FilesystemIterator::SKIP_DOTS
    )
);

foreach ($iterator as $fileInfo) {
    if (! $fileInfo->isFile()) {
        continue;
    }

    $path = str_replace('\\', '/', $fileInfo->getPathname());
    if (! str_ends_with(strtolower($path), '.dart')) {
        continue;
    }

    $dartFiles[] = $path;
}

sort($dartFiles);

// First repair common mojibake representations. Then convert the valid Unicode
// UI character to a Dart escape. The resulting source is ASCII-safe for the
// characters that previously broke under Windows PowerShell 5.1.
$encodingReplacements = [
    // Smart punctuation / separators.
    'â€”' => '\\u2014',
    'â€“' => '\\u2013',
    'â€¢' => '\\u2022',
    'â€¦' => '\\u2026',
    'â†’' => '\\u2192',
    'â†»' => '\\u21BB',
    'âœ“' => '\\u2713',
    'Â°' => '\\u00B0',
    'Â±' => '\\u00B1',
    'â€™' => '\\u2019',
    'â€œ' => '\\u201C',
    'â€' => '\\u201D',

    // Correct characters converted to ASCII Dart escapes.
    '—' => '\\u2014',
    '–' => '\\u2013',
    '•' => '\\u2022',
    '…' => '\\u2026',
    '→' => '\\u2192',
    '↻' => '\\u21BB',
    '✓' => '\\u2713',
    '°' => '\\u00B0',
    '±' => '\\u00B1',
    '’' => '\\u2019',
    '“' => '\\u201C',
    '”' => '\\u201D',

    // UI glyphs currently used by the module registry/weather fallback.
    '▦' => '\\u25A6',
    '📄' => '\\u{1F4C4}',
    '🆘' => '\\u{1F198}',
    '🔔' => '\\u{1F514}',
    '👥' => '\\u{1F465}',
    '📋' => '\\u{1F4CB}',
    '📘' => '\\u{1F4D8}',
    '📢' => '\\u{1F4E2}',
    '🚨' => '\\u{1F6A8}',
    '💬' => '\\u{1F4AC}',
    '🗺️' => '\\u{1F5FA}\\uFE0F',
    '🗺' => '\\u{1F5FA}',
    '📊' => '\\u{1F4CA}',
    '⚙️' => '\\u2699\\uFE0F',
    '⚙' => '\\u2699',
    '🧾' => '\\u{1F9FE}',
    '🛡️' => '\\u{1F6E1}\\uFE0F',
    '🛡' => '\\u{1F6E1}',
    '🌤️' => '\\u{1F324}\\uFE0F',
    '🌤' => '\\u{1F324}',
];

$allPatched = $patched;

foreach ($dartFiles as $path) {
    $text = array_key_exists($path, $allPatched)
        ? $allPatched[$path]
        : readUtf8File($path);

    if (! array_key_exists($path, $originals)) {
        $originals[$path] = $text;
    }

    $text = str_replace(
        array_keys($encodingReplacements),
        array_values($encodingReplacements),
        $text
    );

    $allPatched[$path] = $text;
}

$patched = $allPatched;

// -------------------------------------------------------------------------
// 5. Source guard test. This prevents the same mojibake from silently returning.
// -------------------------------------------------------------------------

$guardPath = 'test/source_encoding_guard_test.dart';
$guardContents = <<<'DART'
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Flutter lib source contains no common mojibake markers', () {
    final lib = Directory('lib');
    expect(lib.existsSync(), isTrue);

    const suspicious = <String>[
      'Ã',
      'Â',
      'â€',
      'â€¢',
      'â†',
      'âœ',
      'ðŸ',
      'ï¸',
      '\uFFFD',
    ];

    final failures = <String>[];

    for (final entity in lib.listSync(recursive: true, followLinks: false)) {
      if (entity is! File || !entity.path.endsWith('.dart')) {
        continue;
      }

      final text = entity.readAsStringSync();
      final hits = suspicious.where(text.contains).toList(growable: false);

      if (hits.isNotEmpty) {
        failures.add('${entity.path}: ${hits.join(', ')}');
      }
    }

    expect(
      failures,
      isEmpty,
      reason: 'Mojibake markers remain in Flutter source:\n${failures.join('\n')}',
    );
  });
}
DART;

if (is_file($guardPath)) {
    $originals[$guardPath] = readUtf8File($guardPath);
} else {
    $originals[$guardPath] = '';
}
$patched[$guardPath] = $guardContents . "\n";

// -------------------------------------------------------------------------
// 6. Validate behavior and source before writing anything.
// -------------------------------------------------------------------------

$checks = [
    'root app uses AuthGate' => str_contains(
        $patched['lib/main.dart'],
        'home: const AuthGate(),'
    ),
    'root app does not use DevSessionGate' => ! str_contains(
        $patched['lib/main.dart'],
        'DevSessionGate'
    ),
    'AuthGate owns logout callback' => str_contains(
        $patched['lib/screens/auth_gate.dart'],
        'Future<void> _handleLoggedOut() async'
    ),
    'AuthGate passes logout callback' => str_contains(
        $patched['lib/screens/auth_gate.dart'],
        'onLoggedOut: _handleLoggedOut'
    ),
    'login SOS has stable identity' => str_contains(
        $patched['lib/screens/auth_gate.dart'],
        "ValueKey<String>('auth-login-sos')"
    ),
    'login Sign up preserved' => str_contains(
        $patched['lib/screens/auth_gate.dart'],
        "'Sign up'"
    ),
    'HomeScreen supports logout callback' => str_contains(
        $patched['lib/screens/home_screen.dart'],
        'final Future<void> Function()? onLoggedOut;'
    ),
    'HomeScreen no longer creates dev session on logout' => ! str_contains(
        $patched['lib/screens/home_screen.dart'],
        'final devSession = await _authService.devSession();'
    ),
    'logout is labelled correctly' => str_contains(
        $patched['lib/screens/home_screen.dart'],
        "sessionActionLabel: 'Log out'"
    ),
    'SOS coin still globally callable' => str_contains(
        $patched['lib/widgets/sos_flip_coin_button.dart'],
        'await GlobalSosOverlay.open(context);'
    ),
    'SOS remains clickable' => str_contains(
        $patched['lib/widgets/sos_flip_coin_button.dart'],
        'onTap: onTap,'
    ),
    'SOS confirmation preserved' => str_contains(
        $patched['lib/widgets/global_sos_overlay.dart'],
        'Confirm Emergency SOS'
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

// Check for common mojibake markers after normalization.
$suspiciousMarkers = ['Ã', 'Â', 'â€', 'â€¢', 'â†', 'âœ', 'ðŸ', 'ï¸', "\u{FFFD}"];
$remaining = [];

foreach ($patched as $path => $text) {
    if (! str_starts_with(str_replace('\\', '/', $path), 'lib/')
        || ! str_ends_with(strtolower($path), '.dart')) {
        continue;
    }

    $hits = [];
    foreach ($suspiciousMarkers as $marker) {
        if ($marker !== '' && str_contains($text, $marker)) {
            $hits[] = $marker;
        }
    }

    if ($hits !== []) {
        $remaining[$path] = $hits;
    }
}

if ($remaining !== []) {
    $details = [];
    foreach ($remaining as $path => $hits) {
        $details[] = $path . ' [' . implode(', ', $hits) . ']';
    }

    throw new RuntimeException(
        "Mojibake markers remain after normalization: "
        . implode('; ', $details)
        . '. Nothing was written.'
    );
}

// -------------------------------------------------------------------------
// 7. Back up changed existing files once, then write UTF-8 bytes exactly.
// -------------------------------------------------------------------------

foreach ($patched as $path => $contents) {
    $before = $originals[$path] ?? '';
    if ($contents === $before) {
        continue;
    }

    if (is_file($path)) {
        $backup = $path . '.before-persistent-auth-encoding-fix';
        if (! is_file($backup)) {
            file_put_contents($backup, $before, LOCK_EX);
        }
    }
}

foreach ($patched as $path => $contents) {
    $before = $originals[$path] ?? '';
    if ($contents === $before) {
        continue;
    }

    $directory = dirname($path);
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($path, $contents, LOCK_EX);
}

echo "Persistent auth + global encoding repair applied successfully.\n";
echo "Logout now rebuilds the original root AuthGate instead of navigating to another login route.\n";
echo "Login SOS is recreated with a fresh flip timer and remains clickable.\n";
echo "Sign up remains part of the same login UI after logout.\n";
echo "Common mojibake and fragile UI Unicode literals were normalized across lib/**/*.dart.\n";
echo "Added test/source_encoding_guard_test.dart to prevent mojibake regressions.\n";
