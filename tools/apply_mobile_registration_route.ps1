$ErrorActionPreference = 'Stop'

$path = 'routes\api.php'

if (-not (Test-Path $path)) {
    throw "Missing $path. Run this script from the Laravel project root."
}

$content = (Get-Content $path -Raw).Replace("`r`n", "`n")

if ($content -match "Route::post\('/register'") {
    Write-Host 'Mobile registration route is already present.' -ForegroundColor Green
    exit 0
}

$needle = @"
Route::prefix('v1/auth')->group(function (): void {
    Route::post('/login', [
"@

$replacement = @"
Route::prefix('v1/auth')->group(function (): void {
    Route::post('/register', [
        AuthController::class,
        'register',
    ])->name('api.v1.auth.register');

    Route::post('/login', [
"@

$count = ([regex]::Matches($content, [regex]::Escape($needle))).Count

if ($count -ne 1) {
    throw "Expected exactly one v1/auth login-group marker, found $count. Route file was not changed."
}

$backup = "$path.before-mobile-register-route"
if (-not (Test-Path $backup)) {
    Copy-Item $path $backup
}

$content = $content.Replace($needle, $replacement)

$utf8 = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText((Resolve-Path $path), $content, $utf8)

Write-Host 'Added POST /api/v1/auth/register.' -ForegroundColor Green
Write-Host "Backup: $backup"
