param(
    [string]$FlutterRoot = "C:\xampp\htdocs\TabangNow_Flutter",
    [string]$LaravelRoot = "C:\xampp\htdocs\TabangNow_Laravel",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

function Test-TcpPort {
    param(
        [string]$HostName,
        [int]$PortNumber,
        [int]$TimeoutMs = 700
    )

    $client = New-Object System.Net.Sockets.TcpClient

    try {
        $task = $client.ConnectAsync($HostName, $PortNumber)

        if (-not $task.Wait($TimeoutMs)) {
            return $false
        }

        return $client.Connected
    }
    catch {
        return $false
    }
    finally {
        $client.Dispose()
    }
}

Write-Host ""
Write-Host "TabangNow Android Profile Launcher" -ForegroundColor Cyan
Write-Host "---------------------------------" -ForegroundColor Cyan
Write-Host "Release-like performance check using the LOCAL development backend." -ForegroundColor DarkGray
Write-Host ""

if (-not (Test-Path -LiteralPath $FlutterRoot)) {
    throw "Flutter project not found: $FlutterRoot"
}

if (-not (Test-Path -LiteralPath $LaravelRoot)) {
    throw "Laravel project not found: $LaravelRoot"
}

$adb = Join-Path $env:LOCALAPPDATA "Android\Sdk\platform-tools\adb.exe"

if (-not (Test-Path -LiteralPath $adb)) {
    throw "ADB not found at: $adb"
}

$flutterCommand = Get-Command flutter -ErrorAction SilentlyContinue

if ($null -eq $flutterCommand) {
    throw "Flutter is not available in PATH."
}

$phpCommand = Get-Command php -ErrorAction SilentlyContinue

if ($null -eq $phpCommand) {
    throw "PHP is not available in PATH."
}

Write-Host "[1/5] Checking Android device..." -ForegroundColor Yellow

& $adb start-server | Out-Null

$deviceLines = @(& $adb devices) |
    Where-Object { $_ -match "`tdevice$" }

if ($deviceLines.Count -lt 1) {
    throw "No authorized Android device found. Connect the phone by USB and enable USB debugging."
}

Write-Host "      Android device ready." -ForegroundColor Green

Write-Host "[2/5] Checking Laravel on 127.0.0.1:$Port..." -ForegroundColor Yellow

if (-not (Test-TcpPort -HostName "127.0.0.1" -PortNumber $Port)) {
    Write-Host "      Laravel is not running. Starting it..." -ForegroundColor Yellow

    $escapedLaravelRoot = $LaravelRoot.Replace("'", "''")
    $laravelCommand =
        "Set-Location -LiteralPath '$escapedLaravelRoot'; " +
        "php artisan serve --host=127.0.0.1 --port=$Port"

    Start-Process `
        -FilePath "powershell.exe" `
        -ArgumentList @(
            "-NoExit",
            "-ExecutionPolicy", "Bypass",
            "-Command", $laravelCommand
        ) | Out-Null

    $deadline = (Get-Date).AddSeconds(15)

    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Milliseconds 400

        if (Test-TcpPort -HostName "127.0.0.1" -PortNumber $Port) {
            break
        }
    }
}

if (-not (Test-TcpPort -HostName "127.0.0.1" -PortNumber $Port)) {
    throw "Laravel did not become reachable on port $Port. Check the Laravel terminal for its actual error."
}

Write-Host "      Laravel backend reachable." -ForegroundColor Green

Write-Host "[3/5] Configuring USB port forwarding..." -ForegroundColor Yellow

& $adb reverse "tcp:$Port" "tcp:$Port" | Out-Null

if ($LASTEXITCODE -ne 0) {
    throw "ADB reverse failed for tcp:$Port."
}

$reverseList = @(& $adb reverse --list)

if (-not ($reverseList -match "tcp:$Port")) {
    throw "ADB reverse verification failed for tcp:$Port."
}

Write-Host "      adb reverse tcp:$Port -> tcp:$Port verified." -ForegroundColor Green

Write-Host "[4/5] Verifying Flutter project..." -ForegroundColor Yellow
Set-Location -LiteralPath $FlutterRoot

if (-not (Test-Path -LiteralPath ".\pubspec.yaml")) {
    throw "pubspec.yaml not found in $FlutterRoot"
}

Write-Host "      Flutter root verified." -ForegroundColor Green

Write-Host "[5/5] Launching TabangNow in PROFILE mode..." -ForegroundColor Yellow
Write-Host ""
Write-Host "API_BASE_URL=http://127.0.0.1:$Port" -ForegroundColor DarkGray
Write-Host ""
Write-Host "Use this run to judge startup and scrolling performance." -ForegroundColor DarkGray
Write-Host "Do NOT treat it as the final production APK." -ForegroundColor DarkGray
Write-Host ""

flutter run `
    --profile `
    "--dart-define=API_BASE_URL=http://127.0.0.1:$Port"
