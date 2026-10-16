$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$mobileRoot = Join-Path $projectRoot 'mobile'
$buildRoot = Join-Path $mobileRoot 'build\web'
$publicApp = Join-Path $projectRoot 'backend\public\app'
Push-Location $mobileRoot
try {
    & flutter build web --base-href /app/
    if ($LASTEXITCODE -ne 0) { throw 'Flutter build failed. No files were published.' }
    if (-not (Test-Path -LiteralPath (Join-Path $buildRoot 'index.html'))) { throw 'Web build output is missing.' }
    New-Item -ItemType Directory -Path $publicApp -Force | Out-Null
    Get-ChildItem -LiteralPath $buildRoot -Force | Copy-Item -Destination $publicApp -Recurse -Force
} finally {
    Pop-Location
}
& (Join-Path $projectRoot 'Start-Local.ps1')
Write-Host 'Open the app: http://127.0.0.1:8000/app/'
