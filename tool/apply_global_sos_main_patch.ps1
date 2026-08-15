$ErrorActionPreference = 'Stop'

$path = Join-Path (Get-Location) 'lib\main.dart'

if (-not (Test-Path $path)) {
    throw "main.dart was not found at $path"
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$content = [System.IO.File]::ReadAllText($path)
$original = $content
$newline = if ($content.Contains("`r`n")) { "`r`n" } else { "`n" }

if (-not $content.Contains("import 'widgets/global_sos_overlay.dart';")) {
    $importAnchor = "import 'core/global_theme_controller.dart';"

    if (-not $content.Contains($importAnchor)) {
        throw 'Could not find the global theme import anchor. No file was written.'
    }

    $content = $content.Replace(
        $importAnchor,
        $importAnchor + $newline + $newline + "import 'widgets/global_sos_overlay.dart';"
    )
}

if (-not $content.Contains('GlobalSosOverlay(')) {
    $pattern = 'TabangNowGlobalTheme\(\s*child:\s*child\s*\?\?\s*const\s+SizedBox\.shrink\(\)\s*\)'
    $matches = [regex]::Matches($content, $pattern)

    if ($matches.Count -ne 1) {
        throw "Expected exactly one TabangNowGlobalTheme builder expression but found $($matches.Count). No file was written."
    }

    $replacement = @"
TabangNowGlobalTheme(
            child: GlobalSosOverlay(
              child: child ?? const SizedBox.shrink(),
            ),
          )
"@
    $replacement = [regex]::Replace($replacement, "`r?`n", $newline).TrimEnd("`r", "`n")
    $content = [regex]::Replace($content, $pattern, $replacement, 1)
}

if ($content -eq $original) {
    Write-Host 'Global SOS root integration is already present.'
    exit 0
}

$backup = "$path.before-global-sos"
[System.IO.File]::WriteAllText($backup, $original, $utf8NoBom)
[System.IO.File]::WriteAllText($path, $content, $utf8NoBom)

Write-Host 'Global SOS root integration applied successfully.'
Write-Host 'The SOS button now wraps the app root, so it is available before login and for every authenticated role.'
Write-Host "Backup: $backup"
