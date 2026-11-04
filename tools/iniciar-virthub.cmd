@echo off
REM ============================================================================
REM  Arrancar Virthub completo
REM ============================================================================
REM
REM  Levanta los contenedores y deja el puente de GPU corriendo en segundo plano,
REM  sin abrir ningun puerto.
REM
REM  Uso:
REM    tools\iniciar-virthub.cmd            produccion
REM    tools\iniciar-virthub.cmd test       entorno de pruebas (puerto 8001)
REM  ============================================================================

setlocal
cd /d "%~dp0.."

set ENTORNO=%1
if "%ENTORNO%"=="" set ENTORNO=produccion

if /i "%ENTORNO%"=="test" (
    echo Levantando el entorno de PRUEBAS...
    docker compose -p virthub-test -f docker-compose.test.yml up -d
) else (
    echo Levantando PRODUCCION...
    docker compose -p virthub -f docker-compose.yml --env-file .env up -d
)

echo.
echo Arrancando el puente de GPU en segundo plano...
wscript "%~dp0gpu-bridge-oculto.vbs"

echo.
echo Listo. El panel de IA mostrara los datos de la GPU en unos segundos.
echo Para comprobarlo: storage\app\gpu\gpu.json
echo Para detenerlo:   tools\detener-gpu.cmd
echo.
