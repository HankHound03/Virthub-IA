<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Los contenedores Webtop no deben ser alcanzables desde fuera.
 *
 * Es el objetivo del proyecto: los escritorios solo se abren a traves de la
 * aplicacion. Si un Webtop publica un puerto, cualquiera que conozca la URL
 * entra al escritorio sin pasar por la comprobacion de acceso, y ademas KasmVNC
 * expulsa al usuario legitimo porque solo admite un cliente por escritorio.
 *
 * Estos tests leen la configuracion desplegada, que es donde puede colarse el
 * fallo: basta con anadir un `ports:` para reabrir el agujero sin tocar codigo.
 */
class ComposeIsolationTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function compose(): array
    {
        $path = base_path('docker-compose.yml');

        $this->assertFileExists($path);

        // YAML sencillo: se parsean los bloques de servicio por indentacion.
        return $this->parseServices((string) file_get_contents($path));
    }

    /**
     * Parser minimo: extrae, por servicio, si publica puertos.
     *
     * Se evita depender de una extension YAML, que no esta garantizada en todos
     * los entornos donde corre la suite.
     *
     * @return array<string, array{ports: bool, networks: array<int, string>, image: string}>
     */
    private function parseServices(string $yaml): array
    {
        $services = [];
        $current = null;
        $enServices = false;
        $enPorts = false;

        foreach (explode("\n", $yaml) as $line) {
            $sinComentario = preg_replace('/\s+#.*$/', '', $line);
            $trimmed = rtrim($sinComentario);

            if (trim($trimmed) === '') {
                continue;
            }

            if (preg_match('/^services:\s*$/', $trimmed)) {
                $enServices = true;
                continue;
            }

            // Un bloque de nivel raiz distinto de services cierra la seccion.
            if ($enServices && preg_match('/^[a-z_]+:\s*$/', $trimmed) && ! preg_match('/^\s/', $trimmed)) {
                if (! preg_match('/^services:/', $trimmed)) {
                    $enServices = false;
                }
                continue;
            }

            if (! $enServices) {
                continue;
            }

            if (preg_match('/^  ([a-z0-9_-]+):\s*$/', $trimmed, $m)) {
                $current = $m[1];
                $services[$current] = ['ports' => false, 'networks' => [], 'image' => ''];
                $enPorts = false;
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^    ports:\s*$/', $trimmed)) {
                $services[$current]['ports'] = true;
                $enPorts = true;
                continue;
            }

            if (preg_match('/^    image:\s*(.+)$/', $trimmed, $m)) {
                $services[$current]['image'] = trim($m[1]);
                $enPorts = false;
                continue;
            }

            if (preg_match('/^      - (.+)$/', $trimmed, $m) && $enPorts) {
                continue;
            }

            if (preg_match('/^    [a-z_]+:/', $trimmed)) {
                $enPorts = false;
            }
        }

        return $services;
    }

    public function test_los_contenedores_webtop_no_publican_puertos(): void
    {
        $services = $this->compose();

        $webtops = array_filter(
            $services,
            static fn (string $name): bool => (bool) preg_match('/^ct\d+$/', $name),
            ARRAY_FILTER_USE_KEY
        );

        $this->assertNotEmpty($webtops, 'Deberia haber al menos un servicio de escritorio declarado.');

        foreach ($webtops as $name => $config) {
            $this->assertFalse(
                $config['ports'],
                "El servicio {$name} publica puertos: quedaria accesible desde fuera y sin pasar por la comprobacion de acceso."
            );
        }
    }

    public function test_la_base_de_datos_tampoco_se_publica(): void
    {
        $services = $this->compose();

        $this->assertArrayHasKey('mariadb', $services);
        $this->assertFalse(
            $services['mariadb']['ports'],
            'MariaDB no debe exponerse al exterior.'
        );
    }

    public function test_solo_la_aplicacion_y_caddy_publican_puertos(): void
    {
        $services = $this->compose();

        $publican = array_keys(array_filter($services, static fn (array $c): bool => $c['ports']));

        sort($publican);

        $this->assertSame(
            ['app', 'caddy'],
            $publican,
            'Solo la aplicacion y la puerta de entrada deben escuchar en el host.'
        );
    }

    public function test_caddy_esta_declarado_como_puerta_de_entrada(): void
    {
        $services = $this->compose();

        $this->assertArrayHasKey('caddy', $services, 'Sin Caddy no hay quien compruebe el acceso a los escritorios.');
        $this->assertTrue($services['caddy']['ports'], 'Caddy debe escuchar en 80 y 443.');
    }

    public function test_el_caddyfile_delega_la_autorizacion_en_la_aplicacion(): void
    {
        $path = base_path('docker/Caddyfile');

        $this->assertFileExists($path);

        $caddyfile = (string) file_get_contents($path);

        // forward_auth es lo que convierte el proxy en puerta y no en pasillo.
        $this->assertStringContainsString('forward_auth', $caddyfile);
        $this->assertStringContainsString('/internal/proxy-auth', $caddyfile);

        // El destino lo pone Caddy, no el cliente.
        $this->assertStringContainsString('header_up X-Container', $caddyfile);

        // Webtop usa WebSocket para noVNC.
        $this->assertStringContainsString('reverse_proxy', $caddyfile);
    }

    public function test_el_punto_de_control_no_es_alcanzable_desde_fuera(): void
    {
        // El Caddyfile debe bloquear /internal/proxy-auth en el sitio publico.
        $caddyfile = (string) file_get_contents(base_path('docker/Caddyfile'));

        $this->assertMatchesRegularExpression(
            '/handle \/internal\/proxy-auth.*?respond\s+"?Not found"?\s+404/s',
            $caddyfile,
            'La ruta interna no debe quedar expuesta en el sitio publico.'
        );
    }
}
