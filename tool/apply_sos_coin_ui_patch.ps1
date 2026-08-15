$ErrorActionPreference = 'Stop'

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText((Resolve-Path $Path), $Content, $utf8)
}

function Normalize-Lf([string]$Text) {
    return $Text.Replace("`r`n", "`n")
}

$overlayPath = 'lib\widgets\global_sos_overlay.dart'
$homePath = 'lib\screens\home_screen.dart'

if (-not (Test-Path $overlayPath)) {
    throw "Missing $overlayPath"
}
if (-not (Test-Path $homePath)) {
    throw "Missing $homePath"
}

$overlayBackup = "$overlayPath.before-sos-coin"
$homeBackup = "$homePath.before-sos-coin"

if (-not (Test-Path $overlayBackup)) {
    Copy-Item $overlayPath $overlayBackup
}
if (-not (Test-Path $homeBackup)) {
    Copy-Item $homePath $homeBackup
}

# -----------------------------------------------------------------------------
# Global SOS flow: remove the old bottom-right floating launcher while keeping
# the established confirmation/form/GPS/send flow. Expose one reusable static
# opener for the flipping coin launchers.
# -----------------------------------------------------------------------------
$overlay = Normalize-Lf (Get-Content $overlayPath -Raw)

if ($overlay -notmatch 'static\s+Future<void>\s+open\s*\(\s*BuildContext\s+context\s*\)') {
    $childPattern = '(?m)^(\s*)final\s+Widget\s+child;\s*$'
    $childMatches = [regex]::Matches($overlay, $childPattern)

    if ($childMatches.Count -ne 1) {
        throw "Expected exactly one GlobalSosOverlay child field, found $($childMatches.Count). No Dart files were written."
    }

    $indent = $childMatches[0].Groups[1].Value
    $staticOpen = @"
$indent`final Widget child;

$indent`static Future<void> open(BuildContext context) async {
$indent  final state = context.findAncestorStateOfType<_GlobalSosOverlayState>();

$indent  if (state == null) {
$indent    return;
$indent  }

$indent  await state._beginSosFlow();
$indent}
"@

    $overlay = [regex]::Replace(
        $overlay,
        $childPattern,
        [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $staticOpen },
        1
    )
}

if ($overlay -notmatch 'return\s+widget\.child\s*;') {
    $buildPattern = '(?s)(\s*@override\s*\n\s*Widget\s+build\s*\(\s*BuildContext\s+context\s*\)\s*\{).*?(\n\s*Future<void>\s+_beginSosFlow\s*\(\s*\)\s+async\s*\{)'
    $buildMatches = [regex]::Matches($overlay, $buildPattern)

    if ($buildMatches.Count -ne 1) {
        throw "Unable to identify the GlobalSosOverlay launcher build method safely. Found $($buildMatches.Count) matches. No Dart files were written."
    }

    $buildReplacement = @"

  @override
  Widget build(BuildContext context) {
    return widget.child;
  }

  Future<void> _beginSosFlow() async {
"@

    $overlay = [regex]::Replace(
        $overlay,
        $buildPattern,
        [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $buildReplacement },
        1
    )
}

# Haptic feedback is fire-and-forget. Awaiting it creates an unnecessary async
# gap before showDialog and triggers use_build_context_synchronously.
$overlay = [regex]::Replace(
    $overlay,
    '(?m)^(\s*)await\s+HapticFeedback\.mediumImpact\(\);\s*$',
    '$1HapticFeedback.mediumImpact();'
)

# Validate the resulting overlay before writing it.
if ($overlay -notmatch 'static\s+Future<void>\s+open\s*\(\s*BuildContext\s+context\s*\)') {
    throw 'SOS static opener validation failed. No Dart files were written.'
}
if ($overlay -notmatch 'return\s+widget\.child\s*;') {
    throw 'Floating-launcher removal validation failed. No Dart files were written.'
}
if ($overlay -notmatch 'Future<void>\s+_beginSosFlow\s*\(') {
    throw 'Existing SOS confirmation flow was not found after patching. No Dart files were written.'
}

# -----------------------------------------------------------------------------
# Authenticated shell: add the same flipping SOS coin to the common app bar so
# Admin, Official, Tanod and Resident all retain access after login.
# -----------------------------------------------------------------------------
$homeContent = Normalize-Lf (Get-Content $homePath -Raw)

if ($homeContent -notmatch "import\s+'\.\./widgets/sos_flip_coin_button\.dart';") {
    $importPattern = "(?m)^(\s*import\s+'\.\./widgets/global_notification_bell\.dart';\s*)$"
    $importMatches = [regex]::Matches($homeContent, $importPattern)

    if ($importMatches.Count -ne 1) {
        throw "Could not identify the GlobalNotificationBell import safely. Found $($importMatches.Count). No Dart files were written."
    }

    $homeContent = [regex]::Replace(
        $homeContent,
        $importPattern,
        '$1' + "`nimport '../widgets/sos_flip_coin_button.dart';",
        1
    )
}

if ($homeContent -notmatch 'SosFlipCoinButton\s*\(\s*size:\s*42\s*\)') {
    $actionsPattern = '(?s)(actions\s*:\s*<Widget>\s*\[\s*\n)(\s*)(GlobalThemeButton\s*\(\s*user:\s*widget\.user\s*,\s*authService:\s*_authService\s*\)\s*,)'
    $actionsMatches = [regex]::Matches($homeContent, $actionsPattern)

    if ($actionsMatches.Count -ne 1) {
        throw "Could not identify the common HomeScreen app-bar actions safely. Found $($actionsMatches.Count). No Dart files were written."
    }

    $homeContent = [regex]::Replace(
        $homeContent,
        $actionsPattern,
        [System.Text.RegularExpressions.MatchEvaluator]{
            param($m)
            $prefix = $m.Groups[1].Value
            $indent = $m.Groups[2].Value
            $themeButton = $m.Groups[3].Value
            return $prefix + $indent + 'const SosFlipCoinButton(size: 42),' + "`n" + $indent + 'const SizedBox(width: 8),' + "`n" + $indent + $themeButton
        },
        1
    )
}

if ($homeContent -notmatch "import\s+'\.\./widgets/sos_flip_coin_button\.dart';") {
    throw 'HomeScreen SOS coin import validation failed. No Dart files were written.'
}
if ($homeContent -notmatch 'SosFlipCoinButton\s*\(\s*size:\s*42\s*\)') {
    throw 'HomeScreen SOS coin placement validation failed. No Dart files were written.'
}

# Only now write both Dart files, after all matching and validations succeeded.
Write-Utf8NoBom $overlayPath $overlay
Write-Utf8NoBom $homePath $homeContent

Write-Host 'SOS coin UI integration applied successfully.' -ForegroundColor Green
Write-Host 'The old floating SOS pill was removed; the confirmation/GPS/send flow was preserved.'
Write-Host 'A flipping SOS coin was added to the authenticated common app bar.'
Write-Host "Overlay backup: $overlayBackup"
Write-Host "HomeScreen backup: $homeBackup"
