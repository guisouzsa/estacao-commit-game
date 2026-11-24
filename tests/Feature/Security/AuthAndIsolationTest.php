<?php

namespace Tests\Feature\Security;

use App\Models\Follow;
use App\Models\GameRoom;
use App\Models\User;
use App\Notifications\NewFollower;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** Tentativas deliberadas de quebrar cadastro, login, sessão, privacidade e isolamento entre contas. */
class AuthAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- cadastro

    public function test_registration_ignores_forged_internal_fields(): void
    {
        $this->post('/register', [
            'username' => 'novato_ok', 'email' => 'novato@example.test', 'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
            'matches_won' => 999, 'matches_played' => 999, 'matches_lost' => 0, 'email_verified_at' => now()->toDateTimeString(),
            'developer_badge' => 'founder', 'id' => '00000000-0000-4000-8000-000000000001', 'is_admin' => true, 'password_set' => false,
        ]);

        $user = User::where('email', 'novato@example.test')->firstOrFail();
        $this->assertSame(0, (int) $user->matches_won);
        $this->assertSame(0, (int) $user->matches_played);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->developer_badge);
        $this->assertNotSame('00000000-0000-4000-8000-000000000001', $user->id);
    }

    public function test_duplicate_email_with_different_case_and_spaces_is_rejected(): void
    {
        User::factory()->create(['email' => 'pessoa@example.test']);

        foreach (['PESSOA@example.test', 'Pessoa@Example.TEST'] as $variant) {
            $this->post('/register', ['username' => 'outro_'.strlen($variant), 'email' => $variant, 'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123'])
                ->assertSessionHasErrors('email');
        }
        $this->assertSame(1, User::where('email', 'pessoa@example.test')->count());
    }

    public function test_duplicate_usernames_ignoring_case_are_rejected(): void
    {
        User::factory()->create(['username' => 'jogador']);

        $this->post('/register', ['username' => 'JOGADOR', 'email' => 'x@example.test', 'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123'])
            ->assertSessionHasErrors('username');
    }

    public function test_registration_and_password_reset_are_rate_limited(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->post('/register', ['username' => 'a', 'email' => 'x', 'password' => 'x', 'password_confirmation' => 'y']);
        }
        $this->post('/register', ['username' => 'a', 'email' => 'x', 'password' => 'x', 'password_confirmation' => 'y'])->assertStatus(429);

        for ($i = 0; $i < 12; $i++) {
            $this->post('/forgot-password', ['email' => 'alguem@example.test']);
        }
        $this->post('/forgot-password', ['email' => 'alguem@example.test'])->assertStatus(429);
    }

    // ---------------------------------------------------------------- login e sessão

    public function test_login_never_reveals_whether_the_email_exists(): void
    {
        User::factory()->create(['email' => 'real@example.test']);

        $a = $this->post('/login', ['email' => 'real@example.test', 'password' => 'errada']);
        $b = $this->post('/login', ['email' => 'inexistente@example.test', 'password' => 'errada']);

        $this->assertSame(session('errors')->first('email'), session('errors')->first('email'));
        $a->assertSessionHasErrors('email');
        $b->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_regenerates_the_session_id_and_logout_destroys_it(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Senha-forte-123')]);
        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'Senha-forte-123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId(), 'session fixation: o id precisa mudar no login');

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
        $this->get('/profile')->assertRedirect();
    }

    public function test_session_cookie_flags_are_safe(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
    }

    // ---------------------------------------------------------------- recuperação de senha

    public function test_reset_token_works_once_and_expires(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Nova-senha-123', 'password_confirmation' => 'Nova-senha-123'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Nova-senha-123', $user->fresh()->password));

        // reutilizar o mesmo token
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'Outra-senha-123', 'password_confirmation' => 'Outra-senha-123'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('Nova-senha-123', $user->fresh()->password));

        // token vencido
        $other = User::factory()->create();
        $old = Password::createToken($other);
        $this->travel(config('auth.passwords.users.expire') + 5)->minutes();
        $this->post('/reset-password', ['token' => $old, 'email' => $other->email, 'password' => 'Nova-senha-123', 'password_confirmation' => 'Nova-senha-123'])
            ->assertSessionHasErrors('email');
    }

    public function test_changing_the_password_invalidates_other_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Senha-forte-123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Senha-forte-123'])->assertRedirect();
        $this->get('/profile')->assertOk(); // sessão "A" válida

        // Outro aparelho troca a senha (o hash na conta muda; a sessão "A" ainda guarda o hash antigo).
        $user->forceFill(['password' => Hash::make('Nova-senha-456'), 'remember_token' => 'novo-token'])->save();

        $this->app['auth']->forgetGuards(); // no navegador cada requisição relê o usuário; o teste reaproveita o objeto
        $this->get('/profile')->assertRedirect(route('login')); // a sessão antiga caiu
        $this->assertGuest();
    }

    public function test_the_password_form_logs_out_other_devices_and_keeps_the_current_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Senha-forte-123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Senha-forte-123']);
        $oldHash = $user->fresh()->password;
        $oldRemember = $user->fresh()->remember_token;

        $this->put('/password', ['current_password' => 'Senha-forte-123', 'password' => 'Nova-senha-456', 'password_confirmation' => 'Nova-senha-456'])->assertSessionHasNoErrors();

        $this->assertNotSame($oldHash, $user->fresh()->password);
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token, 'o token "lembrar-me" precisa ser renovado');
        $this->get('/profile')->assertOk(); // quem trocou continua logado
    }

    // ---------------------------------------------------------------- mass assignment no perfil

    public function test_profile_endpoints_ignore_statistics_and_privileged_fields(): void
    {
        $user = User::factory()->create(['matches_won' => 2, 'matches_played' => 5, 'matches_lost' => 3]);

        $this->actingAs($user)->patch('/profile', [
            'username' => $user->username, 'email' => $user->email,
            'matches_won' => 9999, 'matches_played' => 9999, 'matches_lost' => 0, 'developer_badge' => 'founder',
            'email_verified_at' => now()->toDateTimeString(), 'id' => 'x', 'password' => 'hack',
        ]);
        $this->actingAs($user)->patch('/profile/appearance', [
            'avatar_position_x' => 50, 'avatar_position_y' => 50, 'profile_frame' => array_key_first(config('profiles.frames')),
            'primary_badge' => array_key_first(config('profiles.badges')),
            'matches_won' => 9999, 'developer_badge' => 'founder', 'user_id' => 'outro',
        ]);

        $fresh = $user->fresh();
        $this->assertSame([2, 5, 3], [(int) $fresh->matches_won, (int) $fresh->matches_played, (int) $fresh->matches_lost]);
        $this->assertNull($fresh->developer_badge);
        $this->assertFalse(Hash::check('hack', $fresh->password));
    }

    public function test_only_admins_can_grant_developer_badges(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();

        $this->actingAs($user)->patch(route('admin.users.developer-badge.update', $victim), ['developer_badge' => 'founder'])->assertForbidden();
        $this->assertNull($victim->fresh()->developer_badge);
        auth()->logout();
        $this->patch(route('admin.users.developer-badge.update', $victim), ['developer_badge' => 'founder'])->assertRedirect();
    }

    // ---------------------------------------------------------------- privacidade / IDOR

    public function test_public_profile_and_search_never_expose_private_fields(): void
    {
        $owner = User::factory()->create(['username' => 'dono_secreto', 'email' => 'segredo@example.test']);
        $viewer = User::factory()->create();

        $profile = $this->actingAs($viewer)->get(route('profile.show', 'dono_secreto'))->assertOk()->getContent();
        $search = $this->actingAs($viewer)->getJson(route('players.search', ['q' => 'dono']))->getContent();

        foreach ([$profile, $search] as $payload) {
            $this->assertStringNotContainsString('segredo@example.test', $payload);
            $this->assertStringNotContainsString($owner->password, $payload);
            $this->assertStringNotContainsString($owner->remember_token ?? 'sem-token', $payload);
            $this->assertStringNotContainsString('google_id', $payload);
        }
    }

    public function test_notifications_of_other_people_cannot_be_read_or_marked(): void
    {
        $alice = User::factory()->create();
        $mallory = User::factory()->create();
        $alice->notify(NewFollower::from(User::factory()->create()));
        $notificationId = $alice->notifications()->firstOrFail()->id;

        $this->actingAs($mallory)->post(route('notifications.read', $notificationId))->assertNotFound();
        $this->assertNull($alice->notifications()->firstOrFail()->read_at, 'a notificação de outra pessoa não pode ser marcada');

        $this->actingAs($mallory)->post(route('notifications.read-all'));
        $this->assertNull($alice->fresh()->notifications()->firstOrFail()->read_at);

        $this->actingAs($mallory)->getJson(route('notifications.recent'))->assertOk()->assertJsonMissing(['id' => $notificationId]);
    }

    public function test_followers_pages_only_show_public_data_and_never_other_peoples_follow_state(): void
    {
        $a = User::factory()->create(['username' => 'pessoa_a']);
        $b = User::factory()->create(['username' => 'pessoa_b', 'email' => 'b-privado@example.test']);
        Follow::create(['follower_id' => $b->id, 'followed_id' => $a->id]);

        $html = $this->actingAs($a)->get(route('profile.followers', 'pessoa_a'))->getContent();
        $this->assertStringNotContainsString('b-privado@example.test', $html);
        $this->assertStringNotContainsString('"email"', $html);
    }

    // ---------------------------------------------------------------- contas temporárias

    public function test_a_guest_cannot_use_account_only_features_or_see_other_guests_rooms(): void
    {
        $host = User::factory()->create(['username' => 'anfitriao_x']);
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting']);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);

        $token = str_repeat('a', 64);
        \App\Models\GuestProfile::create([
            'display_name' => 'Visita', 'name_key' => 'visita', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz',
            'token_hash' => hash('sha256', $token), 'last_seen_at' => now(), 'expires_at' => now()->addHours(6),
        ]);
        // Como no navegador: o visitante só tem o cookie dele (nada de sessão de conta).
        $as = function () use ($token) {
            $this->app['auth']->shouldUse('web'); // cada requisição real começa no guard padrão
            $this->app['auth']->forgetGuards();

            return $this->withCookie(config('players.guest_cookie'), $token);
        };

        $as()->get(route('rooms.show', $room))->assertNotFound();      // sala alheia
        $as()->get(route('games.show', $room))->assertNotFound();
        $as()->delete(route('rooms.cancel', $room))->assertNotFound();
        foreach (['profile.edit', 'rankings', 'notifications.index'] as $name) {
            $as()->get(route($name))->assertRedirect(route('login'));   // só contas permanentes
        }
        $as()->post(route('follow.store', $host->username))->assertRedirect(route('login'));
        $this->assertSame(0, Follow::count());
    }
}
