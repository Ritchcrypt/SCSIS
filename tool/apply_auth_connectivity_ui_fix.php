<?php

declare(strict_types=1);

/**
 * Surgical Flutter auth patch.
 *
 * - Makes AuthGate/RegisterScreen input boxes white with dark text/icons.
 * - Makes the post-logout LoginScreen white-field behavior explicit.
 * - Makes login transport failures identify the configured API endpoint and
 *   explain the ADB-reverse requirement for local physical-device testing.
 *
 * Run from the Flutter project root:
 *   php tool/apply_auth_connectivity_ui_fix.php
 */

$root = dirname(__DIR__);

function source(string $path): string
{
    $value = file_get_contents($path);
    if ($value === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }

    return str_replace(["\r\n", "\r"], "\n", $value);
}

function requiredReplace(
    string $value,
    string $search,
    string $replacement,
    string $label
): string {
    if (! str_contains($value, $search)) {
        fwrite(STDERR, "Patch stopped: expected {$label} was not found. No guess was made.\n");
        exit(2);
    }

    return str_replace($search, $replacement, $value);
}

function saveChanged(string $path, string $before, string $after): void
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

// AuthGate: white fields, dark typed text, readable neutral icons/hints.
$before = source($authGatePath);
$after = $before;
$after = requiredReplace(
    $after,
    "hintStyle: const TextStyle(color: Color(0xFFCBD5E1)),\n      prefixIcon: Icon(icon, color: const Color(0xFFCBD5E1)),",
    "hintStyle: const TextStyle(color: Color(0xFF64748B)),\n      prefixIcon: Icon(icon, color: const Color(0xFF64748B)),",
    'AuthGate hint/icon colors'
);
$after = requiredReplace(
    $after,
    'fillColor: const Color(0xFF101827),',
    'fillColor: Colors.white,',
    'AuthGate dark fill'
);
$after = requiredReplace(
    $after,
    'borderSide: const BorderSide(color: Color(0xFF273449)),',
    'borderSide: const BorderSide(color: Color(0xFFCBD5E1)),',
    'AuthGate enabled border'
);
$after = requiredReplace(
    $after,
    "color: Colors.white,\n                              fontSize: 17,",
    "color: Color(0xFF0F172A),\n                              fontSize: 17,",
    'AuthGate typed text'
);
$after = str_replace(
    'color: const Color(0xFFCBD5E1),',
    'color: const Color(0xFF64748B),',
    $after
);
saveChanged($authGatePath, $before, $after);

// RegisterScreen: same visual treatment for all six fields.
$before = source($registerPath);
$after = $before;
$after = requiredReplace(
    $after,
    "hintStyle: const TextStyle(color: Color(0xFFCBD5E1)),\n      prefixIcon: Icon(icon, color: const Color(0xFFCBD5E1)),",
    "hintStyle: const TextStyle(color: Color(0xFF64748B)),\n      prefixIcon: Icon(icon, color: const Color(0xFF64748B)),",
    'RegisterScreen hint/icon colors'
);
$after = requiredReplace(
    $after,
    'fillColor: const Color(0xFF101827),',
    'fillColor: Colors.white,',
    'RegisterScreen dark fill'
);
$after = requiredReplace(
    $after,
    'borderSide: const BorderSide(color: Color(0xFF273449)),',
    'borderSide: const BorderSide(color: Color(0xFFCBD5E1)),',
    'RegisterScreen enabled border'
);

$darkFieldTextCount = substr_count(
    $after,
    'style: const TextStyle(color: Colors.white),'
);
if ($darkFieldTextCount !== 6) {
    fwrite(
        STDERR,
        "Patch stopped: expected exactly six RegisterScreen dark field text styles, found {$darkFieldTextCount}.\n"
    );
    exit(2);
}

$after = str_replace(
    'style: const TextStyle(color: Colors.white),',
    'style: const TextStyle(color: Color(0xFF0F172A)),',
    $after
);
$after = str_replace(
    'color: const Color(0xFFCBD5E1),',
    'color: const Color(0xFF64748B),',
    $after
);
saveChanged($registerPath, $before, $after);

// Post-logout LoginScreen: keep white fields explicit even if global theme changes.
$before = source($loginPath);
$after = $before;
if (! str_contains($after, 'fillColor: Colors.white')) {
    $after = requiredReplace(
        $after,
        "decoration: const InputDecoration(\n                                labelText: 'Email address',",
        "style: const TextStyle(color: Color(0xFF0F172A)),\n                              decoration: const InputDecoration(\n                                filled: true,\n                                fillColor: Colors.white,\n                                labelText: 'Email address',",
        'LoginScreen email field'
    );
    $after = requiredReplace(
        $after,
        "decoration: InputDecoration(\n                                labelText: 'Password',",
        "style: const TextStyle(color: Color(0xFF0F172A)),\n                              decoration: InputDecoration(\n                                filled: true,\n                                fillColor: Colors.white,\n                                labelText: 'Password',",
        'LoginScreen password field'
    );
}
saveChanged($loginPath, $before, $after);

// AuthService: surface the real transport failure instead of a generic catch.
$before = source($authServicePath);
$after = $before;
if (! str_contains($after, "import 'dart:async';")) {
    $after = requiredReplace(
        $after,
        "import 'dart:convert';\n",
        "import 'dart:async';\nimport 'dart:convert';\nimport 'dart:io';\n",
        'AuthService imports'
    );
}

if (! str_contains($after, '_requestTimeout')) {
    $after = requiredReplace(
        $after,
        "  static const String _tokenKey = 'tabangnow_access_token';\n\n",
        "  static const String _tokenKey = 'tabangnow_access_token';\n  static const Duration _requestTimeout = Duration(seconds: 15);\n\n",
        'AuthService timeout location'
    );
}

$oldRequest = <<<'DART'
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

$newRequest = <<<'DART'
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
    $after = requiredReplace(
        $after,
        $oldRequest,
        $newRequest,
        'AuthService login request'
    );
}
saveChanged($authServicePath, $before, $after);

// Independent final assertions. Do not use booleans as associative-array keys.
$checks = [
    [
        ! str_contains(source($authGatePath), 'fillColor: const Color(0xFF101827)'),
        'AuthGate dark field fill removed',
    ],
    [
        str_contains(source($authGatePath), 'fillColor: Colors.white'),
        'AuthGate white field fill present',
    ],
    [
        ! str_contains(source($registerPath), 'fillColor: const Color(0xFF101827)'),
        'RegisterScreen dark field fill removed',
    ],
    [
        str_contains(source($registerPath), 'fillColor: Colors.white'),
        'RegisterScreen white field fill present',
    ],
    [
        str_contains(source($loginPath), 'fillColor: Colors.white'),
        'LoginScreen explicit white field fill present',
    ],
    [
        str_contains(source($authServicePath), 'adb reverse tcp:8000 tcp:8000'),
        'AuthService actionable local-device network error present',
    ],
];

foreach ($checks as [$passed, $label]) {
    if (! $passed) {
        fwrite(STDERR, "Final validation failed: {$label}.\n");
        exit(3);
    }
}

echo "Auth connectivity/UI patch applied successfully.\n";
echo "Login and registration input boxes are white with dark text/icons.\n";
echo "Login network failures now identify the API address and local ADB-reverse requirement.\n";
