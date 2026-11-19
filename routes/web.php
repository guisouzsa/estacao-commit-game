<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileAppearanceController;
use App\Http\Controllers\Admin\DeveloperBadgeController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
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
