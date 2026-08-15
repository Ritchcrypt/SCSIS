$ErrorActionPreference = 'Stop'

$path = Join-Path (Get-Location) 'tool\fix_global_sos_launcher.dart'

if (-not (Test-Path -LiteralPath $path)) {
    throw "Missing tool: $path"
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$text = [System.IO.File]::ReadAllText($path, [System.Text.Encoding]::UTF8)

$broken = '"$anchorimport ''../widgets/sos_flip_coin_button.dart'';\n",'
$fixed  = '"${anchor}import ''../widgets/sos_flip_coin_button.dart'';\n",'

if ($text.Contains($fixed)) {
    Write-Host 'SOS launcher patcher is already repaired.'
    exit 0
}

if (-not $text.Contains($broken)) {
    throw 'Expected anchorimport typo was not found. Nothing was changed.'
}

$text = $text.Replace($broken, $fixed)
[System.IO.File]::WriteAllText($path, $text, $utf8NoBom)

Write-Host 'SOS launcher patcher typo repaired successfully.'
