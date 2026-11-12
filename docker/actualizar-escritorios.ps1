<#
.SYNOPSIS
    Actualiza los escritorios Webtop y las aplicaciones instaladas dentro.

.DESCRIPTION
    Por defecto NO borra nada: respalda cada escritorio y actualiza los paquetes
    que ya tiene dentro del contenedor.

    Ojo con "actualizar la imagen base": los escritorios tienen aplicaciones
    instaladas DENTRO del contenedor (VS Code vive en /usr/bin/code, no en la
    imagen de Webtop). Recrear ct0 desde la imagen base lo deja sin esas
    aplicaciones. Por eso esa parte es opcional (-ActualizarImagenBase) y avisa
    antes de hacerla.

    Que NO se toca nunca: /config (el home de cada persona), los volumenes, la
    base de datos ni Virthub.

.PARAMETER Escritorios
    Que escritorios actualizar. Por defecto, todos los del compose
    (ct0, ct2, ct3, ct4, ct5, ct6, ct7).

.PARAMETER ActualizarImagenBase
    Descarga la imagen nueva de Webtop y recrea ct0 con ella.

    ESTO BORRA las aplicaciones instaladas dentro de ct0 (VS Code, Packet Tracer
    y lo que haya). El respaldo permite volver atras, pero hay que reinstalarlas.
    Los clones (ct2 a ct7) NO se tocan: siguen con su imagen congelada.

.PARAMETER Clonar
    Vuelve a congelar ct0 como virthub-desktop:clon y recrea ct2 a ct7 con ella.
    Util despues de actualizar paquetes en ct0 para que los clones hereden.

.PARAMETER Si
    No pide confirmacion.

.EXAMPLE
    .\actualizar-escritorios.ps1
    Respaldo + actualizacion de paquetes dentro. Nada se borra.

.EXAMPLE
    .\actualizar-escritorios.ps1 -Clonar
    Ademas, congela ct0 y recrea los clones con esa imagen.

.EXAMPLE
    .\actualizar-escritorios.ps1 -ActualizarImagenBase
    Trae la imagen nueva de Webtop y recrea ct0. Pierde las apps de ct0.

.NOTES
    Se ejecuta desde cualquier carpeta del proyecto: el script busca la raiz.
    Requiere Docker en marcha.
#>

