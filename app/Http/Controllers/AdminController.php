<?php

namespace App\Http\Controllers;

use App\Services\ForumStore;
use App\Services\UserStore;
use App\Support\ActiveUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Panel de administracion: usuarios, moderacion del foro y sugerencias.
 */
class AdminController extends Controller
{
    private const USERNAME_RULES = ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.]+$/'];

    public function __construct(
        private readonly UserStore $users,
        private readonly ForumStore $forum,
    ) {}

    public function users(): View
    {
        return view('admin-users', [
            'currentUser' => ActiveUser::from(request()),
            'users' => $this->users->allPublicUsers(),
            'forumReports' => $this->forumReports(),
            'suggestions' => $this->suggestions(),
        ]);
    }

    public function createUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'username' => ['nullable', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.]+$/'],
            'password' => ['nullable', 'string', 'min:6', 'max:72'],
            'role' => 'required|in:user,admin',
            'random_username' => 'nullable|in:1',
            'random_password' => 'nullable|in:1',
        ]);

        $useRandomUsername = $request->boolean('random_username');
        $useRandomPassword = $request->boolean('random_password');
        $role = (string) $validated['role'];

        if ($role === 'admin' && ($useRandomUsername || $useRandomPassword)) {
            return redirect('/admin/users')->with('error', 'Para usuarios admin debes definir username y password manualmente.');
        }

        $username = $useRandomUsername
            ? $this->users->generateRandomUsername('virt')
            : trim((string) ($validated['username'] ?? ''));

        if ($username === '') {
            return redirect('/admin/users')->with('error', 'Debes indicar username o usar modo aleatorio.');
        }

        $password = $useRandomPassword
            ? $this->users->generateRandomPassword()
            : (string) ($validated['password'] ?? '');

        if (trim($password) === '') {
            return redirect('/admin/users')->with('error', 'Debes indicar password o usar modo aleatorio.');
        }

        try {
            $created = $this->users->createUser($username, $password, $role, (string) ($validated['name'] ?? ''));

            $message = "Usuario creado: {$created['username']} ({$created['role']})";

            if ($useRandomPassword) {
                $message .= " | Password: {$password}";
            }

            return redirect('/admin/users')->with('success', $message);
        } catch (RuntimeException $e) {
            return redirect('/admin/users')->with('error', $e->getMessage());
        }
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => self::USERNAME_RULES,
            'new_password' => 'required|string|min:6|max:72',
        ]);

        try {
            $this->users->updatePassword((string) $validated['username'], (string) $validated['new_password']);
        } catch (RuntimeException $e) {
            return redirect('/admin/users')->with('error', $e->getMessage());
        }

        return redirect('/admin/users')->with('success', 'Password actualizado para ' . $validated['username'] . '.');
    }

    public function deactivateUser(Request $request): RedirectResponse
    {
        return $this->flipUserState($request, 'deactivateUser', 'desactivado');
    }

    public function activateUser(Request $request): RedirectResponse
    {
        return $this->flipUserState($request, 'activateUser', 'activado');
    }

    public function deleteUser(Request $request): RedirectResponse
    {
        $validated = $request->validate(['username' => self::USERNAME_RULES]);

        try {
            $this->users->deleteUser((string) $validated['username']);
        } catch (RuntimeException $e) {
            return redirect('/admin/users')->with('error', $e->getMessage());
        }

        return redirect('/admin/users')->with('success', 'Usuario ' . $validated['username'] . ' eliminado.');
    }

    public function deleteForumReport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'post_id' => 'required|string',
            'report_id' => 'required|string',
        ]);

        $deleted = $this->forum->removeReport(
            (string) $validated['post_id'],
            (string) $validated['report_id']
        );

        if (! $deleted) {
            return redirect('/admin/users')->with('error', 'No se pudo eliminar el reporte o ya no existe.');
        }

        return redirect('/admin/users')->with('success', 'Reporte marcado como verificado y eliminado.');
    }

    private function flipUserState(Request $request, string $method, string $label): RedirectResponse
    {
        $validated = $request->validate(['username' => self::USERNAME_RULES]);

        try {
            $this->users->{$method}((string) $validated['username']);
        } catch (RuntimeException $e) {
            return redirect('/admin/users')->with('error', $e->getMessage());
        }

        return redirect('/admin/users')->with('success', 'Usuario ' . $validated['username'] . ' ' . $label . '.');
    }

    /**
     * Aplana los reportes del foro en una lista ordenada por fecha descendente.
     *
     * @return array<int, array<string, string>>
     */
    private function forumReports(): array
    {
        $reports = [];

        foreach ($this->forum->latestPosts(300) as $post) {
            foreach (is_array($post['reports'] ?? null) ? $post['reports'] : [] as $report) {
                $reports[] = [
                    'report_id' => (string) ($report['id'] ?? ''),
                    'post_id' => (string) ($post['id'] ?? ''),
                    'post_title' => (string) ($post['title'] ?? ''),
                    'post_author' => (string) ($post['author'] ?? ''),
                    'post_created_at' => (string) ($post['created_at'] ?? ''),
                    'post_content' => (string) ($post['content'] ?? ''),
                    'reporter' => (string) ($report['reporter'] ?? ''),
                    'reason' => (string) ($report['reason'] ?? 'Sin detalle'),
                    'reported_at' => (string) ($report['created_at'] ?? ''),
                ];
            }
        }

        usort($reports, static fn (array $a, array $b): int => strcmp(
            (string) ($b['reported_at'] ?? ''),
            (string) ($a['reported_at'] ?? '')
        ));

        return $reports;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function suggestions(): array
    {
        $file = storage_path('app/data/suggestions.json');

        if (! is_file($file)) {
            return [];
        }

        $raw = @file_get_contents($file);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded) || ! is_array($decoded['suggestions'] ?? null)) {
            return [];
        }

        $suggestions = $decoded['suggestions'];

        usort($suggestions, static fn (array $a, array $b): int => strcmp(
            (string) ($b['created_at'] ?? ''),
            (string) ($a['created_at'] ?? '')
        ));

        return $suggestions;
    }
}
