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
        // Sem Vite::prefetch(): ele baixava TODOS os pedaços de página (~40 arquivos, ~900 kB)
        // logo após a primeira carga, 3 por vez. O servidor atende só 2 requisições ao mesmo
        // tempo (512 MB de RAM), então esse download em segundo plano disputava vaga com o
        // que a pessoa realmente clicava. Cada página baixa o seu pedaço quando é aberta.

        // Guard "visitor": quem é o visitante vem sempre do cookie HttpOnly.
        Auth::viaRequest('visitor-token', fn (Request $request) => app(GuestIdentity::class)->resolve($request));

        // Eventos de autenticação no log operacional (sem e-mail, senha ou token): auth.login, auth.login_failed,
        // auth.register, auth.password_reset. Ver App\Support\OpsLog.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, fn ($e) => \App\Support\OpsLog::event('auth.login', ['user' => $e->user->getAuthIdentifier(), 'guard' => $e->guard]));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, fn ($e) => \App\Support\OpsLog::event('auth.login_failed', ['guard' => $e->guard], 'warning'));
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Registered::class, function ($e) {
            \App\Support\OpsLog::event('auth.register', ['user' => $e->user->getAuthIdentifier()]);
            if ($e->user instanceof \App\Models\User) {
                // A conta já existe: nada aqui pode derrubar o cadastro.
                try {
                    app(\App\Services\Achievements::class)->onRegistered($e->user); // Estrela Inicial
                } catch (\Throwable $failure) {
                    \App\Support\OpsLog::event('achievements.register_failed', ['user' => $e->user->getKey(), 'error' => class_basename($failure)], 'warning');
                }
                app(\App\Services\AccountEmails::class)->welcome($e->user); // boas-vindas (uma vez por conta)
            }
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\PasswordReset::class, fn ($e) => \App\Support\OpsLog::event('auth.password_reset', ['user' => $e->user->getAuthIdentifier()]));

        // Envio de e-mail por API (Brevo): `MAIL_MAILER=brevo` + BREVO_API_KEY.
        \Illuminate\Support\Facades\Mail::extend('brevo', fn () => new \App\Mail\BrevoTransport((string) config('services.brevo.key'), (int) config('mail.http_timeout')));
        \Illuminate\Support\Facades\Mail::extend('resend-http', fn () => new \App\Mail\ResendHttpTransport((string) config('services.resend.key'), (int) config('mail.http_timeout')));

        // E-mail de recuperação de senha com a mesma identidade visual do código de cadastro.
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $base = rtrim((string) config('app.url'), '/');
            $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Redefinir sua senha na Estação Commit')
                ->view(['emails.reset', 'emails.reset-text'], [
                    'actionUrl' => url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)),
                    'minutes' => $minutes,
                    'logo' => $base.'/images/estacao-commit/mascots/foguete-gato.png',
                    'url' => $base,
                    'host' => parse_url($base, PHP_URL_HOST) ?: $base,
                ]);
        });

        // Só quem tem `can_grant_verified` ligado no banco (por SQL) e e-mail confirmado.
        Gate::define('manage-verified', static function (User $user): bool {
            return $user->canGrantVerified();
        });

        Gate::define('manage-developer-badges', static function (User $user): bool {
            // Mesma regra do selo de verificado: permissão gravada no banco (por SQL) e e-mail confirmado.
            return $user->canGrantVerified();
        });
    }
}
