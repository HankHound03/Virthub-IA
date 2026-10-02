<?php

namespace App\Http\Controllers;

use App\Services\FriendStore;
use App\Services\UserStore;
use App\Support\ActiveUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Busqueda de usuarios y gestion de solicitudes de amistad.
 */
class FriendController extends Controller
{
    public function __construct(
        private readonly UserStore $users,
        private readonly FriendStore $friends,
    ) {}

    public function index(Request $request): View
    {
        $currentUsername = ActiveUser::username(ActiveUser::from($request));
        $query = trim((string) $request->query('q', ''));

        return view('buscar-amigos', [
            'currentUser' => ActiveUser::from($request),
            'query' => $query,
            'results' => $this->search($query, $currentUsername),
            'pendingRequests' => $this->pendingRequests($currentUsername),
        ]);
    }

    public function sendRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:80'],
        ]);

        $from = ActiveUser::username(ActiveUser::from($request));
        $target = trim((string) $validated['username']);
        $targetUser = $this->users->findByUsername($target);

        if ($targetUser === null || ! ($targetUser['is_active'] ?? true)) {
            return redirect('/buscar-amigos')->with('error', 'Ese usuario no existe o esta inactivo.');
        }

        try {
            $this->friends->sendRequest($from, $target);

            return redirect('/buscar-amigos?q=' . rawurlencode($target))
                ->with('success', 'Solicitud de amistad enviada.');
        } catch (RuntimeException $e) {
            return redirect('/buscar-amigos?q=' . rawurlencode($target))->with('error', $e->getMessage());
        }
    }

    public function respond(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string'],
            'status' => ['required', 'in:accepted,declined'],
        ]);

        try {
            $this->friends->respondToRequest(
                (string) $validated['request_id'],
                ActiveUser::username(ActiveUser::from($request)),
                (string) $validated['status']
            );
        } catch (RuntimeException $e) {
            return redirect('/buscar-amigos')->with('error', $e->getMessage());
        }

        return redirect('/buscar-amigos')->with(
            'success',
            $validated['status'] === 'accepted' ? 'Solicitud aceptada.' : 'Solicitud rechazada.'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function search(string $query, string $currentUsername): array
    {
        if ($query === '') {
            return [];
        }

        return array_map(function (array $user) use ($currentUsername): array {
            $user['friendship'] = $this->friends->statusBetween($currentUsername, (string) ($user['username'] ?? ''));

            return $user;
        }, $this->users->searchPublicUsers($query));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingRequests(string $currentUsername): array
    {
        return array_map(function (array $request): array {
            $request['sender'] = $this->users->findPublicProfile((string) ($request['from'] ?? ''));

            return $request;
        }, $this->friends->pendingFor($currentUsername));
    }
}
