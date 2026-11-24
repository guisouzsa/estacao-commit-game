<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A casca do ranking abre na hora; as listas chegam depois (esqueleto no meio tempo). */
class RankingDeferredTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_response_has_the_shell_and_the_lists_load_afterwards(): void
    {
        $user = User::factory()->create(['matches_played' => 3, 'matches_won' => 2]);

        $this->actingAs($user)->get(route('rankings'))->assertInertia(fn ($page) => $page
            ->component('Rankings/Index')
            ->has('periods.weekly')->has('periods.monthly')
            ->missing('rankings')
            ->loadDeferredProps(fn ($deferred) => $deferred
                ->has('rankings.weekly')->has('rankings.monthly')->has('rankings.overall', 1)
                ->where('rankings.overall.0.won', 2)));
    }
}
