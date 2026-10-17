<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Support\SystemStatusMonitor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sondeo de escritorios que viven en OTRA maquina.
 *
 * El panel sondeaba cada escritorio por su nombre de contenedor
 * (http://ct8:3000). Eso solo resuelve dentro de la red de Docker de este equipo,
 * asi que los escritorios del segundo equipo aparecian como caidos aunque
 * estuvieran funcionando perfectamente.
 *
 * La solucion es declarar su host real en 'internal_host'.
 */
class RemoteContainerProbeTest extends TestCase
{
    private function registrar(string $name, array $extra = []): WebtopContainer
    {
        return WebtopContainer::create(array_merge([
            'name' => $name,
            'url' => 'https://' . $name . '.virthub.dpdns.org/',
            'status' => WebtopContainer::STATUS_AVAILABLE,
            'capacity' => 1,
        ], $extra));
    }

    private function estados(): array
    {
        return (array) app(SystemStatusMonitor::class)->snapshot()['container_status'];
    }

    public function test_un_escritorio_remoto_se_sondea_por_su_ip_no_por_su_nombre(): void
    {
        $this->registrar('ct8', [
            'internal_host' => '192.168.1.9',
            'internal_port' => 3000,
        ]);

        $sondeadas = [];
        Http::fake(function ($request) use (&$sondeadas) {
            $sondeadas[] = (string) $request->url();

            return Http::response('', 200);
        });

        $this->estados();

        $this->assertContains('http://192.168.1.9:3000/', $sondeadas, 'Debe sondearse por su IP real.');
        $this->assertNotContains('http://ct8:3000/', $sondeadas, 'El nombre no resuelve fuera de Docker.');
    }

    public function test_el_puerto_de_cada_remoto_es_el_suyo(): void
    {
        $this->registrar('ct8', ['internal_host' => '192.168.1.9', 'internal_port' => 3000]);
        $this->registrar('ct9', ['internal_host' => '192.168.1.9', 'internal_port' => 3100]);
        $this->registrar('ct10', ['internal_host' => '192.168.1.9', 'internal_port' => 3200]);
        $this->registrar('ct11', ['internal_host' => '192.168.1.9', 'internal_port' => 3300]);

        $sondeadas = [];
        Http::fake(function ($request) use (&$sondeadas) {
            $sondeadas[] = (string) $request->url();

            return Http::response('', 200);
        });

        $estados = $this->estados();

        // Cada uno en su puerto, no todos en el 3000 por defecto.
        $this->assertContains('http://192.168.1.9:3000/', $sondeadas);
        $this->assertContains('http://192.168.1.9:3100/', $sondeadas);
        $this->assertContains('http://192.168.1.9:3200/', $sondeadas);
        $this->assertContains('http://192.168.1.9:3300/', $sondeadas);

        foreach (['ct8', 'ct9', 'ct10', 'ct11'] as $nombre) {
            $this->assertTrue($estados[$nombre]['online'], "{$nombre} deberia estar en linea.");
        }
    }

    public function test_un_escritorio_local_sigue_sondeandose_por_su_nombre(): void
    {
        // Sin internal_host, se usa el nombre del contenedor: es la via correcta
        // para los que corren en este equipo.
        $this->registrar('ct0');

        $sondeadas = [];
        Http::fake(function ($request) use (&$sondeadas) {
            $sondeadas[] = (string) $request->url();

            return Http::response('', 200);
        });

        $this->estados();

        $this->assertContains('http://ct0:3000/', $sondeadas);
    }
}
