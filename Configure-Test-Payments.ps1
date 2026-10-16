$ErrorActionPreference = 'Stop'
$secret = Read-Host 'Enter Moyasar TEST secret key (sk_test_...)' -AsSecureString
$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try {
    $value = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    if ($value -notmatch '^sk_test_[A-Za-z0-9_-]+$') { throw 'Only a Moyasar test secret key is accepted.' }
    $envFile = Join-Path $PSScriptRoot 'backend\.env'
    $content = [IO.File]::ReadAllText($envFile)
    $content = [regex]::Replace($content, '(?m)^MOYASAR_SECRET_KEY=.*$', 'MOYASAR_SECRET_KEY=' + $value)
    $content = [regex]::Replace($content, '(?m)^MOYASAR_ENABLED=.*$', 'MOYASAR_ENABLED=true')
    [IO.File]::WriteAllText($envFile, $content, [Text.UTF8Encoding]::new($false))
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    $value = $null
}
& (Join-Path $PSScriptRoot 'runtime\php\php.exe') (Join-Path $PSScriptRoot 'backend\artisan') config:clear
if ($LASTEXITCODE -ne 0) { throw 'Could not clear configuration.' }
Write-Host 'Test payments configured. No payment or charge was submitted.'
