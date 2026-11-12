<#
.SYNOPSIS
    Borra la configuracion de pantalla y de Selkies de los escritorios.

.DESCRIPTION
    Los escritorios guardan en /config/.config/monitors.xml la resolucion con la
    que se conectaron alguna vez. Si esa resolucion se queda corta (por ejemplo
    1280x720 con una ventana de 1800 px), Selkies la estira y todo se ve enorme:
    iconos, ventanas y aplicaciones.

    Este script, en cada escritorio:

      1. Copia a /config/.config/respaldo-selkies-<fecha>/ lo que va a borrar.
      2. Borra monitors.xml y el estado de Selkies.
      3. Reinicia el escritorio, para que la pantalla se cree de nuevo y Selkies
         negocie el tamano con el navegador.

    Lo que NO toca: documentos, apps instaladas, /config salvo esos dos rincones.

.PARAMETER Escritorios
    Cuales. Por defecto, todos: ct0, ct2, ct3, ct4, ct5, ct6, ct7.

.PARAMETER Si
    No pide confirmacion.

.EXAMPLE
    .\reset-selkies.ps1 -Escritorios ct7 -Si
    Probar en uno solo.

.EXAMPLE
    .\reset-selkies.ps1
    Los siete, preguntando antes.
#>

[CmdletBinding()]
param(
    [string[]] $Escritorios = @('ct0', 'ct2', 'ct3', 'ct4', 'ct5', 'ct6', 'ct7'),
    [switch] $Si
)

$ErrorActionPreference = 'Stop'

# Al llamar con "-File", PowerShell recibe la lista como un solo texto
# ("ct0,ct2,..."). Se normaliza aqui para que valga cualquier forma.
$Escritorios = @(
    $Escritorios |
        ForEach-Object { $_ -split ',' } |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -ne '' }
)

Set-StrictMode -Version Latest

$Proyecto = 'virthub'
$Sello = Get-Date -Format 'yyyyMMdd-HHmmss'

$Raiz = $PSScriptRoot

while ($Raiz -and -not (Test-Path (Join-Path $Raiz 'docker-compose.yml'))) {
    $padre = Split-Path $Raiz -Parent

    if (-not $padre -or $padre -eq $Raiz) {
        $Raiz = $null
        break
    }

    $Raiz = $padre
}

if (-not $Raiz) {
    throw 'No encuentro docker-compose.yml. El script tiene que estar dentro del proyecto.'
}

$Compose = Join-Path $Raiz 'docker-compose.yml'
$Registro = Join-Path $Raiz ("docker\reset-selkies-{0}.log" -f $Sello)

function Escribir-Guion {
    param([string] $Texto, [string] $Color = 'Gray')

    $linea = "[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $Texto
    Write-Host $linea -ForegroundColor $Color
    Add-Content -Path $Registro -Value $linea -Encoding UTF8
}

function Salida-Comando {
    param([string[]] $Comando)

    $temporal = [System.IO.Path]::GetTempFileName()

    $linea = ($Comando | ForEach-Object {
        if ($_ -match '\s') { '"' + ($_ -replace '"', '\"') + '"' } else { $_ }
    }) -join ' '

    cmd /c ($linea + ' > "' + $temporal + '" 2>nul') | Out-Null

    $salida = ''

    if (Test-Path $temporal) {
        $salida = (Get-Content $temporal -Raw -ErrorAction SilentlyContinue)
        Remove-Item $temporal -Force -ErrorAction SilentlyContinue
    }

    return $salida
}

function Nombre-Contenedor {
    param([string] $Ct)

    return ("virthub-{0}-1" -f $Ct)
}

function Estado-Contenedor {
    param([string] $Ct)

    $estado = Salida-Comando @('docker', 'inspect', '--format', '{{.State.Status}}', (Nombre-Contenedor $Ct))

    if (-not $estado) {
        return 'no existe'
    }

    return $estado.Trim()
}

# ---------------------------------------------------------------------------
# Aviso
# ---------------------------------------------------------------------------

