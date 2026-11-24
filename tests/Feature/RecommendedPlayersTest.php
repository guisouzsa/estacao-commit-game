<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Jogadores recomendados: gui primeiro, sorteio sem repetição, rodízio e "ver mais". */
class RecommendedPlayersTest extends TestCase
{
    use RefreshDatabase;

    private function names(User $viewer, array $query = [])
    {
        $response = $this->actingAs($viewer)->get(route('dashboard', $query));

        return collect($response->original->getData()['page']['props']['recommendedPlayers'])->pluck('username');
    }

    public function test_five_players_with_gui_always_first_and_nobody_duplicated(): void
    {
        $gui = User::factory()->create(['username' => 'gui']);
        User::factory()->count(30)->create();
        $viewer = User::factory()->create();

        foreach (range(1, 12) as $_) {
            $names = $this->names($viewer);
            $this->assertCount(5, $names);
            $this->assertSame('gui', $names[0]);
            $this->assertSame($names->unique()->count(), $names->count(), 'sem duplicados');
            $this->assertNotContains($viewer->username, $names->all(), 'não recomenda a própria pessoa');
        }
    }

    public function test_each_reload_brings_a_new_selection_and_recent_people_are_rotated_out(): void
    {
        User::factory()->create(['username' => 'gui']);
        User::factory()->count(20)->create();
        $viewer = User::factory()->create();

        $first = $this->names($viewer)->slice(1)->values();   // 4 além do gui
        $second = $this->names($viewer)->slice(1)->values();
        $third = $this->names($viewer)->slice(1)->values();

        $this->assertEmpty($first->intersect($second)->all(), 'quem acabou de aparecer não repete logo em seguida');
        $this->assertEmpty($second->intersect($third)->all());
        $this->assertEmpty($first->intersect($third)->all(), 'o rodízio percorre os que ainda não apareceram');
    }

    public function test_over_time_everyone_gets_exposure_not_always_the_same_people(): void
    {
        User::factory()->create(['username' => 'gui']);
        User::factory()->count(19)->create();
        $viewer = User::factory()->create();

        $seen = collect();
        foreach (range(1, 10) as $_) {
            $seen = $seen->merge($this->names($viewer)->slice(1));
        }

        // 10 recarregamentos x 4 vagas = 40 exibições entre 19 candidatos: todos aparecem, de forma equilibrada.
        $this->assertSame(19, $seen->unique()->count());
        $counts = $seen->countBy();
        $this->assertLessThanOrEqual(3, $counts->max() - $counts->min(), 'distribuição equilibrada');
    }

    public function test_the_selection_is_not_predictable(): void
    {
        User::factory()->create(['username' => 'gui']);
        User::factory()->count(30)->create();
        $viewers = User::factory()->count(6)->create();

        // Pessoas diferentes (sessões novas) recebem sorteios diferentes.
        $lists = $viewers->map(fn ($v) => $this->names($v)->slice(1)->sort()->values()->implode(','))->unique();
        $this->assertGreaterThan(1, $lists->count());
    }

    public function test_few_players_are_shown_without_duplicates_and_without_gui_when_it_does_not_exist(): void
    {
        $viewer = User::factory()->create();
        User::factory()->count(2)->create();

        $names = $this->names($viewer);

        $this->assertCount(2, $names);
        $this->assertSame($names->unique()->count(), $names->count());
    }

    public function test_when_the_viewer_is_gui_the_list_has_five_others(): void
    {
        $gui = User::factory()->create(['username' => 'gui']);
        User::factory()->count(10)->create();

        $names = $this->names($gui);

        $this->assertCount(5, $names);
        $this->assertNotContains('gui', $names->all());
    }

    public function test_see_more_returns_new_players_without_repeating_the_ones_on_screen(): void
    {
        User::factory()->create(['username' => 'gui']);
        User::factory()->count(25)->create();
        $viewer = User::factory()->create();

        $shown = $this->names($viewer)->all();

        $response = $this->actingAs($viewer)->withHeaders([
            'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'moreRecommendedPlayers',
            'X-Inertia-Version' => \Inertia\Inertia::getVersion(),
        ])->get(route('dashboard', ['shown' => $shown]));
        $more = collect($response->json('props.moreRecommendedPlayers'))->pluck('username');

        $this->assertCount(10, $more);
        $this->assertEmpty($more->intersect($shown)->all(), 'não repete quem já está na tela');
        $this->assertNotContains('gui', $more->all());
        $this->assertSame($more->unique()->count(), $more->count());
    }

    public function test_the_extra_list_is_not_computed_on_a_normal_page_load(): void
    {
        User::factory()->count(12)->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('recommendedPlayers')->missing('moreRecommendedPlayers'));
    }

    public function test_visitors_also_get_recommendations(): void
    {
        User::factory()->create(['username' => 'gui']);
        User::factory()->count(6)->create();
        $guest = \App\Models\GuestProfile::create([
            'display_name' => 'Visita', 'name_key' => 'visita', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz',
            'token_hash' => hash('sha256', 'x'), 'last_seen_at' => now(), 'expires_at' => now()->addHours(6),
        ]);

        $response = $this->actingAs($guest, 'visitor')->get(route('dashboard'));

        $names = collect($response->original->getData()['page']['props']['recommendedPlayers'])->pluck('username');
        $this->assertSame('gui', $names[0]);
        $this->assertCount(5, $names);
    }
}
