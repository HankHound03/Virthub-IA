<#
.SYNOPSIS
    Respalda y restaura TODO Virthub: base de datos y archivos.

.DESCRIPTION
    Hay dos cosas que se pueden perder y no se recuperan solas:

      1. La base de datos (usuarios, sesiones, foro, asignaciones de escritorio).
         Vive en el volumen mariadb_data. Se respalda con mariadb-dump.
      2. Los archivos (storage/app/data y los adjuntos del foro). Viven en el
         volumen app_storage.

    Este script copia las dos cosas a .tmp\respaldos\<fecha> y sabe devolverlas.
    Comprueba cada paso: si algo falla, aborta en vez de decir que salio bien.

.PARAMETER Proyecto
    virthub-test (por defecto) o virthub para produccion.

.PARAMETER Restaurar
    En vez de copiar, devuelve el respaldo indicado con -Fecha.

.PARAMETER Fecha
    Carpeta del respaldo a restaurar (ej. 20261118-234500). Sin -Fecha, se usa el
    mas reciente.

.PARAMETER Destino
    Donde guardar los respaldos. Por defecto .tmp\respaldos dentro del proyecto.
    Conviene apuntarlo a otro disco: un respaldo en el mismo disco no protege de
    que ese disco falle.

.EXAMPLE
    .\respaldar-datos.ps1 -Proyecto virthub
    Respalda produccion (base de datos + archivos).

.EXAMPLE
    .\respaldar-datos.ps1 -Proyecto virthub -Destino D:\respaldos
    Respalda produccion en otro disco.

.EXAMPLE
    .\respaldar-datos.ps1 -Proyecto virthub -Restaurar
    Devuelve el respaldo mas reciente de produccion.
#>

