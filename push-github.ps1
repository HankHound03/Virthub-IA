param(
    [string]$CommitMessage = 'Add Windows installation script'
)

$ErrorActionPreference = 'Stop'

function Assert-Command {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Name
    )

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "No se encontro '$Name' en el sistema. Instala Git for Windows primero: https://git-scm.com/download/win"
    }
}

Assert-Command -Name 'git'

Write-Host 'Repositorio:' -ForegroundColor Cyan
Write-Host "  $(Get-Location)"
Write-Host ''
Write-Host 'Estado actual:' -ForegroundColor Cyan
git status --short
Write-Host ''

Write-Host 'Agregando cambios al staging...' -ForegroundColor Yellow
git add -A

Write-Host 'Creando commit...' -ForegroundColor Yellow
git commit -m $CommitMessage

Write-Host 'Enviando a GitHub...' -ForegroundColor Yellow
git push origin main

Write-Host ''
Write-Host 'Listo. Cambios enviados a GitHub.' -ForegroundColor Green
