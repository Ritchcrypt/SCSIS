$ErrorActionPreference = 'Stop'

$path = 'lib\screens\home_screen.dart'

if (-not (Test-Path $path)) {
    throw "Missing $path. Run this script from the Flutter project root."
}

$resolvedPath = (Resolve-Path $path).Path
$backupPath = "$path.before-utf8-repair"

$strictUtf8 = New-Object System.Text.UTF8Encoding($false, $true)
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$cp1252 = [System.Text.Encoding]::GetEncoding(
    1252,
    [System.Text.EncoderFallback]::ExceptionFallback,
    [System.Text.DecoderFallback]::ExceptionFallback
)

$content = [System.IO.File]::ReadAllText($resolvedPath, $strictUtf8)

if (-not (Test-Path $backupPath)) {
    [System.IO.File]::WriteAllText(
        (Join-Path (Get-Location) $backupPath),
        $content,
        $utf8NoBom
    )
}

function Get-MojibakeScore([string] $value) {
    $score = 0

    foreach ($character in $value.ToCharArray()) {
        $code = [int] $character

        if (
            $code -eq 0x00C2 -or
            $code -eq 0x00C3 -or
            $code -eq 0x00E2 -or
            $code -eq 0x00F0 -or
            $code -eq 0x00EF -or
            $code -eq 0xFFFD
        ) {
            $score++
        }
    }

    return $score
}

function Repair-MojibakeLine([string] $line) {
    $current = $line

    for ($pass = 0; $pass -lt 4; $pass++) {
        $currentScore = Get-MojibakeScore $current

        if ($currentScore -eq 0) {
            break
        }

        try {
            $legacyBytes = $cp1252.GetBytes($current)
            $candidate = $strictUtf8.GetString($legacyBytes)
        } catch {
            break
        }

        $candidateScore = Get-MojibakeScore $candidate

        if ($candidateScore -ge $currentScore) {
            break
        }

        $current = $candidate
    }

    return $current
}

$normalized = $content.Replace("`r`n", "`n")
$lines = $normalized -split "`n", -1
$repairedLines = New-Object System.Collections.Generic.List[string]

foreach ($line in $lines) {
    $repairedLines.Add((Repair-MojibakeLine $line))
}

$repaired = [string]::Join("`n", $repairedLines)

# Use ASCII-only Dart Unicode escapes for punctuation that previously became
# mojibake when Windows PowerShell rewrote this file.
$repaired = [regex]::Replace(
    $repaired,
    "subtitle:\s*'Dao, Capiz[^'\r\n]*Community Safety Overview'",
    "subtitle: 'Dao, Capiz \u2014 Community Safety Overview'"
)

$repaired = [regex]::Replace(
    $repaired,
    "suffix:\s*'[^'\r\n]*C'",
    "suffix: '\u00B0C'"
)

$repaired = $repaired.Replace([string][char]0x2014, '\u2014')
$repaired = $repaired.Replace([string][char]0x2022, '\u2022')
$repaired = $repaired.Replace([string][char]0x00B0, '\u00B0')

$weatherEmoji = [string][char]0xD83C + [string][char]0xDF24 + [string][char]0xFE0F
$repaired = $repaired.Replace($weatherEmoji, '\u{1F324}\uFE0F')

$remainingScore = Get-MojibakeScore $repaired

if ($remainingScore -gt 0) {
    throw "UTF-8 repair stopped because $remainingScore suspicious mojibake marker(s) remain. Original file is preserved at $backupPath."
}

if ($repaired -notmatch "Dao, Capiz \\u2014 Community Safety Overview") {
    throw "Dashboard subtitle validation failed. Original file is preserved at $backupPath."
}

if ($repaired -notmatch "suffix: '\\u00B0C'") {
    throw "Weather temperature-unit validation failed. Original file is preserved at $backupPath."
}

[System.IO.File]::WriteAllText($resolvedPath, $repaired, $utf8NoBom)

Write-Host 'Mobile dashboard UTF-8 text repaired successfully.' -ForegroundColor Green
Write-Host 'Protected dashboard punctuation now uses Dart Unicode escapes.' -ForegroundColor Green
Write-Host "Backup: $backupPath"
