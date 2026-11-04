# =============================================================================
# Arranque automatico del puente de GPU (sin administrador)
# =============================================================================
#
# Crea un acceso directo en la carpeta de Inicio del usuario. Windows ejecuta lo
# que hay ahi al iniciar sesion, sin ventanas y sin pedir permisos.
#
#   powershell -ExecutionPolicy Bypass -File tools\instalar-en-inicio.ps1
#
# Para quitarlo:
#
#   powershell -ExecutionPolicy Bypass -File tools\instalar-en-inicio.ps1 -Quitar
#
# No toca nada del sistema: es un archivo en tu carpeta de usuario, y se borra al
# ejecutar -Quitar.
# =============================================================================

param(
    [switch]$Quitar
)

$ErrorActionPreference = 'Stop'

$nombre = 'Virthub GPU.lnk'
$inicio = [Environment]::GetFolderPath('Startup')
$destino = Join-Path $inicio $nombre
$vbs = Join-Path $PSScriptRoot 'gpu-bridge-oculto.vbs'

if ($Quitar) {
    if (Test-Path $destino) {
        Remove-Item $destino -Force
        Write-Host "Acceso quitado de Inicio." -ForegroundColor Green
    } else {
        Write-Host "No estaba instalado en Inicio." -ForegroundColor Yellow
    }
    exit 0
}

if (-not (Test-Path $vbs)) {
    Write-Host "No se encontro $vbs" -ForegroundColor Red
    exit 1
}

$shell = New-Object -ComObject WScript.Shell
$acceso = $shell.CreateShortcut($destino)

# wscript + el .vbs: sin ventana y sin consola parpadeando
$acceso.TargetPath = Join-Path $env:WINDIR 'System32\wscript.exe'
$acceso.Arguments = "`"$vbs`""
$acceso.WorkingDirectory = Split-Path $PSScriptRoot -Parent
$acceso.WindowStyle = 7
$acceso.Description = 'Puente de GPU de Virthub: lee nvidia-smi y deja las cifras para el panel de IA. No abre puertos.'
$acceso.Save()

Write-Host "Acceso creado en:" -ForegroundColor Green
Write-Host "  $destino"
Write-Host ""
Write-Host "A partir del proximo inicio de sesion, el puente arrancara solo."
Write-Host ""
Write-Host "Para probarlo ahora, sin reiniciar:"
Write-Host "  wscript `"$vbs`""
Write-Host ""
Write-Host "Para quitarlo:"
Write-Host "  powershell -ExecutionPolicy Bypass -File tools\instalar-en-inicio.ps1 -Quitar"
