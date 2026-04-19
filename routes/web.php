<?php

use App\Services\ChatStore;
use App\Services\ForumStore;
use App\Services\JsonUserStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

if (!function_exists('virthub_system_status')) {
	function virthub_system_status(): array
	{
		$load = sys_getloadavg();
		$cpuCores = (int) trim((string) @shell_exec('nproc 2>/dev/null'));
		if ($cpuCores <= 0) {
			$cpuCores = 1;
		}

		$cpuUsagePercent = null;
		if (isset($load[0])) {
			$cpuUsagePercent = round(min(100, max(0, (((float) $load[0]) / $cpuCores) * 100)), 1);
		}

		$memTotalKb = null;
		$memAvailableKb = null;
		$memInfoRaw = @file_get_contents('/proc/meminfo');

		if (is_string($memInfoRaw) && $memInfoRaw !== '') {
			if (preg_match('/^MemTotal:\s+(\d+)\s+kB/im', $memInfoRaw, $mTotal)) {
				$memTotalKb = (int) $mTotal[1];
			}
			if (preg_match('/^MemAvailable:\s+(\d+)\s+kB/im', $memInfoRaw, $mAvail)) {
				$memAvailableKb = (int) $mAvail[1];
			}
		}

		$ramUsedMb = null;
		$ramUsedPercent = null;
		if ($memTotalKb && $memAvailableKb !== null && $memTotalKb > 0) {
			$memUsedKb = max(0, $memTotalKb - $memAvailableKb);
			$ramUsedMb = round($memUsedKb / 1024, 1);
			$ramUsedPercent = round(($memUsedKb / $memTotalKb) * 100, 1);
		}

		$diskTotal = @disk_total_space('/');
		$diskFree = @disk_free_space('/');
		$diskUsedPercent = null;

		if (is_int($diskTotal) || is_float($diskTotal)) {
			if ($diskTotal > 0 && (is_int($diskFree) || is_float($diskFree))) {
				$diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
			}
		}

		$webtopUrl = (string) env('WEBTOP_URL', '');
		$webtopOnline = false;

		if ($webtopUrl !== '') {
			try {
				$webtopOnline = Http::timeout(3)->get($webtopUrl)->successful();
			} catch (Throwable $e) {
				$webtopOnline = false;
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
		];
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
			'username' => $freshUser['username'],
			'role' => $freshUser['role'] ?? 'user',
			'profile_image_path' => $freshUser['profile_image_path'] ?? null,
			'profile_frame_color' => $freshUser['profile_frame_color'] ?? '#6ea8ff',
		];
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

Route::get('/', function (Request $request, JsonUserStore $users) {
	$users->bootstrapAdminFromEnv();
	$currentUser = virthub_active_user($request, $users);
	$systemStatus = null;
	$guestRemainingSeconds = null;

	if ($currentUser && ($currentUser['role'] ?? 'user') === 'admin') {
		$systemStatus = virthub_system_status();
	}

	if ($currentUser && ($currentUser['role'] ?? 'user') === 'guest') {
		$guestRemainingSeconds = max(0, (int) $request->session()->get('guest_expires_at', 0) - time());
	}

	return view('home', [
		'currentUser' => $currentUser,
		'systemStatus' => $systemStatus,
		'guestRemainingSeconds' => $guestRemainingSeconds,
	]);
});

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
		'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:5120',
		'poll_question' => 'nullable|string|max:180',
		'poll_option_1' => 'nullable|string|max:120',
		'poll_option_2' => 'nullable|string|max:120',
		'poll_option_3' => 'nullable|string|max:120',
		'poll_option_4' => 'nullable|string|max:120',
	]);

	$pollQuestion = trim((string) ($validated['poll_question'] ?? ''));
	$pollOptions = [];
	foreach (['poll_option_1', 'poll_option_2', 'poll_option_3', 'poll_option_4'] as $field) {
		$option = trim((string) ($validated[$field] ?? ''));
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
		$post = $forumStore->addPost(
			(string) ($currentUser['username'] ?? 'usuario'),
			(string) $validated['content'],
			isset($validated['title']) ? (string) $validated['title'] : null,
			$pollPayload
		);

		if ($request->hasFile('image')) {
			$uploaded = $request->file('image');
			$uploadsDir = public_path('uploads/forum');

			if (!is_dir($uploadsDir)) {
				mkdir($uploadsDir, 0755, true);
			}

			$extension = strtolower((string) $uploaded->getClientOriginalExtension());
			if ($extension === '') {
				$extension = 'jpg';
			}

			$filename = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
			$uploaded->move($uploadsDir, $filename);
			$forumStore->setPostImagePath((string) ($post['id'] ?? ''), 'uploads/forum/' . $filename);
		}

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
		$currentFails = (int) $request->session()->get('login_fail_count', 0) + 1;
		$request->session()->put('login_fail_count', $currentFails);

		if ($currentFails >= 5) {
			$lockSeconds = 120;
			$request->session()->put('login_locked_until', time() + $lockSeconds);
			$request->session()->put('login_fail_count', 0);

			return redirect('/')
				->withInput($request->only('username'))
				->with('error', 'Bloqueado temporalmente por fallos. Espera ' . $lockSeconds . ' segundos.');
		}

		return redirect('/')
			->withInput($request->only('username'))
			->with('error', 'Usuario o contrasena incorrectos. Fallos: ' . $currentFails . '/5');
	}

	$request->session()->regenerate();
	$request->session()->put('auth_user', $authUser);
	$request->session()->forget('login_fail_count');
	$request->session()->forget('login_locked_until');
	$request->session()->forget('guest_expires_at');
	$users->recordLogin((string) $authUser['username']);

	return redirect('/')->with('success', 'Sesion iniciada correctamente.');
})->middleware('throttle:login-ip');

