<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\RoomInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Entrada em sala por convite (link ou código): caminho feliz, todos os motivos de recusa,
 * corrida pela última vaga, falha do tempo real e um teto de consultas por etapa.
 */
class InviteJoinFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: GameRoom, 1: User} sala aguardando, só com o anfitrião, e o anfitrião */
    private function room(string $code = 'ABCDE', array $attributes = []): array
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting', 'empty_since' => now(), ...$attributes]);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        RoomInvitation::create(['room_id' => $room->id, 'token_hash' => hash('sha256', $code), 'expires_at' => now()->addDay()]);

        return [$room, $host];
    }

    private function fill(GameRoom $room, int $upTo): void
    {
        foreach (range($room->participants()->count() + 1, $upTo) as $seat) {
            $room->participants()->create(['user_id' => User::factory()->create()->id, 'kind' => 'user', 'is_host' => false, 'seat' => $seat, 'joined_at' => now()]);
        }
    }

    private function countQueries(callable $do): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $do();

        return $count;
    }

    // ---- caminho feliz ---------------------------------------------------------------

    public function test_a_valid_link_shows_the_invite_page_ready_to_join_on_its_own(): void
    {
        $this->room();

        $this->actingAs(User::factory()->create())->get('/convites/ABCDE')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Invites/Show')
                ->where('code', 'ABCDE')
                ->where('participants', 1)
                ->where('autoJoin', true));
    }

    public function test_a_valid_code_puts_the_player_in_the_room_and_stops_the_empty_countdown(): void
    {
        [$room] = $this->room();
        $player = User::factory()->create();

        $this->actingAs($player)->post('/convites/ABCDE')->assertRedirect(route('rooms.show', $room));

        $this->assertTrue($room->hasParticipant($player));
        $this->assertNull($room->fresh()->empty_since);
        $this->assertSame(2, $room->participants()->count());
    }

    public function test_opening_the_page_never_joins_by_itself_on_the_server(): void
    {
        [$room] = $this->room();

        $this->actingAs(User::factory()->create())->get('/convites/ABCDE')->assertOk();

        $this->assertSame(1, $room->participants()->count()); // GET não altera nada
    }

    public function test_a_signed_out_visitor_is_sent_to_create_a_profile_and_lands_in_the_room(): void
    {
        [$room] = $this->room();

        $this->get('/convites/ABCDE')->assertRedirect(route('guest.create'));
        $this->assertSame('ABCDE', session('pending_room_invite'));

        $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz'])
            ->assertRedirect(route('rooms.show', $room));
        $this->assertSame(2, $room->participants()->count());
        $this->assertNull(session('pending_room_invite'));
    }

    public function test_codes_are_case_insensitive(): void
    {
        [$room] = $this->room();

        $this->actingAs(User::factory()->create())->post('/convites/abcde')->assertRedirect(route('rooms.show', $room));
    }

    // ---- recusas -----------------------------------------------------------------------

    public function test_a_malformed_code_is_rejected_by_the_route(): void
    {
        $this->actingAs(User::factory()->create())->get('/convites/AB!DE')->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/convites/AB1')->assertNotFound();
    }

    public function test_an_unknown_code_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/convites/ZZZZZ')->assertNotFound();
        $this->actingAs($user)->post('/convites/ZZZZZ')->assertNotFound();
    }

    public function test_an_expired_invitation_is_refused(): void
    {
        [$room] = $this->room();
        $room->invitations()->update(['expires_at' => now()->subMinute()]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/convites/ABCDE')->assertStatus(410);
        $this->actingAs($user)->post('/convites/ABCDE')->assertStatus(410);
        $this->assertSame(1, $room->participants()->count());
    }

    public function test_a_revoked_invitation_is_refused(): void
    {
        [$room] = $this->room();
        $room->invitations()->update(['revoked_at' => now()]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/convites/ABCDE')->assertStatus(410);
        $this->actingAs($user)->post('/convites/ABCDE')->assertStatus(410);
    }

    public function test_a_full_room_is_refused(): void
    {
        [$room] = $this->room();
        $this->fill($room, 4);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/convites/ABCDE')->assertStatus(409);
        $this->actingAs($user)->post('/convites/ABCDE')->assertStatus(409);
        $this->assertSame(4, $room->participants()->count());
    }

    public function test_a_room_that_already_started_or_finished_is_refused(): void
    {
        [$playing] = $this->room('ABCDE', ['status' => 'playing', 'started_at' => now(), 'empty_since' => null]);
        [$finished] = $this->room('FGHJK', ['status' => 'finished', 'started_at' => now()->subHour(), 'finished_at' => now(), 'empty_since' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/convites/ABCDE')->assertStatus(409);
        $this->actingAs($user)->post('/convites/ABCDE')->assertStatus(409);
        $this->actingAs($user)->get('/convites/FGHJK')->assertStatus(409);
        $this->actingAs($user)->post('/convites/FGHJK')->assertStatus(409);
        $this->assertSame(1, $playing->participants()->count());
        $this->assertSame(1, $finished->participants()->count());
    }

    public function test_a_failed_join_from_the_invite_page_becomes_a_friendly_message(): void
    {
        [$room] = $this->room();
        $this->fill($room, 4);

        $this->actingAs(User::factory()->create())
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->from('/convites/ABCDE')
            ->post('/convites/ABCDE')
            ->assertRedirect();
        $this->assertSame('error', session('toast.tone'));
        $this->assertSame('A sala está cheia.', session('toast.message'));
    }

    // ---- repetição, corrida e reconexão -----------------------------------------------

    public function test_joining_twice_never_takes_two_seats(): void
    {
        [$room] = $this->room();
        $player = User::factory()->create();

        $this->actingAs($player)->post('/convites/ABCDE')->assertRedirect(route('rooms.show', $room));
        $this->actingAs($player)->post('/convites/ABCDE')->assertRedirect(route('rooms.show', $room));

        $this->assertSame(1, $room->participants()->where('user_id', $player->id)->count());
        $this->assertSame(2, $room->participants()->count());
    }

    public function test_only_one_of_two_players_gets_the_last_seat(): void
    {
        // Simulação sequencial: o SQLite dos testes não tem lockForUpdate. Em produção (Postgres) a trava
        // da sala serializa os dois pedidos e o segundo enxerga a vaga ocupada exatamente como aqui.
        [$room] = $this->room();
        $this->fill($room, 3);
        [$first, $second] = User::factory()->count(2)->create();

        $this->actingAs($first)->post('/convites/ABCDE')->assertRedirect(route('rooms.show', $room));
        $this->actingAs($second)->post('/convites/ABCDE')->assertStatus(409);

        $this->assertSame(4, $room->participants()->count());
        $this->assertSame([1, 2, 3, 4], $room->participants()->orderBy('seat')->pluck('seat')->all());
    }

    public function test_a_player_already_in_the_room_reconnects_even_after_the_match_started(): void
    {
        [$room] = $this->room();
        $player = User::factory()->create();
        $this->actingAs($player)->post('/convites/ABCDE');
        $room->forceFill(['status' => 'playing', 'started_at' => now()])->save();

        $this->actingAs($player)->post('/convites/ABCDE')->assertRedirect(route('rooms.show', $room));
        $this->assertSame(2, $room->participants()->count());
    }

    public function test_a_realtime_failure_does_not_block_the_join(): void
    {
        config([
            'realtime.driver' => 'cloudflare',
            'realtime.worker_url' => 'https://worker.example.test',
            'realtime.notify_secret' => 'segredo-de-teste',
            'realtime.token_secret' => 'outro-segredo-de-teste',
        ]);
        Http::fake(['*' => Http::response('falhou', 500)]);
        [$room] = $this->room();
        $player = User::factory()->create();

        $this->actingAs($player)->post('/convites/ABCDE')->assertRedirect(route('rooms.show', $room));

        $this->assertTrue($room->hasParticipant($player));
        Http::assertSentCount(1); // tentou avisar o Worker uma vez; a falha só vira aviso no log
    }

    // ---- inatividade -------------------------------------------------------------------

    public function test_a_room_left_empty_past_the_deadline_is_closed_when_someone_tries_to_enter(): void
    {
        [$room] = $this->room('ABCDE', ['empty_since' => now()->subMinutes(6)]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/convites/ABCDE')->assertNotFound();
        $this->assertNull(GameRoom::find($room->id));
    }

    public function test_a_room_past_the_deadline_refuses_a_direct_join(): void
    {
        [$room] = $this->room('ABCDE', ['empty_since' => now()->subMinutes(6)]);

        $this->actingAs(User::factory()->create())->post('/convites/ABCDE')->assertStatus(410);
        $this->assertNull(GameRoom::find($room->id));
    }

    public function test_a_room_still_inside_the_deadline_accepts_the_player(): void
    {
        [$room] = $this->room('ABCDE', ['empty_since' => now()->subMinutes(4)]);

        $this->actingAs(User::factory()->create())->post('/convites/ABCDE')->assertRedirect(route('rooms.show', $room));
        $this->assertNull($room->fresh()->empty_since);
    }

    // ---- custo -------------------------------------------------------------------------

    public function test_each_step_stays_within_its_query_budget(): void
    {
        // Sem contar sessão/autenticação (em testes, actingAs não consulta). Antes da otimização: 6 e 9.
        $this->room();
        $player = User::factory()->create();
        $this->actingAs($player);

        $this->assertLessThanOrEqual(4, $this->countQueries(fn () => $this->get('/convites/ABCDE')->assertOk()));
        $this->assertLessThanOrEqual(7, $this->countQueries(fn () => $this->post('/convites/ABCDE')->assertRedirect()));
    }
}
