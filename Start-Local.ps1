$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$phpExe = Join-Path $projectRoot 'runtime\php\php.exe'
$mysqlExe = Join-Path $projectRoot 'runtime\mysql-8.4.11-winx64\bin\mysqld.exe'
$mysqlConfig = Join-Path $projectRoot 'runtime\mysql.ini'
$backend = Join-Path $projectRoot 'backend'

function Test-LocalPort([int]$port) {
    $client = [System.Net.Sockets.TcpClient]::new()
    try { $client.Connect('127.0.0.1', $port); return $true }
    catch { return $false }
    finally { $client.Dispose() }
}

if (-not (Test-LocalPort 3307)) {
    $process = Start-Process -FilePath $mysqlExe -ArgumentList "--defaults-file=`"$mysqlConfig`"" -WindowStyle Hidden -PassThru
    $process.Id | Set-Content (Join-Path $projectRoot 'runtime\mysql.pid')
    for ($i = 0; $i -lt 30 -and -not (Test-LocalPort 3307); $i++) { Start-Sleep -Milliseconds 500 }
    if (-not (Test-LocalPort 3307)) { throw 'MySQL did not start. See runtime/logs/mysql.log.' }
}

if (-not (Test-LocalPort 8000)) {
    $arguments = @('-S', '127.0.0.1:8000', '-t', '.', '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')
    $process = Start-Process -FilePath $phpExe -ArgumentList $arguments -WorkingDirectory (Join-Path $backend 'public') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $projectRoot 'runtime\logs\http.log') -RedirectStandardError (Join-Path $projectRoot 'runtime\logs\http-error.log')
    $process.Id | Set-Content (Join-Path $projectRoot 'runtime\http.pid')
    for ($i = 0; $i -lt 20 -and -not (Test-LocalPort 8000); $i++) { Start-Sleep -Milliseconds 250 }
    if (-not (Test-LocalPort 8000)) { throw 'HTTP server did not start. See runtime/logs/http-error.log.' }
}

Write-Host 'Basateen Al Karaz: http://127.0.0.1:8000'
Write-Host 'Categories: http://127.0.0.1:8000/api/categories'
Write-Host 'MySQL: 127.0.0.1:3307 / basateen_al_karaz'