Route::post('/guest-login', function (Request $request) {
	$request->session()->regenerate();

	$guestName = 'guest_' . substr(bin2hex(random_bytes(3)), 0, 6);
	$request->session()->put('auth_user', [
		'username' => $guestName,
		'role' => 'guest',
	]);
	$request->session()->put('guest_expires_at', time() + (30 * 60));

	return redirect('/')->with('success', 'Acceso temporal activado por 30 minutos.');
});

Route::post('/logout', function (Request $request) {
	$request->session()->invalidate();
	$request->session()->regenerateToken();

	return redirect('/')->with('success', 'Sesion cerrada.');
});

Route::get('/configuracion', function (Request $request, JsonUserStore $users) {
	$currentUser = virthub_active_user($request, $users);

	if (!$currentUser || ($currentUser['role'] ?? 'guest') === 'guest') {
		return redirect('/')->with('error', 'Solo usuarios registrados pueden acceder a configuracion.');
	}

	return view('configuracion', [
		'currentUser' => $currentUser,
	]);
});

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
		'username' => ['nullable', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/'],
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
			$role
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
		'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/'],
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
		'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/'],
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
		'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/'],
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
		'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/'],
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

Route::get('/chat/users', function (Request $request, JsonUserStore $users) {
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
	$contacts = array_values(array_filter($users->allPublicUsers(), function (array $user) use ($currentUsername): bool {
		return ($user['username'] ?? '') !== ''
			&& ($user['username'] ?? '') !== $currentUsername;
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

Route::get('/chat/conversation/{username}', function (Request $request, string $username, JsonUserStore $users, ChatStore $chatStore) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser) {
		return response()->json(['error' => 'No autenticado'], 401);
	}

	if (($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Los invitados no pueden abrir conversaciones privadas.'], 403);
	}

	$users->touchPresence((string) $authUser['username']);

	$targetUser = $users->findByUsername($username);

	if (!$targetUser || !($targetUser['is_active'] ?? true)) {
		return response()->json(['error' => 'Usuario no encontrado'], 404);
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

	return response()->json([
		'messages' => $enrichedMessages,
	], 200);
});

Route::post('/chat/conversation/{username}', function (Request $request, string $username, JsonUserStore $users, ChatStore $chatStore) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser) {
		return response()->json(['error' => 'No autenticado'], 401);
	}

	if (($authUser['role'] ?? 'guest') === 'guest') {
		return response()->json(['error' => 'Los invitados no pueden enviar mensajes privados.'], 403);
	}

	$users->touchPresence((string) $authUser['username']);

	$targetUser = $users->findByUsername($username);

	if (!$targetUser || !($targetUser['is_active'] ?? true)) {
		return response()->json(['error' => 'Usuario no encontrado'], 404);
	}

	$validated = $request->validate([
		'message' => 'required|string|max:1000',
	]);

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

	if (!$authUser) {
		return redirect('/')->with('error', 'Tu cuenta fue desactivada o la sesion ya no es valida.');
	}

	if (($authUser['role'] ?? 'user') === 'guest') {
		$guestRemainingSeconds = max(0, (int) $request->session()->get('guest_expires_at', 0) - time());
	}

	return view('contenedor', [
		'currentUser' => $authUser,
		'guestRemainingSeconds' => $guestRemainingSeconds,
	]);
});

Route::get('/contenedor/launch', function (Request $request) {
	$authUser = virthub_active_user($request, app(JsonUserStore::class));

	if (!$authUser) {
		return redirect('/')->with('error', 'Tu cuenta fue desactivada o la sesion ya no es valida.');
	}

	$url = (string) env('WEBTOP_URL', 'https://example.com');

	return redirect()->away($url);
});

Route::get('/system-status', function (Request $request) {
	$authUser = virthub_active_user($request, app(JsonUserStore::class));

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return response()->json(['error' => 'Solo admin'], 403);
	}

	return response()->json(['status' => virthub_system_status()], 200);
});

Route::get('/linux-news', function () {
	$feedUrl = 'https://www.phoronix.com/rss.php';

	try {
		$response = Http::timeout(8)->get($feedUrl);

		if (!$response->successful()) {
			return response()->json(['items' => []], 200);
		}

		$xml = @simplexml_load_string($response->body());

		if (!$xml || !isset($xml->channel->item)) {
			return response()->json(['items' => []], 200);
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

		return response()->json(['items' => $items], 200);
	} catch (Throwable $e) {
		return response()->json(['items' => []], 200);
	}
});

Route::get('/cyber-news', function () {
	$feedUrl = 'https://feeds.feedburner.com/TheHackersNews';

	try {
		$response = Http::timeout(8)->get($feedUrl);

		if (!$response->successful()) {
			return response()->json(['items' => []], 200);
		}

		$xml = @simplexml_load_string($response->body());

		if (!$xml || !isset($xml->channel->item)) {
			return response()->json(['items' => []], 200);
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

		return response()->json(['items' => $items], 200);
	} catch (Throwable $e) {
		return response()->json(['items' => []], 200);
	}
});
