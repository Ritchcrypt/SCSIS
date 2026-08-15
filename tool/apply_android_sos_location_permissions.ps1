$ErrorActionPreference = 'Stop'

$path = Join-Path (Get-Location) 'android\app\src\main\AndroidManifest.xml'

if (-not (Test-Path $path)) {
    throw "AndroidManifest.xml was not found at $path"
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$content = [System.IO.File]::ReadAllText($path)
$original = $content
$newline = if ($content.Contains("`r`n")) { "`r`n" } else { "`n" }

$permissions = @(
    '<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />',
    '<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />'
)

$missing = @()

foreach ($permission in $permissions) {
    if (-not $content.Contains($permission)) {
        $missing += $permission
    }
}

if ($missing.Count -eq 0) {
    Write-Host 'Android SOS location permissions are already present.'
    exit 0
}

$manifestPattern = '<manifest\s+xmlns:android="http://schemas\.android\.com/apk/res/android"\s*>'
$matches = [regex]::Matches($content, $manifestPattern)

if ($matches.Count -ne 1) {
    throw "Expected exactly one Android manifest root but found $($matches.Count). No file was written."
}

$insert = $matches[0].Value + $newline + '    ' + ($missing -join ($newline + '    '))
$content = [regex]::Replace($content, $manifestPattern, [System.Text.RegularExpressions.MatchEvaluator]{ param($m) $insert }, 1)

$backup = "$path.before-sos-location-permissions"
[System.IO.File]::WriteAllText($backup, $original, $utf8NoBom)
[System.IO.File]::WriteAllText($path, $content, $utf8NoBom)

Write-Host 'Android SOS location permissions applied successfully.'
Write-Host 'Existing INTERNET and other manifest settings were preserved.'
Write-Host "Backup: $backup"
