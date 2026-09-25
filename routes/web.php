<?php

use App\Services\ChatStore;
use App\Services\ForumStore;
use App\Services\FriendStore;
use App\Services\JsonUserStore;
use App\Services\ProfileStore;
use App\Services\TwoFactorService;
use App\Services\UserWorkspaceStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

if (!function_exists('virthub_system_status')) {
	function virthub_system_status(): array
	{
		$isWindows = stripos(PHP_OS_FAMILY, 'Windows') !== false;
		$cpuUsagePercent = null;
		$memTotalKb = null;
		$memAvailableKb = null;

		// ===== CPU =====
		if ($isWindows) {
			// Windows: usar WMI
			$cpuRaw = trim((string) @shell_exec(
				'powershell -NoProfile -Command "(Get-CimInstance Win32_Processor | Measure-Object LoadPercentage -Average).Average" 2>$null'
			));
			if (is_numeric($cpuRaw)) {
				$cpuUsagePercent = round(min(100, max(0, (float) $cpuRaw)), 1);
			}
		} else {
			// Linux: sys_getloadavg()
			if (function_exists('sys_getloadavg')) {
				$load = sys_getloadavg();
				$cpuCores = (int) trim((string) @shell_exec('nproc 2>/dev/null'));
				if ($cpuCores <= 0) {
					$cpuCores = 1;
				}
				if (isset($load[0])) {
					$cpuUsagePercent = round(min(100, max(0, (((float) $load[0]) / $cpuCores) * 100)), 1);
				}
			}
		}

		// ===== RAM =====
		if ($isWindows) {
			// Windows: usar PowerShell
			$totalRaw = trim((string) @shell_exec(
				'powershell -NoProfile -Command "(Get-CimInstance Win32_OperatingSystem).TotalVisibleMemorySize" 2>$null'
			));
			$freeRaw = trim((string) @shell_exec(
				'powershell -NoProfile -Command "(Get-CimInstance Win32_OperatingSystem).FreePhysicalMemory" 2>$null'
			));

			if (is_numeric($totalRaw)) {
				$memTotalKb = (int) $totalRaw;
			}
			if (is_numeric($freeRaw)) {
				$memAvailableKb = (int) $freeRaw;
			}
		} else {
			// Linux: /proc/meminfo
			$memInfoRaw = @file_get_contents('/proc/meminfo');

			if (is_string($memInfoRaw) && $memInfoRaw !== '') {
				if (preg_match('/^MemTotal:\s+(\d+)\s+kB/im', $memInfoRaw, $mTotal)) {
					$memTotalKb = (int) $mTotal[1];
				}
				if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/im', $memInfoRaw, $mAvail)) {
					$memAvailableKb = (int) $mAvail[1];
				}
			}
		}

		$ramUsedMb = null;
		$ramUsedPercent = null;
		if ($memTotalKb && $memAvailableKb !== null && $memTotalKb > 0) {
			$memUsedKb = max(0, $memTotalKb - $memAvailableKb);
			$ramUsedMb = round($memUsedKb / 1024, 1);
			$ramUsedPercent = round(($memUsedKb / $memTotalKb) * 100, 1);
		}

		// ===== DISCO =====
		$diskTotal = @disk_total_space(DIRECTORY_SEPARATOR);
		$diskFree = @disk_free_space(DIRECTORY_SEPARATOR);
		$diskUsedPercent = null;

		if (is_int($diskTotal) || is_float($diskTotal)) {
			if ($diskTotal > 0 && (is_int($diskFree) || is_float($diskFree))) {
				$diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
			}
		}

		// ===== WEBTOP =====
		$webtopUrl = (string) env('WEBTOP_URL', '');
		$webtopOnline = false;

		if ($webtopUrl !== '') {
			try {
				$webtopOnline = Http::timeout(3)->get($webtopUrl)->successful();
			} catch (Throwable $e) {
				$webtopOnline = false;
			}
		}

		// ===== CONTENEDORES =====
		$containerStatus = [];
		$containerIds = [0, 2, 3, 4, 5, 6, 7]; // Sin ct1
		foreach ($containerIds as $i) {
			$containerKey = 'CONTAINER_CT' . $i;
			$containerUrl = (string) env($containerKey, '');
			$containerStatus[$i] = false;

			if ($containerUrl !== '') {
				try {
					$containerStatus[$i] = Http::timeout(3)->get($containerUrl)->successful();
				} catch (Throwable $e) {
					$containerStatus[$i] = false;
				}
			}
		}

		return [
			'timestamp' => date('Y-m-d H:i:s'),
			'timestamp_utc' => gmdate('c'),
			'cpu_usage_percent' => $cpuUsagePercent,
			'ram_used_mb' => $ramUsedMb,
			'ram_used_percent' => $ramUsedPercent,
			'disk_used_percent' => $diskUsedPercent,
			'webtop_online' => $webtopOnline,
			'container_status' => $containerStatus,
		];
	}
}

if (!function_exists('virthub_cached_feed_items')) {
	function virthub_cached_feed_items(string $cacheKey, string $feedUrl): array
	{
		return Cache::remember($cacheKey, 300, function () use ($feedUrl): array {
			try {
				$response = Http::timeout(8)->withoutVerifying()->get($feedUrl);

				if (!$response->successful()) {
					return [];
				}

				$xml = @simplexml_load_string($response->body());
				if (!$xml || !isset($xml->channel->item)) {
					return [];
				}

				$items = [];
				foreach ($xml->channel->item as $item) {
					$items[] = [
						'title' => (string) $item->title,
						'link' => (string) $item->link,
					];

					if (count($items) >= 6) {
						break;
					}
				}

				return $items;
			} catch (Throwable $exception) {
				return [];
			}
		});
	}
}

if (!function_exists('virthub_active_user')) {
	function virthub_active_user(Request $request, JsonUserStore $users): ?array
	{
		$sessionUser = $request->session()->get('auth_user');

		if (!$sessionUser || !isset($sessionUser['username'])) {
			return null;
		}

		if (($sessionUser['role'] ?? '') === 'guest') {
			$guestExpiresAt = (int) $request->session()->get('guest_expires_at', 0);

			if ($guestExpiresAt <= 0 || time() > $guestExpiresAt) {
				$request->session()->invalidate();
				$request->session()->regenerateToken();

				return null;
			}

			return [
				'username' => (string) $sessionUser['username'],
				'role' => 'guest',
				'profile_image_path' => null,
				'profile_frame_color' => '#6ea8ff',
			];
		}

		$freshUser = $users->findByUsername((string) $sessionUser['username']);

		if (!$freshUser || !($freshUser['is_active'] ?? true)) {
			$request->session()->invalidate();
			$request->session()->regenerateToken();

			return null;
		}

		return [
			'name' => (string) ($freshUser['name'] ?? $freshUser['username'] ?? ''),
			'username' => $freshUser['username'],
			'role' => $freshUser['role'] ?? 'user',
			'profile_image_path' => $freshUser['profile_image_path'] ?? null,
			'profile_frame_color' => $freshUser['profile_frame_color'] ?? '#6ea8ff',
		];
	}
}

if (!function_exists('virthub_audit')) {
	function virthub_audit(Request $request, string $event, ?array $user = null, array $context = []): void
	{
		Log::channel('security')->info($event, array_merge([
			'username' => $user['username'] ?? null,
			'role' => $user['role'] ?? null,
			'ip' => $request->ip(),
			'user_agent' => substr((string) $request->userAgent(), 0, 500),
		], $context));
	}
}

if (!function_exists('virthub_installation_request_is_authorized')) {
	function virthub_installation_request_is_authorized(Request $request, JsonUserStore $users): bool
	{
		$installationKey = (string) config('installation.key', '');
		$providedKey = (string) ($request->query('key', $request->input('key', '')));

		if ($installationKey !== '' && $providedKey !== '') {
			return hash_equals($installationKey, $providedKey);
		}

		return !$users->hasAdminAccount()
			&& (app()->environment('testing')
				|| in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true));
	}
}

