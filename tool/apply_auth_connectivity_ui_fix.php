<?php

declare(strict_types=1);

/**
 * Surgical Flutter auth patch.
 *
 * - Makes AuthGate and RegisterScreen input boxes white with dark text/icons.
 * - Keeps the post-logout LoginScreen white-field behavior consistent.
 * - Improves AuthService login transport errors so local Android connectivity
 *   problems identify the configured API address instead of becoming a generic
 *   "Unable to connect" message.
 *
 * Run from the Flutter project root:
 *   php tool/apply_auth_connectivity_ui_fix.php
 */

$root = dirname(__DIR__);

function readSource(string $path): string
{
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }

    return str_replace(["\r\n", "\r"], "\n", $source);
}

function replaceRequired(string $source, string $search, string $replace, string $label): string
{
    if (! str_contains($source, $search)) {
        fwrite(STDERR, "Patch stopped: expected {$label} was not found. No guess was made.\n");
        exit(2);
    }

    return str_replace($search, $replace, $source);
}

function writeIfChanged(string $path, string $before, string $after): void
{
    if ($before === $after) {
        return;
    }

    if (file_put_contents($path, $after, LOCK_EX) === false) {
        fwrite(STDERR, "Unable to write {$path}.\n");
        exit(1);
    }
}

$authGatePath = $root . '/lib/screens/auth_gate.dart';
$registerPath = $root . '/lib/screens/register_screen.dart';
$loginPath = $root . '/lib/screens/login_screen.dart';
$authServicePath = $root . '/lib/services/auth_service.dart';

foreach ([$authGatePath, $registerPath, $loginPath, $authServicePath] as $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Required file not found: {$path}\n");
        exit(1);
    }
}

// -------------------------------------------------------------------------
// AuthGate: remove explicit dark input styling.
// -------------------------------------------------------------------------
$before = readSource($authGatePath);
$after = $before;
$after = replaceRequired(
    $after,
    "hintStyle: const TextStyle(color: Color(0xFFCBD5E1)),\n      prefixIcon: Icon(icon, color: const Color(0xFFCBD5E1)),",
    "hintStyle: const TextStyle(color: Color(0xFF64748B)),\n      prefixIcon: Icon(icon, color: const Color(0xFF64748B)),",
    'AuthGate hint/icon colors'
);
$after = replaceRequired(
    $after,
    "fillColor: const Color(0xFF101827),",
    "fillColor: Colors.white,",
    'AuthGate dark fill color'
);
$after = replaceRequired(
    $after,
    "borderSide: const BorderSide(color: Color(0xFF273449)),",
    "borderSide: const BorderSide(color: Color(0xFFCBD5E1)),",
    'AuthGate enabled border color'
);
$after = replaceRequired(
    $after,
    "color: Colors.white,\n                              fontSize: 17,",
    "color: Color(0xFF0F172A),\n                              fontSize: 17,",
    'AuthGate typed text color'
);
$after = str_replace(
    "color: const Color(0xFFCBD5E1),\n                                ),\n                              ),\n                            ),\n                            validator: (value) =>",
    "color: const Color(0xFF64748B),\n                                ),\n                              ),\n                            ),\n                            validator: (value) =>",
    $after
);
writeIfChanged($authGatePath, $before, $after);

// -------------------------------------------------------------------------
// RegisterScreen: same white fields for every registration input.
// -------------------------------------------------------------------------
$before = readSource($registerPath);
$after = $before;
$after = replaceRequired(
    $after,
    "hintStyle: const TextStyle(color: Color(0xFFCBD5E1)),\n      prefixIcon: Icon(icon, color: const Color(0xFFCBD5E1)),",
    "hintStyle: const TextStyle(color: Color(0xFF64748B)),\n      prefixIcon: Icon(icon, color: const Color(0xFF64748B)),",
    'RegisterScreen hint/icon colors'
);
$after = replaceRequired(
    $after,
    "fillColor: const Color(0xFF101827),",
    "fillColor: Colors.white,",
    'RegisterScreen dark fill color'
);
$after = replaceRequired(
    $after,
    "borderSide: const BorderSide(color: Color(0xFF273449)),",
    "borderSide: const BorderSide(color: Color(0xFFCBD5E1)),",
    'RegisterScreen enabled border color'
);
$whiteTextCount = substr_count($after, "style: const TextStyle(color: Colors.white),");
if ($whiteTextCount < 6) {
    fwrite(STDERR, "Patch stopped: expected six RegisterScreen dark text styles, found {$whiteTextCount}.\n");
    exit(2);
}
$after = str_replace(
    "style: const TextStyle(color: Colors.white),",
    "style: const TextStyle(color: Color(0xFF0F172A)),",
    $after
);
$after = str_replace(
    "color: const Color(0xFFCBD5E1),",
    "color: const Color(0xFF64748B),",
    $after
);
writeIfChanged($registerPath, $before, $after);

