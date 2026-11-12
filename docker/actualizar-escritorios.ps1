<#
.SYNOPSIS
    Actualiza los escritorios Webtop y las aplicaciones instaladas dentro.

.DESCRIPTION
    Hace tres cosas, en este orden:

      1. Respalda la imagen actual de cada escritorio (docker commit).
      2. Descarga la imagen base nueva de Webtop y recrea ct0.
      3. Actualiza los paquetes de dentro (apt) y, si el escritorio usa
         proot-distro, tambien cada distribucion instalada por proot.

    No toca NINGUN dato: ni /config (el home de cada escritorio), ni los
    volumenes, ni la base de datos de Virthub.

.PARAMETER Escritorios
    Que escritorios actualizar. Por defecto, todos los del compose
    (ct0, ct2, ct3, ct4, ct5, ct6, ct7).

.PARAMETER SoloPaquetes
    Solo actualiza lo de dentro de los contenedores. No descarga imagenes ni
    recrea contenedores: no se pierde la sesion de nadie.

.PARAMETER SoloImagenes
    Solo descarga la imagen base y recrea ct0. No instala nada por dentro.

.PARAMETER Clonar
    Despues de actualizar ct0, vuelve a congelar la imagen de los clones
    (virthub-desktop:clon) y recrea ct2 a ct7 con ella.

.PARAMETER Si
    No pide confirmacion.

.EXAMPLE
    .\actualizar-escritorios.ps1
    Actualizacion completa, preguntando antes.

.EXAMPLE
    .\actualizar-escritorios.ps1 -SoloPaquetes -Si
    Solo apt upgrade dentro de los contenedores, sin preguntar y sin cortar
    la sesion de los escritorios.

.NOTES
    Se ejecuta desde la carpeta del proyecto (donde esta docker-compose.yml).
    Requiere Docker en marcha.
#>

