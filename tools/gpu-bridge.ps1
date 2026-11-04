# =============================================================================
# Puente de GPU para el monitor del panel de IA
# =============================================================================
#
# POR QUE EXISTE
# -------------
# VirtHub corre dentro de Docker y ahi no hay nvidia-smi: el contenedor no puede
# preguntarle a la tarjeta grafica cuanto esta trabajando. Este script se ejecuta
# en el equipo, lee nvidia-smi y deja las cifras donde el contenedor las ve.
#
# COMO LAS DEJA (y esto es lo importante)
# ---------------------------------------
# Por defecto NO abre ningun puerto. Escribe un archivo JSON cada dos segundos en
# storage/app/gpu/gpu.json, que el contenedor lee por un volumen de solo lectura.
#
# Antes el script escuchaba en el puerto 11435 en todas las interfaces, y eso
# significaba que cualquier equipo de la red local podia consultarlo. Ahora no hay
# nada que escuchar: sin puerto, sin firewall, sin exposicion.
#
# COMO SE USA
# -----------
#   powershell -ExecutionPolicy Bypass -File tools\gpu-bridge.ps1
#
#   Modo archivo (por defecto): escribe el JSON y se queda vivo hasta Ctrl+C.
#
#   -Http                     vuelve al modo antiguo, con puerto 11435.
#   -Port 11435               puerto para el modo -Http.
#   -IntervaloSegundos 2      cada cuanto se actualiza el archivo.
#
# Si el script no esta corriendo, el panel simplemente oculta los datos de GPU:
# nada se rompe.
#
# SEGURIDAD
# ---------
# En modo archivo no hay superficie de red: solo se escribe un archivo de cifras
# en una carpeta del proyecto. No acepta ordenes ni datos de nadie.
# =============================================================================

param(
    [switch]$Http,
    [int]$Port = 11435,
    [int]$IntervaloSegundos = 2
)

$ErrorActionPreference = 'Stop'
$smi = 'nvidia-smi'

# -----------------------------------------------------------------------------
# Una sola copia
# -----------------------------------------------------------------------------
# Puede pasar que se arranque al iniciar sesion y ademas a mano. Dos copias
# escribiendo el mismo archivo no rompen nada, pero es trabajo doble y la consola
# del segundo se queda muda sin explicar por que. Con este cerrojo, la segunda
# copia avisa y se sale.
$cerrojo = New-Object System.Threading.Mutex($false, 'Global\VirthubGpuBridge')

try {
    if (-not $cerrojo.WaitOne(0)) {
        Write-Host "Ya hay un puente de GPU corriendo. Esta copia se cierra." -ForegroundColor Yellow
        exit 0
    }
} catch {
    # Si el sistema no deja crear el cerrojo, se sigue igual: no es esencial.
}

# Carpeta que comparte con el contenedor (montada de solo lectura alli).
$carpeta = Join-Path $PSScriptRoot '..\storage\app\gpu'
$rutaJson = Join-Path $carpeta 'gpu.json'

function Get-GpuData {
    try {
        $csv = & $smi --query-gpu=name,utilization.gpu,memory.used,memory.total,temperature.gpu --format=csv,noheader,nounits 2>$null

        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($csv)) {
            return $null
        }

        $partes = ($csv -split ',') | ForEach-Object { $_.Trim() }

        return [pscustomobject]@{
            name      = $partes[0]
            util      = [int]$partes[1]
            mem_used  = [int]$partes[2]
            mem_total = [int]$partes[3]
            temp      = [int]$partes[4]
        }
    } catch {
        return $null
    }
}

