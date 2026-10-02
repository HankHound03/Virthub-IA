<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuggestionController;
use App\Http\Controllers\SystemController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureRegisteredUser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Virthub
|--------------------------------------------------------------------------
|
| Aqui no hay logica: cada ruta apunta a un controlador y declara el
| middleware que necesita. La autorizacion vive en el middleware y en los
| controladores, no repartida por closures.
|
| Grupos:
|   - sin middleware de auth: publico (inicio, instalador, precios, noticias)
|   - 'registered': exige cuenta registrada y activa (los invitados no pasan)
|   - 'admin': exige rol admin
|
*/

// ---------------------------------------------------------------------------
// Publico
// ---------------------------------------------------------------------------

Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/foro', [ForumController::class, 'index'])->name('forum.index');
Route::get('/perfil/{username}', [ProfileController::class, 'show'])->name('profile.show');

Route::get('/sugerencias', [SuggestionController::class, 'index'])->name('suggestions.index');
Route::post('/sugerencias', [SuggestionController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('suggestions.store');

Route::get('/linux-news', [SystemController::class, 'linuxNews'])->name('news.linux');
Route::get('/cyber-news', [SystemController::class, 'cyberNews'])->name('news.cyber');

// ---------------------------------------------------------------------------
// Autenticacion
// ---------------------------------------------------------------------------

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login-ip')
    ->name('login');

Route::post('/login/2fa', [AuthController::class, 'verifyTwoFactor'])
    ->middleware('throttle:10,1')
    ->name('login.2fa');

Route::post('/login/2fa/recovery', [AuthController::class, 'verifyRecoveryCode'])
    ->middleware('throttle:10,1')
    ->name('login.2fa.recovery');

// Los invitados consumen recursos compartidos: limite por IP.
Route::post('/guest-login', [AuthController::class, 'guestLogin'])
    ->middleware('throttle:guest-login')
    ->name('guest.login');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ---------------------------------------------------------------------------
// Escritorios Webtop (invitados y registrados)
// ---------------------------------------------------------------------------

Route::get('/contenedor', [ContainerController::class, 'show'])->name('container.show');
Route::get('/contenedor/launch', [ContainerController::class, 'launch'])->name('container.launch');

// ---------------------------------------------------------------------------
// Requiere cuenta registrada
// ---------------------------------------------------------------------------

Route::middleware(EnsureRegisteredUser::class)->group(function (): void {
    // Espacio de trabajo del escritorio
    Route::get('/home/state', [HomeController::class, 'state'])->name('home.state');
    Route::post('/home/state', [HomeController::class, 'saveState'])
        ->middleware('throttle:60,1')
        ->name('home.state.save');

    // Foro: publicar, votar, reaccionar, comentar y reportar
    Route::post('/foro', [ForumController::class, 'store'])->name('forum.store');
    Route::post('/foro/{postId}/poll-vote', [ForumController::class, 'vote'])
        ->middleware('throttle:60,1')
        ->name('forum.vote');
    Route::post('/foro/{postId}/react', [ForumController::class, 'react'])
        ->middleware('throttle:60,1')
        ->name('forum.react');
    Route::post('/foro/{postId}/comment', [ForumController::class, 'comment'])
        ->middleware('throttle:30,1')
        ->name('forum.comment');
    Route::post('/foro/{postId}/report', [ForumController::class, 'report'])->name('forum.report');
    Route::post('/foro/{postId}/delete', [ForumController::class, 'destroy'])->name('forum.destroy');

    // Subidas por trozos para adjuntos grandes (hasta 5 GB por archivo).
    // El trozo llega como cuerpo binario en crudo, no como formulario.
    Route::post('/attachments/initiate', [AttachmentController::class, 'initiate'])
        ->middleware('throttle:30,1')
        ->name('attachments.initiate');
    Route::post('/attachments/{uploadId}/chunk/{index}', [AttachmentController::class, 'chunk'])
        ->where(['uploadId' => '[a-f0-9]{32}', 'index' => '[0-9]+'])
        ->middleware('throttle:upload-chunk')
        ->name('attachments.chunk');
    Route::post('/attachments/{uploadId}/complete', [AttachmentController::class, 'complete'])
        ->where('uploadId', '[a-f0-9]{32}')
        ->middleware('throttle:60,1')
        ->name('attachments.complete');
    Route::delete('/attachments/{uploadId}', [AttachmentController::class, 'abort'])
        ->where('uploadId', '[a-f0-9]{32}')
        ->middleware('throttle:60,1')
        ->name('attachments.abort');

    // Amistades
    Route::get('/buscar-amigos', [FriendController::class, 'index'])->name('friends.index');    Route::post('/amistad/solicitud', [FriendController::class, 'sendRequest'])
        ->middleware('throttle:30,1')
        ->name('friends.request');
    Route::post('/amistad/responder', [FriendController::class, 'respond'])->name('friends.respond');

    // Perfil propio
    Route::post('/perfil/{username}/publicar', [ProfileController::class, 'publish'])
        ->middleware('throttle:30,1')
        ->name('profile.publish');
    Route::post('/profile/appearance', [ProfileController::class, 'updateAppearance'])->name('profile.appearance');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Seguridad de la cuenta
    Route::get('/configuracion', [AuthController::class, 'settings'])->name('settings');
    Route::post('/security/2fa/setup', [AuthController::class, 'setupTwoFactor'])
        ->middleware('throttle:10,1')
        ->name('security.2fa.setup');
    Route::post('/security/2fa/confirm', [AuthController::class, 'confirmTwoFactor'])
        ->middleware('throttle:10,1')
        ->name('security.2fa.confirm');
    Route::post('/security/2fa/disable', [AuthController::class, 'disableTwoFactor'])
        ->middleware('throttle:10,1')
        ->name('security.2fa.disable');
    Route::post('/security/2fa/recovery-codes', [AuthController::class, 'regenerateRecoveryCodes'])
        ->middleware('throttle:10,1')
        ->name('security.2fa.recovery-codes');

    // Chat y presencia
    Route::get('/chat/users', [ChatController::class, 'contacts'])->name('chat.users');
    Route::get('/chat/friend-requests', [ChatController::class, 'friendRequests'])->name('chat.friend-requests');
    Route::post('/chat/friend-requests/{requestId}', [ChatController::class, 'respondToFriendRequest'])
        ->name('chat.friend-requests.respond');
    Route::post('/chat/presence', [ChatController::class, 'touchPresence'])
        ->middleware('throttle:60,1')
        ->name('chat.presence');
    Route::get('/chat/conversation/{username}', [ChatController::class, 'conversation'])->name('chat.conversation');
    Route::post('/chat/conversation/{username}', [ChatController::class, 'sendMessage'])
        ->middleware('throttle:chat-send')
        ->name('chat.conversation.send');
    Route::get('/chat/broadcast', [ChatController::class, 'broadcast'])->name('chat.broadcast');
});

// ---------------------------------------------------------------------------
// Solo admin
// ---------------------------------------------------------------------------

Route::middleware(EnsureAdmin::class)->group(function (): void {
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::post('/admin/users', [AdminController::class, 'createUser'])->name('admin.users.create');
    Route::post('/admin/users/password', [AdminController::class, 'updatePassword'])->name('admin.users.password');
    Route::post('/admin/users/deactivate', [AdminController::class, 'deactivateUser'])->name('admin.users.deactivate');
    Route::post('/admin/users/activate', [AdminController::class, 'activateUser'])->name('admin.users.activate');
    Route::post('/admin/users/delete', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::post('/admin/forum-reports/delete', [AdminController::class, 'deleteForumReport'])
        ->name('admin.forum-reports.delete');

    Route::get('/system-status', [SystemController::class, 'status'])->name('system.status');

    Route::post('/chat/broadcast', [ChatController::class, 'publishBroadcast'])
        ->middleware('throttle:20,1')
        ->name('chat.broadcast.publish');

    Route::post('/ai/ollama', [AiController::class, 'generate'])
        ->middleware('throttle:10,1')
        ->name('ai.ollama');
});
