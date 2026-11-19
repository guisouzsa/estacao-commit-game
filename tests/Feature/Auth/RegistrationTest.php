<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'username' => 'testplayer',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'username' => 'testplayer',
            'display_name' => 'testplayer',
        ]);
    }

    public function test_registration_rejects_duplicate_usernames_case_insensitively(): void
    {
        User::factory()->create(['username' => 'gui']);

        $this->from('/register')->post('/register', [
            'username' => 'Gui',
            'email' => 'other@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors([
            'username' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
        ]);
    }

    public function test_registration_rejects_duplicate_emails_case_insensitively(): void
    {
        User::factory()->create(['email' => 'Player@example.com']);

        $this->from('/register')->post('/register', [
            'username' => 'anotherplayer',
            'email' => 'player@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_user_is_created_with_uuid_v7_and_normalized_username(): void
    {
        $this->post('/register', [
            'username' => 'Player_One',
            'email' => 'player@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'player@example.com')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $user->id);
        $this->assertSame('player_one', $user->username);
        $this->assertSame('player_one', $user->display_name);
    }

    public function test_registration_creates_unverified_account_and_sends_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', [
            'username' => 'unverified_player',
            'email' => 'unverified@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'unverified@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->password_set);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_rejects_invalid_email_format(): void
    {
        $this->from('/register')->post('/register', [
            'username' => 'valid_user',
            'email' => 'not-an-email',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_registration_rejects_short_password(): void
    {
        $this->from('/register')->post('/register', [
            'username' => 'valid_user',
            'email' => 'valid@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertSessionHasErrors('password');
    }
}
