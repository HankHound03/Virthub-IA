<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use App\Services\UserStore;
use App\Support\ActiveUser;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Autenticacion: contrasena, segundo factor, invitados y cierre de sesion.
 *
 * El bloqueo por fallos vive en la sesion (compatibilidad) pero el limite por
 * IP lo impone el rate limiter 'login-ip' registrado en AppServiceProvider.
 */
class AuthController extends Controller
{
    private const MAX_LOGIN_FAILURES = 5;

    private const LOCK_SECONDS = 120;

    private const MAX_TWO_FACTOR_FAILURES = 5;

    public function __construct(
        private readonly UserStore $users,
        private readonly TwoFactorService $twoFactor,
        private readonly SecurityAudit $audit,
    ) {}

    public function login(Request $request): JsonResponse|RedirectResponse
    {
        $remaining = $this->lockRemaining($request);

        if ($remaining > 0) {
            $message = 'Demasiados intentos fallidos. Espera ' . $remaining . ' segundos.';

            return $request->expectsJson()
                ? response()->json(['error' => $message], 429)
                : redirect('/')->with('error', $message);
        }

        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $this->users->bootstrapAdminFromEnv();

        $authUser = $this->users->verifyCredentials(
            (string) $validated['username'],
            (string) $validated['password']
        );

        if ($authUser === null) {
            return $this->handleFailedLogin($request);
        }

        if ($this->users->hasTwoFactorEnabled((string) $authUser['username'])) {
            return $this->beginTwoFactorChallenge($request, $authUser);
        }

        return $this->completeLogin($request, $authUser, 'authentication.login');
    }

