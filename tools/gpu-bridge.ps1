# =============================================================================
# Puente de GPU para el monitor del panel de IA
# =============================================================================
#
# POR QUE EXISTE
# -------------
# VirtHub corre dentro de Docker y ahi no hay nvidia-smi: el contenedor no puede
# preguntarle a la tarjeta grafica cuanto esta trabajando. Este script se ejecuta
# en el equipo, lee nvidia-smi y responde por HTTP en el puerto 11435, que el
# contenedor alcanza como host.docker.internal.
#
# COMO SE USA
# -----------
#   powershell -ExecutionPolicy Bypass -File tools\gpu-bridge.ps1
#
# Se queda escuchando hasta que lo cierres (Ctrl+C). Mientras este abierto, el
# panel de IA muestra GPU, memoria, temperatura y modelo cargado. Si lo cierras,
# el panel simplemente oculta esos datos: nada se rompe.
#
# SEGURIDAD
# ---------
# Solo devuelve cifras de la GPU; no acepta comandos ni datos del cliente. Escucha
# en todas las interfaces para que Docker pueda llegar, pero en una red domestica
# detras del router. Si te preocupa, cierra el puerto en el firewall de Windows.

param(
    [int]$Port = 11435
)

$ErrorActionPreference = 'Stop'
$smi = 'nvidia-smi'

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

$listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Any, $Port)

try {
    $listener.Start()
} catch {
    Write-Host "No se pudo abrir el puerto $Port. Puede estar ocupado." -ForegroundColor Red
    Write-Host $_.Exception.Message
    exit 1
}

Write-Host "Puente de GPU escuchando en el puerto $Port" -ForegroundColor Green
Write-Host "El panel de IA de VirtHub lo consulta en http://host.docker.internal:$Port/"
Write-Host "Ctrl+C para detenerlo."

try {
    while ($true) {
        $client = $listener.AcceptTcpClient()

        try {
            $stream = $client.GetStream()
            $stream.ReadTimeout = 2000

            # Leer la peticion (no hace falta interpretarla)
            $buffer = New-Object byte[] 2048
            $null = $stream.Read($buffer, 0, $buffer.Length)

            $gpu = Get-GpuData

            $cuerpo = if ($gpu) {
                (@{ gpu = $gpu; ok = $true } | ConvertTo-Json -Compress)
            } else {
                (@{ ok = $false; error = 'nvidia-smi no respondio' } | ConvertTo-Json -Compress)
            }

            $bytes = [System.Text.Encoding]::UTF8.GetBytes($cuerpo)
            $cabecera = "HTTP/1.1 200 OK`r`nContent-Type: application/json`r`nAccess-Control-Allow-Origin: *`r`nContent-Length: $($bytes.Length)`r`nConnection: close`r`n`r`n"

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
