<?php

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
		];
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
});

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

Route::get('/admin/users', function (Request $request, JsonUserStore $users) {
	$authUser = virthub_active_user($request, $users);

	if (!$authUser || ($authUser['role'] ?? 'user') !== 'admin') {
		return redirect('/')->with('error', 'Solo admin puede acceder a gestion de usuarios.');
	}

	return view('admin-users', [
		'currentUser' => $authUser,
		'users' => $users->allPublicUsers(),
	]);
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
		$users->deleteUser((string) $validated['username']);
		return redirect('/admin/users')->with('success', 'Usuario ' . $validated['username'] . ' eliminado.');
	} catch (RuntimeException $e) {
		return redirect('/admin/users')->with('error', $e->getMessage());
	}
});

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
