$ErrorActionPreference = 'Stop'

$dartTool = Join-Path (Get-Location) 'tool\fix_global_sos_launcher.dart'

if (-not (Test-Path -LiteralPath $dartTool)) {
    throw "Missing tool: $dartTool"
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$text = [System.IO.File]::ReadAllText($dartTool, [System.Text.Encoding]::UTF8)

$broken = '"$anchorimport ''../widgets/sos_flip_coin_button.dart'';\n",'
$fixed  = '"${anchor}import ''../widgets/sos_flip_coin_button.dart'';\n",'

if ($text.Contains($broken)) {
    $text = $text.Replace($broken, $fixed)
    [System.IO.File]::WriteAllText($dartTool, $text, $utf8NoBom)
    Write-Host 'Repaired SOS launcher patcher typo.'
} elseif ($text.Contains($fixed)) {
    Write-Host 'SOS launcher patcher typo was already repaired.'
} else {
    throw 'Expected SOS launcher patcher marker was not found. Nothing was changed.'
}

Write-Host 'Running corrected SOS launcher repair...'
& dart run .\tool\fix_global_sos_launcher.dart

if ($LASTEXITCODE -ne 0) {
    throw "SOS launcher repair failed with exit code $LASTEXITCODE"
}
