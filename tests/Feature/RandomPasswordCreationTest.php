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
        // y lo que podria romper el HTML o la expresion regular del desplegable.
        $mensaje = 'Usuario creado: virt_abc123 (user) | Password: aB3$k&9Lm!n';

        $html = $this->withSession([
            'auth_user' => ['username' => 'admin', 'role' => 'admin'],
            'success' => $mensaje,
        ])->get('/admin/users')->assertOk()->getContent();

        $this->assertStringContainsString('successMessage', $html);
        $this->assertStringContainsString('aB3$k', $html, 'La contrasena debe llegar a la pagina.');
        $this->assertStringContainsString('credentialsBox', $html);
    }

    public function test_la_contrasena_generada_sirve_para_entrar(): void
    {
        $this->withSession(['auth_user' => ['username' => 'admin', 'role' => 'admin']])
            ->post('/admin/users', [
                'name' => 'Prueba Login',
                'username' => 'prueba_login',
                'role' => 'user',
                'random_password' => '1',
            ]);

        // Se extrae igual que lo hace la vista: "Password: X".
        $mensaje = (string) session('success');
        preg_match('/Password: (\S+)/', $mensaje, $m);
        $password = $m[1] ?? '';

        $this->assertNotSame('', $password, 'Debe poder extraerse la contrasena del mensaje.');

        // Y esa contrasena tiene que abrir la cuenta de verdad.
        $respuesta = $this->post('/login', [
            'username' => 'prueba_login',
            'password' => $password,
        ]);

        $this->assertTrue(
            session('auth_user') !== null
                || $respuesta->headers->get('Location') !== null,
            'La contrasena generada debe permitir iniciar sesion.'
        );

        $this->assertArrayNotHasKey(
            'login_fail_count',
            session()->all(),
            'No debe contarse como intento fallido.'
        );
    }
}
