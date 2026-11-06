# Собирает устанавливаемый архив плагина в dist/wp-smsn1-ru.zip (Windows)
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$slug  = 'wp-smsn1-ru'
$files = @('wp-smsn1-ru.php', 'includes', 'templates', 'assets', 'readme.txt', 'LICENSE')

$dist = Join-Path $root 'dist'
if (Test-Path $dist) { Remove-Item $dist -Recurse -Force }
$stage = Join-Path $dist $slug
New-Item -ItemType Directory -Path $stage -Force | Out-Null

foreach ($f in $files) {
    Copy-Item -Path (Join-Path $root $f) -Destination $stage -Recurse -Force
}

$zip = Join-Path $dist "$slug.zip"
# Важно: архивируем саму папку, иначе WordPress распакует файлы в корень plugins/
Compress-Archive -Path $stage -DestinationPath $zip -CompressionLevel Optimal
Remove-Item $stage -Recurse -Force

Write-Host "Собрано: $zip"