[CmdletBinding()]
param(
    [string[]] $Escritorios = @('ct0', 'ct2', 'ct3', 'ct4', 'ct5', 'ct6', 'ct7'),

    [switch] $ActualizarImagenBase,
    [switch] $Clonar,
    [switch] $Si
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# ---------------------------------------------------------------------------
# Ajustes y raiz del proyecto
# ---------------------------------------------------------------------------

$Proyecto = 'virthub'
$ImagenBase = 'lscr.io/linuxserver/webtop:ubuntu-mate'
$ImagenClon = 'virthub-desktop:clon'
$Sello = Get-Date -Format 'yyyyMMdd-HHmmss'

# La raiz se busca subiendo desde donde esta el script: asi funciona tanto desde
# la raiz del proyecto como desde la carpeta docker.
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
$Registro = Join-Path $Raiz ("docker\actualizar-escritorios-{0}.log" -f $Sello)

# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------

function Escribir-Guion {
    param([string] $Texto, [string] $Color = 'Gray')

    $linea = "[{0}] {1}" -f (Get-Date -Format 'HH:mm:ss'), $Texto
    Write-Host $linea -ForegroundColor $Color
    Add-Content -Path $Registro -Value $linea -Encoding UTF8
}

function Ejecutar {
    param([string[]] $Comando, [string] $Que)

    Escribir-Guion $Que
    & $Comando[0] $Comando[1..($Comando.Count - 1)] 2>&1 | ForEach-Object {
        Add-Content -Path $Registro -Value $_ -Encoding UTF8
    }

    return $LASTEXITCODE
}

function Nombre-Contenedor {
    param([string] $Ct)

    # El compose crea "virthub-ct0-1", no "ct0".
    return ("virthub-{0}-1" -f $Ct)
}

function Estado-Contenedor {
    param([string] $Ct)

    $nombre = Nombre-Contenedor $Ct
    $estado = docker inspect --format '{{.State.Status}}' $nombre 2>$null

    if ($LASTEXITCODE -ne 0 -or -not $estado) {
        return 'no existe'
    }

    return ($estado | Select-Object -First 1).Trim()
}

function Estado-Legible {
    param([string] $Ct)

    switch (Estado-Contenedor $Ct) {
        'running' { return 'arriba' }
        'exited' { return 'parado' }
        'paused' { return 'en pausa' }
        'created' { return 'sin arrancar' }
        default { return 'no existe' }
    }
}

function Esta-Arriba {
    param([string] $Ct)

    return ((Estado-Contenedor $Ct) -eq 'running')
}

# ---------------------------------------------------------------------------
# Comprobaciones previas
# ---------------------------------------------------------------------------

if (-not (Test-Path $Compose)) {
    throw "No encuentro $Compose en $Raiz."
}

New-Item -ItemType Directory -Force -Path (Split-Path $Registro) | Out-Null

Ejecutar @('docker', 'version', '--format', '{{.Server.Version}}') 'Comprobando que Docker responde' | Out-Null
Write-Host ''

Write-Host '  Escritorios:' -ForegroundColor Cyan
foreach ($ct in $Escritorios) {
    Write-Host ("    {0,-5} {1}" -f $ct, (Estado-Legible $ct)) -ForegroundColor Gray
}

Write-Host ''
Write-Host '  Que NO se toca:' -ForegroundColor Cyan
Write-Host '    /config  ->  el home, los ajustes y los archivos de cada persona' -ForegroundColor Gray
Write-Host '    volumenes, base de datos y Virthub' -ForegroundColor Gray

Write-Host ''
if ($ActualizarImagenBase) {
    Write-Host '  ATENCION: -ActualizarImagenBase recrea ct0 desde la imagen de Webtop.' -ForegroundColor Red
    Write-Host '            Las aplicaciones instaladas DENTRO de ct0 se pierden' -ForegroundColor Red
    Write-Host '            (VS Code en /usr/bin/code, Packet Tracer...). Hay respaldo, pero' -ForegroundColor Red
    Write-Host '            habra que reinstalarlas. Los clones no se tocan.' -ForegroundColor Red
} else {
    Write-Host '  Modo normal: respaldo + actualizar paquetes dentro. No se recrea nada,' -ForegroundColor Green
    Write-Host '  asi que no se pierde ninguna aplicacion ni sesion.' -ForegroundColor Green
}

if ($Clonar) {
    Write-Host '  Ademas: ct0 se congela como imagen de los clones y se recrean ct2 a ct7.' -ForegroundColor Yellow
}

if (-not $Si) {
    Write-Host ''
    $respuesta = Read-Host '  Continuar? (s/N)'

    if ($respuesta -notmatch '^[sSyY]') {
        Escribir-Guion 'Cancelado por quien ejecuta.' 'Yellow'
        return
    }
}

Write-Host ''

# ---------------------------------------------------------------------------
# 1. Respaldo de cada escritorio
# ---------------------------------------------------------------------------

Escribir-Guion '1. Respaldo de las imagenes actuales' 'Cyan'

foreach ($ct in $Escritorios) {
    if (-not (Esta-Arriba $ct)) {
        Escribir-Guion ("   {0}: no esta en marcha, se omite el respaldo" -f $ct) 'DarkGray'
        continue
    }

    $etiqueta = "virthub-desktop:respaldo-{0}-{1}" -f $ct, $Sello
    $codigo = Ejecutar @('docker', 'commit', (Nombre-Contenedor $ct), $etiqueta) ("   {0}: respaldando en {1}" -f $ct, $etiqueta)

    if ($codigo -ne 0) {
        throw ("No se pudo respaldar {0}. Se detiene para no dejar nada a medias." -f $ct)
    }
}

# ---------------------------------------------------------------------------
# 2. Imagen base nueva (opcional y con aviso)
# ---------------------------------------------------------------------------

if ($ActualizarImagenBase) {
    Escribir-Guion '2. Imagen base de Webtop' 'Cyan'

    $codigo = Ejecutar @('docker', 'pull', $ImagenBase) ("   descargando {0}" -f $ImagenBase)

    if ($codigo -ne 0) {
        throw 'No se pudo descargar la imagen base. Revisa la conexion o si el registro responde.'
    }

    $codigo = Ejecutar @('docker', 'compose', '-p', $Proyecto, '-f', $Compose, 'up', '-d', '--force-recreate', 'ct0') '   recreando ct0 con la imagen nueva'

    if ($codigo -ne 0) {
        throw 'No se pudo recrear ct0.'
    }

    Escribir-Guion '   ct0 recreado. Sus apps de dentro ya no estan: reinstalalas si las usas.' 'Yellow'
    Write-Host ''
    Start-Sleep -Seconds 15
}

# ---------------------------------------------------------------------------
# 3. Paquetes dentro de cada escritorio
# ---------------------------------------------------------------------------

Escribir-Guion '3. Paquetes dentro de cada escritorio' 'Cyan'

$guionApt = @'
set -e
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get -y -o Dpkg::Options::=--force-confold -o Dpkg::Options::=--force-confdef upgrade
apt-get -y autoremove
apt-get -y clean
'@

foreach ($ct in $Escritorios) {
    $contenedor = Nombre-Contenedor $ct

    if (-not (Esta-Arriba $ct)) {
        Escribir-Guion ("   {0}: no esta en marcha, se omite" -f $ct) 'DarkGray'
        continue
    }

    Ejecutar @('docker', 'exec', $contenedor, 'bash', '-lc', $guionApt) ("   {0}: apt update + upgrade" -f $ct) | Out-Null

    # proot-distro, si ese escritorio lo usa
    $hayProot = docker exec $contenedor bash -lc "command -v proot-distro >/dev/null 2>&1 && echo si || echo no" 2>$null

    if (($hayProot | Select-Object -Last 1) -eq 'si') {
        $distros = docker exec $contenedor bash -lc "proot-distro list --installed 2>/dev/null | awk -F': ' '/^[a-z]/ {print \$1}'" 2>$null
        $lista = @($distros | Where-Object { $_ -and $_.Trim() -ne '' })

        foreach ($distro in $lista) {
            $nombre = $distro.Trim()
            $guionProot = "proot-distro login {0} -- sh -c 'export DEBIAN_FRONTEND=noninteractive; apt-get update -qq && apt-get -y upgrade && apt-get -y clean' || true" -f $nombre

            Ejecutar @('docker', 'exec', $contenedor, 'bash', '-lc', $guionProot) ("   {0}: proot {1} actualizado" -f $ct, $nombre) | Out-Null
        }

        if ($lista.Count -eq 0) {
            Escribir-Guion ("   {0}: proot-distro presente pero sin distros instaladas" -f $ct) 'DarkGray'
        }
    }
}

# ---------------------------------------------------------------------------
# 4. Congelar ct0 y recrear los clones
# ---------------------------------------------------------------------------

if ($Clonar) {
    Escribir-Guion '4. Congelando ct0 para los clones' 'Cyan'

    if (-not (Esta-Arriba 'ct0')) {
        Escribir-Guion '   ct0 no esta en marcha: no se puede congelar' 'Yellow'
    } else {
        $codigo = Ejecutar @('docker', 'commit', (Nombre-Contenedor 'ct0'), $ImagenClon) ("   ct0 -> {0}" -f $ImagenClon)

        if ($codigo -ne 0) {
            throw 'No se pudo congelar la imagen de los clones.'
        }

        $clones = @($Escritorios | Where-Object { $_ -ne 'ct0' })

        if ($clones.Count -gt 0) {
            $argumentos = @('docker', 'compose', '-p', $Proyecto, '-f', $Compose, 'up', '-d', '--force-recreate') + $clones
            Ejecutar $argumentos ("   recreando {0}" -f ($clones -join ', ')) | Out-Null
        }
    }
}

# ---------------------------------------------------------------------------
# Resumen
# ---------------------------------------------------------------------------

Write-Host ''
Escribir-Guion 'Resumen' 'Cyan'

foreach ($ct in $Escritorios) {
    Escribir-Guion ("   {0,-5} {1}" -f $ct, (Estado-Legible $ct)) 'Gray'
}

Write-Host ''
Write-Host ("  Registro: {0}" -f $Registro) -ForegroundColor DarkGray
Write-Host ''
Write-Host '  Volver atras con el respaldo:' -ForegroundColor Yellow
Write-Host ("    docker image ls | Select-String 'respaldo-{0}'" -f $Sello) -ForegroundColor Gray
Write-Host '    docker tag virthub-desktop:respaldo-ct0-<fecha> virthub-desktop:clon   # y recrear' -ForegroundColor Gray
Write-Host ''
Write-Host '  /config no se ha tocado: ahi siguen el home, los ajustes y los archivos de cada persona.' -ForegroundColor Green
Write-Host ''
