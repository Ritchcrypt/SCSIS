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
# Global SOS flow: remove the floating bottom-right launcher but keep the
# established confirmation/form/GPS/send flow. Expose a static opener so any
# deliberate launcher (login coin or authenticated app-bar coin) can reuse it.
# -----------------------------------------------------------------------------
$overlay = Normalize-Lf (Get-Content $overlayPath -Raw)

if ($overlay -notmatch 'static Future<void> open\(BuildContext context\)') {
    $needle = @"
  final Widget child;

  @override
"@

    $replacement = @"
  final Widget child;

  static Future<void> open(BuildContext context) async {
    final state = context.findAncestorStateOfType<_GlobalSosOverlayState>();

    if (state == null) {
      return;
    }

    await state._beginSosFlow();
  }

  @override
"@

    $count = ([regex]::Matches($overlay, [regex]::Escape($needle))).Count
    if ($count -ne 1) {
        throw "Expected one GlobalSosOverlay child marker, found $count. No files were finalized."
    }

    $overlay = $overlay.Replace($needle, $replacement)
}

$buildPattern = '(?s)  @override\n  Widget build\(BuildContext context\) \{.*?\n  \}\n\n  Future<void> _beginSosFlow\(\) async \{'
$buildReplacement = @"
  @override
  Widget build(BuildContext context) {
    return widget.child;
  }

  Future<void> _beginSosFlow() async {
"@

$matches = [regex]::Matches($overlay, $buildPattern)
if ($matches.Count -eq 1) {
    $overlay = [regex]::Replace($overlay, $buildPattern, $buildReplacement, 1)
} elseif ($overlay -notmatch 'Widget build\(BuildContext context\) \{\n    return widget\.child;') {
    throw "Unable to identify the old floating SOS launcher safely. Found $($matches.Count) matches."
}

# Avoid analyzer's use_build_context_synchronously lint before the first dialog.
$overlay = $overlay.Replace(
    '    await HapticFeedback.mediumImpact();',
    '    HapticFeedback.mediumImpact();'
)

Write-Utf8NoBom $overlayPath $overlay

# -----------------------------------------------------------------------------
# Authenticated shell: the same flipping SOS coin stays available to every role
# from the common app bar. This keeps the global SOS behavior after login.
# -----------------------------------------------------------------------------
$home = Normalize-Lf (Get-Content $homePath -Raw)

if ($home -notmatch "import '../widgets/sos_flip_coin_button.dart';") {
    $importNeedle = "import '../widgets/global_notification_bell.dart';"
    if ($home -notmatch [regex]::Escape($importNeedle)) {
        throw 'Could not find the GlobalNotificationBell import in HomeScreen.'
    }

    $home = $home.Replace(
        $importNeedle,
        "$importNeedle`nimport '../widgets/sos_flip_coin_button.dart';"
    )
}

if ($home -notmatch 'SosFlipCoinButton\(size: 42\)') {
    $actionsNeedle = @"
        actions: <Widget>[
          GlobalThemeButton(user: widget.user, authService: _authService),
"@

    $actionsReplacement = @"
        actions: <Widget>[
          const SosFlipCoinButton(size: 42),
          const SizedBox(width: 8),
          GlobalThemeButton(user: widget.user, authService: _authService),
"@

    $count = ([regex]::Matches($home, [regex]::Escape($actionsNeedle))).Count
    if ($count -ne 1) {
        throw "Expected one HomeScreen app-bar actions marker, found $count."
    }

    $home = $home.Replace($actionsNeedle, $actionsReplacement)
}

Write-Utf8NoBom $homePath $home

Write-Host 'SOS coin UI integration applied successfully.' -ForegroundColor Green
Write-Host "Overlay backup: $overlayBackup"
Write-Host "HomeScreen backup: $homeBackup"
