<?php

namespace Tests\Feature;

use App\Services\DatabaseUserStore;
use App\Services\UserStore;
use App\Support\ContainerResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cubre la implementacion de produccion del almacen de usuarios.
 *
 * La suite general corre contra el almacen JSON (rapido y aislado). Estos
 * tests ejercitan DatabaseUserStore de forma explicita, que es lo que atiende
 * a los usuarios reales, para que el codigo de produccion no quede sin
 * cobertura.
 */
class DatabaseUserStoreTest extends TestCase
{
    private function store(): DatabaseUserStore
    {
        return app(DatabaseUserStore::class);
    }

    public function test_la_interfaz_user_store_esta_enlazada(): void
    {
        // La aplicacion nunca resuelve una implementacion concreta: depende de
        // la interfaz. En testing apunta al almacen JSON; en produccion a la
        // base de datos (ver AppServiceProvider::register).
        $this->assertInstanceOf(UserStore::class, app(UserStore::class));

        // Y la implementacion de produccion debe ser usable en esta suite.
        $this->assertInstanceOf(DatabaseUserStore::class, $this->store());
    }

    public function test_crea_y_encuentra_un_usuario_hasheando_la_contrasena(): void
    {
        $created = $this->store()->createUser('ana', 'P@ssword123!', 'user', 'Ana Perez');

        $this->assertSame('ana', $created['username']);
        $this->assertSame('user', $created['role']);

        $found = $this->store()->findByUsername('ana');

        $this->assertNotNull($found);
        $this->assertSame('Ana Perez', $found['name']);
        $this->assertSame('user', $found['role']);
        $this->assertTrue($found['is_active']);

        // La contrasena debe estar hasheada, nunca en claro.
        $hash = (string) DB::table('users')->where('username', 'ana')->value('password');
        $this->assertNotSame('P@ssword123!', $hash);
        $this->assertTrue($this->store()->verifyPassword('ana', 'P@ssword123!'));
        $this->assertFalse($this->store()->verifyPassword('ana', 'incorrecta'));
    }

    public function test_verifica_credenciales_y_rechaza_cuentas_inactivas(): void
    {
        $this->store()->createUser('bea', 'P@ssword123!', 'user');

        $this->assertNotNull($this->store()->verifyCredentials('bea', 'P@ssword123!'));
        $this->assertNull($this->store()->verifyCredentials('bea', 'mala'));

        $this->store()->deactivateUser('bea');

        $this->assertNull($this->store()->verifyCredentials('bea', 'P@ssword123!'));
        $this->assertNull($this->store()->findPublicProfile('bea'));
    }

    public function test_no_permite_eliminar_al_ultimo_admin_activo_ni_al_admin_principal(): void
    {
        $this->store()->createOrUpdateAdmin('admin', 'P@ssword123!');
        $this->store()->createUser('otro', 'P@ssword123!', 'admin');

        // Quitar el segundo admin deja uno solo: el principal queda protegido.
        $this->store()->deleteUser('otro');
        $this->assertSame(1, $this->store()->countActiveAdmins());

        $this->expectException(\RuntimeException::class);
        $this->store()->deleteUser('admin');
    }

    public function test_ciclo_de_vida_de_la_doble_autenticacion(): void
    {
        $this->store()->createUser('caro', 'P@ssword123!', 'user');

        $this->assertFalse($this->store()->hasTwoFactorEnabled('caro'));
        $this->assertNull($this->store()->twoFactorSecret('caro'));

        $this->store()->enableTwoFactor('caro', 'secreto-cifrado', [password_hash('AAAA11111', PASSWORD_BCRYPT)]);

        $this->assertTrue($this->store()->hasTwoFactorEnabled('caro'));
        $this->assertSame('secreto-cifrado', $this->store()->twoFactorSecret('caro'));

        // Un codigo de recuperacion valido se consume y no se puede reutilizar.
        $this->assertTrue($this->store()->consumeTwoFactorRecoveryCode('caro', 'aaaa11111'));
        $this->assertFalse($this->store()->consumeTwoFactorRecoveryCode('caro', 'aaaa11111'));

        $this->store()->disableTwoFactor('caro');
        $this->assertFalse($this->store()->hasTwoFactorEnabled('caro'));
    }

    public function test_la_resolucion_de_contenedores_usa_config_y_es_estable(): void
    {
        config([
            'virthub.containers.urls' => [
                0 => 'https://ct0.test/',
                2 => 'https://ct2.test/',
                3 => 'https://ct3.test/',
                4 => 'https://ct4.test/',
                5 => 'https://ct5.test/',
                6 => 'https://ct6.test/',
                7 => 'https://ct7.test/',
            ],
            'virthub.containers.admin_users' => ['admin'],
            'virthub.containers.guest_index' => 7,
        ]);

        $resolver = app(ContainerResolver::class);

        // El invitado siempre va al contenedor de invitados.
        $this->assertSame('https://ct7.test/', $resolver->urlFor(['username' => 'invitado', 'role' => 'guest']));
        $this->assertSame('https://ct7.test/', $resolver->urlFor(null));

        // El usuario privilegiado va al contenedor exclusivo.
        $this->assertSame('https://ct0.test/', $resolver->urlFor(['username' => 'admin', 'role' => 'admin']));

        // El resto cae en el grupo 2..6 y siempre en el mismo.
        $first = $resolver->urlFor(['username' => 'ana', 'role' => 'user']);
        $second = $resolver->urlFor(['username' => 'ana', 'role' => 'user']);

        $this->assertSame($first, $second);
        $this->assertContains($first, [
            'https://ct2.test/', 'https://ct3.test/',
            'https://ct4.test/', 'https://ct5.test/', 'https://ct6.test/',
        ]);
    }

    public function test_la_configuracion_de_virthub_esta_disponible_sin_env(): void
    {
        // Si estos valores dependieran de env() en tiempo de ejecucion,
        // devolverian null con la configuracion cacheada.
        $this->assertIsArray(config('virthub.containers.urls'));
        $this->assertArrayHasKey(0, config('virthub.containers.urls'));
        $this->assertSame('ollama', config('virthub.ollama.chat_username'));
        $this->assertIsInt(config('virthub.guests.session_minutes'));
        $this->assertFalse((bool) config('virthub.installation.allow_localhost_without_key'));
    }
}