// -------------------------------------------------------------------------
// Legacy/post-logout LoginScreen: make white field appearance explicit so it
// cannot depend on an inherited theme changing later.
// -------------------------------------------------------------------------
$before = readSource($loginPath);
$after = $before;
if (! str_contains($after, "fillColor: Colors.white")) {
    $after = replaceRequired(
        $after,
        "decoration: const InputDecoration(\n                                labelText: 'Email address',",
        "style: const TextStyle(color: Color(0xFF0F172A)),\n                              decoration: const InputDecoration(\n                                filled: true,\n                                fillColor: Colors.white,\n                                labelText: 'Email address',",
        'LoginScreen email decoration'
    );
    $after = replaceRequired(
        $after,
        "decoration: InputDecoration(\n                                labelText: 'Password',",
        "style: const TextStyle(color: Color(0xFF0F172A)),\n                              decoration: InputDecoration(\n                                filled: true,\n                                fillColor: Colors.white,\n                                labelText: 'Password',",
        'LoginScreen password decoration'
    );
}
writeIfChanged($loginPath, $before, $after);

// -------------------------------------------------------------------------
// AuthService: make the actual transport failure actionable.
// -------------------------------------------------------------------------
$before = readSource($authServicePath);
$after = $before;
if (! str_contains($after, "import 'dart:async';")) {
    $after = replaceRequired(
        $after,
        "import 'dart:convert';\n",
        "import 'dart:async';\nimport 'dart:convert';\nimport 'dart:io';\n",
        'AuthService imports'
    );
}

if (! str_contains($after, '_requestTimeout')) {
    $after = replaceRequired(
        $after,
        "  static const String _tokenKey = 'tabangnow_access_token';\n\n",
        "  static const String _tokenKey = 'tabangnow_access_token';\n  static const Duration _requestTimeout = Duration(seconds: 15);\n\n",
        'AuthService timeout constant location'
    );
}

$oldLoginRequest = <<<'DART'
    final response = await _client.post(
      Uri.parse('$_baseUrl/api/v1/auth/login'),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({
        'email': email.trim(),
        'password': password,
        'device_name': deviceName.trim(),
      }),
    );
DART;

$newLoginRequest = <<<'DART'
    late final http.Response response;

    try {
      response = await _client
          .post(
            Uri.parse('$_baseUrl/api/v1/auth/login'),
            headers: const <String, String>{
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode(<String, dynamic>{
              'email': email.trim(),
              'password': password,
              'device_name': deviceName.trim(),
            }),
          )
          .timeout(_requestTimeout);
    } on TimeoutException {
      throw AuthException(
        'The TabangNow server at $_baseUrl did not respond. For local physical-device testing, keep Laravel running on port 8000 and enable ADB reverse for tcp:8000.',
      );
    } on SocketException catch (exception) {
      throw AuthException(
        'Unable to reach the TabangNow server at $_baseUrl (${exception.message}). For a USB-connected Android device, run adb reverse tcp:8000 tcp:8000.',
      );
    } on http.ClientException catch (exception) {
      throw AuthException(
        'Unable to reach the TabangNow server at $_baseUrl (${exception.message}).',
      );
    }
DART;

if (! str_contains($after, 'For local physical-device testing')) {
    $after = replaceRequired(
        $after,
        $oldLoginRequest,
        $newLoginRequest,
        'AuthService login request block'
    );
}
writeIfChanged($authServicePath, $before, $after);

// Final safety assertions.
$authGate = readSource($authGatePath);
$register = readSource($registerPath);
$login = readSource($loginPath);
$authService = readSource($authServicePath);

$checks = [
    ! str_contains($authGate, 'fillColor: const Color(0xFF101827)') => 'AuthGate no longer uses dark field fill',
    str_contains($authGate, 'fillColor: Colors.white') => 'AuthGate uses white field fill',
    ! str_contains($register, 'fillColor: const Color(0xFF101827)') => 'RegisterScreen no longer uses dark field fill',
    str_contains($register, 'fillColor: Colors.white') => 'RegisterScreen uses white field fill',
    str_contains($login, 'fillColor: Colors.white') => 'LoginScreen explicitly uses white field fill',
    str_contains($authService, 'adb reverse tcp:8000 tcp:8000') => 'AuthService has actionable local-device network error',
];

foreach ($checks as $passed => $label) {
    if (! $passed) {
        fwrite(STDERR, "Final validation failed: {$label}.\n");
        exit(3);
    }
}

echo "Auth connectivity/UI patch applied successfully.\n";
echo "Login and registration input boxes are now white with dark text/icons.\n";
echo "Login transport failures now report the configured API address and ADB-reverse hint.\n";
