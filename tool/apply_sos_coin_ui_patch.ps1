$ErrorActionPreference = 'Stop'

function Write-Utf8NoBom([string]$Path, [string]$Content) {
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    $resolved = [System.IO.Path]::GetFullPath((Join-Path (Get-Location) $Path))
    [System.IO.File]::WriteAllText($resolved, $Content, $utf8)
}

function Normalize-Lf([string]$Text) {
    return $Text.Replace("`r`n", "`n")
}

$overlayPath = 'lib\widgets\global_sos_overlay.dart'
$overlayBackup = 'lib\widgets\global_sos_overlay.dart.before-sos-coin'
$homePath = 'lib\screens\home_screen.dart'
$homeBackup = 'lib\screens\home_screen.dart.before-sos-coin'

if (-not (Test-Path $overlayPath)) {
    throw "Missing $overlayPath"
}
if (-not (Test-Path $homePath)) {
    throw "Missing $homePath"
}

# The earlier PowerShell patch could corrupt the Dart field declaration because
# PowerShell interpreted an escape sequence while constructing Dart source.
# Prefer the untouched pre-coin backup whenever it exists. We only read it here;
# nothing is written until both Dart files pass validation below.
if (Test-Path $overlayBackup) {
    $overlayContent = Normalize-Lf (Get-Content $overlayBackup -Raw)
    Write-Host "Repair source: $overlayBackup" -ForegroundColor Yellow
} else {
    $overlayContent = Normalize-Lf (Get-Content $overlayPath -Raw)

    if ($overlayContent -match "[char]12" -or $overlayContent -match '(?m)^\s*inal\s+Widget') {
        throw "The SOS overlay is corrupted and $overlayBackup is missing. Stop here so the file can be restored safely."
    }
}

$homeContent = Normalize-Lf (Get-Content $homePath -Raw)

# Preserve a clean HomeScreen backup if one does not already exist.
if (-not (Test-Path $homeBackup)) {
    Copy-Item $homePath $homeBackup
}

# -----------------------------------------------------------------------------
# 1) Repair/convert GlobalSosOverlay into a flow host (no floating bottom-right
#    button). The confirmation, emergency form, GPS and send behavior stay in
#    the same state object.
# -----------------------------------------------------------------------------

if ($overlayContent -notmatch 'static\s+Future<void>\s+open\s*\(\s*BuildContext\s+context\s*\)') {
    $childPattern = '(?m)^\s*final\s+Widget\s+child;\s*$'
    $childMatches = [regex]::Matches($overlayContent, $childPattern)

    if ($childMatches.Count -ne 1) {
        throw "Expected one clean 'final Widget child;' field in GlobalSosOverlay, found $($childMatches.Count). Nothing was written."
    }

    $staticOpen = @'
  final Widget child;

  static Future<void> open(BuildContext context) async {
    final state = context.findAncestorStateOfType<_GlobalSosOverlayState>();

    if (state == null) {
      return;
    }

    await state._beginSosFlow();
  }
'@

    $overlayContent = [regex]::Replace(
        $overlayContent,
        $childPattern,
        [System.Text.RegularExpressions.MatchEvaluator]{
            param($match)
            return $staticOpen
        },
        1
    )
}

