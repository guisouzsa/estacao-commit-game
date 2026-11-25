<?php

namespace Tests\Feature\Security;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\GuestProfile;
use App\Models\User;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ciclo de vida das contas temporárias: teto de validade, isolamento, sem fusão com contas e sem registros órfãos. */
class GuestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function guest(string $name = 'visita', ?string $token = null): array
    {
        $token ??= bin2hex(random_bytes(32));
        $profile = GuestProfile::create([
            'display_name' => $name, 'name_key' => strtolower($name), 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz',
            'token_hash' => hash('sha256', $token), 'last_seen_at' => now(), 'expires_at' => now()->addHours(6),
        ]);

        return [$profile, $token];
    }

    private function asGuest(string $token)
    {
        $this->app['auth']->shouldUse('web');
        $this->app['auth']->forgetGuards();

        return $this->withCookie(config('players.guest_cookie'), $token);
    }

    public function test_activity_cannot_keep_a_temporary_profile_alive_beyond_the_absolute_ceiling(): void
    {
        config(['players.guest_max_days' => 3]);
        [$profile, $token] = $this->guest();
        $profile->forceFill(['created_at' => now()->subDays(3)->subHour(), 'last_seen_at' => now()->subMinutes(10), 'expires_at' => now()->addHours(2)])->save();

        $this->asGuest($token)->get(route('dashboard'))->assertOk(); // ainda dentro da janela: toca a atividade

        $this->assertTrue($profile->fresh()->expires_at->lte($profile->created_at->copy()->addDays(3)), 'o teto absoluto limita a renovação');
        $this->travel(4)->hours();
        $this->asGuest($token)->get(route('dashboard'))->assertRedirect(route('guest.create')); // venceu mesmo com o cookie válido
    }

    public function test_sliding_renewal_still_works_inside_the_ceiling(): void
    {
        [$profile, $token] = $this->guest();
        $profile->forceFill(['last_seen_at' => now()->subMinutes(10), 'expires_at' => now()->addHour()])->save();

        $this->asGuest($token)->get(route('dashboard'))->assertOk();

        $this->assertTrue($profile->fresh()->expires_at->gt(now()->addHours(5)));
    }

    public function test_a_guest_token_cannot_be_forged_guessed_or_reused_after_leaving(): void
    {
        [, $token] = $this->guest('alguem');

        foreach ([str_repeat('a', 64), 'x', $token.'0', strtoupper($token), ''] as $bad) {
            $this->asGuest($bad)->get(route('dashboard'))->assertRedirect(route('guest.create'));
        }
        $this->asGuest($token)->post(route('guest.leave'));
        $this->asGuest($token)->get(route('dashboard'))->assertRedirect(route('guest.create'));
        $this->assertSame(0, GuestProfile::count());
    }

    public function test_registering_with_a_guest_cookie_creates_a_separate_account_and_never_merges_or_steals(): void
    {
        [$profile, $token] = $this->guest('visitante');

        $this->asGuest($token)->post('/register', [
            'username' => 'contanova', 'email' => 'nova@gmail.com',
            'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
        ]);

        $user = User::where('username', 'contanova')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(GuestProfile::find($profile->id), 'o visitante continua existindo: não há conversão automática');
        $this->assertSame(0, (int) $user->matches_played, 'nada do histórico do visitante vai para a conta');
    }

    public function test_a_guest_cannot_read_or_change_another_guests_or_accounts_data(): void
    {
        [, $tokenA] = $this->guest('alfa');
        [$other] = $this->guest('beta');
        $user = User::factory()->create();

        $this->asGuest($tokenA)->get(route('profile.show', $user->username))->assertOk(); // perfil público é público
        $this->asGuest($tokenA)->patch(route('profile.update'), ['username' => 'invasor', 'email' => 'x@x.com'])->assertRedirect(route('login'));
        $this->asGuest($tokenA)->post(route('follow.store', $user->username))->assertRedirect(route('login'));
        $this->asGuest($tokenA)->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->assertSame('beta', $other->fresh()->display_name);
    }

    public function test_deleting_a_guest_leaves_no_orphans_and_keeps_finished_results(): void
    {
        [$guest, $token] = $this->guest('passageiro');
        $users = User::factory()->count(3)->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        $room->participants()->create(['user_id' => $users[0]->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        $room->participants()->create(['user_id' => $users[1]->id, 'kind' => 'user', 'is_host' => false, 'seat' => 2, 'joined_at' => now()]);
        $room->participants()->create(['user_id' => $users[2]->id, 'kind' => 'user', 'is_host' => false, 'seat' => 3, 'joined_at' => now()]);
        $room->participants()->create(['guest_id' => $guest->id, 'kind' => 'guest', 'is_host' => false, 'seat' => 4, 'joined_at' => now()]);
        $match = app(GameEngine::class)->create($room);
        $room->update(['status' => 'finished', 'started_at' => now(), 'finished_at' => now()]);
        $match->forceFill(['status' => 'finished', 'finished_at' => now()])->save();

        $this->asGuest($token)->post(route('guest.leave'));

        $this->assertNull(GuestProfile::find($guest->id));
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('room_participants')->where('guest_id', $guest->id)->count(), 'sem participações órfãs');
        $this->assertNotNull(GameMatch::find($match->id), 'a partida encerrada continua existindo');
        $this->assertSame(3, $room->participants()->count());
    }
}
