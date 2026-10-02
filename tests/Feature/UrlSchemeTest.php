<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La generacion de URLs no debe depender de APP_URL.
 *
 * El fallo original: bastaba con que APP_URL empezara por https:// para forzar
 * https en todas las URLs. Sirviendo por http plano, el navegador pedia los CSS
 * por https contra un puerto sin TLS, la peticion fallaba y la pagina se veia
 * sin estilos. Estos tests fijan el comportamiento correcto.
 */
class UrlSchemeTest extends TestCase
{
    public function test_no_fuerza_https_cuando_la_peticion_llega_por_http(): void
    {
        config([
            'app.url' => 'https://virthub.dpdns.org',
            'virthub.force_https' => false,
        ]);

        $response = $this->get('http://localhost/style.css');

        // Aunque APP_URL sea https, un asset pedido por http debe resolverse
        // con el mismo esquema de la peticion.
        $this->assertStringStartsWith('http://localhost/', asset('style.css'));
    }

    public function test_los_assets_de_las_vistas_usan_el_esquema_de_la_peticion(): void
    {
        config([
            'app.url' => 'https://virthub.dpdns.org',
            'virthub.force_https' => false,
        ]);

        $response = $this->get('http://localhost/');
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('https://localhost/', $html);
    }

    public function test_la_configuracion_de_esquema_esta_centralizada(): void
    {
        // Debe existir y ser booleana, no depender de la forma de APP_URL.
        $this->assertIsBool(config('virthub.force_https'));
    }
}
