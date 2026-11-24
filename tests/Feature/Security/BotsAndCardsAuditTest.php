<?php

namespace Tests\Feature\Security;

use App\Models\Follow;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameEngine;
use App\Support\Bots;
use App\Support\PlayerPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Perfis de bots (catálogo + sorteio) e simulações estatísticas do baralho. */
class BotsAndCardsAuditTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- bots


    public function test_the_catalog_has_at_least_ten_distinct_featured_bots_with_valid_title_and_badge(): void
    {
        $catalog = collect(config('bots.catalog'));
        $featured = $catalog->filter(fn ($b) => isset($b['title']));

        $this->assertGreaterThanOrEqual(10, $featured->count());
        $this->assertSame($catalog->count(), $catalog->pluck('key')->unique()->count());
        $this->assertSame($catalog->count(), $catalog->pluck('name')->map('strtolower')->unique()->count());
        foreach ($featured as $bot) {
            $this->assertArrayHasKey($bot['title'], config('profiles.titles'));
            $this->assertArrayHasKey($bot['badge'], config('profiles.badges'));
        }
    }

    public function test_bots_are_identified_as_bots_and_have_no_account_stats_or_privileges(): void
    {
        $profile = PlayerPresenter::bot('commitzilla');

        $this->assertTrue($profile['is_bot']);
        $this->assertSame('bot', $profile['kind']);
        $this->assertStringStartsWith('bot:', $profile['key']);
        $this->assertNull($profile['matches_won'], 'bot não tem vitórias nem ranking');
        $this->assertArrayNotHasKey('email', $profile);
        // Bots NÃO são linhas da tabela de usuários: não têm senha, sessão, permissões nem seguidores.
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('sessions')->count());
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser(User::factory()->create())->allows('manage-developer-badges'));
    }

    public function test_bots_have_no_public_profile_cannot_be_followed_and_never_enter_rankings(): void
    {
        $viewer = User::factory()->create();
        $name = strtolower(config('bots.catalog.1.name'));

        $this->actingAs($viewer)->get(route('profile.show', $name))->assertNotFound();
        $this->actingAs($viewer)->post(route('follow.store', $name))->assertNotFound();
        $this->assertSame(0, Follow::count());

        // Partida concluída com bots: só as contas entram em match_results.
        $room = GameRoom::create(['host_user_id' => $viewer->id, 'status' => 'finished']);
        $human = $room->participants()->create(['user_id' => $viewer->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        foreach ([2, 3, 4] as $i => $seat) {
            $room->participants()->create(['kind' => 'bot', 'bot_key' => config("bots.catalog.$i.key"), 'is_host' => false, 'seat' => $seat, 'joined_at' => now()]);
        }
        GameMatch::create(['room_id' => $room->id, 'status' => 'finished', 'current_seat' => 1, 'state' => [], 'winner_participant_id' => $human->id, 'finished_at' => now()]);
        $this->assertSame(1, DB::table('match_results')->count());
        $this->assertSame(0, DB::table('match_results')->whereNull('user_id')->count());
    }

    public function test_bot_draws_are_distinct_independent_of_catalog_order_and_rotate(): void
    {
        $this->startSession();
        $all = array_keys(Bots::all());
        $seenRounds = [];

        for ($round = 0; $round < 6; $round++) { // 6 x 3 = 18 bots (histórico guarda 20)
            $pick = Bots::pickRotating(3);
            $this->assertCount(3, $pick);
            $this->assertSame(3, count(array_unique($pick)), 'sem duplicados na mesma seleção');
            $seenRounds[] = $pick;
        }
        $flat = array_merge(...$seenRounds);
        $this->assertSame(18, count(array_unique($flat)), 'rodízio: ninguém repete antes de todos aparecerem');

        // Não depende da ordem do catálogo: sorteios diferentes dão listas diferentes.
        $firsts = collect(range(1, 30))->map(function () {
            session()->forget('bots.recent');

            return implode(',', Bots::pickRotating(3));
        })->unique();
        $this->assertGreaterThan(5, $firsts->count());
        $this->assertNotSame(array_slice($all, 0, 3), Bots::pickRotating(3, [])); // não é "os 3 primeiros"
    }

    public function test_recommendations_use_real_people_first_and_bots_only_to_fill_clearly_marked(): void
    {
        User::factory()->create(['username' => 'gui']);
        User::factory()->count(1)->create();
        $viewer = User::factory()->create();

        $list = collect($this->dashboardProp($viewer, 'recommendedPlayers'));

        $this->assertCount(5, $list);
        $this->assertSame('gui', $list[0]['username']);
        $real = $list->filter(fn ($p) => empty($p['is_bot']));
        $bots = $list->filter(fn ($p) => ! empty($p['is_bot']));
        $this->assertCount(2, $real);
        $this->assertCount(3, $bots);
        $this->assertSame(3, $bots->pluck('username')->unique()->count());
        $this->assertTrue($list->slice(0, 2)->every(fn ($p) => empty($p['is_bot'])), 'pessoas reais vêm antes');
        $this->assertTrue($bots->every(fn ($p) => ! array_key_exists('key', $p)), 'sem chave interna');

        // com pessoas suficientes, nenhum bot entra
        User::factory()->count(8)->create();
        $full = collect($this->dashboardProp($viewer, 'recommendedPlayers'));
        $this->assertTrue($full->every(fn ($p) => empty($p['is_bot'])));
    }

    public function test_room_creation_bot_previews_are_drawn_and_distinct(): void
    {
        $viewer = User::factory()->create();
        $sets = collect(range(1, 12))->map(function () use ($viewer) {
            $this->app['auth']->forgetGuards();
            $names = collect($this->dashboardProp($viewer, 'botPreviews'))->pluck('username');
            $this->assertSame($names->count(), $names->unique()->count());

            return $names->sort()->implode(',');
        });
        $this->assertGreaterThan(1, $sets->unique()->count());
    }

    // ---------------------------------------------------------------- cartas: simulações

    private function dashboardProp(User $user, string $key): array
    {
        $this->app['auth']->forgetGuards();
        $props = [];
        $this->withoutExceptionHandling();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props'];
            });

        return (array) ($props[$key] ?? []);
    }

    private function room(): GameRoom
    {
        $users = User::factory()->count(4)->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        foreach ($users as $i => $u) {
            $room->participants()->create(['user_id' => $u->id, 'kind' => 'user', 'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now()]);
        }

        return $room;
    }

    public function test_initial_deal_statistics_over_many_matches(): void
    {
        $engine = app(GameEngine::class);
        $room = $this->room();
        $n = 400;
        $handCards = [];
        $triples = 0;
        $seats = array_fill(1, 4, 0);
        $expected = ['git_clone' => 7, 'git_checkout' => 7, 'git_pull' => 4, 'git_add' => 16, 'git_commit' => 16, 'git_push' => 16, 'pull_request' => 4, 'clear_credentials' => 2, 'merge_conflict' => 2];

        for ($i = 0; $i < $n; $i++) {
            GameMatch::query()->delete();
            $state = $engine->create($room)->state;
            $seats[(int) GameMatch::first()->current_seat]++;
            $this->assertCount(62, $state['deck']);
            foreach ($state['players'] as $p) {
                $this->assertSame(1, count(array_keys($p['hand'], 'git_login', true)), 'todos começam com exatamente 1 Git Login');
                foreach (array_count_values($p['hand']) as $card => $count) {
                    $triples += $count >= 3 ? 1 : 0;
                }
                foreach ($p['hand'] as $card) {
                    if ($card !== 'git_login') {
                        $handCards[$card] = ($handCards[$card] ?? 0) + 1;
                    }
                }
            }
        }

        // Proporção de cada carta nas mãos ≈ proporção no baralho comum (sorteio sem viés).
        $drawn = array_sum($handCards);
        $deckCommon = array_sum($expected);
        $report = [];
        foreach ($expected as $card => $qty) {
            $freq = ($handCards[$card] ?? 0) / $drawn;
            $target = $qty / $deckCommon;
            $report[] = sprintf('%s %.1f%% (esperado %.1f%%)', $card, $freq * 100, $target * 100);
            $this->assertEqualsWithDelta($target, $freq, 0.03, "frequência anormal de $card");
        }
        // Início de partida uniforme entre os 4 assentos (esperado ~25% cada).
        foreach ($seats as $seat => $count) {
            $this->assertEqualsWithDelta(0.25, $count / $n, 0.07, "assento $seat sorteado de forma enviesada");
        }
        fwrite(STDERR, "\n[distribuição inicial: {$n} partidas = ".($n * 12)." cartas comuns] ".implode('; ', $report)."\n[assentos iniciais] ".json_encode($seats)." [trincas nas mãos iniciais: {$triples}]\n");
    }

    public function test_exchange_simulation_frequencies_and_runs(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $room = $this->room();
        $this->actingAs($room->participants()->where('seat', 1)->first()->user);
        $engine = app(GameEngine::class);
        $match = $engine->create($room);
        $p1 = $room->participants()->where('seat', 1)->firstOrFail();

        $received = [];
        $sameAsTraded = 0;
        $runs = 0;
        $maxRun = 1;
        $run = 1;
        $last = null;
        $trials = 300;
        for ($t = 0; $t < $trials; $t++) {
            $state = $match->state;
            $deck = [];
            foreach (['git_clone' => 7, 'git_checkout' => 7, 'git_pull' => 4, 'git_add' => 16, 'git_commit' => 16, 'git_push' => 16, 'pull_request' => 4, 'clear_credentials' => 2, 'merge_conflict' => 2] as $c => $q) {
                $deck = [...$deck, ...array_fill(0, $q, $c)];
            }
            shuffle($deck);
            $hand = array_splice($deck, 0, 4);
            $state['players'][(string) $p1->id]['hand'] = $hand;
            $state['players'][(string) $p1->id]['recent_swaps'] = [];
            $state['deck'] = $deck;
            $state['discard'] = [];
            $match->forceFill(['state' => $state, 'current_seat' => 1, 'status' => 'active'])->save();

            $traded = $hand[0];
            $engine->act($match, $p1, 'exchange', ['card' => $traded], (string) Str::uuid());
            $new = $match->state['players'][(string) $p1->id]['hand'];
            $drawn = end($new);
            $received[$drawn] = ($received[$drawn] ?? 0) + 1;
            $sameAsTraded += $drawn === $traded ? 1 : 0;
            $run = $drawn === $last ? $run + 1 : 1;
            $maxRun = max($maxRun, $run);
            $last = $drawn;
        }

        arsort($received);
        $this->assertSame(0, $sameAsTraded, 'a troca nunca devolve a carta recém-trocada quando há alternativa no topo');
        $this->assertLessThanOrEqual(6, $maxRun, 'sequências de cartas iguais recebidas ficam curtas');
        $this->assertGreaterThanOrEqual(7, count($received), 'continua variado: muitos tipos de carta recebidos');
        // nenhuma carta domina: a mais frequente (git_add/commit/push, 16/78 cada) fica longe de 50%
        $this->assertLessThan(0.45, max($received) / $trials);
        fwrite(STDERR, "\n[trocas: {$trials}] recebidas: ".json_encode($received)." | devolveu a mesma: {$sameAsTraded} | maior sequência igual: {$maxRun}\n");
    }

    public function test_reconnecting_never_changes_hands_or_state(): void
    {
        $room = $this->room();
        app(GameEngine::class)->create($room);
        $user = $room->participants()->where('seat', 2)->firstOrFail()->user;
        $room->update(['status' => 'active', 'started_at' => now()]);

        $first = $this->actingAs($user)->get(route('games.show', $room))->original->getData()['page']['props'];
        foreach (range(1, 5) as $_) { // reconexões / recarregamentos repetidos
            $this->app['auth']->forgetGuards();
            $again = $this->actingAs($user)->get(route('games.show', $room))->original->getData()['page']['props'];
            $this->assertSame($first['hand'], $again['hand']);
            $this->assertSame($first['game']['version'], $again['game']['version']);
            $this->assertSame($first['game']['deck_count'], $again['game']['deck_count']);
        }
    }

    public function test_each_match_has_its_own_isolated_deck(): void
    {
        $engine = app(GameEngine::class);
        $a = $engine->create($this->room());
        $b = $engine->create($this->room());

        $this->assertNotSame($a->id, $b->id);
        $sa = $a->state;
        $sb = $b->state;
        $sa['deck'] = [];
        $a->forceFill(['state' => $sa])->save();
        $this->assertCount(62, $b->fresh()->state['deck'], 'mexer no baralho de uma partida não afeta a outra');
    }
}
