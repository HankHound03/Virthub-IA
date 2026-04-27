$ErrorActionPreference = 'Stop'

Write-Host 'VirtHub Windows setup' -ForegroundColor Cyan
Write-Host ''
Write-Host 'Software requerido e instalacion:' -ForegroundColor Yellow
Write-Host '  PHP:         https://windows.php.net/download/' -ForegroundColor White
Write-Host '  Composer:    https://getcomposer.org/download/' -ForegroundColor White
Write-Host '  Node.js:     https://nodejs.org/en/download' -ForegroundColor White
Write-Host '  Git:         https://git-scm.com/download/win' -ForegroundColor White
Write-Host '  MySQL:       https://dev.mysql.com/downloads/installer/' -ForegroundColor White
Write-Host ''

function Assert-Command {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Name,
        [Parameter(Mandatory = $true)]
        [string]$InstallHint
    )

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "No se encontro '$Name'. $InstallHint"
    }
}

Assert-Command -Name 'git' -InstallHint 'Instala Git para Windows: https://git-scm.com/download/win'
Assert-Command -Name 'php' -InstallHint 'Instala PHP 8.2+ para Windows: https://windows.php.net/download/ y agrega php.exe al PATH.'
Assert-Command -Name 'composer' -InstallHint 'Instala Composer para Windows: https://getcomposer.org/download/ y agrega composer.bat al PATH.'
Assert-Command -Name 'node' -InstallHint 'Instala Node.js 20+ para Windows: https://nodejs.org/en/download y agrega node.exe al PATH.'
Assert-Command -Name 'npm' -InstallHint 'Instala Node.js para obtener npm: https://nodejs.org/en/download'

if (-not (Test-Path '.env')) {
    if (Test-Path '.env.example') {
        Copy-Item '.env.example' '.env'
        Write-Host 'Archivo .env creado desde .env.example' -ForegroundColor Green
    } else {
        throw 'No se encontro .env.example para crear el archivo .env.'
    }
}

Write-Host 'Instalando dependencias PHP...' -ForegroundColor Yellow
composer install

Write-Host 'Instalando dependencias Node...' -ForegroundColor Yellow
npm install

Write-Host 'Generando APP_KEY si hace falta...' -ForegroundColor Yellow
php artisan key:generate

Write-Host 'Creando enlace de storage...' -ForegroundColor Yellow
php artisan storage:link

Write-Host 'Ejecutando migraciones...' -ForegroundColor Yellow
php artisan migrate --force

Write-Host 'Compilando assets...' -ForegroundColor Yellow
npm run build

Write-Host ''
Write-Host 'Instalacion completada.' -ForegroundColor Green
Write-Host 'Siguientes pasos:' -ForegroundColor Cyan
Write-Host '  php artisan serve' -ForegroundColor White
Write-Host '  npm run dev' -ForegroundColor White
