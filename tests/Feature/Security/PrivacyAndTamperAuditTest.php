<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Auditoria: ninguém altera as próprias vitórias, apaga ou lê dados privados de outra pessoa, nem se dá o selo. */
class PrivacyAndTamperAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_wins_cannot_be_changed_by_any_profile_or_settings_endpoint(): void
    {
        $user = User::factory()->create(['matches_won' => 1, 'matches_played' => 3, 'matches_lost' => 2]);
        $forged = ['matches_won' => 999, 'matches_played' => 999, 'matches_lost' => 0, 'xp' => 99999, 'level' => 99];

        $this->actingAs($user)->patch('/profile', ['username' => $user->username, 'email' => $user->email, ...$forged]);
        $this->actingAs($user)->patch('/profile/appearance', ['avatar_position_x' => 50, 'avatar_position_y' => 50, ...$forged]);
        $this->actingAs($user)->putJson('/profile/conquistas/destaques', ['mode' => 'none', 'featured' => [], ...$forged]);
        $this->actingAs($user)->patch('/admin/users/'.$user->username.'/verified', ['verified' => true, ...$forged]);

        $fresh = $user->fresh();
        $this->assertSame([1, 3, 2], [(int) $fresh->matches_won, (int) $fresh->matches_played, (int) $fresh->matches_lost]);
        $this->assertFalse($fresh->isVerified());
    }

    public function test_deleting_the_account_only_ever_deletes_the_requester(): void
    {
        $me = User::factory()->create(['password' => bcrypt('senha-correta-123')]);
        $other = User::factory()->create();

        // Tentativas de apontar para outra conta: a rota não aceita alvo, só apaga quem está logado.
        $this->actingAs($me)->delete('/profile', ['password' => 'senha-correta-123', 'id' => $other->id, 'user' => $other->id]);
        $this->assertDatabaseMissing('users', ['id' => $me->id]);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_other_users_cannot_be_deleted_or_edited_through_any_route(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['bio' => 'original']);

        $this->actingAs($me)->deleteJson('/profile/'.$other->id)->assertStatus(404);
        $this->actingAs($me)->patchJson('/perfil/'.$other->username, ['bio' => 'hack'])->assertStatus(405);
        $this->actingAs($me)->patchJson('/admin/users/'.$other->username.'/verified', ['verified' => true])->assertForbidden();

        $this->assertSame('original', $other->fresh()->bio);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_private_data_of_other_players_never_appears_in_any_listing(): void
    {
        $secret = User::factory()->create(['username' => 'alvo_privado', 'email' => 'privado-xyz@example.test', 'google_id' => 'g-secret-777']);
        $viewer = User::factory()->create();

        $pages = [
            $this->actingAs($viewer)->get('/perfil/alvo_privado')->getContent(),
            $this->actingAs($viewer)->get('/perfil/alvo_privado/seguidores')->getContent(),
            $this->actingAs($viewer)->get('/rankings')->getContent(),
            $this->actingAs($viewer)->get('/jogadores/recomendados')->getContent(),
            $this->actingAs($viewer)->get('/dashboard')->getContent(),
            $this->actingAs($viewer)->getJson('/jogadores/busca?q=alvo')->getContent(),
            $this->actingAs($viewer)->get('/configuracoes')->getContent(),
        ];

        foreach ($pages as $html) {
            $this->assertStringNotContainsString('privado-xyz@example.test', $html);
            $this->assertStringNotContainsString('g-secret-777', $html);
            $this->assertStringNotContainsString((string) $secret->password, $html);
            $this->assertStringNotContainsString('can_grant_verified', $html);
        }
    }

    public function test_only_the_database_permission_can_grant_any_badge_and_it_is_not_settable_by_clients(): void
    {
        $player = User::factory()->create(['email_verified_at' => now()]);
        $victim = User::factory()->create();

        $this->actingAs($player)->patch('/profile', ['username' => $player->username, 'email' => $player->email, 'can_grant_verified' => true]);
        $this->actingAs($player->fresh())->patch('/admin/users/'.$victim->username.'/verified', ['verified' => true])->assertForbidden();
        $this->actingAs($player->fresh())
            ->patch(route('admin.users.developer-badge.update', $victim), ['developer_badge' => 'founder'])
            ->assertForbidden();

        $this->assertFalse($victim->fresh()->isVerified());
        $this->assertNull($victim->fresh()->developer_badge);
    }
}
