<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PlayerPresenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ranking de contas permanentes, ordenado por vitórias (desempate: menos derrotas, depois nome).
 *
 * - Semanal e mensal: calculados a partir das partidas CONCLUÍDAS no período.
 * - Geral: estatísticas acumuladas da conta (inclui o histórico anterior às partidas registradas).
 * Visitantes e bots não entram: não têm conta.
 */
class RankingController extends Controller
{
    private const LIMIT = 50;

    public function __invoke(Request $request): Response
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $monthStart = $now->copy()->startOfMonth();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $monthEnd = $monthStart->copy()->endOfMonth();

        $rankings = [
            'weekly' => $this->forPeriod($weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()),
            'monthly' => $this->forPeriod($monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()),
            'overall' => $this->overall(),
        ];

        return Inertia::render('Rankings/Index', [
            'periods' => [
                'weekly' => [
                    'start' => $weekStart->toDateString(),
                    'end' => $weekEnd->toDateString(),
                    'previousStart' => $weekStart->copy()->subWeek()->toDateString(),
                    'previousEnd' => $weekStart->copy()->subDay()->toDateString(),
                ],
                'monthly' => [
                    'start' => $monthStart->toDateString(),
                    'end' => $monthEnd->toDateString(),
                    'previousStart' => $monthStart->copy()->subMonthNoOverflow()->toDateString(),
                    'previousEnd' => $monthStart->copy()->subDay()->toDateString(),
                ],
            ],
            'rankings' => $rankings,
            'viewerId' => $request->user()->id,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function forPeriod(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('game_matches as m')
            ->join('room_participants as p', 'p.room_id', '=', 'm.room_id')
            ->where('m.status', 'finished')
            ->whereBetween('m.finished_at', [$from, $to])
            ->whereNotNull('p.user_id')
            ->groupBy('p.user_id')
            ->selectRaw('p.user_id, count(*) as played, sum(case when p.id = m.winner_participant_id then 1 else 0 end) as won')
            ->orderByDesc('won')
            ->orderBy('played')
            ->limit(self::LIMIT)
            ->get();

        return $this->present($rows->map(fn ($row) => [
            'user_id' => $row->user_id,
            'played' => (int) $row->played,
            'won' => (int) $row->won,
        ]));
    }

    /** @return list<array<string, mixed>> */
    private function overall(): array
    {
        $rows = User::query()
            ->where('matches_played', '>', 0)
            ->orderByDesc('matches_won')
            ->orderBy('matches_lost')
            ->orderBy('username')
            ->limit(self::LIMIT)
            ->get(['id', 'matches_played', 'matches_won']);

        return $this->present($rows->map(fn ($user) => [
            'user_id' => $user->id,
            'played' => (int) $user->matches_played,
            'won' => (int) $user->matches_won,
        ]));
    }

    /**
     * @param  Collection<int, array{user_id: string, played: int, won: int}>  $rows
     * @return list<array<string, mixed>>
     */
    private function present(Collection $rows): array
    {
        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows
            ->filter(fn ($row) => $users->has($row['user_id']))
            ->sort(fn ($a, $b) => ($b['won'] <=> $a['won'])
                ?: (($a['played'] - $a['won']) <=> ($b['played'] - $b['won']))
                ?: strcasecmp($users[$a['user_id']]->username, $users[$b['user_id']]->username))
            ->values()
            ->map(fn ($row, $index) => [
                'position' => $index + 1,
                'user_id' => $row['user_id'],
                'player' => PlayerPresenter::user($users[$row['user_id']]),
                'played' => $row['played'],
                'won' => $row['won'],
                'lost' => max(0, $row['played'] - $row['won']),
                'win_rate' => $row['played'] > 0 ? (int) round($row['won'] / $row['played'] * 100) : 0,
            ])
            ->all();
    }
}
