$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
# Conservative headroom for SDK extraction, Gradle caches and application output.
$MinimumFreeSpaceGB = 15
$freeSpaceGB = (Get-PSDrive -Name C).Free / 1GB
if ($freeSpaceGB -lt $MinimumFreeSpaceGB) {
    throw ('Insufficient disk space on C: {0:N1} GB free. Free at least {1} GB before building Android.' -f $freeSpaceGB, $MinimumFreeSpaceGB)
}
$deviceId = 'R5CT33AEBBT'
$adbExe = 'C:\Users\M\AppData\Local\Android_Sdk\Sdk\platform-tools\adb.exe'
$flutterExe = 'C:\Users\M\AppData\Local\flutter\bin\flutter.bat'
if (-not (Test-Path -LiteralPath $adbExe)) { throw 'Android platform-tools were not found.' }
& (Join-Path $projectRoot 'Start-Local.ps1')
& $adbExe -s $deviceId reverse tcp:8000 tcp:8000
if ($LASTEXITCODE -ne 0) { throw 'USB connection failed. Unlock the phone and check USB debugging authorization.' }
Push-Location (Join-Path $projectRoot 'mobile')
try {
    & $flutterExe run -d $deviceId --debug --dart-define=API_URL=http://127.0.0.1:8000
    if ($LASTEXITCODE -ne 0) { throw 'Flutter could not start the Android application. See the error above.' }
} finally {
    Pop-Location
}

