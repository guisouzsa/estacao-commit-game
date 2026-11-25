<?php

namespace Tests\Feature;

use App\Mail\EmailCodeMail;
use App\Mail\GoogleLinkedMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/**
 * Boas-vindas (cadastro por e-mail ou Google) e aviso de Google vinculado (botão do perfil ou entrar com
 * Google numa conta de e-mail): uma vez cada, nunca no login comum, nunca derrubam a entrada.
 */
class WelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'novo@gmail.com', string $username = 'novato')
    {
        return $this->post('/register', [
            'username' => $username, 'email' => $email,
            'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
        ]);
    }

    private function registerWithGoogle()
    {
        return $this->withSession([
            'pending_google_user' => ['google_id' => 'google-123', 'email' => 'jogador@gmail.com', 'display_name' => 'Jogador'],
        ])->post(route('google.username.store'), ['username' => 'Jogador_G']);
    }

    public function test_an_email_signup_gets_one_welcome_email_for_email_accounts(): void
    {
        Mail::fake();

        $this->register()->assertRedirect(route('dashboard', absolute: false));

        Mail::assertSent(WelcomeMail::class, 1);
        Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->hasTo('novo@gmail.com')
            && $mail->username === 'novato' && $mail->via === WelcomeMail::VIA_EMAIL);
    }

    public function test_with_code_verification_the_welcome_comes_only_after_the_code_is_confirmed(): void
    {
        config(['verification.enabled' => true]);
        Mail::fake();

        $this->register()->assertRedirect(route('verification.code'));
        Mail::assertNotSent(WelcomeMail::class); // ainda não há conta

        $code = null;
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->post(route('verification.verify'), ['code' => $code]);

        $this->assertSame(1, User::count());
        Mail::assertSent(WelcomeMail::class, 1);
    }

    public function test_a_google_signup_gets_the_google_version(): void
    {
        Mail::fake();

        $this->registerWithGoogle()->assertRedirect(route('dashboard', absolute: false));

        Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->hasTo('jogador@gmail.com')
            && $mail->username === 'jogador_g' && $mail->via === WelcomeMail::VIA_GOOGLE);
        Mail::assertSent(WelcomeMail::class, 1);
    }

    public function test_logging_in_never_sends_a_welcome_email(): void
    {
        $user = User::factory()->create(['email' => 'antigo@gmail.com', 'password' => 'Senha-forte-123']);
        Mail::fake();

        $this->post('/login', ['email' => 'antigo@gmail.com', 'password' => 'Senha-forte-123']);
        $this->assertAuthenticatedAs($user);

        Mail::assertNothingSent();
    }

    public function test_a_provider_failure_never_blocks_the_signup(): void
    {
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'chave-de-teste', 'mail.from.address' => 'remetente@gmail.com']);
        Http::fake(['api.brevo.com/*' => Http::response(['code' => 'too_many_requests'], 429)]);

        $this->register()->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame(1, User::count());
        Http::assertSentCount(1); // tentou uma vez, sem insistir
    }

    public function test_it_can_be_switched_off_to_save_the_daily_quota(): void
    {
        config(['verification.welcome_enabled' => false]);
        Mail::fake();

        $this->register()->assertRedirect(route('dashboard', absolute: false));
        $this->registerWithGoogle();

        Mail::assertNotSent(WelcomeMail::class);
    }

    public function test_both_versions_render_with_the_username_and_a_plain_text_part(): void
    {
        $email = (new WelcomeMail('novato', WelcomeMail::VIA_EMAIL));
        $google = (new WelcomeMail('novato', WelcomeMail::VIA_GOOGLE));

        $email->assertSeeInHtml('Bem-vindo, @novato!', false);
        $email->assertSeeInHtml('Cadastro com e-mail');
        $email->assertSeeInHtml('Esqueci minha senha');
        $email->assertSeeInText('Bem-vindo, @novato!');
        $email->assertDontSeeInText('&quot;');
        $google->assertSeeInHtml('Cadastro com Google');
        $google->assertSeeInHtml('Continuar com Google');
        $google->assertSeeInText('Continuar com Google');
        $this->assertSame('Bem-vindo à Estação Commit, @novato!', $email->envelope()->subject);
    }

    private function mockGoogle(string $id = 'google-123', string $email = 'conta@gmail.com'): void
    {
        $googleUser = (new SocialiteUser)->setRaw(['email_verified' => true])->map(['id' => $id, 'email' => $email, 'name' => 'Conta']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
    }

    public function test_linking_google_in_the_settings_sends_one_security_notice_to_the_account_email(): void
    {
        $user = User::factory()->create(['email' => 'conta@gmail.com', 'username' => 'contaemail']);
        Mail::fake();
        $this->mockGoogle();

        $this->actingAs($user)->withSession(['google_oauth_action' => 'link'])->get(route('google.callback'))
            ->assertRedirect(route('profile.edit'));

        Mail::assertSent(GoogleLinkedMail::class, fn (GoogleLinkedMail $mail) => $mail->hasTo('conta@gmail.com')
            && $mail->how === GoogleLinkedMail::HOW_SETTINGS && $mail->username === 'contaemail');
        Mail::assertSent(GoogleLinkedMail::class, 1);
        Mail::assertNotSent(WelcomeMail::class); // não é cadastro novo
    }

    public function test_linking_again_with_the_same_google_account_sends_nothing(): void
    {
        $user = User::factory()->create(['email' => 'conta@gmail.com', 'google_id' => 'google-123']);
        Mail::fake();
        $this->mockGoogle();

        $this->actingAs($user)->withSession(['google_oauth_action' => 'link'])->get(route('google.callback'));

        Mail::assertNothingSent();
    }

    public function test_signing_in_with_google_on_an_email_account_links_it_and_notifies_once(): void
    {
        User::factory()->create(['email' => 'conta@gmail.com', 'email_verified_at' => now()]);
        Mail::fake();
        $this->mockGoogle();

        $this->get(route('google.callback'))->assertRedirect(route('dashboard', absolute: false));

        Mail::assertSent(GoogleLinkedMail::class, fn (GoogleLinkedMail $mail) => $mail->how === GoogleLinkedMail::HOW_SIGN_IN);
        Mail::assertSent(GoogleLinkedMail::class, 1);
        Mail::assertNotSent(WelcomeMail::class);
    }

    public function test_a_returning_google_login_sends_nothing(): void
    {
        User::factory()->create(['email' => 'conta@gmail.com', 'google_id' => 'google-123', 'email_verified_at' => now()]);
        Mail::fake();
        $this->mockGoogle();

        $this->get(route('google.callback'))->assertRedirect(route('dashboard', absolute: false));

        Mail::assertNothingSent();
    }

    public function test_on_a_brevo_error_the_courtesy_email_is_skipped_without_the_backup_provider(): void
    {
        config([
            'mail.default' => 'resilient',
            'mail.mailers.resilient' => ['transport' => 'failover', 'mailers' => ['brevo', 'resend-http']],
            'services.brevo.key' => 'chave-de-teste',
            'services.resend.key' => 'outra-chave-de-teste',
            'mail.from.address' => 'remetente@gmail.com',
        ]);
        Http::fake([
            'api.brevo.com/*' => Http::response(['message' => 'down'], 503),
            'api.resend.com/*' => Http::response(['id' => 'x'], 200),
        ]);

        $this->register()->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.resend.com'));
    }

    public function test_an_unexpected_failure_while_signing_up_offers_a_temporary_profile(): void
    {
        config(['app.debug' => false]);
        Mail::fake();
        Event::listen('eloquent.creating: '.User::class, fn () => throw new \RuntimeException('banco instável'));

        $this->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post('/register', [
                'username' => 'novato', 'email' => 'novo@gmail.com',
                'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
            ])
            ->assertRedirect(route('register'));

        $this->assertTrue(session('auth_fallback'));
        $this->assertSame('error', session('toast.tone'));
        $this->assertGuest();
        $this->flushHeaders()->get(route('register'))->assertInertia(fn ($page) => $page->where('flash.auth_fallback', true));
        $this->get(route('guest.create'))->assertOk(); // o caminho oferecido funciona
    }

    public function test_an_unexpected_failure_on_an_auth_page_shows_the_temporary_profile_option(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('auth/google/falha-de-teste', fn () => throw new \RuntimeException('falha'));

        $this->get('auth/google/falha-de-teste')
            ->assertStatus(500)
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'auth_unavailable')->where('canDashboard', false));
    }

    public function test_the_google_linked_notice_renders_both_versions(): void
    {
        $settings = new GoogleLinkedMail('contaemail', GoogleLinkedMail::HOW_SETTINGS);
        $signIn = new GoogleLinkedMail('contaemail', GoogleLinkedMail::HOW_SIGN_IN);

        $settings->assertSeeInHtml('@contaemail', false);
        $settings->assertSeeInHtml('nas configurações');
        $settings->assertSeeInText('Não foi você?');
        $settings->assertDontSeeInText('&quot;');
        $signIn->assertSeeInHtml('mesmo e-mail da sua conta');
    }
}
