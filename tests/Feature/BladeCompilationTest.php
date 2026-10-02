<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Las plantillas deben compilarse por completo.
 *
 * Motivo: una directiva Blade mal escrita no da error de sintaxis, simplemente
 * queda sin compilar y se cuela en el PHP resultante. A partir de ahi, PHP
 * ejecuta ese texto como codigo y todo lo que viene despues deja de ser Blade.
 *
 * Ocurrio en la practica: un @php(...) mal formado en el bucle de adjuntos dejo
 * sin compilar el bloque de encuestas del foro, y la pagina respondia 500.
 * El fallo era invisible hasta que se renderizaba esa parte concreta.
 */
class BladeCompilationTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function allViews(): array
    {
        $files = File::allFiles(resource_path('views'));

        return array_values(array_map(
            static fn ($file): string => $file->getPathname(),
            array_filter($files, static fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ));
    }

    /**
     * Directivas que nunca deben aparecer como texto en el PHP compilado.
     *
     * @return array<int, string>
     */
    private function directives(): array
    {
        return ['@php', '@endphp', '@if', '@elseif', '@else', '@endif',
            '@foreach', '@endforeach', '@csrf', '@include', '@foreach', '@endunless'];
    }

    public function test_todas_las_plantillas_compilan_sin_directivas_sueltas(): void
    {
        $compiler = app('blade.compiler');
        $problems = [];

        foreach ($this->allViews() as $path) {
            $compiled = $compiler->compileString((string) file_get_contents($path));

            // Los comentarios PHP pueden mencionar directivas sin que sea un fallo.
            $clean = preg_replace('#/\*.*?\*/#s', '', $compiled);
            $clean = preg_replace('#(?<!:)//[^\n]*#', '', $clean);

            $found = [];

            foreach ($this->directives() as $directive) {
                if (preg_match('/(?<![\w$])' . preg_quote($directive, '/') . '(?![\w])/', $clean)) {
                    $found[] = $directive;
                }
            }

            if ($found !== []) {
                $problems[] = basename($path) . ': ' . implode(' ', array_unique($found));
            }
        }

        $this->assertSame(
            [],
            $problems,
            "Plantillas con directivas sin compilar (rompen todo lo que viene despues):\n" . implode("\n", $problems)
        );
    }

    public function test_la_vista_del_foro_renderiza_con_una_encuesta(): void
    {
        // La encuesta es la parte que quedo sin compilar en el fallo real.
        $html = view('forum', [
            'currentUser' => ['username' => 'admin', 'role' => 'admin'],
            'canPost' => true,
            'posts' => [[
                'id' => 'p1',
                'author' => 'admin',
                'title' => 'Con encuesta',
                'content' => 'Contenido',
                'image_path' => null,
                'attachments' => [],
                'poll' => [
                    'question' => 'Que tema quieres?',
                    'options' => [
                        ['id' => 'o1', 'label' => 'Opcion A', 'votes' => ['admin']],
                        ['id' => 'o2', 'label' => 'Opcion B', 'votes' => []],
                    ],
                    'created_at' => '2026-09-03 15:58:37',
                ],
                'reactions' => [],
                'comments' => [],
                'reports' => [],
                'created_at' => '2026-09-03 15:58:37',
            ]],
            'attachmentMaxBytes' => 5242880,
            'attachmentMaxLabel' => '5 MB',
            'chunkedMaxBytes' => 5 * 1024 ** 3,
            'chunkedMaxLabel' => '5 GB',
            'chunkBytes' => 8 * 1024 ** 2,
            'maxFilesPerPost' => 10,
        ])->render();

        $this->assertStringContainsString('forum-poll-stats', $html);
        $this->assertStringContainsString('1 voto(s)', $html);
        $this->assertStringContainsString('50%', $html);
    }

    public function test_la_vista_del_foro_avisa_cuando_falta_el_adjunto(): void
    {
        // Caso real: el registro apunta a un archivo que ya no existe.
        $html = view('forum', [
            'currentUser' => ['username' => 'admin', 'role' => 'admin'],
            'canPost' => true,
            'posts' => [[
                'id' => 'p1',
                'author' => 'maddys',
                'title' => 'Post con adjunto perdido',
                'content' => 'Contenido',
                'image_path' => 'uploads/forum/inexistente.jpg',
                'attachments' => [[
                    'type' => 'photo',
                    'name' => 'perdida.jpg',
                    'mime' => 'image/jpeg',
                    'path' => 'uploads/forum/photos/perdida.jpg',
                ]],
                'poll' => null,
                'reactions' => [],
                'comments' => [],
                'reports' => [],
                'created_at' => '2026-09-03 15:58:37',
            ]],
            'attachmentMaxBytes' => 5242880,
            'attachmentMaxLabel' => '5 MB',
            'chunkedMaxBytes' => 5 * 1024 ** 3,
            'chunkedMaxLabel' => '5 GB',
            'chunkBytes' => 8 * 1024 ** 2,
            'maxFilesPerPost' => 10,
        ])->render();

        // Debe avisar en lugar de pintar un enlace roto.
        $this->assertStringContainsString('attachment-missing', $html);
        $this->assertStringContainsString('ya no esta en el servidor', $html);
    }
}
