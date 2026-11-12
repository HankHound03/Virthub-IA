<#
.SYNOPSIS
    Quita las aplicaciones instaladas por proot-apps (deckcraft, pdfcraft,
    gridcraft, wordcraft) de los escritorios.

.DESCRIPTION
    proot-apps instala cada aplicacion en /config/proot-apps/<imagen> y deja
    cuatro rastros mas: el lanzador, la entrada del menu, el acceso del escritorio
    y su carpeta de datos.

    Este script mueve todo eso a /config/respaldo-craft-<fecha>/ (no lo borra: queda
    ahi por si acaso) y refresca el menu.

    Lo que NO toca: el resto del home, las apps del sistema (VS Code, Packet
    Tracer...), ni los archivos de la persona.

.PARAMETER Escritorios
    Cuales. Por defecto, todos: ct0, ct2, ct3, ct4, ct5, ct6, ct7.

.PARAMETER Si
    No pide confirmacion.

.EXAMPLE
    .\quitar-craft.ps1 -Escritorios ct7 -Si
    Probar en uno solo.

.EXAMPLE
    .\quitar-craft.ps1
    En los siete, preguntando antes.
#>

[CmdletBinding()]
param(
    [string[]] $Escritorios = @('ct0', 'ct2', 'ct3', 'ct4', 'ct5', 'ct6', 'ct7'),
    [switch] $Si
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Escritorios = @(
    $Escritorios |
        ForEach-Object { $_ -split ',' } |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -ne '' }
)

$Sello = Get-Date -Format 'yyyyMMdd-HHmmss'
$Apps = @('deckcraft', 'gridcraft', 'pdfcraft', 'wordcraft')

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

$Registro = Join-Path $Raiz ("docker\quitar-craft-{0}.log" -f $Sello)

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

# Ordenes sueltas, una detras de otra: sin funciones ni bucles, para que quepan
# en una sola linea de shell.
$destino = "/config/respaldo-craft-$Sello"

$rutas = @(
    'proot-apps'
)

foreach ($app in $Apps) {
    $rutas += ".local/bin/$app-pa"
    $rutas += ".local/share/applications/$app-pa.desktop"
    $rutas += "Desktop/$app-pa.desktop"
    $rutas += ".config/$app"
    $rutas += ".local/share/$app"
}

$rutas += '.local/bin/proot-apps'

$ordenes = @("mkdir -p $destino")

foreach ($ruta in $rutas) {
    $ordenes += "if [ -e /config/$ruta ]; then mkdir -p `$(dirname $destino/$ruta); mv /config/$ruta $destino/$ruta; fi"
}

$ordenes += 'command -v update-desktop-database >/dev/null 2>&1 && update-desktop-database /config/.local/share/applications >/dev/null 2>&1'

$guion = ($ordenes -join '; ')

# ---------------------------------------------------------------------------
# Aviso
# ---------------------------------------------------------------------------

Write-Host ''
Write-Host '  Se van a quitar las apps de proot-apps (deckcraft, pdfcraft, gridcraft,' -ForegroundColor Cyan
Write-Host '  wordcraft) de estos escritorios:' -ForegroundColor Cyan
Write-Host ''

foreach ($ct in $Escritorios) {
    Write-Host ("    {0,-5} {1}" -f $ct, (Estado-Contenedor $ct)) -ForegroundColor Gray
}

Write-Host ''
Write-Host '  No se borran: se mueven a /config/respaldo-craft-<fecha>/ dentro de cada' -ForegroundColor Gray
Write-Host '  escritorio, por si hace falta volver atras. No se reinicia nada.' -ForegroundColor Gray

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

foreach ($ct in $Escritorios) {
    $contenedor = Nombre-Contenedor $ct

    if ((Estado-Contenedor $ct) -ne 'running') {
        Escribir-Guion ("   {0}: no esta en marcha, se omite" -f $ct) 'DarkGray'
        continue
    }

    $antes = Salida-Comando @('docker', 'exec', $contenedor, 'bash', '-lc', 'find /config -maxdepth 4 -iname "*craft*-pa*" 2>/dev/null | wc -l')

    Escribir-Guion ("   {0}: quitando apps de proot" -f $ct) 'Cyan'
    $salida = Salida-Comando @('docker', 'exec', $contenedor, 'bash', '-lc', $guion)
    Add-Content -Path $Registro -Value $salida -Encoding UTF8

    $despues = Salida-Comando @('docker', 'exec', $contenedor, 'bash', '-lc', 'find /config -maxdepth 4 -iname "*craft*-pa*" 2>/dev/null | wc -l')

    Escribir-Guion ("   {0}: rastros antes {1}, despues {2} (respaldo en {3})" -f `
        $ct, $(if ($antes) { $antes.Trim() } else { '?' }), $(if ($despues) { $despues.Trim() } else { '?' }), $destino) 'Gray'
}

# ---------------------------------------------------------------------------
# Resumen
# ---------------------------------------------------------------------------

Write-Host ''
Escribir-Guion 'Resumen' 'Cyan'

foreach ($ct in $Escritorios) {
    $queda = Salida-Comando @('docker', 'exec', (Nombre-Contenedor $ct), 'bash', '-lc', 'test -d /config/proot-apps && echo si || echo no')

    Escribir-Guion ("   {0,-5} proot-apps en su sitio: {1}" -f $ct, $(if ($queda) { $queda.Trim() } else { '?' })) 'Gray'
}

Write-Host ''
Write-Host ("  Registro: {0}" -f $Registro) -ForegroundColor DarkGray
Write-Host ''
Write-Host ("  Para devolverlas: dentro del escritorio, mv /config/respaldo-craft-{0}/* /config/" -f $Sello) -ForegroundColor Yellow
Write-Host ''
