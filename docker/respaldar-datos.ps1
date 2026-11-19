<#
.SYNOPSIS
    Respalda y restaura los datos de Virthub (usuarios, notas, chat, avisos...).

.DESCRIPTION
    Todo lo que la aplicacion guarda vive en el volumen app_storage del proyecto
    (dentro, en storage/app/data). Docker no guarda versiones: si algo se borra,
    se pierde. Este script copia esa carpeta a .tmp\respaldos\<fecha> y sabe
    devolverla.

.PARAMETER Proyecto
    virthub-test (por defecto) o virthub para produccion.

.PARAMETER Restaurar
    En vez de copiar, devuelve el respaldo indicado con -Fecha.

.PARAMETER Fecha
    Carpeta del respaldo a restaurar (ej. 20261118-234500). Sin -Fecha, se usa el
    mas reciente.

.EXAMPLE
    .\respaldar-datos.ps1
    Copia los datos del entorno de pruebas.

.EXAMPLE
    .\respaldar-datos.ps1 -Restaurar
    Devuelve el respaldo mas reciente del entorno de pruebas.
#>

[CmdletBinding()]
param(
    [ValidateSet('virthub-test', 'virthub')]
    [string] $Proyecto = 'virthub-test',

    [switch] $Restaurar,

    [string] $Fecha = ''
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

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
$Carpeta = Join-Path $Raiz '.tmp\respaldos'

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

function Estado-Contenedor {
    $estado = Salida-Comando @('docker', 'inspect', '--format', '{{.State.Status}}', $Contenedor)

    if (-not $estado) {
        return 'no existe'
    }

    return $estado.Trim()
}

$estado = Estado-Contenedor

if ($estado -ne 'running') {
    throw "El contenedor $Contenedor no esta en marcha (estado: $estado)."
}

Write-Host ''
Write-Host ("  Proyecto: {0}" -f $Proyecto) -ForegroundColor Cyan

# ---------------------------------------------------------------------------
# Restaurar
# ---------------------------------------------------------------------------
if ($Restaurar) {
    if (-not (Test-Path $Carpeta)) {
        throw "No hay respaldos en $Carpeta"
    }

    $elegido = if ($Fecha -ne '') {
        Get-Item (Join-Path $Carpeta $Fecha) -ErrorAction SilentlyContinue
    } else {
        Get-ChildItem $Carpeta -Directory | Sort-Object Name -Descending | Select-Object -First 1
    }

    if (-not $elegido) {
        throw 'No encuentro ese respaldo.'
    }

    Write-Host ("  Restaurando {0}" -f $elegido.Name) -ForegroundColor Yellow
    Write-Host '  Se sobrescribe storage/app/data. Se guarda otra copia antes.' -ForegroundColor Gray

    # Copia de seguridad del estado actual, por si acaso
    $antes = Join-Path $Carpeta (("{0}-antes-de-restaurar" -f (Get-Date -Format 'yyyyMMdd-HHmmss')))
    New-Item -ItemType Directory -Force -Path $antes | Out-Null
    Salida-Comando @('docker', 'exec', $Contenedor, 'sh', '-c', 'cp -a /var/www/html/storage/app/data/. /tmp/vh-restaurar-antes 2>/dev/null; true') | Out-Null
    Salida-Comando @('docker', 'cp', ("{0}:/tmp/vh-restaurar-antes/." -f $Contenedor), $antes) | Out-Null

    foreach ($archivo in Get-ChildItem $elegido.FullName -File) {
        Salida-Comando @('docker', 'cp', $archivo.FullName, ("{0}:/var/www/html/storage/app/data/{1}" -f $Contenedor, $archivo.Name)) | Out-Null
        Write-Host ("    copiado {0}" -f $archivo.Name) -ForegroundColor Gray
    }

    Write-Host ''
    Write-Host '  Restaurado. Recarga la pagina.' -ForegroundColor Green
    Write-Host ''
    return
}

# ---------------------------------------------------------------------------
# Respaldar
# ---------------------------------------------------------------------------
$destino = Join-Path $Carpeta (Get-Date -Format 'yyyyMMdd-HHmmss')
New-Item -ItemType Directory -Force -Path $destino | Out-Null

Salida-Comando @('docker', 'exec', $Contenedor, 'sh', '-c', 'mkdir -p /tmp/vh-respaldo && cp -a /var/www/html/storage/app/data/. /tmp/vh-respaldo/ 2>/dev/null; true') | Out-Null
Salida-Comando @('docker', 'cp', ("{0}:/tmp/vh-respaldo/." -f $Contenedor), $destino) | Out-Null
Salida-Comando @('docker', 'exec', $Contenedor, 'sh', '-c', 'rm -rf /tmp/vh-respaldo') | Out-Null

$archivos = Get-ChildItem $destino -File -ErrorAction SilentlyContinue

Write-Host ''
foreach ($archivo in $archivos) {
    Write-Host ("    {0,-22} {1,8} KB" -f $archivo.Name, [Math]::Round($archivo.Length / 1KB)) -ForegroundColor Gray
}

Write-Host ''
if ($archivos.Count -eq 0) {
    Write-Host '  Aviso: no se copio ningun archivo.' -ForegroundColor Yellow
} else {
    Write-Host ("  Respaldo en {0}" -f $destino) -ForegroundColor Green
}

Write-Host ''
