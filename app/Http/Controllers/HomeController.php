<?php

namespace App\Http\Controllers;

use App\Services\UserWorkspaceStore;
use App\Support\ActiveUser;
use App\Support\SystemStatusMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Escritorio principal: panel de estado, widgets y estado persistente del
 * espacio de trabajo del usuario.
 */
class HomeController extends Controller
{
    public function __construct(private readonly SystemStatusMonitor $monitor) {}

    public function index(Request $request): View
    {
        $currentUser = ActiveUser::from($request);

        return view('home', [
            'currentUser' => $currentUser,
            'systemStatus' => $this->systemStatusFor($currentUser),
            'guestRemainingSeconds' => $this->guestRemainingSeconds($request, $currentUser),
            'workspaceState' => ActiveUser::isRegistered($currentUser)
                ? app(UserWorkspaceStore::class)->getState(ActiveUser::username($currentUser))
                : null,
        ]);
    }

    public function state(Request $request, UserWorkspaceStore $workspace): JsonResponse
    {
        return response()->json([
            'state' => $workspace->getState(ActiveUser::username(ActiveUser::from($request))),
        ]);
    }

    public function saveState(Request $request, UserWorkspaceStore $workspace): JsonResponse
    {
        $validated = $request->validate([
            'todos' => 'required|array|max:120',
            'notes' => 'required|string|max:2400',
            'calendarEvents' => 'required|array',
        ]);

        return response()->json([
            'state' => $workspace->saveState(ActiveUser::username(ActiveUser::from($request)), $validated),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $currentUser
     * @return array<string, mixed>|null
     */
    private function systemStatusFor(?array $currentUser): ?array
    {
        if (! ActiveUser::isAdmin($currentUser)) {
            return null;
        }

        $ttl = max(1, (int) config('virthub.status.cache_seconds', 15));

        return Cache::remember('virthub.system_status', $ttl, fn (): array => $this->monitor->snapshot());
    }

    /**
     * @param  array<string, mixed>|null  $currentUser
     */
    private function guestRemainingSeconds(Request $request, ?array $currentUser): ?int
    {
        if (! ActiveUser::isGuest($currentUser)) {
            return null;
        }

        return max(0, (int) $request->session()->get('guest_expires_at', 0) - time());
    }
}
