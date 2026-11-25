<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OpsLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\Provider;
use Mockery;
use Tests\TestCase;

/** Log operacional sem segredos e falhas do Google tratadas sem erro 500. */
class OpsLogAndGoogleFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_fields_are_redacted_recursively(): void
    {
        $clean = OpsLog::redact([
            'room' => 'abc', 'password' => 'segredo', 'api_key' => 'xkeysib-123', 'code' => '123456',
            'nested' => ['token' => 'tok', 'ok' => 1, 'cookie' => 'c'], 'Authorization' => 'Bearer x',
        ]);

        $this->assertSame('abc', $clean['room']);
        $this->assertSame('[oculto]', $clean['password']);
        $this->assertSame('[oculto]', $clean['api_key']);
        $this->assertSame('[oculto]', $clean['code']);
        $this->assertSame('[oculto]', $clean['nested']['token']);
        $this->assertSame(1, $clean['nested']['ok']);
        $this->assertSame('[oculto]', $clean['nested']['cookie']);
        $this->assertSame('[oculto]', $clean['Authorization']);
    }

    public function test_events_carry_the_standard_name_and_environment(): void
    {
        Log::spy();

        OpsLog::event('game.turn_timeout', ['room' => 'r1', 'secret' => 'x']);

        Log::shouldHaveReceived('log')->withArgs(fn ($level, $message, $context) => $level === 'info'
            && $message === 'game.turn_timeout' && $context['event'] === 'game.turn_timeout'
            && $context['env'] === config('app.env') && $context['secret'] === '[oculto]')->once();
    }

    public function test_login_and_failed_login_are_logged_without_the_email_or_password(): void
    {
        Log::spy();
        $user = User::factory()->create(['password' => 'Senha-forte-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'errada-errada']);
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => $user->email, 'password' => 'Senha-forte-123']);

        Log::shouldHaveReceived('log')->withArgs(fn ($level, $message, $context) => $message === 'auth.login_failed'
            && ! str_contains(json_encode($context), $user->email) && ! str_contains(json_encode($context), 'errada'))->once();
        Log::shouldHaveReceived('log')->withArgs(fn ($level, $message) => $message === 'auth.login')->once();
    }

    private function configureGoogle(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 's', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
    }

    public function test_google_unavailable_ends_in_a_friendly_message_not_a_500(): void
    {
        $this->configureGoogle();
        Log::spy();
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('cURL error 28: Operation timed out with client_secret=SEGREDO'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, 'e-mail e senha'));

        $this->assertGuest();
        Log::shouldHaveReceived('log')->withArgs(fn ($level, $message, $context) => $level === 'error' && $message === 'integration.failure'
            && $context['service'] === 'google_oauth' && ! str_contains($context['message'], 'SEGREDO'))->once();
    }

    public function test_wrong_google_client_secret_or_redirect_uri_is_handled_the_same_way(): void
    {
        $this->configureGoogle();
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('Client error: 401 invalid_client'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get(route('google.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_user_cancelling_at_google_returns_to_login_with_an_explanation(): void
    {
        $this->configureGoogle();

        $this->get(route('google.callback', ['error' => 'access_denied']))
            ->assertRedirect(route('login'))->assertSessionHas('status');
        $this->assertGuest();
    }

    public function test_google_without_credentials_does_not_start_the_flow(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->get(route('google.redirect'))->assertRedirect(route('login'))->assertSessionHas('status');
    }
}
