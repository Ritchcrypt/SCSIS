<?php

declare(strict_types=1);

/**
 * Idempotent Flutter auth/connectivity patch.
 *
 * - Makes AuthGate and RegisterScreen input boxes white with dark text/icons.
 * - Keeps the legacy/post-logout LoginScreen white-field behavior consistent.
 * - Makes AuthService login transport failures identify the configured API URL
 *   and the ADB-reverse requirement for local physical-device testing.
 *
 * This version is validate-first: every transformation is prepared and checked
 * in memory before any source file is written. It can also be run again safely
 * when some or all changes are already present.
 *
 * Run from the Flutter project root:
 *   php tool/apply_auth_connectivity_ui_fix.php
 */

$root = dirname(__DIR__);

function failPatch(string $message, int $code = 2): never
{
    fwrite(STDERR, "Patch stopped: {$message}\n");
    exit($code);
}

function readSource(string $path): string
{
    $source = file_get_contents($path);

    if ($source === false) {
        failPatch("unable to read {$path}.", 1);
    }

    return str_replace(["\r\n", "\r"], "\n", $source);
}

function replaceOldOrAcceptNew(
    string $source,
    string $old,
    string $new,
    string $label
): string {
    if (str_contains($source, $old)) {
        return str_replace($old, $new, $source);
    }

    if (str_contains($source, $new)) {
        return $source;
    }

    failPatch("expected {$label} was not found and the patched form is not present. No guess was made.");
}

function replaceAllOldOrAcceptNew(
    string $source,
    string $old,
    string $new,
    string $label,
    int $expectedMinimum = 1
): string {
    $oldCount = substr_count($source, $old);

    if ($oldCount >= $expectedMinimum) {
        return str_replace($old, $new, $source);
    }

    if (str_contains($source, $new)) {
        return $source;
    }

    failPatch("expected {$label} was not found and the patched form is not present. No guess was made.");
}

$paths = [
    'auth_gate' => $root . '/lib/screens/auth_gate.dart',
    'register' => $root . '/lib/screens/register_screen.dart',
    'login' => $root . '/lib/screens/login_screen.dart',
    'auth_service' => $root . '/lib/services/auth_service.dart',
];

foreach ($paths as $path) {
    if (! is_file($path)) {
        failPatch("required file not found: {$path}", 1);
    }
}

$original = [];
foreach ($paths as $key => $path) {
    $original[$key] = readSource($path);
}

$patched = $original;

// -------------------------------------------------------------------------
// AuthGate: white field fill, dark text, readable icons/hints.
// -------------------------------------------------------------------------
$authGate = $patched['auth_gate'];
$authGate = replaceOldOrAcceptNew(
    $authGate,
    "hintStyle: const TextStyle(color: Color(0xFFCBD5E1)),\n      prefixIcon: Icon(icon, color: const Color(0xFFCBD5E1)),",
    "hintStyle: const TextStyle(color: Color(0xFF64748B)),\n      prefixIcon: Icon(icon, color: const Color(0xFF64748B)),",
    'AuthGate hint/icon colors'
);
$authGate = replaceOldOrAcceptNew(
    $authGate,
    "fillColor: const Color(0xFF101827),",
    "fillColor: Colors.white,",
    'AuthGate field fill'
);
$authGate = replaceOldOrAcceptNew(
    $authGate,
    "borderSide: const BorderSide(color: Color(0xFF273449)),",
    "borderSide: const BorderSide(color: Color(0xFFCBD5E1)),",
    'AuthGate enabled border color'
);
$authGate = replaceAllOldOrAcceptNew(
    $authGate,
    "style: const TextStyle(\n                              color: Colors.white,\n                              fontSize: 17,\n                            ),",
    "style: const TextStyle(\n                              color: Color(0xFF0F172A),\n                              fontSize: 17,\n                            ),",
    'AuthGate typed text color',
    2
);
$authGate = str_replace(
    "color: const Color(0xFFCBD5E1),",
    "color: const Color(0xFF64748B),",
    $authGate
);
$patched['auth_gate'] = $authGate;

// -------------------------------------------------------------------------
// RegisterScreen: same white-field treatment for all registration inputs.
// -------------------------------------------------------------------------
$register = $patched['register'];
$register = replaceOldOrAcceptNew(
    $register,
    "hintStyle: const TextStyle(color: Color(0xFFCBD5E1)),\n      prefixIcon: Icon(icon, color: const Color(0xFFCBD5E1)),",
    "hintStyle: const TextStyle(color: Color(0xFF64748B)),\n      prefixIcon: Icon(icon, color: const Color(0xFF64748B)),",
    'RegisterScreen hint/icon colors'
);
$register = replaceOldOrAcceptNew(
    $register,
    "fillColor: const Color(0xFF101827),",
    "fillColor: Colors.white,",
    'RegisterScreen field fill'
);
$register = replaceOldOrAcceptNew(
    $register,
    "borderSide: const BorderSide(color: Color(0xFF273449)),",
    "borderSide: const BorderSide(color: Color(0xFFCBD5E1)),",
    'RegisterScreen enabled border color'
);
$register = replaceAllOldOrAcceptNew(
    $register,
    "style: const TextStyle(color: Colors.white),",
    "style: const TextStyle(color: Color(0xFF0F172A)),",
    'RegisterScreen typed text colors',
    6
);
$register = str_replace(
    "color: const Color(0xFFCBD5E1),",
    "color: const Color(0xFF64748B),",
    $register
);
$patched['register'] = $register;

