<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Coherencia de los limites de subida que se despliegan.
 *
 * El fallo original: la imagen php:8.4-apache no carga ningun php.ini, asi que
 * PHP usaba su valor de fabrica upload_max_filesize = 2M. Como la aplicacion
 * valida hasta 5 MB, el limite efectivo era 2 MB y todo archivo mayor se
 * descartaba EN SILENCIO: PHP vacia $_FILES sin devolver error, la validacion
 * de Laravel nunca se ejecuta y el usuario ve que "no se sube nada".
 *
 * Estos tests se ejecutan contra docker/php.ini, que es el archivo que se copia
 * a la imagen, no contra el PHP del host: lo que importa es lo que se despliega.
 */
class UploadLimitsTest extends TestCase
{
    /** Límite mayor del código: 5 MB para adjuntos del foro. */
    private const APP_LIMIT_BYTES = 5 * 1024 * 1024;

    /**
     * @return array<string, string>
     */
    private function deployedIni(): array
    {
        $path = base_path('docker/php.ini');

        $this->assertFileExists($path, 'docker/php.ini debe existir y copiarse en la imagen.');

        $settings = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, ';')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $settings[trim($key)] = trim($value);
            }
        }

        return $settings;
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $bytes = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $bytes * 1024 ** 3,
            'm' => $bytes * 1024 ** 2,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }

    public function test_las_subidas_estan_habilitadas(): void
    {
        $ini = $this->deployedIni();

        $this->assertArrayHasKey('file_uploads', $ini);
        $this->assertSame('On', $ini['file_uploads']);
    }

    /**
     * Si el formulario completo supera post_max_size, PHP descarta todo y tanto
     * $_POST como $_FILES llegan vacios. Por eso post_max_size debe ser mayor.
     */
    public function test_post_max_size_es_mayor_que_upload_max_filesize(): void
    {
        $ini = $this->deployedIni();

        $upload = $this->toBytes($ini['upload_max_filesize'] ?? '0');
        $post = $this->toBytes($ini['post_max_size'] ?? '0');

        $this->assertGreaterThan(0, $upload, 'upload_max_filesize debe estar definido explicitamente.');
        $this->assertGreaterThan(
            $upload,
            $post,
            'post_max_size debe ser mayor que upload_max_filesize o el formulario se descarta entero.'
        );
    }

    /**
     * El limite de PHP debe quedar por encima del limite de la aplicacion; si no,
     * PHP corta antes de que la validacion pueda dar un mensaje al usuario.
     */
    public function test_el_limite_de_php_supera_el_de_la_aplicacion(): void
    {
        $ini = $this->deployedIni();

        $upload = $this->toBytes($ini['upload_max_filesize'] ?? '0');

        $this->assertGreaterThan(
            self::APP_LIMIT_BYTES,
            $upload,
            'Si PHP corta antes que la validacion, el archivo se pierde sin mensaje de error.'
        );
    }

    public function test_hay_memoria_suficiente_para_procesar_los_adjuntos(): void
    {
        $ini = $this->deployedIni();

        $memory = $this->toBytes($ini['memory_limit'] ?? '0');

        $this->assertGreaterThanOrEqual(128 * 1024 * 1024, $memory);
    }

    public function test_la_imagen_copia_el_php_ini(): void
    {
        $dockerfile = (string) file_get_contents(base_path('Dockerfile'));

        $this->assertStringContainsString(
            'docker/php.ini',
            $dockerfile,
            'Sin esta copia, la imagen arranca sin php.ini y vuelve al limite de 2 MB.'
        );
    }
}