    public function verifyTwoFactor(Request $request): JsonResponse|RedirectResponse
    {
        $pendingUsername = $this->pendingTwoFactorUsername($request);

        if ($pendingUsername === '') {
            return $this->twoFactorExpired($request);
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $this->users->findByUsername($pendingUsername);
        $encryptedSecret = $this->users->twoFactorSecret($pendingUsername);

        if ($user === null || ! ($user['is_active'] ?? true) || $encryptedSecret === null) {
            $request->session()->forget(['two_factor_pending_username', 'two_factor_fail_count']);

            return $this->twoFactorExpired($request);
        }

        if (! $this->twoFactor->verifyCode($this->twoFactor->decryptSecret($encryptedSecret), (string) $validated['code'])) {
            return $this->handleFailedTwoFactor($request, $pendingUsername, (string) ($user['role'] ?? 'user'));
        }

        return $this->completeLogin($request, [
            'username' => $user['username'],
            'role' => $user['role'] ?? 'user',
        ], 'authentication.2fa_verified');
    }

    public function verifyRecoveryCode(Request $request): JsonResponse|RedirectResponse
    {
        $pendingUsername = $this->pendingTwoFactorUsername($request);

        if ($pendingUsername === '') {
            return $this->twoFactorExpired($request);
        }

        $validated = $request->validate([
            'recovery_code' => ['required', 'string', 'max:32'],
        ]);

        $user = $this->users->findByUsername($pendingUsername);

        $valid = $user !== null
            && ($user['is_active'] ?? true)
            && $this->users->consumeTwoFactorRecoveryCode($pendingUsername, (string) $validated['recovery_code']);

        if (! $valid) {
            $this->audit->log($request, 'authentication.2fa_recovery_failed', [
                'username' => $pendingUsername,
                'role' => $user['role'] ?? null,
            ]);

            return $request->expectsJson()
                ? response()->json(['error' => 'El codigo de recuperacion no es valido.'], 422)
                : redirect('/')->with('error', 'El codigo de recuperacion no es valido.');
        }

        return $this->completeLogin($request, [
            'username' => $user['username'],
            'role' => $user['role'] ?? 'user',
        ], 'authentication.2fa_recovery_used', 'Sesion iniciada con un codigo de recuperacion.');
    }

    /**
     * Acceso temporal sin cuenta. El limite por IP lo aplica el rate limiter
     * 'guest-login', no este metodo.
     */
    public function guestLogin(Request $request): RedirectResponse
    {
        $request->session()->regenerate();

        $guestName = 'guest_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $minutes = max(1, (int) config('virthub.guests.session_minutes', 30));

        $request->session()->put('auth_user', [
            'username' => $guestName,
            'role' => 'guest',
        ]);
        $request->session()->put('guest_expires_at', time() + ($minutes * 60));

        $this->audit->log($request, 'authentication.guest_login', [
            'username' => $guestName,
            'role' => 'guest',
        ]);

        return redirect('/')->with('success', 'Acceso temporal activado por ' . $minutes . ' minutos.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit->log($request, 'authentication.logout', ActiveUser::from($request));

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Sesion cerrada.');
    }

    public function settings(Request $request, TwoFactorService $twoFactor): View|RedirectResponse
    {
        $currentUser = ActiveUser::from($request);

        if (! ActiveUser::isRegistered($currentUser)) {
            return redirect('/')->with('error', 'Solo usuarios registrados pueden acceder a configuracion.');
        }

        $setupSecret = (string) $request->session()->get('two_factor_setup_secret', '');
        $twoFactorSetupQr = null;

        if ($setupSecret !== '') {
            try {
                $twoFactorSetupQr = $twoFactor->qrCodeDataUri(
                    ActiveUser::username($currentUser),
                    $twoFactor->decryptSecret($setupSecret)
                );
            } catch (Throwable) {
                $request->session()->forget('two_factor_setup_secret');
            }
        }

        return view('configuracion', [
            'currentUser' => $currentUser,
            'twoFactorEnabled' => $this->users->hasTwoFactorEnabled(ActiveUser::username($currentUser)),
            'twoFactorSetupQr' => $twoFactorSetupQr,
        ]);
    }

    public function setupTwoFactor(Request $request): RedirectResponse
    {
        $username = ActiveUser::username(ActiveUser::from($request));

        if ($this->users->hasTwoFactorEnabled($username)) {
            return redirect('/configuracion')->with('error', 'El 2FA ya esta activado.');
        }

        $request->session()->put(
            'two_factor_setup_secret',
            $this->twoFactor->encryptSecret($this->twoFactor->generateSecret())
        );

        return redirect('/configuracion')->with('success', 'Escanea el codigo QR y confirma el codigo generado.');
    }

    public function confirmTwoFactor(Request $request): JsonResponse|RedirectResponse
    {
        $authUser = ActiveUser::from($request);

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $encryptedSecret = (string) $request->session()->get('two_factor_setup_secret', '');

        if ($encryptedSecret === '') {
            return redirect('/configuracion')->with('error', 'Inicia primero la configuracion de 2FA.');
        }

        try {
            $secret = $this->twoFactor->decryptSecret($encryptedSecret);
        } catch (Throwable) {
            $request->session()->forget('two_factor_setup_secret');

            return redirect('/configuracion')->with('error', 'La configuracion de 2FA ya no es valida.');
        }

        if (! $this->twoFactor->verifyCode($secret, (string) $validated['code'])) {
            $this->audit->log($request, 'authentication.2fa_setup_failed', $authUser);

            return redirect('/configuracion')->with('error', 'El codigo de 2FA no es valido.');
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $this->users->enableTwoFactor(
            ActiveUser::username($authUser),
            $encryptedSecret,
            array_map([$this->twoFactor, 'hashRecoveryCode'], $recoveryCodes)
        );

        $request->session()->forget('two_factor_setup_secret');
        $this->audit->log($request, 'security.2fa_enabled', $authUser);

        if ($request->expectsJson()) {
            return response()->json([
                'two_factor_enabled' => true,
                'recovery_codes' => $recoveryCodes,
            ]);
        }

        return redirect('/configuracion')->with('two_factor_recovery_codes', $recoveryCodes);
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $authUser = ActiveUser::from($request);
        $username = ActiveUser::username($authUser);

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:72'],
            'code' => ['required', 'digits:6'],
        ]);

        if (! $this->users->verifyPassword($username, (string) $validated['current_password'])) {
            return redirect('/configuracion')->with('error', 'La contrasena actual no es correcta.');
        }

        if (! $this->twoFactorCodeIsValid($username, (string) $validated['code'])) {
            return redirect('/configuracion')->with('error', 'El codigo de 2FA no es valido.');
        }

        $this->users->disableTwoFactor($username);
        $this->audit->log($request, 'security.2fa_disabled', $authUser);

        return redirect('/configuracion')->with('success', 'El 2FA fue desactivado.');
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $authUser = ActiveUser::from($request);
        $username = ActiveUser::username($authUser);

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'max:72'],
            'code' => ['required', 'digits:6'],
        ]);

        if (! $this->users->hasTwoFactorEnabled($username)
            || ! $this->users->verifyPassword($username, (string) $validated['current_password'])) {
            return response()->json(['error' => 'La contrasena actual no es correcta.'], 422);
        }

        if (! $this->twoFactorCodeIsValid($username, (string) $validated['code'])) {
            return response()->json(['error' => 'El codigo de 2FA no es valido.'], 422);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $this->users->replaceTwoFactorRecoveryCodes(
            $username,
            array_map([$this->twoFactor, 'hashRecoveryCode'], $recoveryCodes)
        );

        $this->audit->log($request, 'security.2fa_recovery_codes_regenerated', $authUser);

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    private function twoFactorCodeIsValid(string $username, string $code): bool
    {
        $encryptedSecret = $this->users->twoFactorSecret($username);

        if ($encryptedSecret === null) {
            return false;
        }

        return $this->twoFactor->verifyCode($this->twoFactor->decryptSecret($encryptedSecret), $code);
    }

    private function lockRemaining(Request $request): int
    {
        $lockedUntil = (int) $request->session()->get('login_locked_until', 0);

        return $lockedUntil > time() ? $lockedUntil - time() : 0;
    }

    private function handleFailedLogin(Request $request): JsonResponse|RedirectResponse
    {
        $this->audit->log($request, 'authentication.failed', null, ['reason' => 'invalid_credentials']);

        $failures = (int) $request->session()->get('login_fail_count', 0) + 1;
        $request->session()->put('login_fail_count', $failures);

        if ($failures >= self::MAX_LOGIN_FAILURES) {
            $request->session()->put('login_locked_until', time() + self::LOCK_SECONDS);
            $request->session()->put('login_fail_count', 0);

            $message = 'Bloqueado temporalmente por fallos. Espera ' . self::LOCK_SECONDS . ' segundos.';

            return $request->expectsJson()
                ? response()->json(['error' => $message], 429)
                : redirect('/')->withInput($request->only('username'))->with('error', $message);
        }

        $message = 'Usuario o contrasena incorrectos. Fallos: ' . $failures . '/' . self::MAX_LOGIN_FAILURES . '.';

        return $request->expectsJson()
            ? response()->json(['error' => $message], 422)
            : redirect('/')->withInput($request->only('username'))->with('error', $message);
    }

    /**
     * @param  array<string, mixed>  $authUser
     */
    private function beginTwoFactorChallenge(Request $request, array $authUser): JsonResponse|RedirectResponse
    {
        $request->session()->regenerate();
        $request->session()->put('two_factor_pending_username', $authUser['username']);
        $request->session()->forget('two_factor_fail_count');

        $message = 'Introduce el codigo de Google Authenticator para continuar.';

        return $request->expectsJson()
            ? response()->json(['two_factor_required' => true, 'message' => $message])
            : redirect('/')->with('success', $message);
    }

    private function handleFailedTwoFactor(Request $request, string $username, string $role): JsonResponse|RedirectResponse
    {
        $failures = (int) $request->session()->get('two_factor_fail_count', 0) + 1;
        $request->session()->put('two_factor_fail_count', $failures);

        $this->audit->log($request, 'authentication.2fa_failed', [
            'username' => $username,
            'role' => $role,
        ]);

        if ($failures >= self::MAX_TWO_FACTOR_FAILURES) {
            $request->session()->forget(['two_factor_pending_username', 'two_factor_fail_count']);

            $message = 'Demasiados codigos 2FA incorrectos. Inicia sesion nuevamente.';

            return $request->expectsJson()
                ? response()->json(['error' => $message], 429)
                : redirect('/')->with('error', $message);
        }

        return $request->expectsJson()
            ? response()->json(['error' => 'El codigo de 2FA no es valido.'], 422)
            : redirect('/')->with('error', 'El codigo de 2FA no es valido.');
    }

    private function twoFactorExpired(Request $request): JsonResponse|RedirectResponse
    {
        $message = 'La verificacion 2FA ya no es valida. Inicia sesion nuevamente.';

        return $request->expectsJson()
            ? response()->json(['error' => $message], 422)
            : redirect('/')->with('error', $message);
    }

    private function pendingTwoFactorUsername(Request $request): string
    {
        return trim((string) $request->session()->get('two_factor_pending_username', ''));
    }

    /**
     * @param  array<string, mixed>  $authUser
     */
    private function completeLogin(
        Request $request,
        array $authUser,
        string $auditEvent,
        string $successMessage = 'Sesion iniciada correctamente.',
    ): JsonResponse|RedirectResponse {
        $request->session()->regenerate();
        $request->session()->put('auth_user', $authUser);
        $request->session()->forget(['login_fail_count', 'login_locked_until', 'guest_expires_at']);

        $this->users->recordLogin((string) $authUser['username']);
        $this->audit->log($request, $auditEvent, $authUser);

        return $request->expectsJson()
            ? response()->json(['authenticated' => true])
            : redirect('/')->with('success', $successMessage);
    }
}
