<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileAppearanceController;
use App\Http\Controllers\Admin\DeveloperBadgeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\GameRoomController;
use App\Http\Controllers\GuestPlayController;
use App\Http\Controllers\PlayerSearchController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
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

// Quem joga: conta permanente (guard web) OU visitante (guard visitor).
Route::middleware('auth:web,visitor')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/salas', [GameRoomController::class, 'store'])->middleware('throttle:10,1')->name('rooms.store');
    Route::get('/salas/{room}', [GameRoomController::class, 'show'])->whereUuid('room')->name('rooms.show');
    Route::post('/salas/{room}/convite', [GameRoomController::class, 'invite'])->whereUuid('room')->middleware('throttle:10,1')->name('rooms.invite');
    Route::delete('/salas/{room}/convite', [GameRoomController::class, 'revoke'])->whereUuid('room')->name('rooms.invite.revoke');
    Route::delete('/salas/{room}', [GameRoomController::class, 'cancel'])->whereUuid('room')->name('rooms.cancel');
    Route::post('/convites/{token}', [GameRoomController::class, 'join'])->where('token', '[A-Z2-9]{5}')->middleware('throttle:10,1')->name('rooms.join');
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
    Route::post('/notificacoes/lidas', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notificacoes/{id}/lida', [NotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/appearance', [ProfileAppearanceController::class, 'update'])->name('profile.appearance.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::patch('/admin/users/{user}/developer-badge', [DeveloperBadgeController::class, 'update'])
    ->middleware(['auth', 'can:manage-developer-badges'])
    ->name('admin.users.developer-badge.update');

Route::get('/perfil/{username}', [\App\Http\Controllers\PublicProfileController::class, 'show'])
    ->where('username', '[A-Za-z0-9_-]{3,30}')
    ->name('profile.show');
Route::get('/u/{username}', [\App\Http\Controllers\PublicProfileController::class, 'show'])
    ->where('username', '[A-Za-z0-9_-]{3,30}')
    ->name('profile.public');

require __DIR__.'/auth.php';
