<?php

namespace App\Providers;

use App\Database\PooledPostgresConnection;
use App\Models\User;
use App\Support\GuestIdentity;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Booleanos seguros com prepares emulados no pooler do Neon (ver a classe).
        Connection::resolverFor('pgsql', fn ($connection, $database, $prefix, $config) => new PooledPostgresConnection($connection, $database, $prefix, $config));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Guard "visitor": quem é o visitante vem sempre do cookie HttpOnly.
        Auth::viaRequest('visitor-token', fn (Request $request) => app(GuestIdentity::class)->resolve($request));

        Gate::define('manage-developer-badges', static function (User $user): bool {
            return $user->email_verified_at !== null
                && in_array(strtolower($user->email), config('profiles.admin_emails'), true);
        });
    }
}
