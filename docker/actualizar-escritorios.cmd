@echo off
rem ---------------------------------------------------------------------------
rem  Actualiza los escritorios Webtop sin pelearse con la politica de PowerShell.
rem
rem  Uso:
rem     actualizar-escritorios.cmd
rem     actualizar-escritorios.cmd -SoloPaquetes -Si
rem     actualizar-escritorios.cmd -Clonar
rem
rem  Lo unico que hace es llamar al .ps1 con -ExecutionPolicy Bypass para esta
rem  ejecucion: no cambia la politica del sistema.
rem ---------------------------------------------------------------------------

setlocal

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0actualizar-escritorios.ps1" %*

set CODIGO=%ERRORLEVEL%

endlocal & exit /b %CODIGO%
