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

Compress-Archive -Path "$stage" -DestinationPath $dest
Remove-Item -Recurse -Force $stageRoot

Write-Host "Package created: $dest"
