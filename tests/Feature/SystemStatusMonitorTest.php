<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Support\SystemStatusMonitor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Estado de los escritorios en el panel.
 *
 * Dos cosas que importaban y que antes estaban mal:
 *
 *  1. La lista salia de indices escritos en el codigo, no de los escritorios
 *     registrados. Al anadir uno, el panel no lo mostraba.
 *
 *  2. Se sondeaba la URL PUBLICA, que pasa por Caddy y responde 403 sin sesion.
 *     Un escritorio sano aparecia como caido, que es justo lo que veia el usuario.
 */
class SystemStatusMonitorTest extends TestCase
{
    private function registrar(string $name, string $url = ''): WebtopContainer
    {
        return WebtopContainer::create([
            'name' => $name,
            'url' => $url !== '' ? $url : 'https://' . $name . '.virthub.dpdns.org/',
            'status' => WebtopContainer::STATUS_AVAILABLE,
            'capacity' => 1,
        ]);
    }

    private function estados(): array
    {
        // Se llama al monitor directamente para no depender de la cache.
        return (array) app(SystemStatusMonitor::class)->snapshot()['container_status'];
    }

    public function test_aparecen_los_escritorios_registrados_no_una_lista_fija(): void
    {
        $this->registrar('ct0');
        $this->registrar('ct2');

        Http::fake([
            'http://ct0:3000/*' => Http::response('', 200),
            'http://ct2:3000/*' => Http::response('', 200),
            '*' => Http::response('', 500),
        ]);

        $estados = $this->estados();

        $this->assertSame(['ct0', 'ct2'], array_keys($estados));
        $this->assertTrue($estados['ct0']['online']);
        $this->assertTrue($estados['ct2']['online']);
    }

    public function test_un_escritorio_que_pide_credenciales_esta_vivo(): void
    {
        $this->registrar('ct0');

        // 401 y 403 significan "estoy en pie, pero no te dejo pasar". Contarlos
        // como caidos era el error que dejaba el panel en rojo.
        Http::fake(['http://ct0:3000/*' => Http::response('', 401)]);
        $this->assertTrue($this->estados()['ct0']['online'], 'Un 401 debe contar como vivo.');

        Http::fake(['http://ct0:3000/*' => Http::response('', 403)]);
        $this->assertTrue($this->estados()['ct0']['online'], 'Un 403 debe contar como vivo.');
    }

    public function test_un_escritorio_sin_respuesta_esta_caido(): void
    {
        $this->registrar('ct0');

        Http::fake(['http://ct0:3000/*' => Http::response('', 500)]);

        $this->assertFalse($this->estados()['ct0']['online']);
    }

    public function test_se_sondea_por_la_red_interna_y_no_por_la_url_publica(): void
    {
        $this->registrar('ct0', 'https://ct0.virthub.dpdns.org/');

        $sondeadas = [];
        Http::fake(function ($request) use (&$sondeadas) {
            $sondeadas[] = (string) $request->url();

            return Http::response('', 200);
        });

        $this->estados();

        $this->assertContains('http://ct0:3000/', $sondeadas);
        $this->assertNotContains('https://ct0.virthub.dpdns.org/', $sondeadas);
    }

    public function test_sin_escritorios_registrados_la_lista_esta_vacia(): void
    {
        // Sin registros el panel debe decir que no hay ninguno, no inventarse
        // siete que no existen.
        Http::fake(['*' => Http::response('', 200)]);

        $this->assertSame([], $this->estados());
    }

    public function test_el_panel_ya_no_lleva_el_total_escrito_a_mano(): void
    {
        $vista = (string) file_get_contents(resource_path('views/home.blade.php'));

        $this->assertStringNotContainsString(
            'containerIds',
            $vista,
            'La vista no debe llevar la lista de contenedores escrita a mano.'
        );
        $this->assertStringNotContainsString(
            '/7`',
            $vista,
            'El total no debe ser un numero fijo: el panel tiene que crecer solo.'
        );
        $this->assertStringContainsString('entries.length', $vista);
    }
}
