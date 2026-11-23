<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\MailDomainChecker;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * O acesso não depende mais de confirmar o e-mail por link. No lugar disso o
 * cadastro confere por DNS se o domínio do e-mail existe e recebe mensagens.
 */
class EmailWithoutVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_verified_email_can_open_the_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_verification_routes_no_longer_exist(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertNotFound();
        $this->actingAs($user)->post('/email/verification-notification')->assertNotFound();
        $this->assertFalse(Route::has('verification.notice'));
    }

    public function test_changing_the_email_in_the_profile_does_not_send_a_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'username' => 'novo_nome',
            'email' => 'novo@example.com',
        ])->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_registration_accepts_a_domain_that_has_mail_servers(): void
    {
        $this->useDns(fn (string $domain, int $type) => $type === DNS_MX
            ? [['target' => 'mx.provedor.test']]
            : []);

        $this->register('jogador@provedor.test')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'jogador@provedor.test']);
    }

    public function test_registration_accepts_a_domain_with_only_an_address_record(): void
    {
        $this->useDns(fn (string $domain, int $type) => $type === DNS_MX ? [] : [['ip' => '192.0.2.10']]);

        $this->register('jogador@provedor.test')->assertSessionHasNoErrors();
    }

    public function test_registration_rejects_a_domain_that_does_not_exist(): void
    {
        $this->useDns(fn () => []);

        $this->register('jogador@dominio-que-nao-existe.test')->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'jogador@dominio-que-nao-existe.test']);
        $this->assertGuest();
    }

    public function test_registration_rejects_a_domain_that_declares_it_receives_no_mail(): void
    {
        // MX nulo (RFC 7505): "MX 0 ."
        $this->useDns(fn (string $domain, int $type) => $type === DNS_MX
            ? [['target' => '.']]
            : [['ip' => '192.0.2.10']]);

        $this->register('jogador@sem-email.test')->assertSessionHasErrors('email');
    }

    public function test_registration_rejects_the_address_when_the_dns_lookup_fails(): void
    {
        $this->useDns(fn () => false);

        $this->register('jogador@provedor.test')->assertSessionHasErrors('email');
    }

    public function test_the_domain_check_does_not_run_for_an_invalid_address_format(): void
    {
        $calls = 0;
        $this->useDns(function () use (&$calls) {
            $calls++;

            return [];
        });

        $this->register('isto-nao-e-um-email')->assertSessionHasErrors('email');

        $this->assertSame(0, $calls);
    }

    public function test_the_domain_check_can_be_turned_off_by_configuration(): void
    {
        $this->useDns(fn () => []);
        config(['auth.email_dns_check' => false]);

        $this->register('jogador@qualquer-coisa.test')->assertSessionHasNoErrors();
    }

    public function test_changing_to_an_unreachable_domain_in_the_profile_is_rejected(): void
    {
        $this->useDns(fn () => []);
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'username' => 'testuser',
            'email' => 'alguem@dominio-que-nao-existe.test',
        ])->assertSessionHasErrors('email');

        $this->assertNotSame('alguem@dominio-que-nao-existe.test', $user->fresh()->email);
    }

    public function test_saving_the_profile_with_an_unchanged_email_does_not_query_dns(): void
    {
        $calls = 0;
        $this->useDns(function () use (&$calls) {
            $calls++;

            return false;
        });
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'username' => 'testuser',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $calls);
    }

    private function register(string $email)
    {
        return $this->post('/register', [
            'username' => 'jogador_novo',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    /** Liga a checagem de DNS e substitui a consulta real por uma resposta fixa. */
    private function useDns(Closure $resolver): void
    {
        config(['auth.email_dns_check' => true]);
        $this->app->instance(MailDomainChecker::class, new MailDomainChecker($resolver));
    }
}