if (!function_exists('virthub_get_container_url')) {
	function virthub_get_container_url(?array $user): string
	{
		if (!$user) {
			return (string) env('CONTAINER_CT7', 'https://ct7.virthub.dpdns.org/');
		}

		$username = $user['username'] ?? 'guest';
		$role = $user['role'] ?? 'user';

		// Usuario invitado → ct7
		if ($role === 'guest') {
			return (string) env('CONTAINER_CT7', 'https://ct7.virthub.dpdns.org/');
		}

		// Admin y hankhound03 → ct0
		$adminUsers = (string) env('CONTAINER_ADMIN_USERS', 'admin,hankhound03');
		$adminUsersList = array_map('trim', explode(',', $adminUsers));

		if (in_array($username, $adminUsersList, true)) {
			return (string) env('CONTAINER_CT0', 'https://ct0.virthub.dpdns.org/');
		}

		// Otros usuarios → distribución ct2 a ct6
		$containerIndex = (crc32($username) % 5) + 2;
		$containerKey = 'CONTAINER_CT' . $containerIndex;

		return (string) env($containerKey, 'https://ct' . $containerIndex . '.virthub.dpdns.org/');
	}
}

if (!function_exists('virthub_chat_is_recent_presence')) {
	function virthub_chat_is_recent_presence(?string $lastSeenAt, int $windowSeconds = 90): bool
	{
		if (!$lastSeenAt) {
			return false;
		}

		$timestamp = strtotime($lastSeenAt);
		if ($timestamp === false) {
			return false;
		}

		return (time() - $timestamp) <= $windowSeconds;
	}
}

if (!function_exists('virthub_ollama_settings')) {
	function virthub_ollama_settings(): array
	{
		$baseUrl = trim((string) env('OLLAMA_BASE_URL', ''));
		$model = trim((string) env('OLLAMA_MODEL', 'llama3.1'));
		$systemPrompt = trim((string) env('OLLAMA_SYSTEM_PROMPT', 'Responde en español, de forma clara, breve y útil. Usa listas y saltos de línea cuando ayuden a leer mejor la respuesta.'));
		$requestTimeout = (int) env('OLLAMA_REQUEST_TIMEOUT', 9999);
		$connectTimeout = (int) env('OLLAMA_CONNECT_TIMEOUT', 5);

		return [
			'enabled' => $baseUrl !== '',
			'base_url' => $baseUrl,
			'model' => $model !== '' ? $model : 'llama3.1',
			'system_prompt' => $systemPrompt !== '' ? $systemPrompt : 'Responde en español, de forma clara, breve y útil.',
			'request_timeout' => $requestTimeout > 0 ? $requestTimeout : 9999,
			'connect_timeout' => $connectTimeout > 0 ? $connectTimeout : 5,
		];
	}
}

if (!function_exists('virthub_ollama_chat_username')) {
	function virthub_ollama_chat_username(): string
	{
		return 'ollama';
	}
}

Route::get('/install', function (Request $request, JsonUserStore $users) {
	if (!virthub_installation_request_is_authorized($request, $users)) {
		abort(404);
	}

	$providedKey = (string) $request->query('key', '');

	$installComplete = $users->hasAdminAccount();

	return view('install', [
		'installComplete' => $installComplete,
		'statusMessage' => session('status_message'),
		'installationKey' => $providedKey,
	]);
});

Route::post('/install', function (Request $request, JsonUserStore $users) {
	if (!virthub_installation_request_is_authorized($request, $users)) {
		abort(404);
	}

	if ($users->hasAdminAccount()) {
		abort(403, 'La instalacion ya fue completada.');
	}

	$validated = $request->validate([
		'admin_username' => ['required', 'string', 'max:50'],
		'admin_password' => ['required', 'confirmed', 'min:8'],
	]);

	$users->createOrUpdateAdmin((string) $validated['admin_username'], (string) $validated['admin_password']);

	return redirect('/')->with('status_message', 'Instalación completada. Ya puedes iniciar sesión con el administrador creado.');
});

Route::get('/', function (Request $request, JsonUserStore $users) {
	$users->bootstrapAdminFromEnv();
	$currentUser = virthub_active_user($request, $users);
	$systemStatus = null;
	$guestRemainingSeconds = null;
	$workspaceState = null;

	if ($currentUser && ($currentUser['role'] ?? 'user') === 'admin') {
		$systemStatus = Cache::remember('virthub.system_status', 15, fn (): array => virthub_system_status());
	}

	if ($currentUser && ($currentUser['role'] ?? 'guest') !== 'guest') {
		$workspaceState = app(UserWorkspaceStore::class)->getState((string) $currentUser['username']);
	}

	if ($currentUser && ($currentUser['role'] ?? 'user') === 'guest') {
		$guestRemainingSeconds = max(0, (int) $request->session()->get('guest_expires_at', 0) - time());
	}

	return view('home', [
		'currentUser' => $currentUser,
		'systemStatus' => $systemStatus,
		'guestRemainingSeconds' => $guestRemainingSeconds,
		'workspaceState' => $workspaceState,
	]);
});

	Route::get('/home/state', function (Request $request, JsonUserStore $users, UserWorkspaceStore $workspaceStore) {
		$authUser = virthub_active_user($request, $users);

		if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
			return response()->json(['error' => 'Debes iniciar sesion con usuario registrado.'], 403);
		}

		return response()->json([
			'state' => $workspaceStore->getState((string) $authUser['username']),
		], 200);
	});

Route::post('/home/state', function (Request $request, JsonUserStore $users, UserWorkspaceStore $workspaceStore) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Debes iniciar sesion con usuario registrado.'], 403);
	}

	$validated = $request->validate([
		'todos' => 'required|array|max:120',
		'notes' => 'required|string|max:2400',
		'calendarEvents' => 'required|array',
	]);

	$state = $workspaceStore->saveState((string) $authUser['username'], $validated);

	return response()->json(['state' => $state], 200);
})->middleware('throttle:60,1');

Route::get('/foro', function (Request $request, JsonUserStore $users, ForumStore $forumStore) {
	$users->bootstrapAdminFromEnv();
	$currentUser = virthub_active_user($request, $users);
	$canPost = !empty($currentUser) && ($currentUser['role'] ?? 'guest') !== 'guest';

	return view('forum', [
		'currentUser' => $currentUser,
		'canPost' => $canPost,
		'posts' => $forumStore->latestPosts(120),
	]);
});

Route::get('/buscar-amigos', function (Request $request, JsonUserStore $users, FriendStore $friends) {
	$users->bootstrapAdminFromEnv();
	$currentUser = virthub_active_user($request, $users);

	if (!$currentUser || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion para buscar amigos.');
	}

	$query = trim((string) $request->query('q', ''));
	$currentUsername = (string) $currentUser['username'];
	$results = $query === '' ? [] : $users->searchPublicUsers($query);
	$results = array_map(function (array $user) use ($friends, $currentUsername): array {
		$user['friendship'] = $friends->statusBetween($currentUsername, (string) ($user['username'] ?? ''));
		return $user;
	}, $results);
	$pendingRequests = $friends->pendingFor($currentUsername);
	$pendingRequests = array_map(function (array $request) use ($users): array {
		$request['sender'] = $users->findPublicProfile((string) ($request['from'] ?? ''));
		return $request;
	}, $pendingRequests);

	return view('buscar-amigos', [
		'currentUser' => $currentUser,
		'query' => $query,
		'results' => $results,
		'pendingRequests' => $pendingRequests,
	]);
});

Route::post('/amistad/solicitud', function (Request $request, JsonUserStore $users, FriendStore $friends) {
	$currentUser = virthub_active_user($request, $users);

	if (!$currentUser || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion para enviar solicitudes de amistad.');
	}

	$validated = $request->validate([
		'username' => ['required', 'string', 'max:80'],
	]);
	$targetUsername = trim((string) $validated['username']);
	$target = $users->findByUsername($targetUsername);

	if (!$target || !($target['is_active'] ?? true)) {
		return redirect('/buscar-amigos')->with('error', 'Ese usuario no existe o esta inactivo.');
	}

	try {
		$friends->sendRequest((string) $currentUser['username'], $targetUsername);
		return redirect('/buscar-amigos?q=' . rawurlencode($targetUsername))->with('success', 'Solicitud de amistad enviada.');
	} catch (RuntimeException $e) {
		return redirect('/buscar-amigos?q=' . rawurlencode($targetUsername))->with('error', $e->getMessage());
	}
})->middleware('throttle:30,1');

