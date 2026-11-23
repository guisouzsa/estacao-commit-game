<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileAppearanceController;
use App\Http\Controllers\Admin\DeveloperBadgeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameRoomController;
use App\Http\Controllers\PlayerSearchController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', DashboardController::class)->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('/onboarding/concluir', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::get('/jogadores/busca', PlayerSearchController::class)->middleware('throttle:60,1')->name('players.search');
    Route::get('/rankings', RankingController::class)->name('rankings');
    Route::post('/salas', [GameRoomController::class, 'store'])->middleware('throttle:10,1')->name('rooms.store');
    Route::get('/salas/{room}', [GameRoomController::class, 'show'])->name('rooms.show');
    Route::post('/salas/{room}/convite', [GameRoomController::class, 'invite'])->middleware('throttle:10,1')->name('rooms.invite');
    Route::delete('/salas/{room}/convite', [GameRoomController::class, 'revoke'])->name('rooms.invite.revoke');
    Route::delete('/salas/{room}', [GameRoomController::class, 'cancel'])->name('rooms.cancel');
    Route::post('/convites/{token}', [GameRoomController::class, 'join'])->where('token', '[A-Z2-9]{5}')->middleware('throttle:10,1')->name('rooms.join');
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
