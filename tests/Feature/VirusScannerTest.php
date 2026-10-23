<?php

namespace Tests\Feature;

use App\Support\VirusScanner;
use Tests\TestCase;

/**
 * Analisis de adjuntos con ClamAV.
 *
 * No se levanta un ClamAV para estas pruebas: se comprueba la interpretacion de
 * las respuestas del daemon y el comportamiento cuando no esta disponible, que
 * es lo que decide si una subida pasa o no.
 */
class VirusScannerTest extends TestCase
{
    public function test_interpreta_una_respuesta_limpia(): void
    {
        $resultado = VirusScanner::interpretar('stream: OK');

        $this->assertTrue($resultado['limpio']);
        $this->assertNull($resultado['virus']);
    }

    public function test_interpreta_un_virus_detectado(): void
    {
        $resultado = VirusScanner::interpretar('stream: Eicar-Test-Signature FOUND');

        $this->assertFalse($resultado['limpio']);
        $this->assertSame('Eicar-Test-Signature', $resultado['virus']);
    }

    public function test_interpreta_un_error_del_daemon(): void
    {
        $resultado = VirusScanner::interpretar('INSTREAM size limit exceeded. ERROR');

        $this->assertFalse($resultado['limpio']);
        $this->assertNull($resultado['virus']);
        $this->assertStringContainsString('size limit', (string) $resultado['error']);
    }

    public function test_una_respuesta_vacia_no_se_da_por_buena(): void
    {
        $this->assertFalse(VirusScanner::interpretar('')['limpio']);
    }

    public function test_desactivado_deja_pasar_sin_analizar(): void
    {
        config(['virthub.antivirus.enabled' => false]);

        $resultado = app(VirusScanner::class)->escanear(__FILE__);

        $this->assertTrue($resultado['limpio']);
        $this->assertNull($resultado['error']);
    }

    public function test_si_el_antivirus_no_responde_se_aplica_fail_open(): void
    {
        // Un puerto donde no hay nadie escuchando.
        config([
            'virthub.antivirus.enabled' => true,
            'virthub.antivirus.host' => '127.0.0.1',
            'virthub.antivirus.port' => 1,
            'virthub.antivirus.timeout' => 5,
            'virthub.antivirus.fail_open' => true,
        ]);

        $resultado = app(VirusScanner::class)->escanear(__FILE__);

        $this->assertTrue($resultado['limpio'], 'Con fail_open se deja pasar la subida.');
        $this->assertNotNull($resultado['error'], 'Pero se deja constancia del problema.');
    }

    public function test_si_el_antivirus_no_responde_y_no_es_fail_open_se_rechaza(): void
    {
        config([
            'virthub.antivirus.enabled' => true,
            'virthub.antivirus.host' => '127.0.0.1',
            'virthub.antivirus.port' => 1,
            'virthub.antivirus.timeout' => 5,
            'virthub.antivirus.fail_open' => false,
        ]);

        $resultado = app(VirusScanner::class)->escanear(__FILE__);

        $this->assertFalse($resultado['limpio'], 'Con fail_open=false se rechaza.');
    }

    public function test_un_archivo_que_no_existe_se_rechaza(): void
    {
        config(['virthub.antivirus.enabled' => true]);

        $resultado = app(VirusScanner::class)->escanear('/ruta/que/no/existe.bin');

        $this->assertFalse($resultado['limpio']);
        $this->assertNotNull($resultado['error']);
    }
}
