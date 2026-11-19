<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_google_only_user_receives_google_login_notice_instead_of_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'google_id' => 'google-123',
            'password_set' => false,
        ]);

        $response = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status', 'Esta conta utiliza o login com Google. Acesse utilizando a opção "Continuar com Google".');
        Notification::assertNothingSent();
    }

    public function test_non_existent_email_does_not_reveal_account_absence(): void
    {
        Notification::fake();

        $response = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nonexistent@example.com']);

        $response->assertRedirect('/forgot-password');
        $response->assertSessionHas('status', 'Se este e-mail estiver cadastrado, enviaremos um link para redefinir sua senha.');
        Notification::assertNothingSent();
    }
}
