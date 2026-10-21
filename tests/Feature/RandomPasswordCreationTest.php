<?php

namespace Tests\Feature;

use App\Services\JsonUserStore;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Creacion de usuario con contrasena aleatoria.
 *
 * El administrador debe recibir la contrasena generada en el mensaje de
 * confirmacion: si no, no hay forma de darsela al usuario.
 */
class RandomPasswordCreationTest extends TestCase
{
    private string $usersFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usersFile = storage_path('app/data/users.json');

        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }

        app(JsonUserStore::class)->createUser('admin', 'P@ssword123!', 'admin');

        DB::table('users')->insert([
            'name' => 'admin',
            'username' => 'admin',
            'email' => 'admin@users.virthub.local',
            'password' => bcrypt('P@ssword123!'),
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->usersFile)) {
            unlink($this->usersFile);
        }

        parent::tearDown();
    }

    public function test_la_contrasena_aleatoria_llega_en_el_mensaje(): void
    {
        $respuesta = $this->withSession(['auth_user' => ['username' => 'admin', 'role' => 'admin']])
            ->post('/admin/users', [
                'name' => 'Prueba Aleatoria',
                'username' => 'prueba_random',
                'role' => 'user',
                'random_password' => '1',
            ]);

        $respuesta->assertRedirect('/admin/users');

        $mensaje = (string) session('success');

        $this->assertNotSame('', $mensaje, 'Debe haber un mensaje de confirmacion.');
        $this->assertStringContainsString('prueba_random', $mensaje);
        $this->assertStringContainsString('Password:', $mensaje, 'El mensaje debe incluir la contrasena generada.');

        // Y el formato que espera el desplegable de la vista.
        $this->assertMatchesRegularExpression('/Usuario creado: \S+/', $mensaje);
        $this->assertMatchesRegularExpression('/Password: \S+/', $mensaje);
    }

    public function test_sin_contrasena_aleatoria_no_se_inventa_una(): void
    {
        $respuesta = $this->withSession(['auth_user' => ['username' => 'admin', 'role' => 'admin']])
            ->post('/admin/users', [
                'name' => 'Prueba Manual',
                'username' => 'prueba_manual',
                'password' => 'ClaveManual123',
                'role' => 'user',
            ]);

        $respuesta->assertRedirect('/admin/users');

        $mensaje = (string) session('success');

        // La que escribio el administrador no se repite en el mensaje.
        $this->assertStringNotContainsString('Password:', $mensaje);
    }

    public function test_la_pagina_muestra_la_contrasena_generada(): void
    {
        // Contrasena con caracteres especiales, que es lo que genera el sistema
        // y lo que podria romper el HTML.
        $html = $this->withSession([
            'auth_user' => ['username' => 'admin', 'role' => 'admin'],
            'credenciales_nuevas' => [
                'username' => 'virt_abc123',
                'password' => 'aB3$k&9Lm!n',
            ],
        ])->get('/admin/users')->assertOk()->getContent();

        $this->assertStringContainsString('credentialsBox', $html);
        $this->assertStringContainsString('virt_abc123', $html);
        $this->assertStringContainsString('aB3$k', $html, 'La contrasena debe llegar a la pagina.');
    }

    public function test_las_credenciales_se_muestran_una_sola_vez(): void
    {
        $html = $this->withSession([
            'auth_user' => ['username' => 'admin', 'role' => 'admin'],
            'credenciales_nuevas' => [
                'username' => 'virt_unica',
                'password' => 'ClaveUnica123',
            ],
        ])->get('/admin/users')->assertOk()->getContent();

        $this->assertStringContainsString('ClaveUnica123', $html, 'Se ven al cargar la pagina.');

        // Y quedan borradas de la sesion: no deben seguir ahi para el siguiente
        // que abra la pagina.
        $this->assertNull(
            session('credenciales_nuevas'),
            'Tras mostrarlas deben borrarse de la sesion.'
        );
    }

    /**
     * Recorrido REAL: enviar el formulario y seguir la redireccion.
     *
     * Esto es lo que hace el navegador al crear un usuario.
     */
    public function test_la_contrasena_se_ve_tras_crear_el_usuario(): void
    {
        $respuesta = $this->withSession(['auth_user' => ['username' => 'admin', 'role' => 'admin']])
            ->followingRedirects()
            ->post('/admin/users', [
                'name' => 'Prueba Redirect',
                'username' => 'prueba_redirect',
                'role' => 'user',
                'random_password' => '1',
            ]);

        $respuesta->assertOk();
        $html = $respuesta->getContent();

        $this->assertStringContainsString(
            'credentialsBox',
            $html,
            'Tras crear el usuario debe aparecer el cuadro de credenciales.'
        );

        // Y la contrasena tiene que estar de verdad, no solo el titulo.
        $this->assertStringContainsString('prueba_redirect', $html);
        $this->assertMatchesRegularExpression('/id="credPassword">\S+</', $html);
    }
}
