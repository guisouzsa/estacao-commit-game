<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Tag "Líder" decidida só no servidor e troca de identificador sem corrida. */
class LeaderAndRenameTest extends TestCase
{
    use RefreshDatabase;

    private function publicProfile(User $viewer, User $owner): array
    {
        $props = [];
        $this->actingAs($viewer)->get(route('profile.show', $owner->username))
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props']['profile'];
            });
        $this->app['auth']->forgetGuards();

        return $props;
    }

    private function appearance(User $user, array $extra)
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user)->patch(route('profile.appearance.update'), array_merge([
            'username' => $user->username,
            'avatar_position_x' => 50, 'avatar_position_y' => 50,
            'profile_frame' => array_key_first(config('profiles.frames')),
            'primary_badge' => array_key_first(config('profiles.badges')),
        ], $extra));
    }

    public function test_only_the_confirmed_owner_account_is_leader(): void
    {
        config(['profiles.admin_emails' => ['dono@exemplo.com']]);
        $owner = User::factory()->create(['email' => 'dono@exemplo.com', 'email_verified_at' => now()]);
        $other = User::factory()->create();
        $unconfirmed = User::factory()->create(['email' => 'dono2@exemplo.com', 'email_verified_at' => null]);

        $this->assertTrue($this->publicProfile($other, $owner)['is_leader']);
        $this->assertFalse($this->publicProfile($other, $other)['is_leader']);
        $this->assertFalse($this->publicProfile($owner, $unconfirmed)['is_leader']);
    }

    public function test_an_unconfirmed_account_with_the_owners_email_does_not_become_leader(): void
    {
        config(['profiles.admin_emails' => ['dono@exemplo.com']]);
        $impostor = User::factory()->create(['email' => 'dono@exemplo.com', 'email_verified_at' => null]);

        $this->assertFalse($impostor->isLeader());
    }

    public function test_nobody_can_grant_themselves_the_tag_by_sending_fields_or_changing_email(): void
    {
        config(['profiles.admin_emails' => ['dono@exemplo.com']]);
        User::factory()->create(['email' => 'dono@exemplo.com', 'email_verified_at' => now()]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->appearance($user, ['is_leader' => true, 'role' => 'leader', 'developer_badge' => 'leader', 'email' => 'dono@exemplo.com']);
        $this->assertFalse($user->fresh()->isLeader());

        // trocar o próprio e-mail pelo do dono é recusado (e-mail único) e, se mudasse, perderia a confirmação
        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->patch(route('profile.update'), ['username' => $user->username, 'email' => 'dono@exemplo.com'])->assertSessionHasErrors('email');
        $this->assertFalse($user->fresh()->isLeader());

        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->patch(route('profile.update'), ['username' => $user->username, 'email' => 'novo@exemplo.com']);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_the_leader_flag_is_part_of_the_player_preview_payload(): void
    {
        config(['profiles.admin_emails' => ['dono@exemplo.com']]);
        $owner = User::factory()->create(['email' => 'dono@exemplo.com', 'email_verified_at' => now()]);
        $normal = User::factory()->create();

        $this->assertTrue(\App\Support\PlayerPresenter::user($owner)['is_leader']);
        $this->assertFalse(\App\Support\PlayerPresenter::user($normal)['is_leader']);
    }

    public function test_renaming_to_a_taken_name_is_refused_case_insensitively(): void
    {
        User::factory()->create(['username' => 'ocupado']);
        $user = User::factory()->create(['username' => 'meunome']);

        $this->appearance($user, ['username' => 'OCUPADO'])->assertSessionHasErrors('username');
        $this->assertSame('meunome', $user->fresh()->username);
    }

    public function test_the_old_name_is_freed_after_a_rename(): void
    {
        $user = User::factory()->create(['username' => 'nomeantigo']);

        $this->appearance($user, ['username' => 'nomenovo'])->assertSessionHasNoErrors();

        $this->assertSame('nomenovo', $user->fresh()->username);
        $this->assertSame(0, User::whereRaw('LOWER(username) = ?', ['nomeantigo'])->count());
        User::factory()->create(['username' => 'nomeantigo']); // outra pessoa pode registrá-lo
        $this->assertSame(1, User::whereRaw('LOWER(username) = ?', ['nomeantigo'])->count());
    }

    public function test_a_race_for_the_same_name_ends_in_a_friendly_error_not_a_500(): void
    {
        $user = User::factory()->create(['username' => 'corredor']);
        $raced = false;
        User::saving(function (User $saving) use (&$raced): void {
            if (! $raced && $saving->isDirty('username') && $saving->username === 'disputado') {
                $raced = true; // outra pessoa pega o nome depois da validação e antes de gravar
                DB::table('users')->insert([
                    'id' => (string) Str::uuid7(), 'username' => 'disputado', 'display_name' => 'disputado',
                    'email' => 'rapido@exemplo.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $this->appearance($user, ['username' => 'disputado'])->assertSessionHasErrors('username');

        $this->assertTrue($raced);
        $this->assertSame('corredor', $user->fresh()->username, 'a troca não foi aplicada pela metade');
    }
}
