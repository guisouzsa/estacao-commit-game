<?php

namespace Tests\Feature;

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
}
