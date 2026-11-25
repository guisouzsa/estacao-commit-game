<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifiedBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $username = 'dono'): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'email_verified_at' => now(),
        ]);
        $user->forceFill(['can_grant_verified' => true])->save();

        return $user;
    }

    public function test_admin_grants_and_removes_the_verified_badge(): void
    {
        $target = User::factory()->create(['username' => 'ada_codes']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch('/admin/users/ada_codes/verified', ['verified' => true])
            ->assertRedirect();
        $this->assertTrue($target->fresh()->isVerified());

        $this->get('/perfil/ada_codes')->assertInertia(
            fn ($page) => $page->where('profile.is_verified', true),
        );

        $this->actingAs($admin)
            ->patch('/admin/users/ada_codes/verified', ['verified' => false])
            ->assertRedirect();
        $this->assertFalse($target->fresh()->isVerified());
    }

    public function test_regular_players_cannot_verify_anyone_not_even_themselves(): void
    {
        $player = User::factory()->create(['username' => 'intruso']);

        $this->actingAs($player)
            ->patch('/admin/users/intruso/verified', ['verified' => true])
            ->assertForbidden();
        $this->assertFalse($player->fresh()->isVerified());
    }

    public function test_the_manage_box_is_offered_only_to_the_admin(): void
    {
        $this->actingAs($this->admin())->get('/profile')->assertInertia(
            fn ($page) => $page->where('canManageVerified', true),
        );

        $this->actingAs(User::factory()->create())->get('/profile')->assertInertia(
            fn ($page) => $page->where('canManageVerified', false),
        );
    }

    public function test_search_reports_the_verified_state(): void
    {
        User::factory()->create(['username' => 'grace_h', 'verified_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->getJson('/jogadores/busca?q=grace')
            ->assertJsonPath('players.0.is_verified', true);
    }

    public function test_the_badge_and_the_power_cannot_be_self_assigned_through_profile_update(): void
    {
        $player = User::factory()->create(['username' => 'esperto']);

        $this->actingAs($player)->patch('/profile', [
            'username' => 'esperto',
            'email' => $player->email,
            'verified_at' => now()->toDateTimeString(),
            'is_verified' => true,
            'can_grant_verified' => true,
        ]);

        $fresh = $player->fresh();
        $this->assertFalse($fresh->isVerified());
        $this->assertFalse($fresh->canGrantVerified());
        $this->actingAs($fresh)->patch('/admin/users/esperto/verified', ['verified' => true])->assertForbidden();
    }

    public function test_power_requires_a_confirmed_email(): void
    {
        $user = User::factory()->create(['username' => 'falso', 'email_verified_at' => null]);
        $user->forceFill(['can_grant_verified' => true])->save();
        User::factory()->create(['username' => 'alvo']);

        $this->actingAs($user)
            ->patch('/admin/users/alvo/verified', ['verified' => true])
            ->assertForbidden();
    }

    public function test_the_username_alone_never_grants_power(): void
    {
        $impostor = User::factory()->create(['username' => 'gui', 'email_verified_at' => now()]);
        User::factory()->create(['username' => 'alvo']);

        $this->actingAs($impostor)
            ->patch('/admin/users/alvo/verified', ['verified' => true])
            ->assertForbidden();
        $this->actingAs($impostor)->get('/profile')->assertInertia(
            fn ($page) => $page->where('canManageVerified', false),
        );
    }

    public function test_the_admin_email_in_env_no_longer_matters(): void
    {
        config(['profiles.admin_emails' => ['leader@example.com']]);
        $leader = User::factory()->create(['email' => 'leader@example.com', 'email_verified_at' => now()]);
        User::factory()->create(['username' => 'alvo']);

        $this->actingAs($leader)
            ->patch('/admin/users/alvo/verified', ['verified' => true])
            ->assertForbidden();
    }

    public function test_guests_and_anonymous_visitors_are_refused(): void
    {
        User::factory()->create(['username' => 'alvo']);

        $this->patch('/admin/users/alvo/verified', ['verified' => true])->assertRedirect();
        $this->assertFalse(User::where('username', 'alvo')->first()->isVerified());
    }

    public function test_admin_can_remove_their_own_badge(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['verified_at' => now()])->save();

        $this->actingAs($admin)->patch('/admin/users/dono/verified', ['verified' => false])->assertRedirect();

        $this->assertFalse($admin->fresh()->isVerified());
    }
}
