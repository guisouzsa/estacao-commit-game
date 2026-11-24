<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\MatchResults;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Rankings semanal, mensal e geral calculados pela data real das vitórias (fuso America/Sao_Paulo). */
class RankingPeriodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ranking.defer' => false]); // estes testes verificam as listas na primeira resposta
    }

    /** Vitória de $winner (contra $loser) concluída em $at (horário de São Paulo). */
    private function win(User $winner, User $loser, string $at): GameMatch
    {
        $finishedAt = CarbonImmutable::parse($at, 'America/Sao_Paulo')->utc();
        $room = GameRoom::create(['host_user_id' => $winner->id, 'status' => 'finished', 'finished_at' => $finishedAt]);
        $w = $room->participants()->create(['user_id' => $winner->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        $room->participants()->create(['user_id' => $loser->id, 'kind' => 'user', 'is_host' => false, 'seat' => 2, 'joined_at' => now()]);

        return GameMatch::create(['room_id' => $room->id, 'status' => 'finished', 'current_seat' => 1, 'state' => [], 'winner_participant_id' => $w->id, 'finished_at' => $finishedAt]);
    }

    private function page(User $viewer, array $query = [])
    {
        return $this->actingAs($viewer)->get(route('rankings', $query));
    }

    public function test_current_week_shows_only_this_weeks_wins_and_labels_the_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00', 'America/Sao_Paulo'));
        [$a, $b] = User::factory()->count(2)->create();
        $this->win($a, $b, '2026-11-24 10:00'); // esta semana (23 a 29)
        $this->win($b, $a, '2026-11-18 10:00'); // semana anterior

        $this->page($a)->assertInertia(fn ($p) => $p
            ->where('periods.weekly.start', '2026-11-23')
            ->where('periods.weekly.end', '2026-11-29')
            ->where('periods.weekly.number', 48)
            ->where('periods.weekly.isCurrent', true)
            ->where('periods.weekly.next', null)
            ->where('periods.weekly.previous', '2026-11-16')
            ->has('rankings.weekly', 2)
            ->where('rankings.weekly.0.user_id', $a->id)
            ->where('rankings.weekly.0.won', 1)
            ->where('rankings.weekly.1.won', 0));
    }

    public function test_previous_week_can_be_consulted_and_a_future_week_falls_back_to_the_current_one(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00', 'America/Sao_Paulo'));
        [$a, $b] = User::factory()->count(2)->create();
        $this->win($b, $a, '2026-11-18 10:00');

        $this->page($a, ['week' => '2026-11-18'])->assertInertia(fn ($p) => $p
            ->where('periods.weekly.start', '2026-11-16')
            ->where('periods.weekly.isCurrent', false)
            ->where('periods.weekly.next', '2026-11-23')
            ->where('rankings.weekly.0.user_id', $b->id));

        $this->page($a, ['week' => '2030-01-01'])->assertInertia(fn ($p) => $p->where('periods.weekly.start', '2026-11-23'));
        $this->page($a, ['week' => 'lixo'])->assertInertia(fn ($p) => $p->where('periods.weekly.start', '2026-11-23'));
    }

    public function test_week_turns_exactly_at_midnight_of_the_ranking_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-30 12:00', 'America/Sao_Paulo'));
        [$a, $b] = User::factory()->count(2)->create();
        $this->win($a, $b, '2026-11-29 23:59:59'); // domingo, ainda semana 48
        $this->win($b, $a, '2026-11-30 00:00:00'); // segunda, semana 49 começa zerada para os outros

        $this->page($a)->assertInertia(fn ($p) => $p
            ->where('periods.weekly.start', '2026-11-30')
            ->has('rankings.weekly', 2)
            ->where('rankings.weekly.0.user_id', $b->id)
            ->where('rankings.weekly.0.won', 1)
            ->where('rankings.weekly.1.won', 0));

        $this->page($a, ['week' => '2026-11-23'])->assertInertia(fn ($p) => $p
            ->where('rankings.weekly.0.user_id', $a->id)
            ->where('rankings.weekly.0.won', 1));
    }

    public function test_monthly_ranking_resets_on_month_and_year_changes_without_losing_history(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-02 12:00', 'America/Sao_Paulo'));
        [$a, $b] = User::factory()->count(2)->create();
        $this->win($a, $b, '2026-12-31 23:30'); // dezembro (ano anterior)
        $this->win($a, $b, '2026-12-05 10:00');
        $this->win($b, $a, '2027-01-01 00:10'); // janeiro: mês novo começa do zero

        $this->page($a)->assertInertia(fn ($p) => $p
            ->where('periods.monthly.key', '2027-01')
            ->where('periods.monthly.previous', '2026-12')
            ->where('periods.monthly.available', ['2027-01', '2026-12'])
            ->where('rankings.monthly.0.user_id', $b->id)
            ->where('rankings.monthly.0.won', 1));

        $this->page($a, ['month' => '2026-12'])->assertInertia(fn ($p) => $p
            ->where('periods.monthly.isCurrent', false)
            ->where('periods.monthly.next', '2027-01')
            ->where('rankings.monthly.0.user_id', $a->id)
            ->where('rankings.monthly.0.won', 2));
    }

    public function test_results_survive_room_deletion_and_are_recorded_only_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00', 'America/Sao_Paulo'));
        [$a, $b] = User::factory()->count(2)->create();
        $match = $this->win($a, $b, '2026-11-24 10:00');

        // Eventos repetidos / reconexões: nunca conta de novo.
        $this->assertSame(0, MatchResults::record($match->fresh()));
        $match->fresh()->save();
        $this->assertSame(2, DB::table('match_results')->count());

        // A sala some (limpeza ou exclusão): partida e participantes saem por cascata; o histórico fica.
        GameRoom::query()->delete();
        $this->assertSame(0, GameMatch::count());
        $this->assertSame(2, DB::table('match_results')->count());

        $this->page($a)->assertInertia(fn ($p) => $p
            ->where('rankings.weekly.0.user_id', $a->id)
            ->where('rankings.weekly.0.won', 1));
    }

    public function test_empty_state_and_unfinished_matches(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-25 12:00', 'America/Sao_Paulo'));
        [$a, $b] = User::factory()->count(2)->create();
        $room = GameRoom::create(['host_user_id' => $a->id, 'status' => 'active']);
        $room->participants()->create(['user_id' => $a->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        GameMatch::create(['room_id' => $room->id, 'status' => 'active', 'current_seat' => 1, 'state' => []]);

        $this->page($a)->assertInertia(fn ($p) => $p
            ->has('rankings.weekly', 0)->has('rankings.monthly', 0)->has('rankings.overall', 0)
            ->where('periods.weekly.previous', null)
            ->where('periods.monthly.available', []));
    }

    public function test_migration_backfills_finished_matches_idempotently(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $this->win($a, $b, '2026-11-24 10:00');
        DB::table('match_results')->delete();

        $migration = require database_path('migrations/2026_11_24_000000_create_match_results_table.php');
        \Illuminate\Support\Facades\Schema::drop('match_results');
        $migration->up();
        $this->assertSame(2, DB::table('match_results')->count());
        $this->assertSame(1, DB::table('match_results')->where('is_winner', true)->count());
    }
}