Route::post('/amistad/responder', function (Request $request, JsonUserStore $users, FriendStore $friends) {
	$currentUser = virthub_active_user($request, $users);

	if (!$currentUser || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion para responder solicitudes.');
	}

	$validated = $request->validate([
		'request_id' => ['required', 'string'],
		'status' => ['required', 'in:accepted,declined'],
	]);

	try {
		$friends->respondToRequest(
			(string) $validated['request_id'],
			(string) $currentUser['username'],
			(string) $validated['status']
		);
		return redirect('/buscar-amigos')->with('success', $validated['status'] === 'accepted' ? 'Solicitud aceptada.' : 'Solicitud rechazada.');
	} catch (RuntimeException $e) {
		return redirect('/buscar-amigos')->with('error', $e->getMessage());
	}
});

Route::get('/perfil/{username}', function (Request $request, string $username, JsonUserStore $users, ProfileStore $profileStore, ForumStore $forumStore, FriendStore $friends) {
	$profile = $users->findPublicProfile($username);

	if (!$profile) {
		abort(404);
	}

	$currentUser = virthub_active_user($request, $users);
	$friendship = $currentUser && ($currentUser['role'] ?? 'guest') !== 'guest'
		? $friends->statusBetween((string) $currentUser['username'], (string) $profile['username'])
		: null;
	$profilePosts = array_merge(
		$profileStore->latestPostsFor((string) $profile['username']),
		$forumStore->postsByAuthor((string) $profile['username'])
	);
	usort($profilePosts, static function (array $first, array $second): int {
		return strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? ''));
	});
	$profilePosts = array_slice($profilePosts, 0, 100);

	return view('perfil', [
		'currentUser' => $currentUser,
		'profile' => $profile,
		'profilePosts' => $profilePosts,
		'friendship' => $friendship,
		'isOwner' => $currentUser && ($currentUser['username'] ?? '') === ($profile['username'] ?? ''),
	]);
});

Route::post('/perfil/{username}/publicar', function (Request $request, string $username, JsonUserStore $users, ProfileStore $profileStore) {
	$currentUser = virthub_active_user($request, $users);

	if (!$currentUser || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion con usuario registrado para publicar en tu perfil.');
	}

	if ((string) ($currentUser['username'] ?? '') !== $username) {
		abort(403, 'Solo puedes publicar en tu propio perfil.');
	}

	if (!$users->findPublicProfile($username)) {
		abort(404);
	}

	$validated = $request->validate([
		'content' => ['required', 'string', 'min:1', 'max:2000'],
	]);

	$profileStore->addPost($username, (string) $validated['content']);

	return redirect('/perfil/' . rawurlencode($username))->with('success', 'Publicacion añadida a tu perfil.');
})->middleware('throttle:30,1');

Route::get('/sugerencias', function (Request $request, JsonUserStore $users) {
	$users->bootstrapAdminFromEnv();
	$currentUser = virthub_active_user($request, $users);

	return view('sugerencias', [
		'currentUser' => $currentUser,
	]);
});

Route::post('/sugerencias', function (Request $request, JsonUserStore $users) {
	$users->bootstrapAdminFromEnv();
	$currentUser = virthub_active_user($request, $users);
	$canIdentifySuggestionAuthor = !empty($currentUser) && (($currentUser['role'] ?? 'guest') !== 'guest');

	$validated = $request->validate([
		'author_mode' => 'required|string|in:anonymous,identified',
		'message' => 'required|string|min:8|max:2000',
	]);

	$authorMode = $canIdentifySuggestionAuthor ? (string) $validated['author_mode'] : 'anonymous';
	$author = 'Anonimo';

	if ($authorMode === 'identified') {
		if ($currentUser && !empty($currentUser['username'])) {
			$author = (string) $currentUser['username'];
		} else {
			$author = 'visitante';
		}
	}

	$entry = [
		'id' => bin2hex(random_bytes(8)),
		'author_mode' => $authorMode,
		'author' => $author,
		'message' => trim((string) $validated['message']),
		'created_at' => date('c'),
	];

	$dataDir = storage_path('app/data');
	if (!is_dir($dataDir)) {
		mkdir($dataDir, 0755, true);
	}

	$file = $dataDir . DIRECTORY_SEPARATOR . 'suggestions.json';
	$handle = fopen($file, 'c+b');

	if ($handle === false) {
		return redirect('/sugerencias')->with('error', 'No se pudo registrar la sugerencia en este momento.');
	}

	try {
		if (!flock($handle, LOCK_EX)) {
			throw new RuntimeException('No se pudo bloquear el archivo de sugerencias.');
		}

		rewind($handle);
		$content = stream_get_contents($handle);
		$decoded = json_decode($content !== false ? $content : '', true);
		$payload = (is_array($decoded) && is_array($decoded['suggestions'] ?? null))
			? $decoded
			: ['suggestions' => []];

		$payload['suggestions'][] = $entry;

		$encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		if ($encoded === false) {
			throw new RuntimeException('No se pudo codificar la sugerencia.');
		}

		rewind($handle);
		ftruncate($handle, 0);
		fwrite($handle, $encoded);
		fflush($handle);
	} catch (RuntimeException $e) {
		return redirect('/sugerencias')->with('error', 'No se pudo registrar la sugerencia en este momento.');
	} finally {
		flock($handle, LOCK_UN);
		fclose($handle);
	}

	return redirect('/sugerencias')->with('success', 'Gracias por compartir tu sugerencia. Ya fue registrada.');
})->middleware('throttle:30,1');

Route::post('/foro', function (Request $request, JsonUserStore $users, ForumStore $forumStore) {
	$currentUser = virthub_active_user($request, $users);

	if (empty($currentUser) || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/foro')->with('error', 'Solo usuarios registrados pueden publicar en el foro.');
	}

	$validated = $request->validate([
		'title' => 'nullable|string|max:120',
		'content' => 'required|string|max:5000',
		'photos' => 'nullable|array|max:10',
		'photos.*' => 'image|mimes:jpg,jpeg,png,webp,gif|max:5242880',
		'videos' => 'nullable|array|max:5',
		'videos.*' => 'file|mimes:mp4,webm,mov,avi|max:5242880',
		'files' => 'nullable|array|max:10',
		'files.*' => 'file|max:5242880',
		'poll_question' => 'nullable|string|max:180',
		'poll_options' => 'nullable|array|max:10',
		'poll_options.*' => 'nullable|string|max:120',
	]);

	$pollQuestion = trim((string) ($validated['poll_question'] ?? ''));
	$pollOptions = [];
	foreach (($validated['poll_options'] ?? []) as $rawOption) {
		$option = trim((string) $rawOption);
		if ($option !== '') {
			$pollOptions[] = $option;
		}
	}
	$pollOptions = array_values(array_unique($pollOptions));

	if ($pollQuestion === '' && count($pollOptions) > 0) {
		return redirect('/foro')->withInput()->with('error', 'Para crear una encuesta debes completar la pregunta.');
	}

	if ($pollQuestion !== '' && count($pollOptions) < 2) {
		return redirect('/foro')->withInput()->with('error', 'La encuesta necesita al menos 2 opciones.');
	}

	$pollPayload = null;
	if ($pollQuestion !== '' && count($pollOptions) >= 2) {
		$pollPayload = [
			'question' => $pollQuestion,
			'options' => $pollOptions,
		];
	}

	try {
		$attachments = [];
		foreach ([
			'photos' => 'photo',
			'videos' => 'video',
			'files' => 'file',
		] as $inputName => $attachmentType) {
			foreach ($request->file($inputName, []) as $uploaded) {
				$uploadsDir = public_path('uploads/forum/' . $attachmentType . 's');
				if (!is_dir($uploadsDir)) {
					mkdir($uploadsDir, 0755, true);
				}

				$extension = strtolower((string) $uploaded->getClientOriginalExtension()) ?: 'bin';
				$filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
				$uploaded->move($uploadsDir, $filename);
				$attachments[] = [
					'type' => $attachmentType,
					'name' => (string) $uploaded->getClientOriginalName(),
					'mime' => (string) $uploaded->getMimeType(),
					'path' => 'uploads/forum/' . $attachmentType . 's/' . $filename,
				];
			}
		}

		$post = $forumStore->addPost(
			(string) ($currentUser['username'] ?? 'usuario'),
			(string) $validated['content'],
			isset($validated['title']) ? (string) $validated['title'] : null,
			$pollPayload,
			$attachments
		);

		return redirect('/foro')->with('success', 'Publicacion creada en el foro.');
	} catch (RuntimeException $e) {
		return redirect('/foro')->with('error', $e->getMessage());
	}
});

