param([string]$Php = 'php')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
& (Join-Path $PSScriptRoot 'test.ps1') -Php $Php
$sourceRoot = Join-Path $projectRoot 'source\calico-canonical-redirects'
$releaseRoot = Join-Path $projectRoot 'releases'
$null = New-Item -ItemType Directory -Force -Path $releaseRoot
$pluginText = Get-Content -LiteralPath (Join-Path $sourceRoot 'calico-canonical-redirects.php') -Raw
if ($pluginText -notmatch 'Version:\s*(\d+\.\d+\.\d+)') { throw 'Plugin version missing' }
$releaseVersion = $Matches[1]
$packagePath = Join-Path $releaseRoot "calico-canonical-redirects-$releaseVersion.zip"
if (Test-Path -LiteralPath $packagePath) { throw 'Package exists. Preserve the existing release; increment version before building another.' }
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($packagePath, 'Create')
try {
    Get-ChildItem -LiteralPath $sourceRoot -File -Recurse | ForEach-Object {
        $relativeName = $_.FullName.Substring($sourceRoot.Length + 1).Replace('\', '/')
        $null = [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, "calico-canonical-redirects/$relativeName")
    }
} finally { $archive.Dispose() }
$archive = [System.IO.Compression.ZipFile]::OpenRead($packagePath)
try {
    foreach ($entry in $archive.Entries) {
        if ($entry.FullName -notmatch '^calico-canonical-redirects/' -or $entry.FullName.Contains('\')) { throw 'Invalid archive layout' }
        $sourcePath = Join-Path $sourceRoot $entry.FullName.Substring('calico-canonical-redirects/'.Length)
        $entryStream = $entry.Open()
        $sha = [System.Security.Cryptography.SHA256]::Create()
        try { $entryHash = [Convert]::ToHexString($sha.ComputeHash($entryStream)) } finally { $entryStream.Dispose(); $sha.Dispose() }
        if ($entryHash -ne (Get-FileHash -LiteralPath $sourcePath -Algorithm SHA256).Hash) { throw "Package differs from tested source: $sourcePath" }
    }
} finally { $archive.Dispose() }
Get-FileHash -LiteralPath $packagePath -Algorithm SHA256 | Format-List
Copy-Item -LiteralPath $packagePath -Destination (Join-Path $releaseRoot 'calico-canonical-redirects.zip')
