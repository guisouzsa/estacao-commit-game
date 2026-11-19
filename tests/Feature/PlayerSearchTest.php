<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_search_matches_username_or_display_name(): void
    {
        User::factory()->create([
            'username' => 'ada_codes',
            'display_name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
        User::factory()->create([
            'username' => 'codewizard',
            'display_name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/jogadores/busca?q=lovelace')
            ->assertOk()
            ->assertJsonCount(1, 'players')
            ->assertJsonPath('players.0.username', 'ada_codes')
            ->assertJsonPath('players.0.display_name', 'Ada Lovelace')
            ->assertJsonMissingPath('players.0.email');

        $this->actingAs(User::factory()->create())
            ->getJson('/jogadores/busca?q=CODEWIZARD')
            ->assertOk()
            ->assertJsonCount(1, 'players')
            ->assertJsonPath('players.0.username', 'codewizard')
            ->assertJsonPath('players.0.display_name', 'Grace Hopper');
    }

    public function test_player_search_requires_at_least_two_characters(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/jogadores/busca?q=a')
            ->assertOk()
            ->assertExactJson(['players' => []]);
    }
}