[CmdletBinding()]
param(
    [string[]] $Escritorios = @('ct0', 'ct2', 'ct3', 'ct4', 'ct5', 'ct6', 'ct7'),

    [switch] $SoloPaquetes,
    [switch] $SoloImagenes,
    [switch] $Clonar,
    [switch] $Si
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# ---------------------------------------------------------------------------
# Ajustes
# ---------------------------------------------------------------------------

$Proyecto = 'virthub'
$ImagenBase = 'lscr.io/linuxserver/webtop:ubuntu-mate'
$ImagenClon = 'virthub-desktop:clon'
$Sello = Get-Date -Format 'yyyyMMdd-HHmmss'

# La raiz del proyecto se busca subiendo desde donde esta el script, asi se puede
# ejecutar tanto desde la raiz como desde la carpeta docker.
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

function Existe-Contenedor {
    param([string] $Nombre)

    $salida = docker ps -a --filter ("name=^/" + $Nombre + '$') --format '{{.Names}}' 2>$null

    return ($salida -contains $Nombre)
}

function Esta-Arriba {
    param([string] $Nombre)

    $salida = docker ps --filter ("name=^/" + $Nombre + '$') --format '{{.Names}}' 2>$null

    return ($salida -contains $Nombre)
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

Write-Host '  Se van a actualizar estos escritorios:' -ForegroundColor Cyan
foreach ($ct in $Escritorios) {
    $estado = if (Esta-Arriba $ct) { 'arriba' } elseif (Existe-Contenedor $ct) { 'parado' } else { 'no existe' }
    Write-Host ("    {0,-5} {1}" -f $ct, $estado) -ForegroundColor Gray
}

Write-Host ''
Write-Host '  Que hace y que NO hace:' -ForegroundColor Cyan
Write-Host '    - Respalda la imagen actual de cada escritorio (docker commit).' -ForegroundColor Gray
Write-Host '    - Actualiza paquetes dentro (apt) y, si hay, cada distro de proot.' -ForegroundColor Gray
Write-Host '    - NO toca /config: el home, los ajustes y las apps del usuario siguen.' -ForegroundColor Gray
Write-Host '    - NO toca volumenes, ni la base de datos, ni Virthub.' -ForegroundColor Gray

if (-not $SoloPaquetes) {
    Write-Host ''
    Write-Host '  Aviso: recrear un escritorio cierra su sesion. Quien este trabajando' -ForegroundColor Yellow
    Write-Host '         dentro perdera lo que no haya guardado.' -ForegroundColor Yellow
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
# 1. Respaldo de la imagen de cada escritorio
# ---------------------------------------------------------------------------

Escribir-Guion '1. Respaldo de las imagenes actuales' 'Cyan'

foreach ($ct in $Escritorios) {
    if (-not (Esta-Arriba $ct)) {
        Escribir-Guion ("   {0}: no esta en marcha, se omite el respaldo" -f $ct) 'DarkGray'
        continue
    }

    $etiqueta = "virthub-desktop:respaldo-{0}-{1}" -f $ct, $Sello
    $codigo = Ejecutar @('docker', 'commit', ("virthub-{0}-1" -f $ct), $etiqueta) ("   {0}: respaldando en {1}" -f $ct, $etiqueta)

    if ($codigo -ne 0) {
        throw ("No se pudo respaldar {0}. Se detiene para no dejar nada a medias." -f $ct)
    }
}

# ---------------------------------------------------------------------------
# 2. Imagen base nueva y recreacion de ct0
# ---------------------------------------------------------------------------

if (-not $SoloPaquetes) {
    Escribir-Guion '2. Imagen base de Webtop' 'Cyan'

    $codigo = Ejecutar @('docker', 'pull', $ImagenBase) ("   descargando {0}" -f $ImagenBase)

    if ($codigo -ne 0) {
        throw 'No se pudo descargar la imagen base. Revisa la conexion o si el registro responde.'
    }

    if (-not $SoloImagenes) {
        Escribir-Guion '   ct0 se recrea con la imagen nueva' 'DarkGray'
    }

    $codigo = Ejecutar @('docker', 'compose', '-p', $Proyecto, '-f', $Compose, 'up', '-d', '--force-recreate', 'ct0') '   recreando ct0'

    if ($codigo -ne 0) {
        throw 'No se pudo recrear ct0.'
    }

    Write-Host ''
    Start-Sleep -Seconds 15
}

# ---------------------------------------------------------------------------
# 3. Actualizacion de paquetes dentro de cada escritorio
# ---------------------------------------------------------------------------

if (-not $SoloImagenes) {
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
        $contenedor = "virthub-{0}-1" -f $ct

        if (-not (Esta-Arriba $ct)) {
            Escribir-Guion ("   {0}: no esta en marcha, se omite" -f $ct) 'DarkGray'
            continue
        }

        # apt dentro del contenedor (Ubuntu Mate)
        Ejecutar @('docker', 'exec', $contenedor, 'bash', '-lc', $guionApt) ("   {0}: apt update + upgrade" -f $ct) | Out-Null

        # proot-distro, si el escritorio lo usa
        $hayProot = docker exec $contenedor bash -lc "command -v proot-distro >/dev/null 2>&1 && echo si || echo no" 2>$null

        if (($hayProot | Select-Object -Last 1) -eq 'si') {
            $distros = docker exec $contenedor bash -lc "proot-distro list --installed 2>/dev/null | awk -F': ' '/^[a-z]/ {print \$1}'" 2>$null

            foreach ($distro in ($distros | Where-Object { $_ -and $_.Trim() -ne '' })) {
                $nombre = $distro.Trim()
                $guionProot = "proot-distro login {0} -- sh -c 'export DEBIAN_FRONTEND=noninteractive; apt-get update -qq && apt-get -y upgrade && apt-get -y clean' || true" -f $nombre

                Ejecutar @('docker', 'exec', $contenedor, 'bash', '-lc', $guionProot) ("   {0}: proot {1} actualizado" -f $ct, $nombre) | Out-Null
            }

            if (-not $distros) {
                Escribir-Guion ("   {0}: proot-distro presente pero sin distros instaladas" -f $ct) 'DarkGray'
            }
        }
    }
}

# ---------------------------------------------------------------------------
# 4. Congelar la imagen y recrear los clones
# ---------------------------------------------------------------------------

if ($Clonar -and -not $SoloPaquetes) {
    Escribir-Guion '4. Congelando ct0 para los clones' 'Cyan'

    $codigo = Ejecutar @('docker', 'commit', 'virthub-ct0-1', $ImagenClon) ("   ct0 -> {0}" -f $ImagenClon)

    if ($codigo -ne 0) {
        throw 'No se pudo congelar la imagen de los clones.'
    }

    $clones = $Escritorios | Where-Object { $_ -ne 'ct0' }

    if ($clones.Count -gt 0) {
        $argumentos = @('docker', 'compose', '-p', $Proyecto, '-f', $Compose, 'up', '-d', '--force-recreate') + $clones
        Ejecutar $argumentos ("   recreando {0}" -f ($clones -join ', ')) | Out-Null
    }
}

# ---------------------------------------------------------------------------
# Resumen
# ---------------------------------------------------------------------------

Write-Host ''
Escribir-Guion 'Resumen' 'Cyan'

foreach ($ct in $Escritorios) {
    $estado = if (Esta-Arriba $ct) { 'arriba' } else { 'parado' }
    Escribir-Guion ("   {0,-5} {1}" -f $ct, $estado) 'Gray'
}

Write-Host ''
Write-Host ("  Registro: {0}" -f $Registro) -ForegroundColor DarkGray
Write-Host ''
Write-Host '  Si algo va mal, se puede volver atras:' -ForegroundColor Yellow
Write-Host ("    docker image ls | Select-String 'respaldo-{0}'" -f $Sello) -ForegroundColor Gray
Write-Host "    docker commit <contenedor> <imagen-de-respaldo>   # o recrear con la imagen de respaldo" -ForegroundColor Gray
Write-Host ''
Write-Host '  Recuerda: /config no se ha tocado. Ahí siguen el home, los ajustes y las apps del usuario.' -ForegroundColor Green
Write-Host ''
