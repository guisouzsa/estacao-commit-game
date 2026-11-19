<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_first_google_login_requires_a_verified_email_and_username_before_creating_an_account(): void
    {
        $this->mockGoogleUser();

        $this->get(route('google.callback'))
            ->assertRedirect(route('google.username'))
            ->assertSessionHas('pending_google_user', [
                'google_id' => 'google-123',
                'email' => 'new-player@example.com',
                'display_name' => 'New Player',
            ]);

        $this->assertDatabaseCount('users', 0);

        $this->get(route('google.username'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/GoogleUsername')
                ->where('email', 'new-player@example.com')
                ->where('displayName', 'New Player'));
    }

    public function test_google_redirect_requests_only_basic_identity_scopes(): void
    {
        $response = $this->get(route('google.redirect'));

        $response->assertRedirect();

        $location = parse_url($response->headers->get('Location'));
        parse_str($location['query'] ?? '', $query);

        $this->assertSame('accounts.google.com', $location['host'] ?? null);
        $this->assertEqualsCanonicalizing(
            ['openid', 'email', 'profile'],
            explode(' ', $query['scope'] ?? '')
        );
        $this->assertNotEmpty($query['state'] ?? null);
    }

    public function test_google_redirect_explains_when_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $this->get(route('google.redirect'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'A autenticação com Google ainda não está configurada.');
    }

    public function test_google_registration_creates_a_verified_uuid_user_with_the_chosen_username(): void
    {
        $this->withSession([
            'pending_google_user' => [
                'google_id' => 'google-123',
                'email' => 'new-player@example.com',
                'display_name' => 'New Player',
            ],
        ])->post(route('google.username.store'), [
            'username' => 'My_Player',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'username' => 'my_player',
            'email' => 'new-player@example.com',
            'google_id' => 'google-123',
        ]);

        $user = User::where('google_id', 'google-123')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $user->id);
    }

    public function test_google_cannot_automatically_take_over_an_existing_password_account_by_email(): void
    {
        User::factory()->create(['email' => 'NEW-PLAYER@EXAMPLE.COM']);
        $this->mockGoogleUser();

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Este e-mail já pertence a uma conta. Entre com e-mail e senha e vincule o Google pelo seu perfil.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_username_must_be_available_case_insensitively(): void
    {
        User::factory()->create(['username' => 'gui']);

        $this->withSession([
            'pending_google_user' => [
                'google_id' => 'google-123',
                'email' => 'new-player@example.com',
                'display_name' => 'New Player',
            ],
        ])->from(route('google.username'))
            ->post(route('google.username.store'), ['username' => 'GUI'])
            ->assertSessionHasErrors([
                'username' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
            ]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_google_user_can_sign_in_by_google_id(): void
    {
        $user = User::factory()->create(['google_id' => 'google-123']);
        $this->mockGoogleUser();

        $this->get(route('google.callback'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_user_can_link_google_only_when_it_is_not_linked_to_another_account(): void
    {
        $user = User::factory()->create(['email' => 'new-player@example.com']);
        $this->mockGoogleUser();

        $this->actingAs($user)
            ->withSession(['google_oauth_action' => 'link'])
            ->get(route('google.callback'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Conta Google vinculada com sucesso.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-123',
        ]);
    }

    public function test_authenticated_user_starts_google_link_through_an_inertia_external_redirect(): void
    {
        $user = User::factory()->create();
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('scopes')
            ->with(['openid', 'email', 'profile'])
            ->once()
            ->andReturnSelf();
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth?state=secure'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($provider);

        $this->actingAs($user)
            ->post(route('google.link'), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://accounts.google.com/o/oauth2/auth?state=secure')
            ->assertSessionHas('google_oauth_action', 'link');
    }

    public function test_google_account_linked_to_another_user_cannot_be_reassigned(): void
    {
        User::factory()->create(['google_id' => 'google-123']);
        $user = User::factory()->create(['email' => 'another-player@example.com']);
        $this->mockGoogleUser();

        $this->actingAs($user)
            ->withSession(['google_oauth_action' => 'link'])
            ->get(route('google.callback'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'Esta conta Google já está vinculada a outro usuário.');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'google_id' => 'google-123',
        ]);
    }

    public function test_google_cannot_be_linked_to_a_different_account_that_already_owns_its_email(): void
    {
        User::factory()->create(['email' => 'new-player@example.com']);
        $user = User::factory()->create(['email' => 'another-player@example.com']);
        $this->mockGoogleUser();

        $this->actingAs($user)
            ->withSession(['google_oauth_action' => 'link'])
            ->get(route('google.callback'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'O e-mail desta conta Google pertence a outro usuário. Entre nessa conta antes de vincular o Google.');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'google_id' => 'google-123',
        ]);
    }

    public function test_google_provider_rejects_unverified_email(): void
    {
        $this->mockGoogleUser(verified: false);

        $this->get(route('google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'O Google não forneceu um e-mail verificado válido para esta conta.');

        $this->assertDatabaseCount('users', 0);
    }

    private function mockGoogleUser(
        string $id = 'google-123',
        string $email = 'new-player@example.com',
        string $name = 'New Player',
        bool $verified = true,
    ): void {
        $googleUser = (new SocialiteUser)
            ->setRaw(['email_verified' => $verified])
            ->map([
                'id' => $id,
                'email' => $email,
                'name' => $name,
            ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($provider);
    }
}
