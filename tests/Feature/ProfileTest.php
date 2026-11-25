<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_exposes_google_link_status_and_configuration(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
        $user = User::factory()->create(['google_id' => 'google-123']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('googleConfigured', true)
                ->where('googleLinked', true));
    }

    public function test_profile_disables_google_link_when_oauth_is_not_configured(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $this->actingAs(User::factory()->create())->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('googleConfigured', false)
                ->where('googleLinked', false));
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'username' => 'testuser',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('testuser', $user->display_name);
        $this->assertSame('testuser', $user->username);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'username' => 'testuser',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_profile_appearance_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/appearance', [
                'avatar_position_x' => 28,
                'avatar_position_y' => 64,
                'profile_frame' => 'violet',
                'primary_badge' => 'branch',
                'profile_title' => 'collaborator',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'A aparência do perfil foi atualizada.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_position_x' => 28,
            'avatar_position_y' => 64,
            'profile_frame' => 'violet',
            'primary_badge' => 'branch',
            'profile_title' => 'collaborator',
        ]);
    }

    public function test_avatar_upload_is_stored_in_the_database(): void
    {
        $user = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/U3QAAAAASUVORK5CYII=');

        $this->actingAs($user)
            ->patch('/profile/appearance', [
                'avatar' => UploadedFile::fake()->createWithContent('avatar.png', $png),
                'avatar_position_x' => 50,
                'avatar_position_y' => 50,
                'profile_frame' => 'standard',
                'primary_badge' => 'terminal',
                'profile_title' => 'explorer',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertStringStartsWith('data:image/', $user->avatar);
        $this->assertStringContainsString(';base64,', $user->avatar);
    }

    public function test_cover_upload_and_position_are_persisted(): void
    {
        $user = User::factory()->create();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/U3QAAAAASUVORK5CYII=');

        $this->actingAs($user)->patch('/profile/appearance', [
            'cover' => UploadedFile::fake()->createWithContent('cover.png', $png),
            'avatar_position_x' => 50, 'avatar_position_y' => 50, 'avatar_zoom' => 125,
            'cover_position_x' => 35, 'cover_position_y' => 70,
            'profile_frame' => 'standard', 'primary_badge' => 'terminal', 'profile_title' => 'explorer',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertStringStartsWith('data:image/', $user->profile_cover);
        $this->assertSame(35, $user->cover_position_x);
        $this->assertSame(70, $user->cover_position_y);
        $this->assertSame(125, $user->avatar_zoom);
    }

    public function test_profile_appearance_rejects_unavailable_cosmetics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile/appearance', [
                'avatar_position_x' => 50,
                'avatar_position_y' => 50,
                'profile_frame' => 'founder',
                'primary_badge' => 'terminal',
                'profile_title' => 'explorer',
            ])
            ->assertSessionHasErrors('profile_frame');
    }

    public function test_profile_username_updates_are_unique_case_insensitively(): void
    {
        User::factory()->create(['username' => 'gui']);
        $user = User::factory()->create(['username' => 'anotherplayer']);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile/appearance', [
                'username' => 'GUI',
                'avatar_position_x' => 50,
                'avatar_position_y' => 50,
                'profile_frame' => 'standard',
                'primary_badge' => 'terminal',
                'profile_title' => 'explorer',
            ])
            ->assertSessionHasErrors([
                'username' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
            ]);
    }

    public function test_profile_appearance_cannot_be_used_to_assign_a_developer_badge(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile/appearance', [
                'avatar_position_x' => 50,
                'avatar_position_y' => 50,
                'profile_frame' => 'standard',
                'primary_badge' => 'terminal',
                'profile_title' => 'explorer',
                'developer_badge' => 'founder',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->developer_badge);
    }

    public function test_public_profile_does_not_expose_private_account_fields(): void
    {
        $user = User::factory()->create([
            'username' => 'privateuser',
            'email' => 'private-player@example.com',
            'password' => 'private-password',
        ]);

        $this->get(route('profile.show', $user->username))
            ->assertOk()
            ->assertDontSee($user->email)
            ->assertDontSee('private-password')
            ->assertDontSee($user->id);
    }

    public function test_public_profile_is_available_from_short_username_url(): void
    {
        $user = User::factory()->create(['username' => 'shorturl']);
        $this->get('/u/shorturl')->assertOk()->assertInertia(fn ($page) => $page->component('Profile/Public')->where('profile.username', 'shorturl'));
    }

    public function test_profile_links_are_validated_and_persisted_in_order(): void
    {
        $user = User::factory()->create(['username' => 'linkplayer']);
        $payload = [
            'username' => $user->username, 'bio' => '', 'avatar_position_x' => 50, 'avatar_position_y' => 50,
            'avatar_zoom' => 100, 'cover_position_x' => 50, 'cover_position_y' => 50,
            'profile_frame' => 'standard', 'primary_badge' => 'terminal', 'profile_title' => null,
            'links' => [
                ['platform' => 'github', 'label' => 'Meu GitHub', 'url' => 'https://github.com/player', 'is_public' => true],
                ['platform' => 'portfolio', 'label' => 'Portfólio', 'url' => 'https://example.com', 'is_public' => false],
            ],
        ];
        $this->actingAs($user)->patch('/profile/appearance', $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('profile_links', ['user_id' => $user->id, 'label' => 'Meu GitHub', 'sort_order' => 0]);
        $this->assertDatabaseHas('profile_links', ['user_id' => $user->id, 'label' => 'Portfólio', 'sort_order' => 1, 'is_public' => false]);

        $payload['links'][0]['url'] = 'javascript:alert(1)';
        $this->actingAs($user)->patch('/profile/appearance', $payload)->assertSessionHasErrors('links.0.url');
    }

    public function test_only_an_unlocked_badge_can_be_selected_as_primary_achievement(): void
    {
        $user = User::factory()->create();
        $badge = Badge::query()->firstOrFail();
        $payload = ['avatar_position_x' => 50, 'avatar_position_y' => 50, 'profile_frame' => 'standard', 'primary_badge' => 'terminal', 'profile_title' => null, 'achievement_badge_id' => $badge->id];
        $this->actingAs($user)->patch('/profile/appearance', $payload)->assertSessionHasErrors('achievement_badge_id');
        $user->badges()->attach($badge->id, ['unlocked_at' => now(), 'granted_by' => 'test']);
        $this->actingAs($user)->patch('/profile/appearance', $payload)->assertSessionHasNoErrors();
        $this->assertSame($badge->id, $user->fresh()->achievement_badge_id);
    }

    public function test_only_configured_verified_administrators_can_assign_developer_badges(): void
    {
        config(['profiles.admin_emails' => ['admin@example.com']]);
        $player = User::factory()->create();
        $ordinaryUser = User::factory()->create();

        $this->actingAs($ordinaryUser)
            ->patch(route('admin.users.developer-badge.update', $player), [
                'developer_badge' => 'founder',
            ])
            ->assertForbidden();

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.developer-badge.update', $player), [
                'developer_badge' => 'founder',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('founder', $player->fresh()->developer_badge);
    }
}
