<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class RankingController extends Controller
{
    public function __invoke(): Response
    {
        $now = Carbon::now();
        $weekStart = $now->copy()->startOfWeek(Carbon::MONDAY);
        $monthStart = $now->copy()->startOfMonth();

        return Inertia::render('Rankings/Index', [
            'periods' => [
                'weekly' => [
                    'start' => $weekStart->toDateString(),
                    'end' => $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
                    'previousStart' => $weekStart->copy()->subWeek()->toDateString(),
                    'previousEnd' => $weekStart->copy()->subDay()->toDateString(),
                ],
                'monthly' => [
                    'start' => $monthStart->toDateString(),
                    'end' => $monthStart->copy()->endOfMonth()->toDateString(),
                    'previousStart' => $monthStart->copy()->subMonthNoOverflow()->toDateString(),
                    'previousEnd' => $monthStart->copy()->subDay()->toDateString(),
                ],
            ],
        ]);
    }
}