if ($overlayContent -notmatch 'return\s+widget\.child\s*;') {
    $buildPattern = '(?s)\s*@override\s*\n\s*Widget\s+build\s*\(\s*BuildContext\s+context\s*\)\s*\{.*?\n\s*\}\s*\n\s*\n\s*Future<void>\s+_beginSosFlow\s*\(\s*\)\s+async\s*\{'
    $buildMatches = [regex]::Matches($overlayContent, $buildPattern)

    if ($buildMatches.Count -ne 1) {
        throw "Expected one floating-launcher build method before _beginSosFlow, found $($buildMatches.Count). Nothing was written."
    }

    $flowHostBuild = @'

  @override
  Widget build(BuildContext context) {
    return widget.child;
  }

  Future<void> _beginSosFlow() async {
'@

    $overlayContent = [regex]::Replace(
        $overlayContent,
        $buildPattern,
        [System.Text.RegularExpressions.MatchEvaluator]{
            param($match)
            return $flowHostBuild
        },
        1
    )
}

# Haptics do not need to be awaited. Removing the await also avoids the analyzer
# warning about using BuildContext across the artificial async gap.
$overlayContent = [regex]::Replace(
    $overlayContent,
    '(?m)^(\s*)await\s+HapticFeedback\.mediumImpact\(\);\s*$',
    '$1HapticFeedback.mediumImpact();'
)

# -----------------------------------------------------------------------------
# 2) Add the same flipping coin to the authenticated common app bar.
# -----------------------------------------------------------------------------

if ($homeContent -notmatch "import\s+'\.\./widgets/sos_flip_coin_button\.dart';") {
    $importPattern = "(?m)^(\s*import\s+'\.\./widgets/global_notification_bell\.dart';\s*)$"
    $importMatches = [regex]::Matches($homeContent, $importPattern)

    if ($importMatches.Count -ne 1) {
        throw "Expected one GlobalNotificationBell import in HomeScreen, found $($importMatches.Count). Nothing was written."
    }

    $homeContent = [regex]::Replace(
        $homeContent,
        $importPattern,
        [System.Text.RegularExpressions.MatchEvaluator]{
            param($match)
            return $match.Groups[1].Value + "`nimport '../widgets/sos_flip_coin_button.dart';"
        },
        1
    )
}

if ($homeContent -notmatch 'SosFlipCoinButton\s*\(\s*size:\s*42\s*\)') {
    $actionsPattern = '(?s)(actions\s*:\s*<Widget>\s*\[\s*\n)(\s*)(GlobalThemeButton\s*\(\s*user:\s*widget\.user\s*,\s*authService:\s*_authService\s*\)\s*,)'
    $actionsMatches = [regex]::Matches($homeContent, $actionsPattern)

    if ($actionsMatches.Count -ne 1) {
        throw "Expected one common HomeScreen app-bar action list, found $($actionsMatches.Count). Nothing was written."
    }

    $homeContent = [regex]::Replace(
        $homeContent,
        $actionsPattern,
        [System.Text.RegularExpressions.MatchEvaluator]{
            param($match)
            $prefix = $match.Groups[1].Value
            $indent = $match.Groups[2].Value
            $themeButton = $match.Groups[3].Value
            return $prefix + $indent + 'const SosFlipCoinButton(size: 42),' + "`n" + $indent + 'const SizedBox(width: 8),' + "`n" + $indent + $themeButton
        },
        1
    )
}

# -----------------------------------------------------------------------------
# 3) Validate everything BEFORE writing either Dart file.
# -----------------------------------------------------------------------------

$overlayChecks = @(
    @{ Name = 'final child field'; Pattern = '(?m)^\s*final\s+Widget\s+child;\s*$' },
    @{ Name = 'static SOS opener'; Pattern = 'static\s+Future<void>\s+open\s*\(' },
    @{ Name = 'flow-host build'; Pattern = 'return\s+widget\.child\s*;' },
    @{ Name = 'SOS confirmation flow'; Pattern = 'Future<void>\s+_beginSosFlow\s*\(' }
)

foreach ($check in $overlayChecks) {
    if ($overlayContent -notmatch $check.Pattern) {
        throw "GlobalSosOverlay validation failed: $($check.Name). Nothing was written."
    }
}

if ($overlayContent.Contains([char]12)) {
    throw 'GlobalSosOverlay still contains an illegal form-feed/control character. Nothing was written.'
}

if ($overlayContent -match '(?m)^\s*inal\s+Widget') {
    throw "GlobalSosOverlay still contains the corrupted 'inal Widget' token. Nothing was written."
}

if ($homeContent -notmatch "import\s+'\.\./widgets/sos_flip_coin_button\.dart';") {
    throw 'HomeScreen validation failed: SOS coin import missing. Nothing was written.'
}

if ($homeContent -notmatch 'SosFlipCoinButton\s*\(\s*size:\s*42\s*\)') {
    throw 'HomeScreen validation failed: SOS coin action missing. Nothing was written.'
}

Write-Utf8NoBom $overlayPath $overlayContent
Write-Utf8NoBom $homePath $homeContent

Write-Host 'SOS overlay repaired and SOS coin integration applied successfully.' -ForegroundColor Green
Write-Host 'Removed: bottom-right floating SOS pill.'
Write-Host 'Preserved: confirmation, emergency form, GPS/current-or-last-known location, and send flow.'
Write-Host 'Added: flipping SOS coin to the authenticated common app bar.'
Write-Host "Clean overlay source: $overlayBackup"
Write-Host "HomeScreen backup: $homeBackup"