Route::post('/foro/{postId}/poll-vote', function (Request $request, string $postId, JsonUserStore $users, ForumStore $forumStore) {
	$currentUser = virthub_active_user($request, $users);

	if (empty($currentUser) || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/foro')->with('error', 'Solo usuarios registrados pueden votar en encuestas.');
	}

	$validated = $request->validate([
		'option_id' => 'required|string',
	]);

	try {
		$forumStore->votePoll(
			$postId,
			(string) ($currentUser['username'] ?? ''),
			(string) $validated['option_id']
		);

		return redirect('/foro')->with('success', 'Voto registrado en la encuesta.');
	} catch (RuntimeException $e) {
		return redirect('/foro')->with('error', $e->getMessage());
	}
});

Route::post('/foro/{postId}/react', function (Request $request, string $postId, JsonUserStore $users, ForumStore $forumStore) {
	$currentUser = virthub_active_user($request, $users);

	if (empty($currentUser) || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/foro')->with('error', 'Solo usuarios registrados pueden reaccionar en el foro.');
	}

	$validated = $request->validate([
		'reaction' => 'required|string|in:like,love,fire',
	]);

	$reactionMap = [
		'like' => '👍',
		'love' => '❤️',
		'fire' => '🔥',
	];

	try {
		$forumStore->toggleReaction(
			$postId,
			(string) ($currentUser['username'] ?? ''),
			$reactionMap[(string) $validated['reaction']] ?? '👍'
		);

		return redirect('/foro');
	} catch (RuntimeException $e) {
		return redirect('/foro')->with('error', $e->getMessage());
	}
})->middleware('throttle:60,1');

Route::post('/foro/{postId}/comment', function (Request $request, string $postId, JsonUserStore $users, ForumStore $forumStore) {
	$currentUser = virthub_active_user($request, $users);

	if (empty($currentUser) || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/foro')->with('error', 'Solo usuarios registrados pueden comentar en el foro.');
	}

	$validated = $request->validate([
		'content' => 'required|string|max:1500',
	]);

	try {
		$forumStore->addComment(
			$postId,
			(string) ($currentUser['username'] ?? ''),
			(string) $validated['content']
		);

		return redirect('/foro');
	} catch (RuntimeException $e) {
		return redirect('/foro')->with('error', $e->getMessage());
	}
})->middleware('throttle:30,1');

Route::post('/foro/{postId}/report', function (Request $request, string $postId, JsonUserStore $users, ForumStore $forumStore) {
	$currentUser = virthub_active_user($request, $users);

	if (empty($currentUser) || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/foro')->with('error', 'Solo usuarios registrados pueden reportar publicaciones.');
	}

	$validated = $request->validate([
		'reason' => 'required|string|min:8|max:280',
	]);

	try {
		$forumStore->addReport(
			$postId,
			(string) ($currentUser['username'] ?? ''),
			(string) $validated['reason']
		);

		return redirect('/foro')->with('success', 'Reporte enviado a moderacion.');
	} catch (RuntimeException $e) {
		return redirect('/foro')->with('error', $e->getMessage());
	}
});

Route::post('/foro/{postId}/delete', function (Request $request, string $postId, JsonUserStore $users, ForumStore $forumStore) {
	$currentUser = virthub_active_user($request, $users);

	if (empty($currentUser) || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/foro')->with('error', 'Solo usuarios registrados pueden eliminar publicaciones.');
	}

	$post = $forumStore->findById($postId);

	if (!$post) {
		return redirect('/foro')->with('error', 'No existe la publicacion solicitada.');
	}

	$isAdmin = (($currentUser['role'] ?? 'user') === 'admin');
	$isOwner = (($post['author'] ?? '') === ($currentUser['username'] ?? ''));

	if (!$isAdmin && !$isOwner) {
		return redirect('/foro')->with('error', 'Solo puedes borrar tus propias publicaciones.');
	}

	$deleted = $forumStore->deletePost($postId);

	if (!$deleted) {
		return redirect('/foro')->with('error', 'No se pudo eliminar la publicacion.');
	}

	$imagePath = (string) ($deleted['image_path'] ?? '');
	if ($imagePath !== '') {
		$fullPath = public_path(ltrim($imagePath, '/'));
		if (is_file($fullPath)) {
			@unlink($fullPath);
		}
	}

	return redirect('/foro')->with('success', 'Publicacion eliminada del foro.');
});

Route::post('/login', function (Request $request, JsonUserStore $users) {
	$lockedUntil = (int) $request->session()->get('login_locked_until', 0);

	if ($lockedUntil > time()) {
		$remaining = $lockedUntil - time();
		if ($request->expectsJson()) {
			return response()->json(['error' => 'Demasiados intentos fallidos. Espera ' . $remaining . ' segundos.'], 429);
		}

		return redirect('/')->with('error', 'Demasiados intentos fallidos. Espera ' . $remaining . ' segundos.');
	}

	$request->validate([
		'username' => 'required|string',
		'password' => 'required|string',
	]);

	$users->bootstrapAdminFromEnv();
	$authUser = $users->verifyCredentials(
		(string) $request->input('username'),
		(string) $request->input('password')
	);

	if (!$authUser) {
		virthub_audit($request, 'authentication.failed', null, [
			'reason' => 'invalid_credentials',
		]);

		$currentFails = (int) $request->session()->get('login_fail_count', 0) + 1;
		$request->session()->put('login_fail_count', $currentFails);

		if ($currentFails >= 5) {
			$lockSeconds = 120;
			$request->session()->put('login_locked_until', time() + $lockSeconds);
			$request->session()->put('login_fail_count', 0);

			if ($request->expectsJson()) {
				return response()->json(['error' => 'Bloqueado temporalmente por fallos. Espera ' . $lockSeconds . ' segundos.'], 429);
			}

			return redirect('/')
				->withInput($request->only('username'))
				->with('error', 'Bloqueado temporalmente por fallos. Espera ' . $lockSeconds . ' segundos.');
		}

		if ($request->expectsJson()) {
			return response()->json(['error' => 'Usuario o contrasena incorrectos. Fallos: ' . $currentFails . '/5'], 422);
		}

		return redirect('/')
			->withInput($request->only('username'))
			->with('error', 'Usuario o contrasena incorrectos. Fallos: ' . $currentFails . '/5');
	}

	if ($users->hasTwoFactorEnabled((string) $authUser['username'])) {
		$request->session()->regenerate();
		$request->session()->put('two_factor_pending_username', $authUser['username']);
		$request->session()->forget('two_factor_fail_count');

		if ($request->expectsJson()) {
			return response()->json([
				'two_factor_required' => true,
				'message' => 'Introduce el codigo de Google Authenticator para continuar.',
			], 200);
		}

		return redirect('/')->with('success', 'Introduce el codigo de Google Authenticator para continuar.');
	}

	$request->session()->regenerate();
	$request->session()->put('auth_user', $authUser);
	$request->session()->forget('login_fail_count');
	$request->session()->forget('login_locked_until');
	$request->session()->forget('guest_expires_at');
	$users->recordLogin((string) $authUser['username']);
	virthub_audit($request, 'authentication.login', $authUser);
	if ($request->expectsJson()) {
		return response()->json(['authenticated' => true], 200);
	}

	return redirect('/')->with('success', 'Sesion iniciada correctamente.');
})->middleware('throttle:login-ip');

