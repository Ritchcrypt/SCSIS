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

$devices = & $adb devices
$authorized = @(
    $devices |
        Select-Object -Skip 1 |
        Where-Object { $_ -match "\sdevice$" }
)

if ($authorized.Count -eq 0) {
    throw 'No authorized Android device was found. Connect the phone by USB, enable USB debugging, and accept the RSA prompt.'
}

& $adb reverse --remove tcp:8000 2>$null | Out-Null
& $adb reverse tcp:8000 tcp:8000 | Out-Null

$reverse = & $adb reverse --list
if (-not ($reverse -match 'tcp:8000\s+tcp:8000')) {
    throw 'ADB reverse for tcp:8000 was not established.'
}

Write-Host 'ADB reverse verified: phone tcp:8000 -> laptop tcp:8000.'
Write-Host 'Launching Flutter with API_BASE_URL=http://127.0.0.1:8000 ...'

Set-Location $flutterRoot
flutter run --dart-define=API_BASE_URL=http://127.0.0.1:8000
