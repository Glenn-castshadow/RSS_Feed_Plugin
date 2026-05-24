param(
	[string] $Version = "",
	[string] $Slug = "curated-rss-aggregator"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root "dist"
$packageRoot = Join-Path $dist $Slug

if (Test-Path $dist) {
	Remove-Item -LiteralPath $dist -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $packageRoot | Out-Null

$items = @(
	"assets",
	"includes",
	"README.md",
	"uninstall.php",
	"wordpress-rss-aggregator.php"
)

foreach ($item in $items) {
	$source = Join-Path $root $item
	if (Test-Path $source) {
		Copy-Item -LiteralPath $source -Destination $packageRoot -Recurse -Force
	}
}

$zipName = if ($Version) { "$Slug-$Version.zip" } else { "$Slug.zip" }
$zipPath = Join-Path $dist $zipName

Compress-Archive -Path $packageRoot -DestinationPath $zipPath -Force

Write-Host "Built $zipPath"
