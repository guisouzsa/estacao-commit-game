<?php

namespace Tests\Feature;

use App\Models\GameAction;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\RoomInvitation;
use App\Models\User;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GameMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_host_can_start_a_full_room(): void
    {
        [$room, $users] = $this->humanRoom();

        $this->actingAs($users[1])->post(route('games.start', $room))->assertForbidden();
        $this->assertDatabaseCount('game_matches', 0);

        $this->actingAs($users[0])->post(route('games.start', $room))->assertRedirect(route('games.show', $room));
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'active']);
        $this->assertDatabaseHas('game_matches', ['room_id' => $room->id, 'status' => 'active']);
    }

    public function test_a_match_cannot_start_without_four_participants(): void
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting']);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);

        $this->actingAs($host)->post(route('games.start', $room))->assertSessionHasErrors('room');
        $this->assertDatabaseCount('game_matches', 0);
    }

    public function test_each_player_receives_four_private_common_cards_and_a_block(): void
    {
        [$room, $users] = $this->humanRoom();
        $this->actingAs($users[0])->post(route('games.start', $room));
        $match = GameMatch::firstOrFail();

        $this->assertCount(67, $match->state['deck']);
        foreach ($match->state['players'] as $player) {
            $this->assertCount(4, $player['hand']);
            $this->assertSame(0, $player['step']);
            $this->assertContains('git_login', $player['hand'], 'todos começam com o Git Login');
        }
        $this->assertNotContains('git_login', $match->state['deck'], 'as 4 cópias já estão nas mãos');

        $this->actingAs($users[0])->get(route('games.show', $room))->assertInertia(fn (Assert $page) => $page
            ->component('Games/Show')
            ->has('hand', 4)
            ->has('participants', 4)
            ->missing('game.state')
            ->where('requiredCard', 'git_login'));
    }

    public function test_a_valid_card_advances_the_path_and_turn(): void
    {
        [$room, $users] = $this->startedHumanRoom();
        $match = GameMatch::firstOrFail();
        $host = $room->participants()->where('seat', 1)->firstOrFail();
        $this->preparePlayer($match, $host->id, 0, ['git_login', 'git_push']);

        $this->actingAs($users[0])->post(route('games.action', $room), $this->action($match, 'play_card', ['card' => 'git_login']))->assertRedirect();

        $match->refresh();
        $player = $match->state['players'][(string) $host->id];
        $this->assertSame(1, $player['step']);
        $this->assertSame(['git_login'], $player['path']);
        $this->assertSame(2, $match->current_seat);
        $this->assertCount(1, GameAction::all());
    }

    public function test_out_of_order_and_out_of_turn_moves_are_rejected_without_changing_state(): void
    {
        [$room, $users] = $this->startedHumanRoom();
        $match = GameMatch::firstOrFail();
        $host = $room->participants()->where('seat', 1)->firstOrFail();
        $this->preparePlayer($match, $host->id, 0, ['git_push']);

        $this->actingAs($users[0])->from(route('games.show', $room))
            ->post(route('games.action', $room), $this->action($match, 'play_card', ['card' => 'git_push']))
            ->assertSessionHasErrors('card');
        $this->actingAs($users[1])->from(route('games.show', $room))
            ->post(route('games.action', $room), $this->action($match->fresh(), 'exchange', ['card' => 'git_login']))
            ->assertSessionHasErrors('turn');

        $this->assertSame(0, $match->fresh()->state['players'][(string) $host->id]['step']);
        $this->assertDatabaseCount('game_actions', 0);
    }

    public function test_repeated_action_uuid_does_not_apply_a_move_twice(): void
    {
        [$room, $users] = $this->startedHumanRoom();
        $match = GameMatch::firstOrFail();
        $host = $room->participants()->where('seat', 1)->firstOrFail();
        $this->preparePlayer($match, $host->id, 0, ['git_login']);
        $payload = $this->action($match, 'play_card', ['card' => 'git_login']);

        $this->actingAs($users[0])->post(route('games.action', $room), $payload);
        $this->actingAs($users[0])->post(route('games.action', $room), $payload);

        $this->assertDatabaseCount('game_actions', 1);
        $this->assertSame(1, $match->fresh()->state['players'][(string) $host->id]['step']);
    }

    public function test_pull_request_finishes_the_match_and_records_real_statistics(): void
    {
        [$room, $users] = $this->startedHumanRoom();
        $match = GameMatch::firstOrFail();
        $host = $room->participants()->where('seat', 1)->firstOrFail();
        $this->preparePlayer($match, $host->id, 17, ['pull_request']);

        $this->actingAs($users[0])->post(route('games.action', $room), $this->action($match, 'play_card', ['card' => 'pull_request']));

        $match->refresh();
        $room->refresh();
        $this->assertSame('finished', $match->status);
        $this->assertSame('finished', $room->status);
        $this->assertNotNull($room->finished_at);
        $this->assertSame($host->id, $match->winner_participant_id);
        $this->assertSame(1, $users[0]->fresh()->matches_won);
        $this->assertSame(1, $users[1]->fresh()->matches_lost);
    }

    public function test_bots_use_the_same_engine_and_return_the_turn_to_a_human(): void
    {
        [$room, $host] = $this->botRoom();
        $this->actingAs($host)->post(route('games.start', $room));
        $match = GameMatch::firstOrFail();
        $state = $match->state;
        foreach ($room->participants()->get() as $participant) {
            $state['players'][(string) $participant->id]['step'] = 0;
            $state['players'][(string) $participant->id]['path'] = [];
            $state['players'][(string) $participant->id]['hand'] = ['git_login', 'git_clone'];
            $state['players'][(string) $participant->id]['pending_discard'] = 0;
        }
        $match->forceFill(['state' => $state, 'current_seat' => 2])->save();

        app(GameEngine::class)->runBots($match->fresh(), $room);

        $match->refresh();
        $this->assertSame(1, $match->current_seat);
        // Com uma pessoa na mesa, cada bot pode avançar ou "hesitar" (trocar uma carta) — BotBrain::HANDICAP.
        // Em qualquer caso joga exatamente uma vez e nunca pula etapa.
        foreach ($room->participants()->where('kind', 'bot')->get() as $bot) {
            $player = $match->state['players'][(string) $bot->id];
            $this->assertContains($player['step'], [0, 1]);
            $this->assertCount(2, $player['hand']);
        }
    }

    public function test_merge_conflict_skips_exactly_one_turn(): void
    {
        [$room, $users] = $this->startedHumanRoom();
        $match = GameMatch::firstOrFail();
        $host = $room->participants()->where('seat', 1)->firstOrFail();
        $target = $room->participants()->where('seat', 2)->firstOrFail();
        $this->preparePlayer($match, $host->id, 0, ['merge_conflict']);

        $this->actingAs($users[0])->post(route('games.action', $room), $this->action($match, 'play_card', [
            'card' => 'merge_conflict', 'target_id' => $target->id,
        ]));

        $match->refresh();
        $this->assertSame(3, $match->current_seat);
        $this->assertFalse($match->state['players'][(string) $target->id]['blocked']);
    }

    public function test_clear_credentials_restarts_only_a_training_path(): void
    {
        [$room, $users] = $this->startedHumanRoom();
        $match = GameMatch::firstOrFail();
        $host = $room->participants()->where('seat', 1)->firstOrFail();
        $target = $room->participants()->where('seat', 2)->firstOrFail();
        $state = $match->state;
        $state['players'][(string) $host->id]['hand'] = ['clear_credentials'];
        $state['players'][(string) $target->id]['step'] = 2;
        $state['players'][(string) $target->id]['path'] = ['git_login', 'git_clone'];
        $match->forceFill(['state' => $state, 'current_seat' => 1])->save();

        $this->actingAs($users[0])->post(route('games.action', $room), $this->action($match, 'play_card', [
            'card' => 'clear_credentials', 'target_id' => $target->id,
        ]));

        $targetState = $match->fresh()->state['players'][(string) $target->id];
        $this->assertSame(0, $targetState['step']);
        $this->assertSame([], $targetState['path']);
    }

    public function test_opening_an_invite_without_login_preserves_it_for_the_guest_flow(): void
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting']);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        $token = 'A2B3C';
        RoomInvitation::create(['room_id' => $room->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour()]);

        $this->get(route('invites.open', $token))
            ->assertRedirect(route('guest.create'))
            ->assertSessionHas('pending_room_invite', $token);
    }

    public function test_a_visitor_arriving_by_invite_joins_the_room_right_after_creating_the_profile(): void
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting']);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        RoomInvitation::create(['room_id' => $room->id, 'token_hash' => hash('sha256', 'A2B3C'), 'expires_at' => now()->addHour()]);

        $this->get(route('invites.open', 'A2B3C'));
        $this->post(route('guest.store'), ['name' => 'Visitante Novo', 'avatar_style' => 'pixel', 'avatar_seed' => 'abcdef12'])
            ->assertRedirect(route('rooms.show', $room));

        $this->assertSame(1, $room->participants()->where('kind', 'guest')->count());
    }

    public function test_a_revoked_invite_does_not_let_the_new_visitor_in(): void
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting']);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        RoomInvitation::create(['room_id' => $room->id, 'token_hash' => hash('sha256', 'A2B3C'), 'expires_at' => now()->addHour(), 'revoked_at' => now()]);

        $this->withSession(['pending_room_invite' => 'A2B3C'])
            ->post(route('guest.store'), ['name' => 'Visitante Novo', 'avatar_style' => 'pixel', 'avatar_seed' => 'abcdef12'])
            ->assertRedirect(route('invites.open', 'A2B3C'));

        $this->assertSame(0, $room->participants()->where('kind', 'guest')->count());
    }

    private function humanRoom(): array
    {
        $users = User::factory()->count(4)->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        foreach ($users as $index => $user) {
            $room->participants()->create(['user_id' => $user->id, 'kind' => 'user', 'is_host' => $index === 0, 'seat' => $index + 1, 'joined_at' => now()]);
        }

        return [$room, $users];
    }

    private function startedHumanRoom(): array
    {
        [$room, $users] = $this->humanRoom();
        $this->actingAs($users[0])->post(route('games.start', $room));
        $match = GameMatch::firstOrFail();
        $match->forceFill(['current_seat' => 1])->save();

        return [$room, $users];
    }

    public function test_a_bot_takes_over_a_human_after_three_consecutive_timeouts(): void
    {
        [$room, $users] = $this->humanRoom();
        $this->actingAs($users[0])->post(route('games.start', $room));
        $match = GameMatch::firstOrFail();
        $match->forceFill(['current_seat' => 2])->save();
        $idle = $room->participants()->where('user_id', $users[1]->id)->firstOrFail();

        // Antes do prazo nada muda; com dois timeouts já acumulados, o terceiro remove o jogador.
        $this->actingAs($users[0])->get(route('games.show', $room))->assertOk();
        $this->assertSame('user', $idle->fresh()->kind);
        $idle->forceFill(['timeout_streak' => 2])->save();

        $this->travel(config('game.turn_timeout_seconds') + 5)->seconds();
        $this->actingAs($users[0])->get(route('games.show', $room))->assertOk();

        $idle->refresh();
        $this->assertSame('bot', $idle->kind);
        $this->assertNull($idle->user_id);
        $this->assertNotNull($idle->bot_key);
        $match->refresh();
        // O bot ocupou o lugar (mesma mão) e joga na vez dele, no passo de bot, não em cadeia.
        $this->assertSame(2, $match->current_seat);
        $this->actingAs($users[0])->post(route('games.bots', $room), ['version' => $match->version])->assertRedirect();
        $match->refresh();
        $this->assertNotSame(2, $match->current_seat);
        $this->assertNotEmpty($match->state['players'][(string) $idle->id]['hand']);
        $this->assertTrue(collect($match->state['history'])->contains(fn ($item) => ($item['notify'] ?? false) && str_contains($item['message'], 'assumiu')));
    }

    public function test_the_replaced_player_is_sent_back_to_the_dashboard_with_a_notice(): void
    {
        [$room, $users] = $this->humanRoom();
        $this->actingAs($users[0])->post(route('games.start', $room));
        GameMatch::firstOrFail()->forceFill(['current_seat' => 2])->save();
        $room->participants()->where('user_id', $users[1]->id)->update(['timeout_streak' => 2]);

        $this->travel(config('game.turn_timeout_seconds') + 5)->seconds();

        $this->actingAs($users[1])->get(route('games.show', $room))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('toast.tone', 'warning');
    }

    public function test_only_the_player_whose_turn_it_is_gets_replaced(): void
    {
        [$room, $users] = $this->humanRoom();
        $this->actingAs($users[0])->post(route('games.start', $room));
        GameMatch::firstOrFail()->forceFill(['current_seat' => 1])->save();
        $room->participants()->where('seat', 1)->update(['timeout_streak' => 2]);
        $this->travel(config('game.turn_timeout_seconds') + 5)->seconds();

        // Quem está na vez é o jogador 1: só ele poderia ser substituído, e ele é substituído.
        $this->actingAs($users[1])->get(route('games.show', $room))->assertOk();
        $this->assertSame(1, $room->participants()->where('kind', 'bot')->count());
        $this->assertSame('user', $room->participants()->where('user_id', $users[1]->id)->firstOrFail()->kind);
    }

    public function test_a_partial_reload_returns_only_the_requested_match_props(): void
    {
        [$room, $users] = $this->humanRoom();
        $this->actingAs($users[0])->post(route('games.start', $room));

        $response = $this->actingAs($users[0])->get(route('games.show', $room), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => \Inertia\Inertia::getVersion(),
            'X-Inertia-Partial-Component' => 'Games/Show',
            'X-Inertia-Partial-Data' => 'game,hand',
        ])->assertOk();

        $props = $response->json('props');
        $this->assertArrayHasKey('game', $props);
        $this->assertArrayHasKey('hand', $props);
        $this->assertArrayNotHasKey('participants', $props);
        $this->assertArrayNotHasKey('requiredCard', $props);
    }

    public function test_the_page_tells_how_long_the_current_human_has_left_and_never_for_bots(): void
    {
        $this->freezeTime(); // o relógio da vez é gravado em segundos inteiros: sem congelar, a virada do segundo deixaria o teste instável
        [$room, $users] = $this->humanRoom();
        $this->actingAs($users[0])->post(route('games.start', $room));
        GameMatch::firstOrFail()->forceFill(['current_seat' => 1])->save();

        $this->actingAs($users[0])->get(route('games.show', $room))->assertInertia(fn (Assert $page) => $page
            ->where('game.turn_seconds_left', config('game.turn_timeout_seconds')));

        $this->travel(10)->seconds();
        $this->actingAs($users[0])->get(route('games.show', $room))->assertInertia(fn (Assert $page) => $page
            ->where('game.turn_seconds_left', config('game.turn_timeout_seconds') - 10));
    }

    public function test_exchanging_a_card_counts_as_the_turn_and_passes_it_on(): void
    {
        [$room, $users] = $this->humanRoom();
        $this->actingAs($users[0])->post(route('games.start', $room));
        $match = GameMatch::firstOrFail();
        $me = $room->participants()->where('user_id', $users[0]->id)->firstOrFail();
        $state = $match->state;
        $state['players'][(string) $me->id]['hand'] = ['git_push', 'git_add', 'git_commit', 'git_push'];
        $match->forceFill(['state' => $state, 'current_seat' => $me->seat])->save();

        $this->actingAs($users[0])->post(route('games.action', $room), $this->action($match->fresh(), 'exchange', ['card' => 'git_push']))->assertSessionHasNoErrors();

        $match->refresh();
        $hand = $match->state['players'][(string) $me->id]['hand'];
        $this->assertCount(4, $hand, 'troca descarta 1 e compra 1');
        $this->assertSame(1, $match->state['discard'] === [] ? 0 : count(array_keys($match->state['discard'], 'git_push')));
        $this->assertNotSame($me->seat, $match->current_seat, 'a vez passou ao próximo jogador');
        $this->assertSame(0, $match->state['players'][(string) $me->id]['step'], 'trocar não avança o percurso');
    }

    public function test_bots_play_one_move_at_a_time_never_in_a_chain_after_a_human_move(): void
    {
        [$room, $host] = $this->botRoom();
        $this->actingAs($host)->post(route('games.start', $room));
        $match = GameMatch::firstOrFail();
        $me = $room->participants()->where('user_id', $host->id)->firstOrFail();
        $state = $match->state;
        $state['players'][(string) $me->id]['hand'] = ['git_login', 'git_add', 'git_add', 'git_pull'];
        $match->forceFill(['state' => $state, 'current_seat' => 1])->save();

        // Depois da minha jogada a vez passa ao bot 2 e NENHUM bot joga sozinho.
        $this->actingAs($host)->post(route('games.action', $room), $this->action($match, 'play_card', ['card' => 'git_login']))->assertSessionHasNoErrors();
        $match->refresh();
        $this->assertSame(2, $match->current_seat);
        $this->assertSame(0, $match->state['players'][(string) $room->participants()->where('seat', 2)->value('id')]['step']);

        // Cada passo de bot joga UMA vez e passa a vez ao seguinte.
        foreach ([3, 4, 1] as $expectedSeat) {
            $this->actingAs($host)->post(route('games.bots', $room), ['version' => $match->fresh()->version])->assertRedirect();
            $this->assertSame($expectedSeat, $match->fresh()->current_seat);
        }
    }

    public function test_a_bot_step_with_a_stale_version_or_on_a_humans_turn_does_nothing(): void
    {
        [$room, $host] = $this->botRoom();
        $this->actingAs($host)->post(route('games.start', $room));
        $match = GameMatch::firstOrFail();
        $match->forceFill(['current_seat' => 1])->save();
        $version = $match->fresh()->version;

        // Vez de um humano: nada acontece.
        $this->actingAs($host)->post(route('games.bots', $room), ['version' => $version])->assertRedirect();
        $this->assertSame($version, $match->fresh()->version);

        // Vez de um bot, mas com a versão velha (outro cliente já deu o passo): ignorado.
        $match->forceFill(['current_seat' => 2])->save();
        $this->actingAs($host)->post(route('games.bots', $room), ['version' => $version + 5])->assertRedirect();
        $this->assertSame($version, $match->fresh()->version);
    }

    public function test_a_match_does_not_play_for_a_bot_at_the_start(): void
    {
        [$room, $host] = $this->botRoom();
        $this->actingAs($host)->post(route('games.start', $room));

        $this->assertSame(1, GameMatch::firstOrFail()->version, 'ninguém jogou ainda, nem o bot sorteado');
    }

    private function botRoom(): array
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting', 'bot_slots' => 3]);
        $room->participants()->create(['user_id' => $host->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        foreach (array_slice(array_column(config('bots.catalog'), 'key'), 0, 3) as $index => $botKey) {
            $room->participants()->create(['kind' => 'bot', 'bot_key' => $botKey, 'is_host' => false, 'seat' => $index + 2, 'joined_at' => now()]);
        }

        return [$room, $host];
    }

    private function preparePlayer(GameMatch $match, int $participantId, int $step, array $hand): void
    {
        $state = $match->state;
        $state['players'][(string) $participantId]['step'] = $step;
        $state['players'][(string) $participantId]['hand'] = $hand;
        $state['players'][(string) $participantId]['path'] = array_slice(GameEngine::REQUIRED, 0, $step);
        $state['players'][(string) $participantId]['pending_discard'] = 0;
        $match->forceFill(['state' => $state, 'current_seat' => 1])->save();
    }

    private function action(GameMatch $match, string $action, array $payload = []): array
    {
        return ['action_uuid' => (string) Str::uuid(), 'action' => $action, 'version' => $match->fresh()->version, ...$payload];
    }
}
