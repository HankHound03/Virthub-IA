<?php

namespace App\Http\Controllers;

use App\Services\ChatStore;
use App\Services\FriendStore;
use App\Services\UserStore;
use App\Support\ActiveUser;
use App\Support\ChatPresence;
use App\Support\OllamaClient;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Chat: contactos, presencia, anuncios del admin, conversaciones privadas y
 * el puente con la IA local de Ollama.
 */
class ChatController extends Controller
{
    private const MAX_MESSAGE_LENGTH = 1000;

    public function __construct(
        private readonly UserStore $users,
        private readonly ChatStore $chat,
        private readonly FriendStore $friends,
        private readonly ChatPresence $presence,
        private readonly OllamaClient $ollama,
        private readonly SecurityAudit $audit,
    ) {}

    public function contacts(Request $request): JsonResponse
    {
        $currentUsername = ActiveUser::username(ActiveUser::from($request));
        $this->users->touchPresence($currentUsername);

        $friendUsernames = $this->friends->friendsOf($currentUsername);

        $contacts = array_values(array_filter(
            $this->users->allPublicUsers(),
            static fn (array $user): bool => ($user['username'] ?? '') !== ''
                && ($user['username'] ?? '') !== $currentUsername
                && in_array((string) ($user['username'] ?? ''), $friendUsernames, true)
        ));

        return response()->json([
            'users' => array_map(function (array $user): array {
                $user['account_active'] = (bool) ($user['is_active'] ?? true);
                $user['presence_status'] = $this->presence->isRecent($user['last_seen_at'] ?? null) ? 'online' : 'offline';

                return $user;
            }, $contacts),
        ]);
    }

    public function friendRequests(Request $request): JsonResponse
    {
        $requests = array_map(function (array $friendRequest): array {
            $sender = $this->users->findPublicProfile((string) ($friendRequest['from'] ?? ''));

            return [
                'id' => $friendRequest['id'] ?? '',
                'from' => $friendRequest['from'] ?? '',
                'name' => $sender['name'] ?? ($friendRequest['from'] ?? ''),
                'created_at' => $friendRequest['created_at'] ?? null,
            ];
        }, $this->friends->pendingFor(ActiveUser::username(ActiveUser::from($request))));

        return response()->json(['requests' => $requests]);
    }

