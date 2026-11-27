# Собирает устанавливаемый архив плагина в dist/wp-smsn1-ru.zip (Windows)
#
# Важно: записи в ZIP должны иметь прямые слэши ("/"), иначе на Linux-хостинге
# WordPress распаковывает файлы с обратными слэшами в имени (битая установка).
# Поэтому используем tar (bsdtar, Windows 10+) или правим имена в ZIP после Compress-Archive.
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$slug  = 'wp-smsn1-ru'
$files = @('wp-smsn1-ru.php', 'includes', 'templates', 'assets', 'languages', 'readme.txt', 'uninstall.php', 'LICENSE')

$dist = Join-Path $root 'dist'
if (Test-Path $dist) { Remove-Item $dist -Recurse -Force }
$stage = Join-Path $dist $slug
New-Item -ItemType Directory -Path $stage -Force | Out-Null

foreach ($f in $files) {
    Copy-Item -Path (Join-Path $root $f) -Destination $stage -Recurse -Force
}

Remove-Item (Join-Path $stage "languages/.gitkeep") -Force -ErrorAction SilentlyContinue

$zip = Join-Path $dist "$slug.zip"
$tar = Get-Command tar.exe -ErrorAction SilentlyContinue

if ($tar) {
    # bsdtar пишет корректные пути с "/"
    Push-Location $dist
    try {
        & $tar.Source -a -c -f "$slug.zip" $slug
        if ($LASTEXITCODE -ne 0) { throw "tar завершился с кодом $LASTEXITCODE" }
    } finally {
        Pop-Location
    }
} else {
    Compress-Archive -Path $stage -DestinationPath $zip -CompressionLevel Optimal
    # Правим имена записей: "\" -> "/"
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $tmp = "$zip.tmp"
    $source = [System.IO.Compression.ZipFile]::OpenRead($zip)
    $target = [System.IO.Compression.ZipFile]::Open($tmp, 'Create')
    try {
        foreach ($entry in $source.Entries) {
            $name = $entry.FullName.Replace('\', '/')
            $new = $target.CreateEntry($name, [System.IO.Compression.CompressionLevel]::Optimal)
            $out = $new.Open()
            try {
                $in = $entry.Open()
                $in.CopyTo($out)
                $in.Dispose()
            } finally {
                $out.Dispose()
            }
        }
    } finally {
        $source.Dispose()
        $target.Dispose()
    }
    Move-Item $tmp $zip -Force
}

Remove-Item $stage -Recurse -Force

Write-Host "Собрано: $zip"
