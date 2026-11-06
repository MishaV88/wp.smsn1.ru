# Настройка репозитория на GitHub (описание, topics, защита ветки, релиз).
# Требует выполненного `gh auth login` (см. README/RUNBOOK в ответе ассистента).
#
# Пример:
#   pwsh -File scripts/setup-github.ps1
#   pwsh -File scripts/setup-github.ps1 -CreateRelease

[CmdletBinding()]
param(
    [string] $Repo   = 'MishaV88/wp.smsn1.ru',
    [string] $Branch = 'main',
    [switch] $CreateRelease
)

$ErrorActionPreference = 'Stop'
$env:GH_PAGER = ''
$env:GH_NO_UPDATE_NOTIFIER = '1'

$gh = $null
$ghCommand = Get-Command gh -ErrorAction SilentlyContinue
if ($ghCommand) { $gh = $ghCommand.Source }
if (-not $gh) {
    $candidate = 'C:\Program Files\GitHub CLI\gh.exe'
    if (Test-Path $candidate) { $gh = $candidate } else { throw 'GitHub CLI (gh) не найден в PATH.' }
}

function Invoke-Gh {
    param([string[]] $Arguments)
    & $gh @Arguments
    if ($LASTEXITCODE -ne 0) { throw "gh $($Arguments -join ' ') завершился с кодом $LASTEXITCODE" }
}

Write-Host '== Проверка авторизации ==' -ForegroundColor Cyan
if ($env:GH_TOKEN) {
    Write-Host 'Используется токен из переменной GH_TOKEN.' -ForegroundColor Yellow
} else {
    & $gh auth status
    if ($LASTEXITCODE -ne 0) {
        throw 'Нет авторизации. Выполните: gh auth login --hostname github.com --git-protocol https --web'
    }

    Write-Host '== Настройка git credential helper на gh ==' -ForegroundColor Cyan
    try {
        Invoke-Gh @('auth', 'setup-git', '--hostname', 'github.com')
    } catch {
        Write-Warning "Не удалось настроить credential helper ($_). Git push придётся выполнять с токеном."
    }
}

Write-Host '== Описание, ссылка, topics ==' -ForegroundColor Cyan
Invoke-Gh @(
    'repo', 'edit', $Repo,
    '--description', 'WordPress-плагин: вход и регистрация по номеру телефона через OTP-код из СМС, SMS-уведомления и интеграция с WooCommerce.',
    '--homepage', 'https://wp.smsn1.ru/',
    '--add-topic', 'wordpress',
    '--add-topic', 'wordpress-plugin',
    '--add-topic', 'sms',
    '--add-topic', 'otp',
    '--add-topic', 'two-factor-authentication',
    '--add-topic', 'woocommerce',
    '--add-topic', 'phone-verification',
    '--add-topic', 'telegram-login',
    '--add-topic', 'vk-id'
)

Write-Host '== Issues включены, Wiki/Projects выключены ==' -ForegroundColor Cyan
Invoke-Gh @('repo', 'edit', $Repo, '--enable-issues', '--disable-wiki', '--disable-projects')

Write-Host "== Защита ветки $Branch ==" -ForegroundColor Cyan
$protection = @{
    required_status_checks          = @{
        strict   = $false
        contexts = @('Build plugin archive', 'PHP 8.3 — syntax check')
    }
    enforce_admins                  = $false      # владелец может пушить напрямую
    required_pull_request_reviews   = $null
    restrictions                    = $null
    allow_force_pushes              = $false
    allow_deletions                 = $false
    required_linear_history         = $false
    required_conversation_resolution = $true
} | ConvertTo-Json -Depth 6

$protection | & $gh api --method PUT "repos/$Repo/branches/$Branch/protection" --input -
if ($LASTEXITCODE -ne 0) { throw 'Не удалось включить защиту ветки.' }

if ($CreateRelease) {
    Write-Host '== Сборка архива и создание релиза ==' -ForegroundColor Cyan
    & pwsh -NoProfile -File (Join-Path $PSScriptRoot '..\bin\build.ps1')

    $header = Get-Content (Join-Path $PSScriptRoot '..\wp-smsn1-ru.php') -Raw
    $version = [regex]::Match($header, '(?m)^\s*\*?\s*Version:\s*([0-9][^\s*]*)').Groups[1].Value
    if (-not $version) { throw 'Не удалось определить версию плагина.' }

    $tag = "v$version"
    $zip = (Resolve-Path (Join-Path $PSScriptRoot '..\dist\wp-smsn1-ru.zip')).Path

    $existing = & $gh release view $tag --repo $Repo 2>$null
    if ($LASTEXITCODE -eq 0) {
        Write-Host "Релиз $tag уже существует — обновляю ассет." -ForegroundColor Yellow
        Invoke-Gh @('release', 'upload', $tag, $zip, '--repo', $Repo, '--clobber')
    } else {
        Invoke-Gh @('release', 'create', $tag, $zip, '--repo', $Repo, '--title', $tag, '--generate-notes')
    }
}

Write-Host "`nГотово. Проверьте: https://github.com/$Repo" -ForegroundColor Green
