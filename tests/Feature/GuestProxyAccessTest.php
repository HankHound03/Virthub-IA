<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use App\Support\DesktopPass;
use Tests\TestCase;

/**
 * Acceso del invitado a su escritorio.
 *
 * Los invitados comparten un unico contenedor. Su cuenta es de sesion, no existe
 * en el almacen de usuarios, y eso hacia que el punto de control los rechazara:
 * identificaba al usuario por el pase y luego lo buscaba en la base, donde no
 * estaba. Resultado: 403 en la pagina del escritorio del invitado.
 */
class GuestProxyAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El escritorio del invitado y un par de escritorios de usuarios.
        foreach (['ct2', 'ct7'] as $nombre) {
            WebtopContainer::create([
                'name' => $nombre,
                'url' => 'https://' . $nombre . '.virthub.dpdns.org/',
                'status' => WebtopContainer::STATUS_AVAILABLE,
                'capacity' => 1,
            ]);
        }
    }

    private function comoCaddy(string $container, ?string $pase = null): \Illuminate\Testing\TestResponse
    {
        $query = $pase === null ? [] : [DesktopPass::PARAM => $pase];

        return $this->withHeaders(['X-Container' => $container])
            ->get('/internal/proxy-auth?' . http_build_query($query));
    }

    public function test_el_invitado_entra_a_su_escritorio_con_su_pase(): void
    {
        $pase = app(DesktopPass::class)->para('guest_482bc0', 'ct7');

        $this->comoCaddy('ct7', $pase)->assertStatus(200);
    }

    public function test_el_invitado_no_puede_abrir_el_escritorio_de_un_usuario(): void
    {
        // Pase valido, pero para el escritorio de otro.
        $pase = app(DesktopPass::class)->para('guest_482bc0', 'ct2');

        $this->comoCaddy('ct2', $pase)->assertStatus(403);
    }

    public function test_sin_pase_el_invitado_no_entra(): void
    {
        $this->comoCaddy('ct7')->assertStatus(403);
    }

    public function test_un_pase_de_invitado_con_firma_falsa_no_sirve(): void
    {
        $pase = app(DesktopPass::class)->para('guest_482bc0', 'ct7');

        // Se altera la firma.
        $falso = str_replace('signature=', 'signature=00', $pase);

        $this->comoCaddy('ct7', $falso)->assertStatus(403);
    }

    public function test_un_nombre_que_parece_invitado_pero_no_lo_es_se_rechaza(): void
    {
        // 'guest_zzzzzz' no sigue el formato (solo hex) y ademas no existe en el
        // almacen, asi que no debe colarse como invitado.
        $pase = app(DesktopPass::class)->para('guest_zzzzzz', 'ct7');

        $this->comoCaddy('ct7', $pase)->assertStatus(403);
    }
}