Route::post('/login/2fa', function (Request $request, JsonUserStore $users, TwoFactorService $twoFactor) {
	$pendingUsername = trim((string) $request->session()->get('two_factor_pending_username', ''));

	if ($pendingUsername === '') {
		if ($request->expectsJson()) {
			return response()->json(['error' => 'La verificacion 2FA ya no es valida. Inicia sesion nuevamente.'], 422);
		}

		return redirect('/')->with('error', 'La verificacion 2FA ya no es valida. Inicia sesion nuevamente.');
	}

	$validated = $request->validate([
		'code' => ['required', 'digits:6'],
	]);
	$user = $users->findByUsername($pendingUsername);
	$encryptedSecret = $users->twoFactorSecret($pendingUsername);

	if (!$user || !($user['is_active'] ?? true) || $encryptedSecret === null) {
		$request->session()->forget(['two_factor_pending_username', 'two_factor_fail_count']);
		if ($request->expectsJson()) {
			return response()->json(['error' => 'La verificacion 2FA ya no es valida.'], 422);
		}

		return redirect('/')->with('error', 'La verificacion 2FA ya no es valida.');
	}

	$secret = $twoFactor->decryptSecret($encryptedSecret);
	if (!$twoFactor->verifyCode($secret, (string) $validated['code'])) {
		$failCount = (int) $request->session()->get('two_factor_fail_count', 0) + 1;
		$request->session()->put('two_factor_fail_count', $failCount);
		virthub_audit($request, 'authentication.2fa_failed', [
			'username' => $pendingUsername,
			'role' => $user['role'] ?? 'user',
		]);

		if ($failCount >= 5) {
			$request->session()->forget(['two_factor_pending_username', 'two_factor_fail_count']);
			if ($request->expectsJson()) {
				return response()->json(['error' => 'Demasiados codigos 2FA incorrectos. Inicia sesion nuevamente.'], 429);
			}

			return redirect('/')->with('error', 'Demasiados codigos 2FA incorrectos. Inicia sesion nuevamente.');
		}

		if ($request->expectsJson()) {
			return response()->json(['error' => 'El codigo de 2FA no es valido.'], 422);
		}

		return redirect('/')->with('error', 'El codigo de 2FA no es valido.');
	}

	$authUser = [
		'username' => $user['username'],
		'role' => $user['role'] ?? 'user',
	];
	$request->session()->regenerate();
	$request->session()->put('auth_user', $authUser);
	$request->session()->forget(['two_factor_pending_username', 'two_factor_fail_count']);
	$users->recordLogin((string) $authUser['username']);
	virthub_audit($request, 'authentication.2fa_verified', $authUser);
	if ($request->expectsJson()) {
		return response()->json(['authenticated' => true], 200);
	}

	return redirect('/')->with('success', 'Sesion iniciada correctamente.');
})->middleware('throttle:10,1');

Route::post('/login/2fa/recovery', function (Request $request, JsonUserStore $users) {
	$pendingUsername = trim((string) $request->session()->get('two_factor_pending_username', ''));

	if ($pendingUsername === '') {
		if ($request->expectsJson()) {
			return response()->json(['error' => 'La verificacion 2FA ya no es valida. Inicia sesion nuevamente.'], 422);
		}

		return redirect('/')->with('error', 'La verificacion 2FA ya no es valida. Inicia sesion nuevamente.');
	}

	$validated = $request->validate([
		'recovery_code' => ['required', 'string', 'max:32'],
	]);
	$user = $users->findByUsername($pendingUsername);

	if (!$user || !($user['is_active'] ?? true) || !$users->consumeTwoFactorRecoveryCode($pendingUsername, (string) $validated['recovery_code'])) {
		virthub_audit($request, 'authentication.2fa_recovery_failed', [
			'username' => $pendingUsername,
			'role' => $user['role'] ?? null,
		]);
		if ($request->expectsJson()) {
			return response()->json(['error' => 'El codigo de recuperacion no es valido.'], 422);
		}

		return redirect('/')->with('error', 'El codigo de recuperacion no es valido.');
	}

	$authUser = [
		'username' => $user['username'],
		'role' => $user['role'] ?? 'user',
	];
	$request->session()->regenerate();
	$request->session()->put('auth_user', $authUser);
	$request->session()->forget(['two_factor_pending_username', 'two_factor_fail_count']);
	$users->recordLogin((string) $authUser['username']);
	virthub_audit($request, 'authentication.2fa_recovery_used', $authUser);
	if ($request->expectsJson()) {
		return response()->json(['authenticated' => true], 200);
	}

	return redirect('/')->with('success', 'Sesion iniciada con un codigo de recuperacion.');
})->middleware('throttle:10,1');

Route::post('/guest-login', function (Request $request) {
	$request->session()->regenerate();

	$guestName = 'guest_' . substr(bin2hex(random_bytes(3)), 0, 6);
	$request->session()->put('auth_user', [
		'username' => $guestName,
		'role' => 'guest',
	]);
	$request->session()->put('guest_expires_at', time() + (30 * 60));
	virthub_audit($request, 'authentication.guest_login', [
		'username' => $guestName,
		'role' => 'guest',
	]);

	return redirect('/')->with('success', 'Acceso temporal activado por 30 minutos.');
});

Route::post('/logout', function (Request $request) {
	$sessionUser = $request->session()->get('auth_user');
	virthub_audit($request, 'authentication.logout', is_array($sessionUser) ? $sessionUser : null);

	$request->session()->invalidate();
	$request->session()->regenerateToken();

	return redirect('/')->with('success', 'Sesion cerrada.');
});

Route::get('/configuracion', function (Request $request, JsonUserStore $users, TwoFactorService $twoFactor) {
	$currentUser = virthub_active_user($request, $users);

	if (!$currentUser || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Solo usuarios registrados pueden acceder a configuracion.');
	}

	$setupSecret = (string) $request->session()->get('two_factor_setup_secret', '');
	$twoFactorSetupQr = null;

	if ($setupSecret !== '') {
		try {
			$twoFactorSetupQr = $twoFactor->qrCodeDataUri((string) $currentUser['username'], $twoFactor->decryptSecret($setupSecret));
		} catch (Throwable $exception) {
			$request->session()->forget('two_factor_setup_secret');
		}
	}

	return view('configuracion', [
		'currentUser' => $currentUser,
		'twoFactorEnabled' => $users->hasTwoFactorEnabled((string) $currentUser['username']),
		'twoFactorSetupQr' => $twoFactorSetupQr,
	]);
});

Route::post('/security/2fa/setup', function (Request $request, JsonUserStore $users, TwoFactorService $twoFactor) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion para configurar 2FA.');
	}

	$username = (string) $authUser['username'];

	if ($users->hasTwoFactorEnabled($username)) {
		return redirect('/configuracion')->with('error', 'El 2FA ya esta activado.');
	}

	$request->session()->put('two_factor_setup_secret', $twoFactor->encryptSecret($twoFactor->generateSecret()));

	return redirect('/configuracion')->with('success', 'Escanea el codigo QR y confirma el codigo generado.');
})->middleware('throttle:10,1');

Route::post('/security/2fa/confirm', function (Request $request, JsonUserStore $users, TwoFactorService $twoFactor) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion para confirmar 2FA.');
	}

	$validated = $request->validate([
		'code' => ['required', 'digits:6'],
	]);
	$encryptedSecret = (string) $request->session()->get('two_factor_setup_secret', '');

	if ($encryptedSecret === '') {
		return redirect('/configuracion')->with('error', 'Inicia primero la configuracion de 2FA.');
	}

	try {
		$secret = $twoFactor->decryptSecret($encryptedSecret);
	} catch (Throwable $exception) {
		$request->session()->forget('two_factor_setup_secret');
		return redirect('/configuracion')->with('error', 'La configuracion de 2FA ya no es valida.');
	}

	if (!$twoFactor->verifyCode($secret, (string) $validated['code'])) {
		virthub_audit($request, 'authentication.2fa_setup_failed', $authUser);
		return redirect('/configuracion')->with('error', 'El codigo de 2FA no es valido.');
	}

	$recoveryCodes = $twoFactor->generateRecoveryCodes();
	$recoveryHashes = array_map([$twoFactor, 'hashRecoveryCode'], $recoveryCodes);
	$users->enableTwoFactor((string) $authUser['username'], $encryptedSecret, $recoveryHashes);
	$request->session()->forget('two_factor_setup_secret');
	virthub_audit($request, 'security.2fa_enabled', $authUser);

	if ($request->expectsJson()) {
		return response()->json([
			'two_factor_enabled' => true,
			'recovery_codes' => $recoveryCodes,
		], 200);
	}

	return redirect('/configuracion')->with('two_factor_recovery_codes', $recoveryCodes);
})->middleware('throttle:10,1');