// -------------------------------------------------------------------------
// Legacy/post-logout LoginScreen: make white input fill explicit.
// -------------------------------------------------------------------------
$login = $patched['login'];

if (! str_contains($login, "filled: true,\n                                fillColor: Colors.white,")) {
    $login = replaceOldOrAcceptNew(
        $login,
        "decoration: const InputDecoration(\n                                labelText: 'Email address',",
        "style: const TextStyle(color: Color(0xFF0F172A)),\n                              decoration: const InputDecoration(\n                                filled: true,\n                                fillColor: Colors.white,\n                                labelText: 'Email address',",
        'LoginScreen email field decoration'
    );

    $login = replaceOldOrAcceptNew(
        $login,
        "decoration: InputDecoration(\n                                labelText: 'Password',",
        "style: const TextStyle(color: Color(0xFF0F172A)),\n                              decoration: InputDecoration(\n                                filled: true,\n                                fillColor: Colors.white,\n                                labelText: 'Password',",
        'LoginScreen password field decoration'
    );
}

$patched['login'] = $login;

// -------------------------------------------------------------------------
// AuthService: actionable transport failure for local Android development.
// -------------------------------------------------------------------------
$authService = $patched['auth_service'];

if (! str_contains($authService, "import 'dart:async';")) {
    $authService = replaceOldOrAcceptNew(
        $authService,
        "import 'dart:convert';\n",
        "import 'dart:async';\nimport 'dart:convert';\nimport 'dart:io';\n",
        'AuthService imports'
    );
} elseif (! str_contains($authService, "import 'dart:io';")) {
    $authService = str_replace(
        "import 'dart:convert';\n",
        "import 'dart:convert';\nimport 'dart:io';\n",
        $authService
    );
}

if (! str_contains($authService, '_requestTimeout')) {
    $authService = replaceOldOrAcceptNew(
        $authService,
        "  static const String _tokenKey = 'tabangnow_access_token';\n\n",
        "  static const String _tokenKey = 'tabangnow_access_token';\n  static const Duration _requestTimeout = Duration(seconds: 15);\n\n",
        'AuthService timeout constant'
    );
}

if (! str_contains($authService, 'For local physical-device testing')) {
    $loginRequestPattern = <<<'REGEX'
~    final response = await _client\.post\(\R\s+Uri\.parse\('\$_baseUrl/api/v1/auth/login'\),.*?\R    \);~s
REGEX;

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

    $count = 0;
    $candidate = preg_replace(
        $loginRequestPattern,
        $newLoginRequest,
        $authService,
        1,
        $count
    );

    if ($candidate === null || $count !== 1) {
        failPatch('AuthService login request block could not be identified safely. No source files were written.');
    }

    $authService = $candidate;
}

$patched['auth_service'] = $authService;

// -------------------------------------------------------------------------
// Validate every final source before writing anything.
// -------------------------------------------------------------------------
$checks = [
    [
        'ok' => ! str_contains($patched['auth_gate'], 'fillColor: const Color(0xFF101827)'),
        'label' => 'AuthGate dark field fill removed',
    ],
    [
        'ok' => str_contains($patched['auth_gate'], 'fillColor: Colors.white'),
        'label' => 'AuthGate white field fill present',
    ],
    [
        'ok' => str_contains($patched['auth_gate'], 'color: Color(0xFF0F172A)'),
        'label' => 'AuthGate dark typed text present',
    ],
    [
        'ok' => ! str_contains($patched['register'], 'fillColor: const Color(0xFF101827)'),
        'label' => 'RegisterScreen dark field fill removed',
    ],
    [
        'ok' => str_contains($patched['register'], 'fillColor: Colors.white'),
        'label' => 'RegisterScreen white field fill present',
    ],
    [
        'ok' => ! str_contains($patched['register'], 'style: const TextStyle(color: Colors.white),'),
        'label' => 'RegisterScreen field text is no longer white-on-white',
    ],
    [
        'ok' => str_contains($patched['login'], 'fillColor: Colors.white'),
        'label' => 'LoginScreen explicit white input fill present',
    ],
    [
        'ok' => str_contains($patched['auth_service'], "import 'dart:async';")
            && str_contains($patched['auth_service'], "import 'dart:io';"),
        'label' => 'AuthService transport imports present',
    ],
    [
        'ok' => str_contains($patched['auth_service'], '_requestTimeout')
            && str_contains($patched['auth_service'], 'adb reverse tcp:8000 tcp:8000')
            && str_contains($patched['auth_service'], 'For local physical-device testing'),
        'label' => 'AuthService actionable connectivity handling present',
    ],
];

foreach ($checks as $check) {
    if (! $check['ok']) {
        failPatch('final validation failed: ' . $check['label'] . '. No source files were written.', 3);
    }
}

// Only now write changed files.
$changed = [];
foreach ($paths as $key => $path) {
    if ($patched[$key] === $original[$key]) {
        continue;
    }

    if (file_put_contents($path, $patched[$key], LOCK_EX) === false) {
        failPatch("unable to write {$path}.", 1);
    }

    $changed[] = str_replace('\\', '/', substr($path, strlen($root) + 1));
}

if ($changed === []) {
    echo "Auth connectivity/UI patch already applied; no source changes were needed.\n";
} else {
    echo "Auth connectivity/UI patch applied successfully.\n";
    echo "Changed:\n";
    foreach ($changed as $path) {
        echo " - {$path}\n";
    }
}

echo "Login and registration input boxes are white with dark text/icons.\n";
echo "Login transport failures now identify the configured API address and ADB-reverse requirement.\n";
