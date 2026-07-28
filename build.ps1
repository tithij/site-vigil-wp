# Build the WordPress-installable ZIP for the Site Vigil plugin.
# Run from the site-vigil-wp directory:
#   .\build.ps1

$root = $PSScriptRoot
$distDir = Join-Path $root "dist"

$versionLine = Get-Content (Join-Path $root "site-vigil.php") | Where-Object { $_ -match '^\s*\*\s*Version:\s*(.+)$' } | Select-Object -First 1
if (-not $versionLine) {
    Write-Error "Could not read Version: header from site-vigil.php"
    exit 1
}
$version = ($versionLine -replace '^\s*\*\s*Version:\s*', '').Trim()

$dest = Join-Path $distDir "site-vigil-wp-$version.zip"
New-Item -ItemType Directory -Force -Path $distDir | Out-Null
if (Test-Path $dest) { Remove-Item $dest -Force }

# Stage a clean copy honoring .distignore, under a "site-vigil" top-level
# folder (what WP's installer expects inside the zip), then compress that.
$stageRoot = Join-Path ([System.IO.Path]::GetTempPath()) ([System.Guid]::NewGuid().ToString())
$stage = Join-Path $stageRoot "site-vigil"
New-Item -ItemType Directory -Force -Path $stage | Out-Null

$ignore = Get-Content (Join-Path $root ".distignore") | Where-Object { $_.Trim() -ne "" }
$ignore += "dist"

Get-ChildItem -Path $root -Force | Where-Object {
    $name = $_.Name
    -not ($ignore | Where-Object { $name -eq $_ -or $name -like $_ })
} | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination (Join-Path $stage $_.Name) -Recurse -Force
}

# Build the zip manually with System.IO.Compression instead of
# Compress-Archive: on Windows, Compress-Archive writes backslashes as the
# path separator inside zip entries (and emits an explicit entry for any
# directory that contains only subdirectories, e.g. .../Puc). The zip format
# requires forward slashes, and an entry ending in "\" isn't recognized as a
# directory marker, so WordPress's installer tries to extract it as a file
# named "...Puc\" and fails with "Could not copy file.". Adding only file
# entries with normalized "/" paths sidesteps both problems - extractors
# create parent directories implicitly from the file paths.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($dest, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -Path $stageRoot -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($stageRoot.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $relativePath) | Out-Null
    }
} finally {
    $zip.Dispose()
}
Remove-Item -Recurse -Force $stageRoot

Write-Host "Package created: $dest"
