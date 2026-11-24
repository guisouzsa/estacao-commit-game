<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PlayerPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ranking de contas permanentes, ordenado por vitórias (desempate: menos derrotas, depois nome).
 *
 * - Semanal e mensal: calculados a partir do histórico permanente (match_results), pela data real
 *   de cada vitória. A sala pode ser excluída depois; o histórico não depende dela.
 * - Geral: estatísticas acumuladas da conta (inclui o histórico anterior às partidas registradas).
 * Visitantes e bots não entram: não têm conta.
 */
class RankingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $tz = config('ranking.timezone');
        $now = CarbonImmutable::now($tz);
        $first = DB::table('match_results')->min('finished_at');
        $firstAt = $first ? CarbonImmutable::parse($first, 'UTC')->setTimezone($tz) : null;

        // Semana de calendário (segunda a domingo) e mês, no fuso do ranking. Pedidos para o
        // futuro ou com data inválida caem no período atual. Nada é "zerado": cada ranking
        // filtra os resultados pela data real da vitória.
        $weekStart = $this->parseWeek($request->query('week'), $now);
        $monthStart = $this->parseMonth($request->query('month'), $now);
        $weekEnd = $weekStart->addDays(6);
        $monthEnd = $monthStart->endOfMonth();

        $rankings = [
            'weekly' => $this->forPeriod($weekStart, $weekStart->addWeek()),
            'monthly' => $this->forPeriod($monthStart, $monthStart->addMonthNoOverflow()),
            'overall' => $this->overall(),
        ];

        $currentWeek = $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $currentMonth = $now->startOfMonth();
        $firstWeek = $firstAt?->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $firstMonth = $firstAt?->startOfMonth();

        // Meses com histórico (do primeiro resultado até o atual), do mais novo ao mais antigo.
        $months = [];
        for ($m = $currentMonth; $firstMonth && $m->gte($firstMonth) && count($months) < 36; $m = $m->subMonthNoOverflow()) {
            $months[] = $m->format('Y-m');
        }

        return Inertia::render('Rankings/Index', [
            'periods' => [
                'weekly' => [
                    'start' => $weekStart->toDateString(),
                    'end' => $weekEnd->toDateString(),
                    'previousStart' => $weekStart->subWeek()->toDateString(),
                    'previousEnd' => $weekStart->subDay()->toDateString(),
                    'number' => (int) $weekStart->isoWeek(),
                    'year' => (int) $weekStart->isoWeekYear(),
                    'isCurrent' => $weekStart->equalTo($currentWeek),
                    'previous' => $firstWeek && $weekStart->gt($firstWeek) ? $weekStart->subWeek()->toDateString() : null,
                    'next' => $weekStart->lt($currentWeek) ? $weekStart->addWeek()->toDateString() : null,
                ],
                'monthly' => [
                    'start' => $monthStart->toDateString(),
                    'end' => $monthEnd->toDateString(),
                    'previousStart' => $monthStart->subMonthNoOverflow()->toDateString(),
                    'previousEnd' => $monthStart->subDay()->toDateString(),
                    'key' => $monthStart->format('Y-m'),
                    'isCurrent' => $monthStart->equalTo($currentMonth),
                    'previous' => $firstMonth && $monthStart->gt($firstMonth) ? $monthStart->subMonthNoOverflow()->format('Y-m') : null,
                    'next' => $monthStart->lt($currentMonth) ? $monthStart->addMonthNoOverflow()->format('Y-m') : null,
                    'available' => $months,
                ],
            ],
            'timezone' => $tz,
            'rankings' => $rankings,
            'viewerId' => $request->user()->id,
        ]);
    }

    private function parseWeek(mixed $raw, CarbonImmutable $now): CarbonImmutable
    {
        $current = $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        if (! is_string($raw) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $current;
        }
        try {
            $week = CarbonImmutable::createFromFormat('!Y-m-d', $raw, config('ranking.timezone'))->startOfWeek(CarbonImmutable::MONDAY);
        } catch (\Throwable) {
            return $current;
        }

        return $week->gt($current) ? $current : $week;
    }

    private function parseMonth(mixed $raw, CarbonImmutable $now): CarbonImmutable
    {
        $current = $now->startOfMonth();
        if (! is_string($raw) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $raw)) {
            return $current;
        }
        $month = CarbonImmutable::createFromFormat('!Y-m', $raw, config('ranking.timezone'))->startOfMonth();

        return $month->gt($current) ? $current : $month;
    }

    /**
     * Resultados com conclusão em [início, fim) no fuso do ranking, convertidos para UTC
     * (é assim que o banco guarda). Fim exclusivo evita contar o instante da virada duas vezes.
     *
     * @return list<array<string, mixed>>
     */
    private function forPeriod(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('match_results')
            ->where('finished_at', '>=', $from->utc()->format('Y-m-d H:i:s'))
            ->where('finished_at', '<', $to->utc()->format('Y-m-d H:i:s'))
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as played, sum(case when is_winner then 1 else 0 end) as won')
            ->orderByDesc('won')
            ->orderBy('played')
            ->limit((int) config('ranking.limit'))
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
            ->limit((int) config('ranking.limit'))
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