    public function respondToFriendRequest(Request $request, string $requestId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:accepted,declined'],
        ]);

        try {
            return response()->json([
                'request' => $this->friends->respondToRequest(
                    $requestId,
                    ActiveUser::username(ActiveUser::from($request)),
                    (string) $validated['status']
                ),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function touchPresence(Request $request): JsonResponse
    {
        $currentUser = ActiveUser::from($request);

        if (ActiveUser::isRegistered($currentUser)) {
            $this->users->touchPresence(ActiveUser::username($currentUser));
        }

        return response()->json(['ok' => true]);
    }

    public function conversation(Request $request, string $username): JsonResponse
    {
        $currentUser = ActiveUser::from($request);
        $this->users->touchPresence(ActiveUser::username($currentUser));

        $guard = $this->guardConversation($currentUser, $username);

        if ($guard !== null) {
            return $guard;
        }

        $messages = $this->chat->getConversationMessages(ActiveUser::username($currentUser), $username);

        return response()->json(['messages' => $this->decorate($messages, $username)]);
    }

    public function sendMessage(Request $request, string $username): JsonResponse
    {
        $currentUser = ActiveUser::from($request);
        $this->users->touchPresence(ActiveUser::username($currentUser));

        $guard = $this->guardConversation($currentUser, $username);

        if ($guard !== null) {
            return $guard;
        }

        $validated = $request->validate([
            'message' => 'required|string|max:' . self::MAX_MESSAGE_LENGTH,
        ]);

        $message = (string) $validated['message'];

        return $this->ollama->isChatUsername($username)
            ? $this->sendToOllama($request, $currentUser, $message)
            : $this->sendToUser($request, $currentUser, $username, $message);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $messages = $this->chat->getBroadcastMessages();

        return response()->json(['messages' => $this->decorate($messages)]);
    }

    public function publishBroadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:' . self::MAX_MESSAGE_LENGTH,
        ]);

        $this->users->touchPresence(ActiveUser::username(ActiveUser::from($request)));

        return response()->json([
            'message' => $this->chat->appendBroadcastMessage(
                ActiveUser::username(ActiveUser::from($request)),
                (string) $validated['message']
            ),
        ], 201);
    }

    private function sendToUser(Request $request, ?array $currentUser, string $username, string $message): JsonResponse
    {
        $entry = $this->chat->appendConversationMessage(ActiveUser::username($currentUser), $username, $message);
        $sender = $this->users->findByUsername((string) ($entry['from'] ?? ''));

        if ($sender !== null) {
            $entry['profile_image_path'] = $sender['profile_image_path'] ?? null;
        }

        return response()->json(['message' => $entry], 201);
    }

    private function sendToOllama(Request $request, ?array $currentUser, string $message): JsonResponse
    {
        if (! ActiveUser::isAdmin($currentUser)) {
            return response()->json(['error' => 'Solo el admin puede usar esta IA.'], 403);
        }

        if (! $this->ollama->isEnabled()) {
            return response()->json([
                'error' => 'Configura OLLAMA_BASE_URL en .env para habilitar la IA local.',
            ], 503);
        }

        $username = ActiveUser::username($currentUser);

        try {
            $response = $this->ollama->chat($this->buildOllamaHistory($username, $message));
        } catch (Throwable) {
            return response()->json([
                'error' => 'No se pudo conectar con Ollama o la respuesta tardó demasiado.',
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'error' => 'Ollama devolvio un error.',
                'details' => $response->json() ?: $response->body(),
            ], 502);
        }

        $reply = trim((string) data_get($response->json(), 'message.content', ''));

        if ($reply === '') {
            return response()->json(['error' => 'Ollama no devolvio texto util.'], 502);
        }

        $userMessage = $this->chat->appendConversationMessage($username, $this->ollama->chatUsername(), $message);
        $assistantMessage = $this->chat->appendConversationMessage($this->ollama->chatUsername(), $username, $reply);

        return response()->json([
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'model' => $this->ollama->model(),
        ], 201);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildOllamaHistory(string $username, string $message): array
    {
        $history = [[
            'role' => 'system',
            'content' => $this->ollama->systemPrompt(),
        ]];

        foreach ($this->chat->getConversationMessages($username, $this->ollama->chatUsername()) as $existing) {
            $history[] = [
                'role' => ($existing['from'] ?? '') === $this->ollama->chatUsername() ? 'assistant' : 'user',
                'content' => (string) ($existing['message'] ?? ''),
            ];
        }

        $history[] = ['role' => 'user', 'content' => $message];

        return $history;
    }

    /**
     * Devuelve una respuesta de error si la conversacion no esta permitida.
     *
     * Reglas: los invitados no tienen mensajes privados y solo se puede hablar
     * con amigos aceptados (o con la IA).
     */
    private function guardConversation(?array $currentUser, string $username): ?JsonResponse
    {
        if (ActiveUser::isGuest($currentUser)) {
            return response()->json(['error' => 'Los invitados no pueden abrir conversaciones privadas.'], 403);
        }

        if ($this->ollama->isChatUsername($username)) {
            return null;
        }

        $target = $this->users->findByUsername($username);

        if ($target === null || ! ($target['is_active'] ?? true)) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        if (! in_array($username, $this->friends->friendsOf(ActiveUser::username($currentUser)), true)) {
            return response()->json(['error' => 'Solo puedes conversar con tus amigos aceptados.'], 403);
        }

        return null;
    }

    /**
     * Adjunta la imagen de perfil del remitente a cada mensaje.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function decorate(array $messages, ?string $hideAvatarFor = null): array
    {
        return array_map(function (array $message) use ($hideAvatarFor): array {
            $sender = $this->users->findByUsername((string) ($message['from'] ?? ''));

            if ($sender !== null) {
                $message['profile_image_path'] = $sender['profile_image_path'] ?? null;
            }

            if ($hideAvatarFor !== null && ($message['from'] ?? '') === $this->ollama->chatUsername()) {
                $message['profile_image_path'] = null;
            }

            return $message;
        }, $messages);
    }
}
