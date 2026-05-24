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
	"blocks",
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

# Compress-Archive and ZipFile.CreateFromDirectory both write Windows backslashes
# into zip entry names on .NET/Windows.  PHP's ZipArchive on Linux servers does NOT
# normalise backslashes to forward slashes, so the plugin file is never found after
# extraction — causing the "Plugin file does not exist" error in WordPress.
# We build the archive manually so every entry path uses forward slashes.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipStream = [System.IO.File]::Create($zipPath)
$archive   = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)

try {
	Get-ChildItem -Path $packageRoot -Recurse -File | ForEach-Object {
		$relativePath = $_.FullName.Substring($packageRoot.Length).TrimStart('\', '/').Replace('\', '/')
		$entryName    = "$Slug/$relativePath"
		$entry        = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
		$entryStream  = $entry.Open()
		try {
			$fileStream = [System.IO.File]::OpenRead($_.FullName)
			try   { $fileStream.CopyTo($entryStream) }
			finally { $fileStream.Close() }
		} finally {
			$entryStream.Close()
		}
	}
} finally {
	$archive.Dispose()
	$zipStream.Close()
}

Write-Host "Built $zipPath"
