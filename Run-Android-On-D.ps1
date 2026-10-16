$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$destinationRoot = 'D:\Basateen-Al-Karaz-Build'
$sourceSdk = 'C:\Users\M\AppData\Local\Android_Sdk\Sdk'
$targetSdk = Join-Path $destinationRoot 'android-sdk'
$targetMobile = Join-Path $destinationRoot 'mobile'
$flutterExe = 'C:\Users\M\AppData\Local\flutter\bin\flutter.bat'
$deviceId = 'R5CT33AEBBT'
if (-not (Test-Path -LiteralPath $sourceSdk)) { throw 'Original Android SDK was not found.' }
if (-not (Test-Path -LiteralPath $flutterExe)) { throw 'Flutter was not found.' }
if ((Get-PSDrive D).Free -lt 20GB) { throw 'At least 20 GB free on D is required for this setup.' }
New-Item -ItemType Directory -Path $destinationRoot -Force | Out-Null
function Copy-Checked([string]$source, [string]$destination, [string[]]$exclude = @()) {
    New-Item -ItemType Directory -Path $destination -Force | Out-Null
    $copyArgs = @($source, $destination, '/E', '/XJ', '/R:1', '/W:1', '/COPY:DAT', '/DCOPY:DAT', '/NFL', '/NDL', '/NP')
    if ($exclude.Count -gt 0) { $copyArgs += '/XD'; $copyArgs += $exclude }
    & robocopy.exe @copyArgs
    if ($LASTEXITCODE -ge 8) { throw "Copy failed: $source. Original files remain unchanged." }
}
Write-Host 'Copying Android SDK to D. Keep this window open.'
Copy-Checked $sourceSdk $targetSdk
Write-Host 'Verifying copied SDK file sizes...'
$sourcePrefix = $sourceSdk.TrimEnd('\') + '\'
foreach ($sourceFile in (Get-ChildItem -LiteralPath $sourceSdk -Recurse -File -ErrorAction Stop)) {
    $relativePath = $sourceFile.FullName.Substring($sourcePrefix.Length)
    $copiedFile = Get-Item -LiteralPath (Join-Path $targetSdk $relativePath) -ErrorAction Stop
    if ($sourceFile.Length -ne $copiedFile.Length) { throw "Copy size mismatch: $relativePath" }
}
Write-Host 'SDK copy verified. Original SDK has not been deleted.'
$env:ANDROID_HOME = $targetSdk
$env:ANDROID_SDK_ROOT = $targetSdk
$env:GRADLE_USER_HOME = Join-Path $destinationRoot 'gradle-cache-clean'
$env:PUB_CACHE = Join-Path $destinationRoot 'pub-cache'
$env:TEMP = Join-Path $destinationRoot 'temp'
$env:TMP = $env:TEMP
New-Item -ItemType Directory -Path $env:GRADLE_USER_HOME,$env:TEMP,$env:PUB_CACHE -Force | Out-Null
# Use a fresh Gradle cache; never copy active caches or lock files.
Write-Host 'Copying application sources to the build directory on D.'
Copy-Checked (Join-Path $projectRoot 'mobile') $targetMobile @('build', '.dart_tool', '.gradle', '.git')
$propertiesFile = Join-Path $targetMobile 'android\local.properties'
$properties = Get-Content -LiteralPath $propertiesFile
$properties = $properties | Where-Object { $_ -notmatch '^sdk\.dir=' }
$properties += 'sdk.dir=D:/Basateen-Al-Karaz-Build/android-sdk'
Set-Content -LiteralPath $propertiesFile -Value $properties -Encoding ascii
if ((Get-PSDrive C).Free -lt 1GB) {
    throw 'Copy is ready on D. Free at least 1 GB on C for Windows, then run this file again. Do not delete the original SDK yet.'
}
& (Join-Path $projectRoot 'Start-Local.ps1')
$adbExe = Join-Path $targetSdk 'platform-tools\adb.exe'
& $adbExe -s $deviceId reverse tcp:8000 tcp:8000
if ($LASTEXITCODE -ne 0) { throw 'Reconnect and unlock the phone, then retry.' }
Push-Location $targetMobile
try {
    $migrationMarker = Join-Path $destinationRoot 'kotlin-in-process-v2.ready'
    if (-not (Test-Path -LiteralPath $migrationMarker)) {
        $resolvedMobile = (Resolve-Path -LiteralPath $targetMobile).Path
        if ($resolvedMobile -ne 'D:\Basateen-Al-Karaz-Build\mobile') {
            throw 'Unexpected build directory. Cleanup cancelled.'
        }
        Write-Host 'Cleaning generated build output once for the Kotlin compiler repair.'
        & $flutterExe clean
        if ($LASTEXITCODE -ne 0) { throw 'Build cleanup failed. Close other builds and retry.' }
    }
    & $flutterExe pub get
    if ($LASTEXITCODE -ne 0) { throw 'Package setup failed. See the output above.' }
    Set-Content -LiteralPath $migrationMarker -Value 'Package cache moved to D' -Encoding ascii
    & $flutterExe run -d $deviceId --debug --dart-define=API_URL=http://127.0.0.1:8000
    if ($LASTEXITCODE -ne 0) { throw 'Android build failed. Share the final error output.' }
} finally { Pop-Location }



