<?php

namespace Tests\Feature;

use App\Models\WebtopContainer;
use Tests\TestCase;

/**
 * Botones de la cuenta admin principal.
 *
 * El servidor no deja desactivar ni eliminar el usuario 'admin'. Antes la pagina
 * los mostraba activos igual, asi que al pulsarlos aparecia un error. Ahora se
 * pintan deshabilitados, y esto lo comprueba.
 */
class AdminPrincipalButtonsTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $users
     */
    private function render(array $users): string
    {
        return view('admin-users', [
            'currentUser' => ['username' => 'admin', 'role' => 'admin', 'name' => 'Admin'],
            'users' => $users,
            'forumReports' => [],
            'suggestions' => [],
        ])->render();
    }

    public function test_la_cuenta_admin_principal_se_muestra_deshabilitada(): void
    {
        $html = $this->render([
            ['username' => 'admin', 'role' => 'admin', 'is_active' => true, 'last_login_at' => '2026-10-20 06:01:04'],
        ]);

        // Los dos botones que el servidor rechazaria quedan apagados.
        $this->assertStringContainsString('class="btn-deactivate" disabled', $html);
        $this->assertStringContainsString('class="btn-delete" disabled', $html);

        // Y se explica el motivo, para que no parezca un fallo.
        $this->assertStringContainsString('La cuenta admin principal no se puede desactivar', $html);
        $this->assertStringContainsString('La cuenta admin principal no se puede eliminar', $html);
    }

    public function test_un_usuario_normal_si_puede_desactivarse(): void
    {
        $html = $this->render([
            ['username' => 'maddys', 'role' => 'user', 'is_active' => true, 'last_login_at' => null],
        ]);

        // Sin el atributo disabled y con su accion correspondiente.
        $this->assertStringNotContainsString('class="btn-deactivate" disabled', $html);
        $this->assertStringContainsString("openConfirmation('deactivate', 'maddys')", $html);
        $this->assertStringContainsString("openConfirmation('delete', 'maddys')", $html);
    }

    public function test_el_admin_se_deshabilita_aunque_haya_mas_usuarios(): void
    {
        $html = $this->render([
            ['username' => 'admin', 'role' => 'admin', 'is_active' => true, 'last_login_at' => null],
            ['username' => 'maddys', 'role' => 'user', 'is_active' => true, 'last_login_at' => null],
            ['username' => 'frank', 'role' => 'user', 'is_active' => false, 'last_login_at' => null],
        ]);

        // Solo una vez: la del admin, no las de los demas.
        $this->assertSame(1, substr_count($html, 'class="btn-deactivate" disabled'));
        $this->assertSame(1, substr_count($html, 'class="btn-delete" disabled'));

        // Los otros dos conservan sus acciones normales.
        $this->assertStringContainsString("openConfirmation('deactivate', 'maddys')", $html);
        $this->assertStringContainsString("openConfirmation('delete', 'frank')", $html);
    }
}
