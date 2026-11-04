@echo off
REM ============================================================================
REM  Puente de GPU para el monitor del panel de IA
REM ============================================================================
REM
REM  Arranca tools\gpu-bridge.ps1 sin tener que pelearse con la politica de
REM  ejecucion de PowerShell.
REM
REM  Windows firma los .ps1 que descarga, pero este lo escribimos nosotros: para
REM  RemoteSigned eso lo deja como "no firmado" y se niega a ejecutarlo. La
REM  solucion limpia es saltarse la politica solo para esta ventana, que es lo
REM  que hace la linea de abajo: -ExecutionPolicy Bypass afecta a este proceso y
REM  no cambia la configuracion del equipo.
REM
REM  Uso: doble clic, o desde PowerShell:  .\tools\gpu-bridge.cmd
REM ============================================================================

setlocal
cd /d "%~dp0.."

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0gpu-bridge.ps1" %*

echo.
echo El puente se detuvo. El panel de IA ocultara los datos de la GPU.
pause
