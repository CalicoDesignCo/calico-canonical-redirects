param([string]$Php = 'php')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Get-ChildItem -LiteralPath $projectRoot -Recurse -Filter *.php | ForEach-Object {
    & $Php -l $_.FullName
    if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $($_.FullName)" }
}
& $Php (Join-Path $projectRoot 'tests\policy.php')
if ($LASTEXITCODE -ne 0) { throw 'Policy tests failed' }
& $Php (Join-Path $projectRoot 'tests\adapter.php')
if ($LASTEXITCODE -ne 0) { throw 'Adapter tests failed' }
& node (Join-Path $projectRoot 'tests\http-tests.cjs') $Php
if ($LASTEXITCODE -ne 0) { throw 'HTTP tests failed' }
