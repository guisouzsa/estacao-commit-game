<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_weekly_and_monthly_periods(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));

        $this->actingAs(User::factory()->create())
            ->get(route('rankings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rankings/Index')
                ->where('periods.weekly.start', '2026-11-16')
                ->where('periods.weekly.end', '2026-11-22')
                ->where('periods.weekly.previousStart', '2026-11-09')
                ->where('periods.weekly.previousEnd', '2026-11-15')
                ->where('periods.monthly.start', '2026-11-01')
                ->where('periods.monthly.end', '2026-11-30')
                ->where('periods.monthly.previousStart', '2026-10-01')
                ->where('periods.monthly.previousEnd', '2026-10-31'));
    }

    public function test_rankings_require_authentication(): void
    {
        $this->get(route('rankings'))->assertRedirect(route('login'));
    }

    public function test_periods_are_correct_when_a_week_and_month_cross_year_boundary(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 2)->setTime(12, 0));

        $this->actingAs(User::factory()->create())
            ->get(route('rankings'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('periods.weekly.start', '2026-12-28')
                ->where('periods.weekly.end', '2027-01-03')
                ->where('periods.monthly.start', '2027-01-01')
                ->where('periods.monthly.end', '2027-01-31')
                ->where('periods.monthly.previousStart', '2026-12-01')
                ->where('periods.monthly.previousEnd', '2026-12-31'));
    }

    public function test_overall_ranking_uses_account_statistics_and_puts_the_leader_first(): void
    {
        $leader = User::factory()->create(['username' => 'gui', 'matches_played' => 120, 'matches_won' => 100, 'matches_lost' => 20]);
        User::factory()->create(['username' => 'ana', 'matches_played' => 10, 'matches_won' => 4, 'matches_lost' => 6]);
        User::factory()->create(['username' => 'semjogos', 'matches_played' => 0, 'matches_won' => 0, 'matches_lost' => 0]);

        $this->actingAs($leader)->get(route('rankings'))->assertInertia(fn ($page) => $page
            ->has('rankings.overall', 2)
            ->where('rankings.overall.0.player.username', 'gui')
            ->where('rankings.overall.0.position', 1)
            ->where('rankings.overall.0.won', 100)
            ->where('rankings.overall.0.lost', 20)
            ->where('rankings.overall.0.win_rate', 83)
            ->where('rankings.overall.1.player.username', 'ana')
            ->where('viewerId', $leader->id));
    }

    public function test_weekly_ranking_counts_only_finished_matches_of_the_period(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
        [$winner, $loser] = User::factory()->count(2)->create();

        $this->finishedMatch($winner, $loser, now()->subHour());
        $this->finishedMatch($winner, $loser, now()->subHours(2));
        $this->finishedMatch($loser, $winner, now()->subDays(20)); // fora da semana e do mês

        $this->actingAs($winner)->get(route('rankings'))->assertInertia(fn ($page) => $page
            ->has('rankings.weekly', 2)
            ->where('rankings.weekly.0.user_id', $winner->id)
            ->where('rankings.weekly.0.won', 2)
            ->where('rankings.weekly.0.played', 2)
            ->where('rankings.weekly.1.user_id', $loser->id)
            ->where('rankings.weekly.1.won', 0)
            ->where('rankings.weekly.1.lost', 2));
    }

    public function test_guests_and_bots_are_never_ranked(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
        $user = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $user->id, 'status' => 'finished']);
        $host = $room->participants()->create(['user_id' => $user->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        $room->participants()->create(['kind' => 'bot', 'bot_key' => config('bots.catalog.0.key'), 'is_host' => false, 'seat' => 2, 'joined_at' => now()]);
        GameMatch::create(['room_id' => $room->id, 'status' => 'finished', 'current_seat' => 1, 'state' => [], 'winner_participant_id' => $host->id, 'finished_at' => now()]);

        $this->actingAs($user)->get(route('rankings'))->assertInertia(fn ($page) => $page
            ->has('rankings.weekly', 1)
            ->where('rankings.weekly.0.user_id', $user->id));
    }

    private function finishedMatch(User $winner, User $loser, $finishedAt): void
    {
        $room = GameRoom::create(['host_user_id' => $winner->id, 'status' => 'finished']);
        $w = $room->participants()->create(['user_id' => $winner->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        $room->participants()->create(['user_id' => $loser->id, 'kind' => 'user', 'is_host' => false, 'seat' => 2, 'joined_at' => now()]);
        GameMatch::create(['room_id' => $room->id, 'status' => 'finished', 'current_seat' => 1, 'state' => [], 'winner_participant_id' => $w->id, 'finished_at' => $finishedAt]);
    }
}
