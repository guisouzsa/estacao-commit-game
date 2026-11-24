<?php

namespace Tests\Feature;

use App\Mail\EmailCodeMail;
use App\Models\User;
use App\Services\EmailVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailCodeVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'novo@gmail.com', string $username = 'novato')
    {
        return $this->post('/register', [
            'username' => $username, 'email' => $email,
            'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
        ]);
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(EmailCodeMail::class, function (EmailCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    public function test_disabled_by_default_nothing_changes(): void
    {
        Mail::fake();
        $this->assertFalse(EmailVerificationCode::enabled());

        $this->register()->assertRedirect(route('dashboard', absolute: false));
        Mail::assertNothingSent();
        $this->assertSame(1, User::count());
    }

    public function test_registration_creates_no_account_until_the_code_is_confirmed(): void
    {
        config(['verification.enabled' => true]);
        Mail::fake();

        $this->register()->assertRedirect(route('verification.code'));
        $this->assertSame(0, User::count(), 'sem código, sem conta');
        $this->assertGuest();
        $this->get(route('verification.code'))->assertOk();

        $this->post(route('verification.verify'), ['code' => $this->sentCode()])->assertRedirect(route('dashboard'));

        $user = User::where('username', 'novato')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_login_is_always_just_email_and_password(): void
    {
        config(['verification.enabled' => true]);
        $user = User::factory()->create(['email_verified_at' => null, 'password' => 'Senha-forte-123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Senha-forte-123'])->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertOk(); // nunca cai na tela do código
    }

    public function test_the_password_is_never_kept_in_plain_text_and_still_works_for_login(): void
    {
        config(['verification.enabled' => true]);
        Mail::fake();
        $this->register();
        $this->assertStringNotContainsString('Senha-forte-123', json_encode(session()->all()));

        $this->post(route('verification.verify'), ['code' => $this->sentCode()]);
        $this->post('/logout');
        $this->post('/login', ['email' => 'novo@gmail.com', 'password' => 'Senha-forte-123'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_wrong_codes_are_counted_and_lock_after_the_limit(): void
    {
        config(['verification.enabled' => true, 'verification.max_attempts' => 3]);
        Mail::fake();
        $this->register();
        $real = $this->sentCode();
        $wrong = $real === '000000' ? '111111' : '000000';

        foreach (range(1, 3) as $_) {
            $this->post(route('verification.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->post(route('verification.verify'), ['code' => $real])->assertSessionHasErrors('code');
        $this->assertSame(0, User::count());
    }

    public function test_expired_code_is_refused_and_resend_has_a_cooldown(): void
    {
        config(['verification.enabled' => true]);
        Mail::fake();
        $this->register();
        $code = $this->sentCode();

        $this->post(route('verification.resend'))->assertSessionHasErrors('code'); // cooldown
        $this->travel(11)->minutes();
        $this->post(route('verification.verify'), ['code' => $code])->assertSessionHasErrors('code');

        $this->post(route('verification.resend'))->assertRedirect();
        Mail::assertSent(EmailCodeMail::class, 2);
        $this->assertSame(0, User::count());
    }

    public function test_the_code_page_without_a_pending_registration_goes_back_to_register(): void
    {
        config(['verification.enabled' => true]);

        $this->get(route('verification.code'))->assertRedirect(route('register'));
        $this->post(route('verification.verify'), ['code' => '123456'])->assertRedirect(route('register'));
    }

    public function test_a_name_taken_meanwhile_returns_to_register_with_a_message(): void
    {
        config(['verification.enabled' => true]);
        Mail::fake();
        $this->register();
        $code = $this->sentCode();
        User::factory()->create(['username' => 'novato']); // alguém pegou o nome enquanto o código era digitado

        $this->post(route('verification.verify'), ['code' => $code])
            ->assertRedirect(route('register'))->assertSessionHasErrors('username');
        $this->assertSame(1, User::count());
    }

    public function test_a_failing_mail_provider_does_not_break_registration(): void
    {
        config(['verification.enabled' => true, 'mail.default' => 'brevo', 'services.brevo.key' => 'chave-de-teste']);
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'down'], 500)]);

        $this->register()->assertRedirect(route('verification.code'));
        $this->get(route('verification.code'))->assertOk(); // pode pedir reenvio
    }

    public function test_brevo_transport_posts_to_the_api_with_key_header_only(): void
    {
        config(['mail.default' => 'brevo', 'services.brevo.key' => 'chave-de-teste', 'mail.from.address' => 'remetente@gmail.com']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'x'], 201)]);

        Mail::to('alguem@gmail.com')->send(new EmailCodeMail('123456', 10));

        Http::assertSent(fn ($req) => $req->url() === 'https://api.brevo.com/v3/smtp/email'
            && $req->hasHeader('api-key', 'chave-de-teste')
            && $req['to'][0]['email'] === 'alguem@gmail.com'
            && str_contains($req['htmlContent'], '123456'));
    }

    public function test_the_password_reset_email_is_branded_and_the_link_works(): void
    {
        config(['app.url' => 'https://estacaocommit.onrender.com']);
        $user = User::factory()->create(['email' => 'pessoa@gmail.com']);

        $token = \Illuminate\Support\Facades\Password::createToken($user);
        $html = (string) (new \Illuminate\Auth\Notifications\ResetPassword($token))->toMail($user)->render();

        $this->assertStringContainsString('foguete-gato.png', $html);
        $this->assertStringContainsString('Criar nova senha', $html);
        $this->assertStringContainsString('/reset-password/'.$token, $html);
        $this->assertStringContainsString('https://estacaocommit.onrender.com', $html);
        $this->get('/reset-password/'.$token.'?email=pessoa@gmail.com')->assertOk();
    }

    public function test_the_email_is_branded_escaped_and_has_a_text_version(): void
    {
        config(['app.url' => 'https://estacaocommit.onrender.com']);
        $html = (new EmailCodeMail('482913', 10))->render();

        $this->assertStringContainsString('https://estacaocommit.onrender.com/images/estacao-commit/mascots/foguete-gato.png', $html);
        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('10 minutos', $html);
        $this->assertStringContainsString('align="center"', $html);
        $this->assertFileExists(public_path('images/estacao-commit/mascots/foguete-gato.png'));
        $this->assertStringNotContainsString('<script', (new EmailCodeMail('<script>1</script>', 10))->render());

        $text = view('emails.code-text', ['code' => '482913', 'minutes' => 10, 'url' => 'https://x.test'])->render();
        $this->assertStringContainsString('482913', $text);
    }
}
