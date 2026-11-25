<?php

use App\Http\Controllers\Admin\DeveloperBadgeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GameMatchController;
use App\Http\Controllers\GameRoomController;
use App\Http\Controllers\GuestPlayController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlayerSearchController;
use App\Http\Controllers\ProfileAppearanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileImageController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\RealtimeCallbackController;
use App\Http\Controllers\RealtimeTokenController;
use App\Http\Controllers\RecommendedPlayerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

// Jogar sem conta: o perfil temporário é criado aqui.
Route::get('/jogar', [GuestPlayController::class, 'create'])->name('guest.create');
Route::get('/jogar/nome', [GuestPlayController::class, 'availability'])->middleware('throttle:30,1')->name('guest.name');
Route::post('/jogar', [GuestPlayController::class, 'store'])->middleware('throttle:6,1')->name('guest.store');
Route::post('/jogar/sair', [GuestPlayController::class, 'destroy'])->middleware('auth:visitor')->name('guest.leave');
Route::post('/realtime/callback', RealtimeCallbackController::class)->middleware('throttle:120,1')->name('realtime.callback');
Route::get('/convites/{token}', [GameRoomController::class, 'open'])->where('token', '[A-Za-z2-9]{5}')->middleware('throttle:30,1')->name('invites.open');

// Quem joga: conta permanente (guard web) OU visitante (guard visitor).
Route::middleware('auth:web,visitor')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/jogadores/recomendados', RecommendedPlayerController::class)->name('players.recommended');
    Route::post('/jogadores/recomendados/atualizar', [RecommendedPlayerController::class, 'refresh'])->middleware('throttle:10,1')->name('players.recommended.refresh');
    Route::post('/salas', [GameRoomController::class, 'store'])->middleware('throttle:10,1')->name('rooms.store');
    Route::get('/salas/{room}', [GameRoomController::class, 'show'])->whereUuid('room')->name('rooms.show');
    Route::post('/salas/{room}/convite', [GameRoomController::class, 'invite'])->whereUuid('room')->middleware('throttle:10,1')->name('rooms.invite');
    Route::delete('/salas/{room}/convite', [GameRoomController::class, 'revoke'])->whereUuid('room')->name('rooms.invite.revoke');
    Route::delete('/salas/{room}', [GameRoomController::class, 'cancel'])->whereUuid('room')->name('rooms.cancel');
    Route::delete('/salas/{room}/participacao', [GameRoomController::class, 'leave'])->whereUuid('room')->name('rooms.leave');
    Route::post('/salas/{room}/partida', [GameMatchController::class, 'start'])->whereUuid('room')->middleware('throttle:10,1')->name('games.start');
    Route::get('/salas/{room}/partida', [GameMatchController::class, 'show'])->whereUuid('room')->name('games.show');
    Route::post('/salas/{room}/partida/acoes', [GameMatchController::class, 'action'])->whereUuid('room')->middleware('throttle:60,1')->name('games.action');
    Route::post('/salas/{room}/partida/bots', [GameMatchController::class, 'botStep'])->whereUuid('room')->middleware('throttle:120,1')->name('games.bots');
    Route::post('/realtime/token', RealtimeTokenController::class)->middleware('throttle:30,1')->name('realtime.token');
    Route::post('/convites/{token}', [GameRoomController::class, 'join'])->where('token', '[A-Za-z2-9]{5}')->middleware('throttle:10,1')->name('rooms.join');
});

Route::middleware('auth')->group(function () {
    Route::post('/onboarding/concluir', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::get('/jogadores/busca', PlayerSearchController::class)->middleware('throttle:60,1')->name('players.search');
    Route::get('/rankings', RankingController::class)->name('rankings');

    // Social: só contas permanentes (visitantes e bots não seguem nem são seguidos).
    Route::post('/perfil/{username}/seguir', [FollowController::class, 'store'])->where('username', '[A-Za-z0-9_-]{3,30}')->middleware('throttle:30,1')->name('follow.store');
    Route::delete('/perfil/{username}/seguir', [FollowController::class, 'destroy'])->where('username', '[A-Za-z0-9_-]{3,30}')->name('follow.destroy');
    Route::get('/perfil/{username}/seguidores', [FollowController::class, 'followers'])->where('username', '[A-Za-z0-9_-]{3,30}')->name('profile.followers');
    Route::get('/perfil/{username}/seguindo', [FollowController::class, 'following'])->where('username', '[A-Za-z0-9_-]{3,30}')->name('profile.following');

    // Central de notificações (as que valem ser consultadas depois).
    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notificacoes/recentes', [NotificationController::class, 'recent'])->middleware('throttle:30,1')->name('notifications.recent');
    Route::post('/notificacoes/lidas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notificacoes/{id}/lida', [NotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/configuracoes', [ProfileController::class, 'settings'])->name('settings');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/appearance', [ProfileAppearanceController::class, 'update'])->name('profile.appearance.update');
    Route::put('/profile/conquistas/destaques', [\App\Http\Controllers\ProfileAchievementController::class, 'featured'])->middleware('throttle:30,1')->name('profile.achievements.featured');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::patch('/admin/users/{user}/developer-badge', [DeveloperBadgeController::class, 'update'])
    ->middleware(['auth', 'can:manage-developer-badges'])
    ->name('admin.users.developer-badge.update');

Route::patch('/admin/users/{username}/verified', [\App\Http\Controllers\Admin\VerifiedController::class, 'update'])
    ->where('username', '[A-Za-z0-9_-]{3,30}')
    ->middleware(['auth', 'can:manage-verified', 'throttle:60,1'])
    ->name('admin.users.verified.update');

// Foto e capa como imagem cacheável (o JSON das páginas leva só a URL versionada).
Route::get('/imagens/{kind}/{user}', ProfileImageController::class)->where('kind', 'avatar|cover')->whereUuid('user')->middleware('throttle:240,1')->name('profile.image');

Route::get('/perfil/{username}', [PublicProfileController::class, 'show'])
    ->where('username', '[A-Za-z0-9_-]{3,30}')
    ->name('profile.show');
Route::get('/u/{username}', [PublicProfileController::class, 'show'])
    ->where('username', '[A-Za-z0-9_-]{3,30}')
    ->name('profile.public');

require __DIR__.'/auth.php';
