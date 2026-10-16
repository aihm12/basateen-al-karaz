$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$pidFile = Join-Path $projectRoot 'runtime\http.pid'
if (Test-Path -LiteralPath $pidFile) {
    $serverPid = [int](Get-Content -LiteralPath $pidFile)
    $process = Get-Process -Id $serverPid -ErrorAction SilentlyContinue
    $expectedPath = (Resolve-Path (Join-Path $projectRoot 'runtime\php\php.exe')).Path
    if ($process -and $process.Path -eq $expectedPath) { Stop-Process -Id $serverPid }
}
$mysqlAdmin = Join-Path $projectRoot 'runtime\mysql-8.4.11-winx64\bin\mysqladmin.exe'
$config = Join-Path $projectRoot 'runtime\mysql-admin.ini'
& $mysqlAdmin "--defaults-file=$config" --default-character-set=utf8mb4 shutdown
if ($LASTEXITCODE -ne 0) { Write-Warning 'MySQL may already be stopped.' }
