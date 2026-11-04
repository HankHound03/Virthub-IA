@echo off
REM ============================================================================
REM  Detener el puente de GPU
REM ============================================================================
REM
REM  Cierra las copias del puente que esten corriendo. Los contenedores siguen
REM  funcionando: el panel de IA solo deja de mostrar los datos de la GPU.
REM ============================================================================

setlocal

echo Buscando el puente de GPU...

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$encontrados = Get-CimInstance Win32_Process -Filter \"Name = 'powershell.exe'\" | Where-Object { $_.CommandLine -like '*gpu-bridge.ps1*' }; if (-not $encontrados) { Write-Host 'No estaba corriendo.'; exit 0 }; foreach ($p in $encontrados) { Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue; Write-Host ('Detenido el proceso ' + $p.ProcessId) }"

echo.
echo Listo. El panel de IA ocultara los datos de la GPU.