[CmdletBinding()]
param(
    [ValidateSet('virthub-test', 'virthub')]
    [string] $Proyecto = 'virthub-test',

    [switch] $Restaurar,

    [string] $Fecha = '',

    [string] $Destino = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

# ---------------------------------------------------------------------------
# Rutas y contenedores
# ---------------------------------------------------------------------------

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

$Contenedor = "$Proyecto-app-1"
$BaseDatos = "$Proyecto-mariadb-1"

if ($Destino -eq '') {
    $Destino = Join-Path $Raiz '.tmp\respaldos'
}

# Los datos dentro del contenedor
$RutaDatos = '/var/www/html/storage/app/data'
$RutaAdjuntos = '/var/www/html/storage/app/public/uploads'
$Temporal = '/tmp/vh-respaldo'

# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------

function Escribir {
    param([string] $Texto, [string] $Color = 'Gray')
    Write-Host $Texto -ForegroundColor $Color
}

<#
    Ejecuta un comando y devuelve su salida. Falla de verdad si el comando falla:
    antes se usaba `2>nul` y no se miraba el codigo de salida, asi que un fallo
    de Docker se leia como "no hay nada" y el respaldo se anunciaba como bueno.
#>
function Ejecutar {
    param(
        [Parameter(Mandatory)][string[]] $Comando,
        [switch] $PermitirFallo
    )

    $salida = & $Comando[0] $Comando[1..($Comando.Count - 1)] 2>&1
    $codigo = $LASTEXITCODE

    $texto = ($salida | Where-Object { $_ -notmatch 'Failed to create stream fd' } | Out-String).Trim()

    if ($codigo -ne 0 -and -not $PermitirFallo) {
        throw ("Fallo el comando: {0}`n  codigo: {1}`n  salida: {2}" -f ($Comando -join ' '), $codigo, $texto)
    }

    return [pscustomobject]@{ Codigo = $codigo; Salida = $texto }
}

function Estado-Contenedor {
    param([string] $Nombre)

    $r = Ejecutar -Comando @('docker', 'inspect', '--format', '{{.State.Status}}', $Nombre) -PermitirFallo

    if ($r.Codigo -ne 0) {
        return 'no existe'
    }

    return $r.Salida.Trim()
}

function Leer-Env {
    param([string] $Clave)

    $archivo = Join-Path $Raiz '.env'

    if (-not (Test-Path $archivo)) {
        return ''
    }

    foreach ($linea in Get-Content $archivo) {
        if ($linea -match ('^\s*' + [regex]::Escape($Clave) + '\s*=\s*(.*)$')) {
            return $Matches[1].Trim().Trim('"').Trim("'")
        }
    }

    return ''
}

# ---------------------------------------------------------------------------
# Comprobaciones previas
# ---------------------------------------------------------------------------

$estadoApp = Estado-Contenedor $Contenedor
$estadoDb = Estado-Contenedor $BaseDatos

Escribir ''
Escribir ("  Proyecto:   {0}" -f $Proyecto) -Color Cyan
Escribir ("  App:        {0} ({1})" -f $Contenedor, $estadoApp) -Color Gray
Escribir ("  Base:       {0} ({1})" -f $BaseDatos, $estadoDb) -Color Gray
Escribir ("  Destino:    {0}" -f $Destino) -Color Gray

if ($estadoApp -ne 'running') {
    throw "El contenedor $Contenedor no esta en marcha (estado: $estadoApp)."
}

if ($estadoDb -ne 'running') {
    throw "El contenedor $BaseDatos no esta en marcha (estado: $estadoDb). Sin base no hay respaldo util."
}

# ---------------------------------------------------------------------------
# Restaurar
# ---------------------------------------------------------------------------

if ($Restaurar) {
    if (-not (Test-Path $Destino)) {
        throw "No hay respaldos en $Destino"
    }

    $elegido = if ($Fecha -ne '') {
        Get-Item (Join-Path $Destino $Fecha) -ErrorAction SilentlyContinue
    } else {
        Get-ChildItem $Destino -Directory | Where-Object { $_.Name -match '^\d{8}-\d{6}' } |
            Sort-Object Name -Descending | Select-Object -First 1
    }

    if (-not $elegido) {
        throw 'No encuentro ese respaldo.'
    }

    Escribir ''
    Escribir ("  Restaurando {0}" -f $elegido.Name) -Color Yellow
    Escribir '  Antes se guarda una copia de lo que hay ahora.' -Color Gray

    # 1. Copia del estado actual, bien hecha: se crea el directorio y se comprueba.
    $antes = Join-Path $Destino ((Get-Date -Format 'yyyyMMdd-HHmmss') + '-antes-de-restaurar')
    New-Item -ItemType Directory -Force -Path $antes | Out-Null

    Ejecutar -Comando @('docker', 'exec', $BaseDatos, 'sh', '-c',
        'mkdir -p /tmp/vh-antes && rm -rf /tmp/vh-antes/* && mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" --databases "$MARIADB_DATABASE" > /tmp/vh-antes/base.sql 2>/dev/null') |
        Out-Null

    Ejecutar -Comando @('docker', 'cp', ("{0}:/tmp/vh-antes/base.sql" -f $BaseDatos), (Join-Path $antes 'base.sql')) | Out-Null

    Ejecutar -Comando @('docker', 'exec', $Contenedor, 'sh', '-c', "mkdir -p /tmp/vh-antes-datos && rm -rf /tmp/vh-antes-datos/* && cp -a $RutaDatos/. /tmp/vh-antes-datos/") | Out-Null
    Ejecutar -Comando @('docker', 'cp', ("{0}:/tmp/vh-antes-datos/." -f $Contenedor), (Join-Path $antes 'datos')) | Out-Null

    Escribir ("    copia previa en {0}" -f $antes) -Color DarkGray

    # 2. Base de datos
    $dump = Join-Path $elegido.FullName 'base.sql'

    if (-not (Test-Path $dump)) {
        throw "El respaldo no tiene base.sql: $dump"
    }

    Ejecutar -Comando @('docker', 'cp', $dump, ("{0}:/tmp/restaurar.sql" -f $BaseDatos)) | Out-Null
    Ejecutar -Comando @('docker', 'exec', $BaseDatos, 'sh', '-c',
        'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" < /tmp/restaurar.sql && rm -f /tmp/restaurar.sql') | Out-Null

    Escribir '    base de datos restaurada' -Color Gray

    # 3. Archivos: TODO, incluido workspace/ y los adjuntos (antes solo se copiaban
    #    los archivos del primer nivel y se perdian las subcarpetas).
    $datos = Join-Path $elegido.FullName 'datos'

    if (Test-Path $datos) {
        Ejecutar -Comando @('docker', 'exec', $Contenedor, 'sh', '-c', "mkdir -p $RutaDatos && rm -rf $RutaDatos/*") | Out-Null
        Ejecutar -Comando @('docker', 'cp', ($datos + '/.'), ("{0}:{1}" -f $Contenedor, $RutaDatos)) | Out-Null
        Escribir '    datos restaurados (con subcarpetas)' -Color Gray
    } else {
        Escribir '    aviso: este respaldo no tiene carpeta datos/' -Color Yellow
    }

    $adjuntos = Join-Path $elegido.FullName 'adjuntos'

    if (Test-Path $adjuntos) {
        Ejecutar -Comando @('docker', 'exec', $Contenedor, 'sh', '-c', "mkdir -p $RutaAdjuntos && rm -rf $RutaAdjuntos/*") | Out-Null
        Ejecutar -Comando @('docker', 'cp', ($adjuntos + '/.'), ("{0}:{1}" -f $Contenedor, $RutaAdjuntos)) | Out-Null
        Escribir '    adjuntos restaurados' -Color Gray
    }

    Ejecutar -Comando @('docker', 'exec', $Contenedor, 'sh', '-c', 'php artisan optimize:clear > /dev/null 2>&1; true') -PermitirFallo | Out-Null

    Escribir ''
    Escribir '  Restaurado. Recarga la pagina.' -Color Green
    Escribir ''
    return
}

# ---------------------------------------------------------------------------
# Respaldar
# ---------------------------------------------------------------------------

$sello = Get-Date -Format 'yyyyMMdd-HHmmss'
$carpeta = Join-Path $Destino $sello
New-Item -ItemType Directory -Force -Path $carpeta | Out-Null

Escribir ''

# 1. Base de datos
Escribir '  Base de datos...' -Color Gray

$bd = Leer-Env 'DB_DATABASE'
if ($bd -eq '') { $bd = 'virthub' }

Ejecutar -Comando @('docker', 'exec', $BaseDatos, 'sh', '-c',
    "mkdir -p $Temporal && mariadb-dump -u root -p`"`$MARIADB_ROOT_PASSWORD`" --single-transaction --quick --databases $bd > $Temporal/base.sql") | Out-Null

Ejecutar -Comando @('docker', 'cp', ("{0}:{1}/base.sql" -f $BaseDatos, $Temporal), (Join-Path $carpeta 'base.sql')) | Out-Null

$tamanoBase = [Math]::Round((Get-Item (Join-Path $carpeta 'base.sql')).Length / 1KB, 1)

if ($tamanoBase -lt 1) {
    throw 'El volcado de la base salio vacio. No se da el respaldo por bueno.'
}

Escribir ("    base.sql  {0} KB" -f $tamanoBase) -Color Gray

# 2. Archivos de la aplicacion (datos + adjuntos)
Escribir '  Archivos...' -Color Gray

Ejecutar -Comando @('docker', 'exec', $Contenedor, 'sh', '-c',
    "mkdir -p $Temporal && rm -rf $Temporal/datos $Temporal/adjuntos && cp -a $RutaDatos $Temporal/datos && mkdir -p $Temporal/adjuntos && cp -a $RutaAdjuntos/. $Temporal/adjuntos/ 2>/dev/null || true") | Out-Null

Ejecutar -Comando @('docker', 'cp', ("{0}:{1}/datos/." -f $Contenedor, $Temporal), (Join-Path $carpeta 'datos')) | Out-Null
Ejecutar -Comando @('docker', 'cp', ("{0}:{1}/adjuntos/." -f $Contenedor, $Temporal), (Join-Path $carpeta 'adjuntos')) -PermitirFallo | Out-Null
Ejecutar -Comando @('docker', 'exec', $Contenedor, 'sh', '-c', "rm -rf $Temporal") -PermitirFallo | Out-Null

# 3. Resumen y comprobacion
$archivos = Get-ChildItem $carpeta -Recurse -File -ErrorAction SilentlyContinue
$totalMB = [Math]::Round((($archivos | Measure-Object -Property Length -Sum).Sum) / 1MB, 1)

Escribir ''
Escribir ("    archivos: {0}   total: {1} MB" -f $archivos.Count, $totalMB) -Color Gray

if ($archivos.Count -lt 2) {
    throw 'El respaldo quedo practicamente vacio. Revisa los contenedores.'
}

# 4. Un archivo que dice que hay dentro, para saber que se copio
$resumen = @(
    ("fecha:     {0}" -f $sello),
    ("proyecto:  {0}" -f $Proyecto),
    ("base:      {0}" -f $bd),
    ("archivos:  {0}" -f $archivos.Count),
    ("total:     {0} MB" -f $totalMB)
)

Set-Content -Path (Join-Path $carpeta 'resumen.txt') -Value $resumen -Encoding UTF8

Escribir ''
Escribir ("  Respaldo en {0}" -f $carpeta) -Color Green
Escribir ''
Escribir '  Recuerda: si el respaldo esta en el mismo disco, no protege de que el disco falle.' -Color DarkGray
Escribir '  Copia esta carpeta a otro equipo o a la nube de vez en cuando.' -Color DarkGray
Escribir ''
