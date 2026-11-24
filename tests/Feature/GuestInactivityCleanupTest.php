<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\GuestProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Contas temporárias (visitantes) saem após 6 horas sem atividade; contas com e-mail nunca. */
class GuestInactivityCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function guest(string $name, int $inactiveMinutes): GuestProfile
    {
        $ttl = (int) config('players.guest_ttl_hours') * 60;
        $lastSeen = now()->subMinutes($inactiveMinutes);

        return GuestProfile::create([
            'display_name' => $name, 'name_key' => strtolower($name), 'avatar_style' => 'pixel', 'avatar_seed' => 'seed'.$name,
            'token_hash' => hash('sha256', $name), 'last_seen_at' => $lastSeen,
            // mesma regra do GuestIdentity: expira ttl depois da última atividade
            'expires_at' => $lastSeen->copy()->addMinutes($ttl),
        ]);
    }

    public function test_the_inactivity_window_is_six_hours(): void
    {
        $this->assertSame(6, config('players.guest_ttl_hours'));
    }

    public function test_inactive_for_six_hours_is_removed_and_recent_ones_stay(): void
    {
        $stale = $this->guest('Parado', 6 * 60 + 1);
        $almost = $this->guest('Quase', 6 * 60 - 5);
        $recent = $this->guest('Recente', 10);

        $this->artisan('guests:prune')->expectsOutput('Perfis temporários removidos: 1')->assertSuccessful();

        $this->assertNull(GuestProfile::find($stale->id));
        $this->assertNotNull(GuestProfile::find($almost->id));
        $this->assertNotNull(GuestProfile::find($recent->id));
    }

    public function test_permanent_accounts_are_never_reached_by_the_routine(): void
    {
        $old = User::factory()->create(['created_at' => now()->subYear(), 'updated_at' => now()->subYear()]);
        $this->guest('Parado', 7 * 60);

        $this->artisan('guests:prune')->assertSuccessful();

        $this->assertNotNull(User::find($old->id));
        $this->assertSame(1, User::count());
    }

    public function test_active_visitor_activity_extends_the_window(): void
    {
        $guest = $this->guest('Ativo', 5 * 60 + 50); // quase vencendo
        $token = 'Ativo';
        $guest->forceFill(['token_hash' => hash('sha256', str_repeat('a', 64))])->save();

        // Uma requisição de quem está em jogo renova a janela (o cookie guarda o token).
        $this->withCookie(config('players.guest_cookie'), str_repeat('a', 64))->get(route('dashboard'));

        $this->assertTrue($guest->fresh()->expires_at->gt(now()->addHours(5)));
        $this->artisan('guests:prune')->expectsOutput('Perfis temporários removidos: 0')->assertSuccessful();
    }

    public function test_a_visitor_in_a_running_match_is_kept_even_if_inactive_and_removal_leaves_no_orphans(): void
    {
        $playing = $this->guest('Jogando', 9 * 60);
        $room = GameRoom::create(['host_guest_id' => $playing->id, 'status' => 'active', 'started_at' => now()->subHours(9)]);
        $room->participants()->create(['guest_id' => $playing->id, 'kind' => 'guest', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);

        $gone = $this->guest('Saiu', 8 * 60);
        $other = GameRoom::create(['host_guest_id' => $gone->id, 'status' => 'waiting']);
        $other->participants()->create(['guest_id' => $gone->id, 'kind' => 'guest', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);

        $this->artisan('guests:prune')->assertSuccessful();

        $this->assertNotNull(GuestProfile::find($playing->id));
        $this->assertNull(GuestProfile::find($gone->id));
        $this->assertDatabaseMissing('game_rooms', ['id' => $other->id]);      // sala dele sai por cascata
        $this->assertDatabaseMissing('room_participants', ['guest_id' => $gone->id]); // sem órfãos
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_routine_is_idempotent_and_logs_only_a_count(): void
    {
        $this->guest('Parado', 7 * 60);
        Log::spy();

        $this->artisan('guests:prune')->expectsOutput('Perfis temporários removidos: 1')->assertSuccessful();
        $this->artisan('guests:prune')->expectsOutput('Perfis temporários removidos: 0')->assertSuccessful();

        Log::shouldHaveReceived('info')->once()->withArgs(fn ($message, $context) => $context === ['count' => 1, 'inactivity_hours' => 6]
            && ! str_contains(json_encode($context), 'Parado'));
    }
}