Route::post('/security/2fa/disable', function (Request $request, JsonUserStore $users, TwoFactorService $twoFactor) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion para desactivar 2FA.');
	}

	$validated = $request->validate([
		'current_password' => ['required', 'string', 'max:72'],
		'code' => ['required', 'digits:6'],
	]);
	$username = (string) $authUser['username'];

	if (!$users->verifyPassword($username, (string) $validated['current_password'])) {
		return redirect('/configuracion')->with('error', 'La contrasena actual no es correcta.');
	}

	$encryptedSecret = $users->twoFactorSecret($username);
	if ($encryptedSecret === null || !$twoFactor->verifyCode($twoFactor->decryptSecret($encryptedSecret), (string) $validated['code'])) {
		return redirect('/configuracion')->with('error', 'El codigo de 2FA no es valido.');
	}

	$users->disableTwoFactor($username);
	virthub_audit($request, 'security.2fa_disabled', $authUser);

	return redirect('/configuracion')->with('success', 'El 2FA fue desactivado.');
})->middleware('throttle:10,1');

Route::post('/security/2fa/recovery-codes', function (Request $request, JsonUserStore $users, TwoFactorService $twoFactor) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Debes iniciar sesion para regenerar los codigos.'], 403);
	}

	$validated = $request->validate([
		'current_password' => ['required', 'string', 'max:72'],
		'code' => ['required', 'digits:6'],
	]);
	$username = (string) $authUser['username'];

	if (!$users->hasTwoFactorEnabled($username) || !$users->verifyPassword($username, (string) $validated['current_password'])) {
		return response()->json(['error' => 'La contrasena actual no es correcta.'], 422);
	}

	$encryptedSecret = $users->twoFactorSecret($username);
	if ($encryptedSecret === null || !$twoFactor->verifyCode($twoFactor->decryptSecret($encryptedSecret), (string) $validated['code'])) {
		return response()->json(['error' => 'El codigo de 2FA no es valido.'], 422);
	}

	$recoveryCodes = $twoFactor->generateRecoveryCodes();
	$users->replaceTwoFactorRecoveryCodes(
		$username,
		array_map([$twoFactor, 'hashRecoveryCode'], $recoveryCodes)
	);
	virthub_audit($request, 'security.2fa_recovery_codes_regenerated', $authUser);

	return response()->json(['recovery_codes' => $recoveryCodes], 200);
})->middleware('throttle:10,1');

Route::post('/profile/appearance', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion con usuario registrado para editar tu perfil.');
	}

	$validated = $request->validate([
		'frame_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
		'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
	]);

	$username = (string) ($authUser['username'] ?? '');
	$existingUser = $users->findByUsername($username);

	if (!$existingUser) {
		return redirect('/')->with('error', 'Usuario no encontrado.');
	}

	$newImagePath = null;
	$shouldUpdateImage = false;

	if ($request->hasFile('profile_image')) {
		$uploadsDir = public_path('uploads/profiles');

		if (!is_dir($uploadsDir)) {
			mkdir($uploadsDir, 0755, true);
		}

		$uploaded = $request->file('profile_image');
		$extension = strtolower((string) $uploaded->getClientOriginalExtension());
		if ($extension === '') {
			$extension = 'jpg';
		}

		$filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
		$uploaded->move($uploadsDir, $filename);
		$newImagePath = 'uploads/profiles/' . $filename;
		$shouldUpdateImage = true;

		$oldImagePath = (string) ($existingUser['profile_image_path'] ?? '');
		if ($oldImagePath !== '') {
			$oldFullPath = public_path(ltrim($oldImagePath, '/'));
			if (is_file($oldFullPath)) {
				@unlink($oldFullPath);
			}
		}
	}

	$users->updateProfileAppearance(
		$username,
		$shouldUpdateImage ? $newImagePath : null,
		(string) $validated['frame_color']
	);

	return redirect('/')->with('success', 'Perfil actualizado correctamente.');
});

Route::post('/profile/password', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Debes iniciar sesion con usuario registrado para cambiar tu password.');
	}

	$validated = $request->validate([
		'current_password' => 'required|string|min:6|max:72',
		'new_password' => 'required|string|min:6|max:72|confirmed',
	]);

	$username = (string) ($authUser['username'] ?? '');

	if (!$users->verifyPassword($username, (string) $validated['current_password'])) {
		return redirect('/')->with('error', 'La contrasena actual no es correcta.');
	}

	$users->updatePassword($username, (string) $validated['new_password']);

	return redirect('/')->with('success', 'Tu contrasena fue actualizada correctamente.');
});

Route::get('/admin/users', function (Request $request, JsonUserStore $users, ForumStore $forumStore) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return redirect('/')->with('error', 'Solo admin puede acceder a gestion de usuarios.');
	}

	$forumReports = [];
	$posts = $forumStore->latestPosts(300);

	foreach ($posts as $post) {
		$reports = is_array($post['reports'] ?? null) ? $post['reports'] : [];

		foreach ($reports as $report) {
			$forumReports[] = [
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

	usort($forumReports, function (array $a, array $b): int {
		return strcmp((string) ($b['reported_at'] ?? ''), (string) ($a['reported_at'] ?? ''));
	});

	$suggestions = [];
	$suggestionsFile = storage_path('app/data/suggestions.json');

	if (is_file($suggestionsFile)) {
		$rawSuggestions = @file_get_contents($suggestionsFile);
		$decodedSuggestions = is_string($rawSuggestions) ? json_decode($rawSuggestions, true) : null;

		if (is_array($decodedSuggestions) && is_array($decodedSuggestions['suggestions'] ?? null)) {
			$suggestions = $decodedSuggestions['suggestions'];
		}
	}

	usort($suggestions, function (array $a, array $b): int {
		return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
	});

	return view('admin-users', [
		'currentUser' => $authUser,
		'users' => $users->allPublicUsers(),
		'forumReports' => $forumReports,
		'suggestions' => $suggestions,
	]);
});

Route::post('/admin/forum-reports/delete', function (Request $request, JsonUserStore $users, ForumStore $forumStore) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return redirect('/')->with('error', 'Solo admin puede gestionar reportes.');
	}

	$validated = $request->validate([
		'post_id' => 'required|string',
		'report_id' => 'required|string',
	]);

	$deleted = $forumStore->removeReport(
		(string) $validated['post_id'],
		(string) $validated['report_id']
	);

	if (!$deleted) {
		return redirect('/admin/users')->with('error', 'No se pudo eliminar el reporte o ya no existe.');
	}

	return redirect('/admin/users')->with('success', 'Reporte marcado como verificado y eliminado.');
});

Route::post('/admin/users', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return redirect('/')->with('error', 'Solo admin puede crear usuarios.');
	}

	$validated = $request->validate([
		'name' => ['nullable', 'string', 'max:80'],
		'username' => ['nullable', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.]+$/'],
		'password' => ['nullable', 'string', 'min:6', 'max:72'],
		'role' => 'required|in:user,admin',
		'random_username' => 'nullable|in:1',
		'random_password' => 'nullable|in:1',
	]);

	$useRandomUsername = $request->boolean('random_username');
	$role = (string) $validated['role'];

	if ($role === 'admin' && ($useRandomUsername || $request->boolean('random_password'))) {
		return redirect('/admin/users')->with('error', 'Para usuarios admin debes definir username y password manualmente.');
	}

	$username = $useRandomUsername
		? $users->generateRandomUsername('virt')
		: (string) ($validated['username'] ?? '');

	if (!$useRandomUsername && trim($username) === '') {
		return redirect('/admin/users')->with('error', 'Debes indicar username o usar modo aleatorio.');
	}

	$useRandomPassword = $request->boolean('random_password');
	$password = $useRandomPassword
		? $users->generateRandomPassword()
		: (string) ($validated['password'] ?? '');

	if (!$useRandomPassword && trim($password) === '') {
		return redirect('/admin/users')->with('error', 'Debes indicar password o usar modo aleatorio.');
	}

	try {
		$createdUser = $users->createUser(
			$username,
			$password,
			$role,
			(string) ($validated['name'] ?? '')
		);

		$message = "Usuario creado: {$createdUser['username']} ({$createdUser['role']})";
		if ($useRandomPassword) {
			$message .= " | Password: {$password}";
		}

		return redirect('/admin/users')->with('success', $message);
	} catch (RuntimeException $e) {
		return redirect('/admin/users')->with('error', $e->getMessage());
	}
});

