<?php

namespace Tests\Feature;

use App\Mail\EmailCodeMail;
use App\Models\User;
use App\Services\EmailVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailCodeVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email = 'novo@gmail.com'): void
    {
        $this->post('/register', [
            'username' => 'novato', 'email' => $email,
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
        $this->register();

        Mail::assertNothingSent();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_registration_sends_a_code_and_blocks_the_app_until_confirmed(): void
    {
        config(['verification.enabled' => true]);
        Mail::fake();
        $this->register();

        $user = User::where('username', 'novato')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertNotSame($this->sentCode(), $user->email_code_hash, 'código nunca fica em texto puro');
        $this->app['auth']->forgetGuards(); // como no navegador: cada requisição relê o usuário do banco
        $this->get(route('dashboard'))->assertRedirect(route('verification.code'));
        $this->app['auth']->forgetGuards();
        $this->get(route('verification.code'))->assertOk();
        $this->app['auth']->forgetGuards();

        $this->post(route('verification.verify'), ['code' => $this->sentCode()])->assertRedirect(route('dashboard'));
        $this->app['auth']->forgetGuards();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->email_code_hash);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_wrong_codes_are_counted_and_lock_after_the_limit(): void
    {
        config(['verification.enabled' => true, 'verification.max_attempts' => 3]);
        Mail::fake();
        $this->register();
        $real = $this->sentCode();
        $wrong = $real === '000000' ? '111111' : '000000';

        foreach (range(1, 3) as $_) {
            $this->app['auth']->forgetGuards();
            $this->post(route('verification.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        // mesmo o código certo é recusado depois de esgotar as tentativas
        $this->post(route('verification.verify'), ['code' => $real])->assertSessionHasErrors('code');
        $this->assertNull(User::where('username', 'novato')->first()->email_verified_at);
    }

    public function test_expired_code_is_refused_and_resend_has_a_cooldown(): void
    {
        config(['verification.enabled' => true]);
        Mail::fake();
        $this->register();
        $code = $this->sentCode();

        $this->app['auth']->forgetGuards();
        $this->post(route('verification.resend'))->assertSessionHasErrors('code'); // cooldown
        $this->travel(11)->minutes();
        $this->app['auth']->forgetGuards();
        $this->post(route('verification.verify'), ['code' => $code])->assertSessionHasErrors('code');

        $this->app['auth']->forgetGuards();
        $this->post(route('verification.resend'))->assertSessionDoesntHaveErrors();
        Mail::assertSent(EmailCodeMail::class, 2);
    }

    public function test_old_accounts_google_accounts_and_visitors_are_never_blocked(): void
    {
        config(['verification.enabled' => true]);
        $old = User::factory()->create(['email_verified_at' => null]); // conta antiga: sem código pendente

        $this->actingAs($old)->get(route('dashboard'))->assertOk();
        $this->assertFalse(EmailVerificationCode::isPending($old));
    }

    public function test_a_failing_mail_provider_does_not_break_registration(): void
    {
        config(['verification.enabled' => true, 'mail.default' => 'brevo', 'services.brevo.key' => 'chave-de-teste']);
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'down'], 500)]);
        $this->register();

        $this->assertNotNull(User::where('username', 'novato')->first());
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

    public function test_code_hash_is_hidden_from_serialization(): void
    {
        $user = User::factory()->create(['email_code_hash' => Hash::make('123456')]);

        $this->assertArrayNotHasKey('email_code_hash', $user->toArray());
    }
}
