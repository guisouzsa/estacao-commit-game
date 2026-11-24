<?php

namespace Tests\Feature\Security;

use App\Models\Follow;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Tentativas de trapacear partidas, estatísticas, ranking e seguidores. O servidor precisa recusar todas. */
class GameIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private GameRoom $room;

    /** @var \Illuminate\Support\Collection<int, User> */
    private $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->users = User::factory()->count(4)->create(['matches_won' => 3, 'matches_played' => 10, 'matches_lost' => 7])->values();
        $this->room = GameRoom::create(['host_user_id' => $this->users[0]->id, 'status' => 'waiting']);
        foreach ($this->users as $i => $u) {
            $this->room->participants()->create(['user_id' => $u->id, 'kind' => 'user', 'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now()]);
        }
        $this->actingAs($this->users[0])->post(route('games.start', $this->room));
    }

    private function match(): GameMatch
    {
        return GameMatch::firstOrFail()->fresh();
    }

    private function me(int $i): int
    {
        return $this->room->participants()->where('seat', $i + 1)->firstOrFail()->id;
    }

    private function act(User $user, array $body)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user)->post(route('games.action', $this->room), [
            'action_uuid' => (string) Str::uuid(), 'version' => $this->match()->version, ...$body,
        ]);
    }

    private function setHand(int $seatIndex, array $hand, int $step = 0, array $path = []): void
    {
        $m = $this->match();
        $s = $m->state;
        $s['players'][(string) $this->me($seatIndex)]['hand'] = $hand;
        $s['players'][(string) $this->me($seatIndex)]['step'] = $step;
        $s['players'][(string) $this->me($seatIndex)]['path'] = $path;
        $m->forceFill(['state' => $s, 'current_seat' => $seatIndex + 1])->save();
    }

    // ---------------------------------------------------------------- jogadas inválidas

    public function test_a_player_cannot_play_a_card_they_do_not_hold_or_out_of_turn_or_the_wrong_step(): void
    {
        $this->setHand(0, ['git_add', 'git_commit', 'git_push', 'git_clone']);
        $before = $this->match();

        // carta que não está na mão (inclusive a vitória)
        foreach (['pull_request', 'git_login', 'inexistente', '', str_repeat('x', 40)] as $card) {
            $this->act($this->users[0], ['action' => 'play_card', 'card' => $card])->assertSessionHasErrors('card');
        }
        // carta que possui, mas que não é a da etapa
        $this->act($this->users[0], ['action' => 'play_card', 'card' => 'git_push'])->assertSessionHasErrors('card');
        // fora da vez
        $this->act($this->users[1], ['action' => 'play_card', 'card' => 'git_login'])->assertSessionHasErrors('turn');
        // ação inventada / bloco quando não é a etapa do bloco
        $this->act($this->users[0], ['action' => 'forcar_vitoria'])->assertSessionHasErrors('action');
        $this->act($this->users[0], ['action' => 'play_block'])->assertSessionHasErrors('card');

        $this->assertEquals($before->state, $this->match()->state, 'nenhuma tentativa pode alterar o estado');
        $this->assertSame($before->version, $this->match()->version);
    }

    public function test_cannot_force_a_win_without_the_whole_path(): void
    {
        $this->setHand(0, ['pull_request', 'git_push', 'git_pull', 'git_add'], 5, ['git_login', 'git_clone', 'git_checkout', 'block_1', 'git_add']);

        $this->act($this->users[0], ['action' => 'play_card', 'card' => 'pull_request'])->assertSessionHasErrors('card');

        $this->assertSame('active', $this->match()->status);
        $this->assertNull($this->match()->winner_participant_id);
        $this->assertSame(3, (int) $this->users[0]->fresh()->matches_won);
        $this->assertSame(0, DB::table('match_results')->count());
    }

    public function test_forged_identity_and_result_fields_in_the_request_are_ignored(): void
    {
        $this->setHand(0, ['git_login', 'git_add', 'git_commit', 'git_push']);

        // Quem joga é SEMPRE quem está autenticado: ids/vencedor/resultado enviados no corpo não valem.
        $this->act($this->users[0], [
            'action' => 'play_card', 'card' => 'git_login',
            'participant_id' => $this->me(1), 'user_id' => $this->users[1]->id, 'winner_participant_id' => $this->me(0),
            'status' => 'finished', 'matches_won' => 999, 'seat' => 2,
        ])->assertSessionHasNoErrors();

        $m = $this->match();
        $this->assertSame('active', $m->status);
        $this->assertNull($m->winner_participant_id);
        $this->assertSame([3, 10, 7], [(int) $this->users[0]->fresh()->matches_won, (int) $this->users[0]->fresh()->matches_played, (int) $this->users[0]->fresh()->matches_lost]);
        // a jogada foi feita pelo participante autenticado (assento 1), não pelo "participant_id" forjado
        $this->assertSame(['git_login'], $m->state['players'][(string) $this->me(0)]['path']);
        $this->assertSame([], $m->state['players'][(string) $this->me(1)]['path']);
    }

    public function test_the_same_move_cannot_be_applied_twice_and_stale_versions_are_refused(): void
    {
        $this->setHand(0, ['git_login', 'git_add', 'git_commit', 'git_push']);
        $uuid = (string) Str::uuid();
        $version = $this->match()->version;
        $body = ['action_uuid' => $uuid, 'action' => 'play_card', 'card' => 'git_login', 'version' => $version];

        $this->actingAs($this->users[0])->post(route('games.action', $this->room), $body);
        $after = $this->match();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->users[0])->post(route('games.action', $this->room), $body); // replay do mesmo pedido

        $this->assertEquals($after->state, $this->match()->state);
        $this->assertSame($version + 1, $this->match()->version);
        $this->assertSame(1, DB::table('game_actions')->where('action_uuid', $uuid)->count());

        // versão antiga (jogada baseada em estado velho)
        $this->app['auth']->forgetGuards();
        $this->setHand(1, ['git_login', 'git_add', 'git_commit', 'git_push']);
        $this->actingAs($this->users[1])->post(route('games.action', $this->room), ['action_uuid' => (string) Str::uuid(), 'action' => 'exchange', 'card' => 'git_login', 'version' => 1])->assertStatus(409);
    }

    public function test_hands_deck_and_state_of_other_players_are_never_sent_to_a_viewer(): void
    {
        $this->app['auth']->forgetGuards();
        $page = $this->actingAs($this->users[1])->get(route('games.show', $this->room))->original->getData()['page']['props'];
        $json = json_encode($page);

        $m = $this->match();
        $this->assertArrayNotHasKey('state', $page['game']);
        $this->assertArrayNotHasKey('deck', $page['game']);
        foreach ($page['participants'] as $p) {
            $this->assertArrayNotHasKey('hand', $p, 'a mão dos outros não vai para o navegador');
            $this->assertArrayNotHasKey('recent_swaps', $p);
        }
        // a única mão enviada é a do próprio jogador
        $mine = $m->state['players'][(string) $this->me(1)]['hand'];
        $this->assertSame($mine, $page['hand']);
        // nem o baralho inteiro, nem a mão de um oponente aparecem em lugar nenhum do JSON
        $this->assertStringNotContainsString(json_encode($m->state['deck']), $json);
        $this->assertStringNotContainsString(json_encode($m->state['players'][(string) $this->me(2)]['hand']), $json);
    }

    public function test_outsiders_cannot_act_read_or_step_bots_and_cannot_join_a_started_room(): void
    {
        $stranger = User::factory()->create();
        $this->app['auth']->forgetGuards();

        $this->actingAs($stranger)->get(route('games.show', $this->room))->assertNotFound();
        $this->actingAs($stranger)->post(route('games.action', $this->room), ['action_uuid' => (string) Str::uuid(), 'action' => 'exchange', 'card' => 'git_add', 'version' => 1])->assertNotFound();
        $this->actingAs($stranger)->post(route('games.bots', $this->room), ['version' => 1])->assertNotFound();
        $this->actingAs($stranger)->delete(route('rooms.cancel', $this->room))->assertNotFound();
        $this->actingAs($stranger)->post(route('realtime.token'), ['channel' => 'rooms.'.$this->room->id])->assertStatus(404); // desligado/sem acesso
    }

    public function test_a_bot_step_never_plays_for_a_human_and_never_skips_versions(): void
    {
        $m = $this->match();
        $this->setHand(0, ['git_login', 'git_add', 'git_commit', 'git_push']); // vez de um humano
        $before = $this->match();

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->users[1])->post(route('games.bots', $this->room), ['version' => $before->version])->assertRedirect();

        $this->assertEquals($before->state, $this->match()->state, 'o passo de bot não joga por humanos');
        $this->assertSame($before->version, $this->match()->version);
    }

    public function test_finished_matches_accept_nothing_and_never_double_count(): void
    {
        $this->setHand(0, ['pull_request', 'git_add', 'git_commit', 'git_push'], 17, array_slice(GameEngine::REQUIRED, 0, 17));
        $this->act($this->users[0], ['action' => 'play_card', 'card' => 'pull_request'])->assertSessionHasNoErrors();
        $this->assertSame('finished', $this->match()->status);
        $stats = fn () => $this->users->map(fn ($u) => [(int) $u->fresh()->matches_won, (int) $u->fresh()->matches_played, (int) $u->fresh()->matches_lost])->all();
        $snapshot = $stats();
        $results = DB::table('match_results')->count();

        // depois do fim: qualquer ação, de qualquer jogador, é recusada e nada muda
        foreach ([0, 1, 2, 3] as $i) {
            $this->act($this->users[$i], ['action' => 'exchange', 'card' => 'git_add'])->assertSessionHasErrors();
        }
        $this->act($this->users[0], ['action' => 'play_card', 'card' => 'pull_request'])->assertSessionHasErrors();

        $this->assertSame($snapshot, $stats());
        $this->assertSame($results, DB::table('match_results')->count());
        $this->assertSame(1, DB::table('match_results')->where('is_winner', true)->count());
        $this->assertSame([4, 11, 7], $snapshot[0]);
    }

    public function test_no_endpoint_accepts_a_result_from_a_client_and_stats_columns_are_not_writable(): void
    {
        $user = $this->users[1];
        $this->app['auth']->forgetGuards();
        foreach (['/profile', '/profile/appearance'] as $path) {
            $this->actingAs($user)->patch($path, ['matches_won' => 500, 'matches_played' => 500, 'matches_lost' => 0, 'xp' => 99999]);
        }
        foreach (['/partidas', '/resultado', '/salas/'.$this->room->id.'/resultado', '/rankings'] as $path) {
            $this->actingAs($user)->post($path, ['winner' => $user->id, 'matches_won' => 500])->assertStatus(in_array($path, ['/rankings']) ? 405 : 404);
        }
        $this->assertSame(3, (int) $user->fresh()->matches_won);
        $this->assertFalse(in_array('matches_won', (new User)->getFillable(), true));
        $this->assertFalse(in_array('matches_played', (new User)->getFillable(), true));
        $this->assertFalse(in_array('matches_lost', (new User)->getFillable(), true));
    }

    // ---------------------------------------------------------------- seguidores

    public function test_follow_rules_hold_under_repetition_forgery_and_concurrency_like_requests(): void
    {
        $me = $this->users[0];
        $target = User::factory()->create(['username' => 'alvo_seguido']);
        $this->app['auth']->forgetGuards();

        // repetição e "cliques simultâneos": uma única linha e um único aviso
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($me)->post(route('follow.store', 'alvo_seguido'));
        }
        $this->assertSame(1, Follow::where('follower_id', $me->id)->where('followed_id', $target->id)->count());
        $this->assertSame(1, $target->notifications()->count());

        // seguir e largar repetidamente não gera avisos novos (janela de 24 h)
        $this->actingAs($me)->delete(route('follow.destroy', 'alvo_seguido'));
        $this->actingAs($me)->post(route('follow.store', 'alvo_seguido'));
        $this->assertSame(1, $target->notifications()->count());

        // a si mesmo, inexistente, e dados forjados no corpo
        $this->actingAs($me)->post(route('follow.store', $me->username));
        $this->assertSame(0, Follow::where('follower_id', $me->id)->where('followed_id', $me->id)->count());
        $this->actingAs($me)->post(route('follow.store', 'ninguem_existe'))->assertNotFound();
        $this->actingAs($me)->post(route('follow.store', 'alvo_seguido'), ['follower_id' => $this->users[1]->id, 'followed_id' => $this->users[2]->id]);
        $this->assertSame(0, Follow::where('follower_id', $this->users[1]->id)->where('followed_id', $this->users[2]->id)->count());

        // não dá para remover o relacionamento de TERCEIROS
        Follow::create(['follower_id' => $this->users[1]->id, 'followed_id' => $target->id]);
        $this->actingAs($me)->delete(route('follow.destroy', 'alvo_seguido'));
        $this->assertSame(1, Follow::where('follower_id', $this->users[1]->id)->where('followed_id', $target->id)->count());

        // contagens do perfil = linhas reais
        $payload = $this->actingAs($me)->get(route('profile.followers', 'alvo_seguido'))->original->getData()['page']['props'];
        $this->assertSame(Follow::where('followed_id', $target->id)->count(), $payload['counts']['followers']);
        $this->assertSame(Follow::where('follower_id', $target->id)->count(), $payload['counts']['following']);
    }

    public function test_the_database_itself_refuses_duplicate_or_self_follows(): void
    {
        $a = $this->users[0];
        Follow::create(['follower_id' => $a->id, 'followed_id' => $this->users[1]->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Follow::create(['follower_id' => $a->id, 'followed_id' => $this->users[1]->id]);
    }
}