Route::post('/admin/users/password', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return redirect('/')->with('error', 'Solo admin puede cambiar passwords.');
	}

	$validated = $request->validate([
		'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.]+$/'],
		'new_password' => 'required|string|min:6|max:72',
	]);

	try {
		$users->updatePassword(
			(string) $validated['username'],
			(string) $validated['new_password']
		);

		return redirect('/admin/users')->with('success', 'Password actualizado para ' . $validated['username'] . '.');
	} catch (RuntimeException $e) {
		return redirect('/admin/users')->with('error', $e->getMessage());
	}
});

Route::post('/admin/users/deactivate', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return redirect('/')->with('error', 'Solo admin puede desactivar usuarios.');
	}

	$validated = $request->validate([
		'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.]+$/'],
	]);

	try {
		$targetUser = $users->findByUsername((string) $validated['username']);
		if ($targetUser && strtolower((string) ($targetUser['username'] ?? '')) === 'admin') {
			return redirect('/admin/users')->with('error', 'No se permite desactivar la cuenta admin principal.');
		}

		if ($targetUser && (($targetUser['role'] ?? 'user') === 'admin') && $users->countActiveAdmins() <= 1) {
			return redirect('/admin/users')->with('error', 'No puedes desactivar al ultimo admin activo.');
		}

		$users->deactivateUser((string) $validated['username']);
		return redirect('/admin/users')->with('success', 'Usuario ' . $validated['username'] . ' desactivado.');
	} catch (RuntimeException $e) {
		return redirect('/admin/users')->with('error', $e->getMessage());
	}
});

Route::post('/admin/users/activate', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return redirect('/')->with('error', 'Solo admin puede activar usuarios.');
	}

	$validated = $request->validate([
		'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.]+$/'],
	]);

	try {
		$users->activateUser((string) $validated['username']);
		return redirect('/admin/users')->with('success', 'Usuario ' . $validated['username'] . ' activado.');
	} catch (RuntimeException $e) {
		return redirect('/admin/users')->with('error', $e->getMessage());
	}
});

Route::post('/admin/users/delete', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return redirect('/')->with('error', 'Solo admin puede eliminar usuarios.');
	}

	$validated = $request->validate([
		'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9_.]+$/'],
	]);

	try {
		$targetUser = $users->findByUsername((string) $validated['username']);
		if ($targetUser && strtolower((string) ($targetUser['username'] ?? '')) === 'admin') {
			return redirect('/admin/users')->with('error', 'No se permite eliminar la cuenta admin principal.');
		}

		$users->deleteUser((string) $validated['username']);
		return redirect('/admin/users')->with('success', 'Usuario ' . $validated['username'] . ' eliminado.');
	} catch (RuntimeException $e) {
		return redirect('/admin/users')->with('error', $e->getMessage());
	}
});

Route::get('/chat/users', function (Request $request, JsonUserStore $users, FriendStore $friends) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser) {
		return response()->json(['error' => 'No autenticado'], 401);
	}

	if (($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Los invitados solo pueden ver anuncios.'], 403);
	}

	$users->bootstrapAdminFromEnv();
	$users->touchPresence((string) $authUser['username']);
	$currentUsername = (string) ($authUser['username'] ?? '');
	$friendUsernames = $friends->friendsOf($currentUsername);
	$contacts = array_values(array_filter($users->allPublicUsers(), function (array $user) use ($currentUsername, $friendUsernames): bool {
		return ($user['username'] ?? '') !== ''
			&& ($user['username'] ?? '') !== $currentUsername
			&& in_array((string) ($user['username'] ?? ''), $friendUsernames, true);
	}));

	$contacts = array_map(function (array $user): array {
		$accountActive = (bool) ($user['is_active'] ?? true);
		$presenceActive = virthub_chat_is_recent_presence($user['last_seen_at'] ?? null);

		$user['account_active'] = $accountActive;
		$user['presence_status'] = $presenceActive ? 'online' : 'offline';

		return $user;
	}, $contacts);

	return response()->json(['users' => $contacts], 200);
});

Route::get('/chat/friend-requests', function (Request $request, JsonUserStore $users, FriendStore $friends) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Solo usuarios registrados pueden consultar solicitudes.'], 403);
	}

	$requests = array_map(function (array $friendRequest) use ($users): array {
		$sender = $users->findPublicProfile((string) ($friendRequest['from'] ?? ''));

		return [
			'id' => $friendRequest['id'] ?? '',
			'from' => $friendRequest['from'] ?? '',
			'name' => $sender['name'] ?? ($friendRequest['from'] ?? ''),
			'created_at' => $friendRequest['created_at'] ?? null,
		];
	}, $friends->pendingFor((string) $authUser['username']));

	return response()->json(['requests' => $requests], 200);
});

Route::post('/chat/friend-requests/{requestId}', function (Request $request, string $requestId, JsonUserStore $users, FriendStore $friends) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Solo usuarios registrados pueden responder solicitudes.'], 403);
	}

	$validated = $request->validate([
		'status' => ['required', 'in:accepted,declined'],
	]);

	try {
		$updatedRequest = $friends->respondToRequest(
			$requestId,
			(string) $authUser['username'],
			(string) $validated['status']
		);

		return response()->json(['request' => $updatedRequest], 200);
	} catch (RuntimeException $e) {
		return response()->json(['error' => $e->getMessage()], 422);
	}
});

Route::post('/chat/presence', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser) {
		return response()->json(['error' => 'No autenticado'], 401);
	}

	if (($authUser['role'] ?? 'guest') !== 'guest') {
		$users->touchPresence((string) $authUser['username']);
	}

	return response()->json(['ok' => true], 200);
});

Route::get('/chat/conversation/{username}', function (Request $request, string $username, JsonUserStore $users, ChatStore $chatStore, FriendStore $friends) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser) {
		return response()->json(['error' => 'No autenticado'], 401);
	}

	if (($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Los invitados no pueden abrir conversaciones privadas.'], 403);
	}

	$users->touchPresence((string) $authUser['username']);

	$isOllamaConversation = strtolower(trim($username)) === virthub_ollama_chat_username();

	if (!$isOllamaConversation) {
		$targetUser = $users->findByUsername($username);

		if (!$targetUser || !($targetUser['is_active'] ?? true)) {
			return response()->json(['error' => 'Usuario no encontrado'], 404);
		}

		if (!in_array($username, $friends->friendsOf((string) $authUser['username']), true)) {
			return response()->json(['error' => 'Solo puedes conversar con tus amigos aceptados.'], 403);
		}
	}

	$messages = $chatStore->getConversationMessages((string) $authUser['username'], $username);
	
	// Enriquecer mensajes con profile_image_path del remitente
	$enrichedMessages = array_map(function ($message) use ($users) {
		$sender = $users->findByUsername($message['from'] ?? '');
		if ($sender) {
			$message['profile_image_path'] = $sender['profile_image_path'] ?? null;
		}
		return $message;
	}, $messages);

	if ($isOllamaConversation) {
		$enrichedMessages = array_map(function (array $message): array {
			if (($message['from'] ?? '') === virthub_ollama_chat_username()) {
				$message['profile_image_path'] = null;
			}

			return $message;
		}, $enrichedMessages);
	}

	return response()->json([
		'messages' => $enrichedMessages,
	], 200);
});