Write-Host ''
Write-Host '  Se va a borrar la configuracion de pantalla y de Selkies:' -ForegroundColor Cyan
Write-Host '    /config/.config/monitors.xml' -ForegroundColor Gray
Write-Host '    /config/.local/state/selkies' -ForegroundColor Gray
Write-Host ''
Write-Host '  Antes se copian a /config/.config/respaldo-selkies-<fecha>/, asi que se' -ForegroundColor Gray
Write-Host '  pueden devolver.' -ForegroundColor Gray
Write-Host ''
Write-Host '  Aviso: el escritorio se reinicia, asi que se cierra la sesion de quien' -ForegroundColor Yellow
Write-Host '         este dentro. Los archivos y las apps no se tocan.' -ForegroundColor Yellow
Write-Host ''

foreach ($ct in $Escritorios) {
    Write-Host ("    {0,-5} {1}" -f $ct, (Estado-Contenedor $ct)) -ForegroundColor Gray
}

if (-not $Si) {
    Write-Host ''
    $respuesta = Read-Host '  Continuar? (s/N)'

    if ($respuesta -notmatch '^[sSyY]') {
        Escribir-Guion 'Cancelado por quien ejecuta.' 'Yellow'
        return
    }
}

# ---------------------------------------------------------------------------
# Trabajo
# ---------------------------------------------------------------------------

$guion = @'
mkdir -p /config/.config/respaldo-selkies-RESPALDO
[ -f /config/.config/monitors.xml ] && cp -a /config/.config/monitors.xml /config/.config/respaldo-selkies-RESPALDO/ 2>/dev/null
[ -d /config/.local/state/selkies ] && cp -a /config/.local/state/selkies /config/.config/respaldo-selkies-RESPALDO/ 2>/dev/null
rm -f /config/.config/monitors.xml
rm -rf /config/.local/state/selkies
echo borrado
'@

foreach ($ct in $Escritorios) {
    $contenedor = Nombre-Contenedor $ct

    if ((Estado-Contenedor $ct) -ne 'running') {
        Escribir-Guion ("   {0}: no esta en marcha, se omite" -f $ct) 'DarkGray'
        continue
    }

    $pasos = $guion -replace 'RESPALDO', $Sello
    $pasos = $pasos -replace "`r?`n", '; '

    Escribir-Guion ("   {0}: borrando configuracion (respaldo en /config/.config/respaldo-selkies-{1})" -f $ct, $Sello) 'Cyan'
    $salida = Salida-Comando @('docker', 'exec', $contenedor, 'bash', '-lc', $pasos)
    Add-Content -Path $Registro -Value $salida -Encoding UTF8

    Escribir-Guion ("   {0}: reiniciando el escritorio" -f $ct) 'Cyan'
    docker restart $contenedor 2>&1 | Out-Null

    # Se le da tiempo a levantar el escritorio y Selkies
    Start-Sleep -Seconds 20

    $codigo = Salida-Comando @('docker', 'exec', $contenedor, 'bash', '-lc', 'curl -s -o /dev/null -w "%{http_code}" http://localhost:3000/ 2>/dev/null')

    Escribir-Guion ("   {0}: el escritorio responde {1}" -f $ct, $(if ($codigo) { $codigo.Trim() } else { 'sin respuesta' })) 'Gray'
}

# ---------------------------------------------------------------------------
# Resumen
# ---------------------------------------------------------------------------

Write-Host ''
Escribir-Guion 'Resumen' 'Cyan'

foreach ($ct in $Escritorios) {
    Escribir-Guion ("   {0,-5} {1}" -f $ct, (Estado-Contenedor $ct)) 'Gray'
}

Write-Host ''
Write-Host ("  Registro: {0}" -f $Registro) -ForegroundColor DarkGray
Write-Host ''
Write-Host '  Si hiciera falta volver atras, dentro de cada escritorio:' -ForegroundColor Yellow
Write-Host ("    cp -a /config/.config/respaldo-selkies-{0}/monitors.xml /config/.config/" -f $Sello) -ForegroundColor Gray
Write-Host ''
Write-Host '  Importante: el panel "Configuracion de video/pantalla" de Selkies se guarda' -ForegroundColor Cyan
Write-Host '  en el NAVEGADOR, no en el contenedor. Si sigue saliendo pequeno o estirado,' -ForegroundColor Cyan
Write-Host '  abre ese panel y elige el tamano de la ventana (o borra los datos del sitio).' -ForegroundColor Cyan
Write-Host ''