# -----------------------------------------------------------------------------
# Modo HTTP (el de antes), solo si se pide con -Http
# -----------------------------------------------------------------------------
if ($Http) {
    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Any, $Port)

    try {
        $listener.Start()
    } catch {
        Write-Host "No se pudo abrir el puerto $Port. Puede estar ocupado." -ForegroundColor Red
        Write-Host $_.Exception.Message
        exit 1
    }

    Write-Host "Puente de GPU escuchando en el puerto $Port" -ForegroundColor Yellow
    Write-Host "Aviso: este modo abre el puerto a la red local. El modo archivo no." -ForegroundColor Yellow
    Write-Host "Ctrl+C para detenerlo."

    try {
        while ($true) {
            $client = $listener.AcceptTcpClient()

            try {
                $stream = $client.GetStream()
                $stream.ReadTimeout = 2000

                $buffer = New-Object byte[] 2048
                $null = $stream.Read($buffer, 0, $buffer.Length)

                $gpu = Get-GpuData

                $cuerpo = if ($gpu) {
                    (@{ gpu = $gpu; ok = $true } | ConvertTo-Json -Compress)
                } else {
                    (@{ ok = $false; error = 'nvidia-smi no respondio' } | ConvertTo-Json -Compress)
                }

                $bytes = [System.Text.Encoding]::UTF8.GetBytes($cuerpo)
                $cabecera = "HTTP/1.1 200 OK`r`nContent-Type: application/json`r`nContent-Length: $($bytes.Length)`r`nConnection: close`r`n`r`n"

                $cabeceraBytes = [System.Text.Encoding]::ASCII.GetBytes($cabecera)
                $stream.Write($cabeceraBytes, 0, $cabeceraBytes.Length)
                $stream.Write($bytes, 0, $bytes.Length)
                $stream.Flush()
            } catch {
                # Una peticion fallida no debe tumbar el puente.
            } finally {
                $client.Close()
            }
        }
    } finally {
        $listener.Stop()
        Write-Host "Puente detenido."
    }

    exit 0
}

# -----------------------------------------------------------------------------
# Modo archivo (por defecto): sin puerto, sin red
# -----------------------------------------------------------------------------
if (-not (Test-Path $carpeta)) {
    New-Item -ItemType Directory -Path $carpeta -Force | Out-Null
}

Write-Host "Puente de GPU en modo archivo (sin puerto)." -ForegroundColor Green
Write-Host "Escribe: $rutaJson"
Write-Host "Cada $IntervaloSegundos segundo(s). Ctrl+C para detenerlo."

$ultimoAviso = $null

try {
    while ($true) {
        $gpu = Get-GpuData

        $contenido = if ($gpu) {
            @{
                ok    = $true
                leido = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
                gpu   = $gpu
            } | ConvertTo-Json -Compress
        } else {
            @{
                ok    = $false
                leido = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
                error = 'nvidia-smi no respondio'
            } | ConvertTo-Json -Compress
        }

        # Escritura atomica: si el contenedor lee justo ahora, no ve un archivo a
        # medias. Se escribe en un temporal y se reemplaza.
        $temporal = "$rutaJson.tmp"

        try {
            [System.IO.File]::WriteAllText($temporal, $contenido, (New-Object System.Text.UTF8Encoding($false)))
            Move-Item -Path $temporal -Destination $rutaJson -Force
        } catch {
            Write-Host "No se pudo escribir el archivo: $($_.Exception.Message)" -ForegroundColor Red
        }

        # Avisar una sola vez si la tarjeta dejo de responder, para que la consola
        # no se llene de lo mismo.
        if (-not $gpu -and $ultimoAviso -ne 'sin-gpu') {
            Write-Host "nvidia-smi no responde. Se reintenta en cada vuelta." -ForegroundColor Yellow
            $ultimoAviso = 'sin-gpu'
        } elseif ($gpu -and $ultimoAviso -eq 'sin-gpu') {
            Write-Host "nvidia-smi vuelve a responder." -ForegroundColor Green
            $ultimoAviso = $null
        }

        Start-Sleep -Seconds $IntervaloSegundos
    }
} finally {
    Write-Host "Puente detenido."
}