Route::post('/chat/conversation/{username}', function (Request $request, string $username, JsonUserStore $users, ChatStore $chatStore, FriendStore $friends) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser) {
		return response()->json(['error' => 'No autenticado'], 401);
	}

	if (($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Los invitados no pueden enviar mensajes privados.'], 403);
	}

	$users->touchPresence((string) $authUser['username']);

	$isOllamaConversation = strtolower(trim($username)) === virthub_ollama_chat_username();

	if (!$isOllamaConversation) {
		$targetUser = $users->findByUsername($username);

		if (!$targetUser || !($targetUser['is_active'] ?? true)) {
			return response()->json(['error' => 'Usuario no encontrado'], 404);
		}

		if (!in_array($username, $friends->friendsOf((string) $authUser['username']), true)) {
			return response()->json(['error' => 'Solo puedes conversar con tus amigos aceptados.'], 403);
		}
	}

	$validated = $request->validate([
		'message' => 'required|string|max:1000',
	]);

	if ($isOllamaConversation) {
		if (($authUser['role'] ?? 'user') !== 'admin') {
			return response()->json(['error' => 'Solo el admin puede usar esta IA.'], 403);
		}

		$ollamaSettings = virthub_ollama_settings();

		if (!$ollamaSettings['enabled']) {
			return response()->json([
				'error' => 'Configura OLLAMA_BASE_URL en .env para habilitar la IA local.',
			], 503);
		}

		$existingMessages = $chatStore->getConversationMessages((string) $authUser['username'], virthub_ollama_chat_username());
		$ollamaMessages = [[
			'role' => 'system',
			'content' => $ollamaSettings['system_prompt'],
		]];

		foreach ($existingMessages as $existingMessage) {
			$ollamaMessages[] = [
				'role' => (($existingMessage['from'] ?? '') === virthub_ollama_chat_username()) ? 'assistant' : 'user',
				'content' => (string) ($existingMessage['message'] ?? ''),
			];
		}

		$ollamaMessages[] = [
			'role' => 'user',
			'content' => (string) $validated['message'],
		];

		try {
			set_time_limit(9999);

			$response = Http::connectTimeout($ollamaSettings['connect_timeout'])
				->timeout($ollamaSettings['request_timeout'])
				->acceptJson()
				->post(rtrim($ollamaSettings['base_url'], '/') . '/api/chat', [
					'model' => $ollamaSettings['model'],
					'messages' => $ollamaMessages,
					'stream' => false,
				]);
		} catch (Throwable $e) {
			return response()->json([
				'error' => 'No se pudo conectar con Ollama o la respuesta tardó demasiado.',
			], 502);
		}

		if (!$response->successful()) {
			return response()->json([
				'error' => 'Ollama devolvio un error.',
				'details' => $response->json() ?: $response->body(),
			], 502);
		}

		$assistantReply = trim((string) data_get($response->json(), 'message.content', ''));

		if ($assistantReply === '') {
			return response()->json([
				'error' => 'Ollama no devolvio texto util.',
			], 502);
		}

		$userMessage = $chatStore->appendConversationMessage(
			(string) $authUser['username'],
			virthub_ollama_chat_username(),
			(string) $validated['message']
		);

		$assistantMessage = $chatStore->appendConversationMessage(
			virthub_ollama_chat_username(),
			(string) $authUser['username'],
			$assistantReply
		);

		return response()->json([
			'user_message' => $userMessage,
			'assistant_message' => $assistantMessage,
			'model' => $ollamaSettings['model'],
		], 201);
	}

	$message = $chatStore->appendConversationMessage(
		(string) $authUser['username'],
		$username,
		(string) $validated['message']
	);
	
	// Enriquecer mensaje con profile_image_path del remitente
	$sender = $users->findByUsername($message['from'] ?? '');
	if ($sender) {
		$message['profile_image_path'] = $sender['profile_image_path'] ?? null;
	}

	return response()->json(['message' => $message], 201);
});

Route::get('/chat/broadcast', function (Request $request, JsonUserStore $users, ChatStore $chatStore) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser) {
		return response()->json(['error' => 'No autenticado'], 401);
	}

	if (($authUser['role'] ?? 'guest') !== 'guest') {
		$users->touchPresence((string) $authUser['username']);
	}

	$messages = $chatStore->getBroadcastMessages();
	
	// Enriquecer mensajes con información de perfil del usuario
	$enrichedMessages = array_map(function ($message) use ($users) {
		$sender = $users->findByUsername($message['from'] ?? '');
		if ($sender) {
			$message['profile_image_path'] = $sender['profile_image_path'] ?? null;
		}
		return $message;
	}, $messages);

	return response()->json(['messages' => $enrichedMessages], 200);
});

Route::post('/chat/broadcast', function (Request $request, JsonUserStore $users, ChatStore $chatStore) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return response()->json(['error' => 'Solo admin puede publicar anuncios'], 403);
	}

	$users->touchPresence((string) $authUser['username']);

	$validated = $request->validate([
		'message' => 'required|string|max:1000',
	]);

	$message = $chatStore->appendBroadcastMessage(
		(string) $authUser['username'],
		(string) $validated['message']
	);

	return response()->json(['message' => $message], 201);
})->middleware('throttle:20,1');

Route::get('/contenedor', function (Request $request) {
	$authUser = virthub_active_user($request, app(JsonUserStore::class));
	$guestRemainingSeconds = null;
	$ollamaSettings = virthub_ollama_settings();
	$canUseOllama = ($authUser['role'] ?? 'user') === 'admin';

	if (!$authUser) {
		return redirect('/')->with('error', 'Tu cuenta fue desactivada o la sesion ya no es valida.');
	}

	if (($authUser['role'] ?? 'user') === 'guest') {
		$guestRemainingSeconds = max(0, (int) $request->session()->get('guest_expires_at', 0) - time());
	}

	return view('contenedor', [
		'currentUser' => $authUser,
		'guestRemainingSeconds' => $guestRemainingSeconds,
		'ollamaEnabled' => $ollamaSettings['enabled'],
		'ollamaModel' => $ollamaSettings['model'],
		'ollamaVisible' => $canUseOllama,
	]);
});

Route::get('/contenedor/launch', function (Request $request) {
	$authUser = virthub_active_user($request, app(JsonUserStore::class));

	if (!$authUser) {
		return redirect('/')->with('error', 'Tu cuenta fue desactivada o la sesion ya no es valida.');
	}

	$url = virthub_get_container_url($authUser);
	virthub_audit($request, 'webtop.launch', $authUser, [
		'container_url' => $url,
	]);

	return redirect()->away($url);
});

Route::post('/ai/ollama', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser) {
		return response()->json(['error' => 'Tu sesion no es valida.'], 403);
	}

	if (($authUser['role'] ?? 'user') !== 'admin') {
		return response()->json(['error' => 'Solo el admin puede usar esta IA.'], 403);
	}

	$validated = $request->validate([
		'prompt' => 'required|string|max:4000',
	]);

	$ollamaSettings = virthub_ollama_settings();

	if (!$ollamaSettings['enabled']) {
		return response()->json([
			'error' => 'Configura OLLAMA_BASE_URL en .env para habilitar la IA local.',
		], 503);
	}

	try {
		$response = Http::timeout(120)
			->acceptJson()
			->post(rtrim($ollamaSettings['base_url'], '/') . '/api/generate', [
				'model' => $ollamaSettings['model'],
				'prompt' => $validated['prompt'],
				'system' => $ollamaSettings['system_prompt'],
				'stream' => false,
			]);
	} catch (Throwable $e) {
		return response()->json([
			'error' => 'No se pudo conectar con Ollama.',
		], 502);
	}

	if (!$response->successful()) {
		return response()->json([
			'error' => 'Ollama devolvio un error.',
			'details' => $response->json() ?: $response->body(),
		], 502);
	}

	$reply = trim((string) ($response->json('response') ?? ''));

	if ($reply === '') {
		return response()->json([
			'error' => 'Ollama no devolvio texto util.',
		], 502);
	}

	return response()->json([
		'response' => $reply,
		'model' => $ollamaSettings['model'],
	], 200);
})->middleware('throttle:10,1');

Route::get('/system-status', function (Request $request) {
	$authUser = virthub_active_user($request, app(JsonUserStore::class));

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return response()->json(['error' => 'Solo admin'], 403);
	}

	return response()->json([
		'status' => Cache::remember('virthub.system_status', 15, fn (): array => virthub_system_status()),
	], 200);
});

Route::get('/linux-news', function () {
	return response()->json([
		'items' => virthub_cached_feed_items('virthub.news.linux', 'https://www.phoronix.com/rss.php'),
	], 200);
});

Route::get('/cyber-news', function () {
	return response()->json([
		'items' => virthub_cached_feed_items('virthub.news.cyber', 'https://feeds.feedburner.com/TheHackersNews'),
	], 200);
});
