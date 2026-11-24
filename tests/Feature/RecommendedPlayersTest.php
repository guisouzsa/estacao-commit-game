<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\GuestProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Jogadores recomendados: prévia rotativa na Dashboard e lista completa em página própria. */
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

        $props = $this->actingAs($viewer)->get(route('dashboard'))->original->getData()['page']['props']['recommendedPlayers'];
        $real = collect($props)->reject(fn ($p) => ! empty($p['is_bot']))->pluck('username');

        // Poucas pessoas reais: aparecem todas, sem duplicar; o resto é completado por bots sinalizados.
        $this->assertCount(2, $real);
        $this->assertSame($real->unique()->count(), $real->count());
        $this->assertCount(5, $props);
    }

    public function test_when_the_viewer_is_gui_the_list_has_five_others(): void
    {
        $gui = User::factory()->create(['username' => 'gui']);
        User::factory()->count(10)->create();

        $names = $this->names($gui);

        $this->assertCount(5, $names);
        $this->assertNotContains('gui', $names->all());
    }

    public function test_recommended_players_have_their_own_paginated_page(): void
    {
        User::factory()->create(['username' => 'gui']);
        User::factory()->count(25)->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('players.recommended'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Players/Recommended')
                ->has('people.data', 20)
                ->where('people.data.0.username', 'gui')
                ->where('people.current_page', 1)
                ->where('people.last_page', 2)
                ->where('people.total', 26));
    }

    public function test_recommended_page_excludes_the_viewer_and_marks_followed_people(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create(['username' => 'ana']);
        Follow::query()->create([
            'follower_id' => $viewer->id,
            'followed_id' => $followed->id,
        ]);

        $response = $this->actingAs($viewer)->get(route('players.recommended'));
        $people = collect($response->original->getData()['page']['props']['people']['data']);

        $this->assertNotContains($viewer->username, $people->pluck('username')->all());
        $this->assertTrue($people->firstWhere('username', 'ana')['is_following']);
        $this->assertTrue($people->firstWhere('username', 'ana')['can_follow']);
    }

    public function test_visitors_also_get_recommendations(): void
    {
        User::factory()->create(['username' => 'gui']);
        User::factory()->count(6)->create();
        $guest = GuestProfile::create([
            'display_name' => 'Visita', 'name_key' => 'visita', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz',
            'token_hash' => hash('sha256', 'x'), 'last_seen_at' => now(), 'expires_at' => now()->addHours(6),
        ]);

        $response = $this->actingAs($guest, 'visitor')->get(route('dashboard'));

        $names = collect($response->original->getData()['page']['props']['recommendedPlayers'])->pluck('username');
        $this->assertSame('gui', $names[0]);
        $this->assertCount(5, $names);
    }

    public function test_visitors_can_open_the_recommended_page_without_follow_actions(): void
    {
        User::factory()->count(3)->create();
        $guest = GuestProfile::create([
            'display_name' => 'Visita', 'name_key' => 'visita', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz',
            'token_hash' => hash('sha256', 'x'), 'last_seen_at' => now(), 'expires_at' => now()->addHours(6),
        ]);

        $response = $this->actingAs($guest, 'visitor')->get(route('players.recommended'));
        $people = collect($response->original->getData()['page']['props']['people']['data']);

        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Players/Recommended'));
        $this->assertTrue($people->every(fn (array $person) => $person['can_follow'] === false));
    }
}
