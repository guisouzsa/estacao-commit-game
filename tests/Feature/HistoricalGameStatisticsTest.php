<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalGameStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_historical_totals_are_shown_on_public_profile_without_inventing_match_count(): void
    {
        $user = User::factory()->create([
            'username' => 'gui',
            'matches_won' => 100,
            'matches_lost' => 20,
        ]);

        $this->get(route('profile.show', $user->username))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Public')
                ->where('profile.statistics.matches', null)
                ->where('profile.statistics.wins', 100)
                ->where('profile.statistics.losses', 20)
                ->where('profile.statistics.win_rate', null));
    }

    public function test_confirmed_historical_totals_are_available_in_account_profile(): void
    {
        $user = User::factory()->create([
            'matches_won' => 100,
            'matches_lost' => 20,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('profileExtras.statistics.matches', null)
                ->where('profileExtras.statistics.wins', 100)
                ->where('profileExtras.statistics.losses', 20)
                ->where('profileExtras.statistics.win_rate', null));
    }
}
