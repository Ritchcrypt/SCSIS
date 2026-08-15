$ErrorActionPreference = 'Stop'

$flutterRoot = Split-Path -Parent $PSScriptRoot
$laravelRoot = Join-Path (Split-Path -Parent $flutterRoot) 'TabangNow_Laravel'
$adb = Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe'

if (-not (Test-Path (Join-Path $flutterRoot 'pubspec.yaml'))) {
    throw "Flutter project root not found at $flutterRoot"
}

if (-not (Test-Path (Join-Path $laravelRoot 'artisan'))) {
    throw "Laravel project was not found at $laravelRoot"
}

if (-not (Test-Path $adb)) {
    throw "ADB was not found at $adb"
}

function Test-Port8000 {
    try {
        $client = [System.Net.Sockets.TcpClient]::new()
        $async = $client.BeginConnect('127.0.0.1', 8000, $null, $null)
        $connected = $async.AsyncWaitHandle.WaitOne(600)

        if (-not $connected) {
            $client.Close()
            return $false
        }

        $client.EndConnect($async)
        $client.Close()
        return $true
    }
    catch {
        return $false
    }
}

function Invoke-AdbCommand {
    param(
        [Parameter(Mandatory = $true)]
        [string[]] $Arguments
    )

    # PowerShell 5.1 can promote native stderr text into NativeCommandError when
    # $ErrorActionPreference is Stop. Capture ADB output explicitly and decide
    # success from ADB's exit code instead.
    $previousPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'

    try {
        $output = @(& $adb @Arguments 2>&1 | ForEach-Object { $_.ToString() })
        $exitCode = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousPreference
    }

    if ($exitCode -ne 0) {
        $message = ($output -join [Environment]::NewLine).Trim()
        if ([string]::IsNullOrWhiteSpace($message)) {
            $message = "ADB exited with code $exitCode."
        }

        throw "ADB command failed: adb $($Arguments -join ' ')`n$message"
    }

    return $output
}

if (-not (Test-Port8000)) {
    Write-Host 'Laravel is not listening on 127.0.0.1:8000. Starting it in a separate PowerShell window...'
    $escapedLaravel = $laravelRoot.Replace("'", "''")

    Start-Process powershell.exe -ArgumentList @(
        '-NoExit',
        '-Command',
        "Set-Location '$escapedLaravel'; php artisan serve --host=127.0.0.1 --port=8000"
    ) | Out-Null

    $ready = $false

    for ($i = 0; $i -lt 20; $i++) {
        Start-Sleep -Milliseconds 500

        if (Test-Port8000) {
            $ready = $true
            break
        }
    }

    if (-not $ready) {
        throw 'Laravel did not become reachable on port 8000. Check the separate Laravel PowerShell window for the PHP error.'
    }
}

Write-Host 'Laravel is reachable on 127.0.0.1:8000.'

$deviceOutput = Invoke-AdbCommand -Arguments @('devices')
$authorizedSerials = @(
    $deviceOutput |
        Select-Object -Skip 1 |
        Where-Object { $_ -match '^\S+\s+device$' } |
        ForEach-Object { ($_ -split '\s+')[0] }
)

if ($authorizedSerials.Count -eq 0) {
    throw 'No authorized Android device was found. Connect the phone by USB, enable USB debugging, and accept the RSA prompt.'
}

if ($authorizedSerials.Count -gt 1) {
    throw "More than one authorized Android device is connected: $($authorizedSerials -join ', '). Disconnect extra devices and retry."
}

$serial = $authorizedSerials[0]
Write-Host "Using Android device: $serial"

# Do not remove tcp:8000 first. `adb reverse --remove` returns a non-zero exit
# status when no previous listener exists, which is harmless but broke the old
# PowerShell 5.1 runner. Creating the mapping directly is idempotent.
Invoke-AdbCommand -Arguments @(
    '-s', $serial,
    'reverse',
    'tcp:8000',
    'tcp:8000'
) | Out-Null

$reverse = Invoke-AdbCommand -Arguments @(
    '-s', $serial,
    'reverse',
    '--list'
)

if (-not ($reverse -match 'tcp:8000\s+tcp:8000')) {
    throw 'ADB reverse for tcp:8000 was not established.'
}

Write-Host 'ADB reverse verified: phone tcp:8000 -> laptop tcp:8000.'
Write-Host 'Launching Flutter with API_BASE_URL=http://127.0.0.1:8000 ...'

Set-Location $flutterRoot
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000
