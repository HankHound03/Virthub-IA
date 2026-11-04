# =============================================================================
# Arranque automatico del puente de GPU
# =============================================================================
#
# Crea una tarea programada de Windows que lanza el puente al iniciar sesion, sin
# ventana y sin abrir ningun puerto.
#
#   powershell -ExecutionPolicy Bypass -File tools\instalar-arranque-automatico.ps1
#
# Para quitarla:
#
#   powershell -ExecutionPolicy Bypass -File tools\instalar-arranque-automatico.ps1 -Quitar
#
# La tarea se llama "Virthub GPU" y se puede ver en el Programador de tareas.
# =============================================================================

param(
    [switch]$Quitar
)

$ErrorActionPreference = 'Stop'

$nombreTarea = 'Virthub GPU'
$vbs = Join-Path $PSScriptRoot 'gpu-bridge-oculto.vbs'

if ($Quitar) {
    $existente = Get-ScheduledTask -TaskName $nombreTarea -ErrorAction SilentlyContinue

    if (-not $existente) {
        Write-Host "La tarea '$nombreTarea' no existe. Nada que quitar." -ForegroundColor Yellow
        exit 0
    }

    Unregister-ScheduledTask -TaskName $nombreTarea -Confirm:$false
    Write-Host "Tarea '$nombreTarea' eliminada." -ForegroundColor Green
    exit 0
}

if (-not (Test-Path $vbs)) {
    Write-Host "No se encontro $vbs" -ForegroundColor Red
    exit 1
}

# Accion: wscript sin ventana
$accion = New-ScheduledTaskAction -Execute 'wscript.exe' -Argument "`"$vbs`""

# Disparador: al iniciar sesion
$disparador = New-ScheduledTaskTrigger -AtLogOn

# Sin ventana, sin limite de tiempo, y sin que Windows la frene por bateria
$ajustes = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -StartWhenAvailable `
    -Hidden

$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited

$existente = Get-ScheduledTask -TaskName $nombreTarea -ErrorAction SilentlyContinue

if ($existente) {
    Unregister-ScheduledTask -TaskName $nombreTarea -Confirm:$false
    Write-Host "Tarea anterior reemplazada." -ForegroundColor Yellow
}

Register-ScheduledTask `
    -TaskName $nombreTarea `
    -Action $accion `
    -Trigger $disparador `
    -Settings $ajustes `
    -Principal $principal `
    -Description 'Lee nvidia-smi y deja las cifras en storage/app/gpu/gpu.json para el panel de IA de Virthub. No abre ningun puerto.' | Out-Null

Write-Host "Tarea '$nombreTarea' creada." -ForegroundColor Green
Write-Host "Arrancara sola al iniciar sesion, sin ventana y sin abrir puertos."
Write-Host ""
Write-Host "Para arrancarla ahora mismo, sin reiniciar:"
Write-Host "  Start-ScheduledTask -TaskName '$nombreTarea'"
Write-Host ""
Write-Host "Para quitarla:"
Write-Host "  powershell -ExecutionPolicy Bypass -File tools\instalar-arranque-automatico.ps1 -Quitar"
