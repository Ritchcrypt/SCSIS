param(
    [string]$Alias = "tabangnow",
    [string]$KeystorePath = "$env:USERPROFILE\.tabangnow\android\tabangnow-release.jks"
)

$ErrorActionPreference = "Stop"

function Find-Keytool {
    $command = Get-Command keytool -ErrorAction SilentlyContinue
    if ($null -ne $command) {
        return $command.Source
    }

    if ($env:JAVA_HOME) {
        $candidate = Join-Path $env:JAVA_HOME "bin\keytool.exe"
        if (Test-Path -LiteralPath $candidate) {
            return $candidate
        }
    }

    $androidStudioKeytool =
        "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe"

    if (Test-Path -LiteralPath $androidStudioKeytool) {
        return $androidStudioKeytool
    }

    throw "keytool.exe was not found. Run flutter doctor -v and verify Java/Android Studio."
}

function Convert-SecureStringToPlainText {
    param([Security.SecureString]$SecureString)

    return [System.Net.NetworkCredential]::new(
        "",
        $SecureString
    ).Password
}

$flutterRoot = Split-Path -Parent $PSScriptRoot
$androidRoot = Join-Path $flutterRoot "android"
$keyPropertiesPath = Join-Path $androidRoot "key.properties"

if (-not (Test-Path -LiteralPath $androidRoot)) {
    throw "Android project not found: $androidRoot"
}

$keytool = Find-Keytool
$keystoreFullPath = [IO.Path]::GetFullPath($KeystorePath)
$keystoreDirectory = Split-Path -Parent $keystoreFullPath

New-Item -ItemType Directory -Force -Path $keystoreDirectory | Out-Null

if (Test-Path -LiteralPath $keystoreFullPath) {
    throw @"
A TabangNow release keystore already exists:

$keystoreFullPath

It was NOT overwritten. Keep using the same signing key for future APK updates.
"@
}

Write-Host ""
Write-Host "TabangNow Android Release Key" -ForegroundColor Cyan
Write-Host "-----------------------------" -ForegroundColor Cyan
Write-Host ""
Write-Host "The following keytool prompts create the permanent Android signing identity." -ForegroundColor Yellow
Write-Host "Store the password somewhere safe. Losing this key/password can prevent future APK updates." -ForegroundColor Yellow
Write-Host ""

& $keytool `
    -genkeypair `
    -v `
    -keystore $keystoreFullPath `
    -storetype JKS `
    -keyalg RSA `
    -keysize 2048 `
    -validity 10000 `
    -alias $Alias

if ($LASTEXITCODE -ne 0) {
    throw "keytool failed. key.properties was not created."
}

if (-not (Test-Path -LiteralPath $keystoreFullPath)) {
    throw "The release keystore was not created."
}

Write-Host ""
Write-Host "Re-enter the keystore password for Android Gradle signing." -ForegroundColor Yellow
$storeSecure = Read-Host "Keystore password" -AsSecureString

Write-Host ""
Write-Host "Enter the key password used for alias '$Alias'." -ForegroundColor Yellow
Write-Host "If you pressed Enter at keytool's key-password prompt, use the SAME password as the keystore." -ForegroundColor DarkGray
$keySecure = Read-Host "Key password" -AsSecureString

$storePassword = Convert-SecureStringToPlainText $storeSecure
$keyPassword = Convert-SecureStringToPlainText $keySecure

if ([string]::IsNullOrWhiteSpace($storePassword)) {
    throw "Keystore password cannot be empty."
}

if ([string]::IsNullOrWhiteSpace($keyPassword)) {
    throw "Key password cannot be empty."
}

$escapedStoreFile = $keystoreFullPath.Replace("\", "\\")

$content = @"
storePassword=$storePassword
keyPassword=$keyPassword
keyAlias=$Alias
storeFile=$escapedStoreFile
"@

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

[System.IO.File]::WriteAllText(
    $keyPropertiesPath,
    $content,
    $utf8NoBom
)

Write-Host ""
Write-Host "SUCCESS: TabangNow release signing configured." -ForegroundColor Green
Write-Host "Keystore:      $keystoreFullPath" -ForegroundColor Green
Write-Host "key.properties: $keyPropertiesPath" -ForegroundColor Green
Write-Host ""
Write-Host "IMPORTANT:" -ForegroundColor Yellow
Write-Host "- android/key.properties is Git-ignored and must remain private."
Write-Host "- The .jks file is outside the repository."
Write-Host "- Back up the .jks file and its passwords securely before public distribution."
