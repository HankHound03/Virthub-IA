<?php

namespace App\Http\Controllers;

use App\Services\ForumStore;
use App\Services\FriendStore;
use App\Services\ProfileStore;
use App\Services\UserStore;
use App\Support\ActiveUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfil publico de un usuario: muro propio, apariencia y contrasena.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly UserStore $users,
        private readonly ProfileStore $profiles,
        private readonly ForumStore $forum,
        private readonly FriendStore $friends,
    ) {}

    public function show(Request $request, string $username): View
    {
        $profile = $this->users->findPublicProfile($username);

        abort_if($profile === null, 404);

        $currentUser = ActiveUser::from($request);

        return view('perfil', [
            'currentUser' => $currentUser,
            'profile' => $profile,
            'profilePosts' => $this->postsFor($username),
            'friendship' => ActiveUser::isRegistered($currentUser)
                ? $this->friends->statusBetween(ActiveUser::username($currentUser), $username)
                : null,
            'isOwner' => ActiveUser::username($currentUser) === (string) ($profile['username'] ?? ''),
        ]);
    }

    public function publish(Request $request, string $username, ProfileStore $store): RedirectResponse
    {
        // El middleware ya garantiza una cuenta registrada.
        abort_unless(ActiveUser::username(ActiveUser::from($request)) === $username, 403, 'Solo puedes publicar en tu propio perfil.');

        abort_if($this->users->findPublicProfile($username) === null, 404);

        $validated = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $store->addPost($username, (string) $validated['content']);

        return redirect('/perfil/' . rawurlencode($username))->with('success', 'Publicacion añadida a tu perfil.');
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $username = ActiveUser::username(ActiveUser::from($request));

        $validated = $request->validate([
            'frame_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
        ]);

        $existingUser = $this->users->findByUsername($username);

        if ($existingUser === null) {
            return redirect('/')->with('error', 'Usuario no encontrado.');
        }

        $newImagePath = null;

        if ($request->hasFile('profile_image')) {
            $newImagePath = $this->storeAvatar($request, (string) ($existingUser['profile_image_path'] ?? ''));
        }

        $this->users->updateProfileAppearance($username, $newImagePath, (string) $validated['frame_color']);

        return redirect('/')->with('success', 'Perfil actualizado correctamente.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $username = ActiveUser::username(ActiveUser::from($request));

        $validated = $request->validate([
            'current_password' => 'required|string|min:6|max:72',
            'new_password' => 'required|string|min:6|max:72|confirmed',
        ]);

        if (! $this->users->verifyPassword($username, (string) $validated['current_password'])) {
            return redirect('/')->with('error', 'La contrasena actual no es correcta.');
        }

        $this->users->updatePassword($username, (string) $validated['new_password']);

        return redirect('/')->with('success', 'Tu contrasena fue actualizada correctamente.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function postsFor(string $username): array
    {
        $posts = array_merge(
            $this->profiles->latestPostsFor($username),
            $this->forum->postsByAuthor($username)
        );

        usort($posts, static fn (array $a, array $b): int => strcmp(
            (string) ($b['created_at'] ?? ''),
            (string) ($a['created_at'] ?? '')
        ));

        return array_slice($posts, 0, 100);
    }

    /**
     * Guarda el avatar y elimina el anterior para no acumular archivos huerfanos.
     *
     * Se escribe en storage/app/public, que en Docker esta en el volumen
     * persistente; public/uploads se perderia al reconstruir el contenedor.
     */
    private function storeAvatar(Request $request, string $oldImagePath): string
    {
        $uploadsDir = storage_path('app/public/uploads/profiles');

        if (! is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $uploaded = $request->file('profile_image');
        $extension = strtolower((string) $uploaded->getClientOriginalExtension()) ?: 'jpg';
        $filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;

        $uploaded->move($uploadsDir, $filename);

        if ($oldImagePath !== '') {
            $oldFullPath = storage_path('app/public/' . ltrim($oldImagePath, '/'));

            if (is_file($oldFullPath)) {
                @unlink($oldFullPath);
            }
        }

        // La ruta guardada se resuelve contra el enlace public/storage.
        return 'uploads/profiles/' . $filename;
    }
}
