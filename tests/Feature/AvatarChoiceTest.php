<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\GuestProfile;
use App\Models\User;
use App\Support\AvatarUrl;
use App\Support\PlayerPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Ícones gerados: escolha na entrada do visitante e na edição de perfil das contas. */
class AvatarChoiceTest extends TestCase
{
    use RefreshDatabase;

    private function appearance(User $user, array $extra = [])
    {
        return $this->actingAs($user)->patch(route('profile.appearance.update'), array_merge([
            'avatar_position_x' => 50,
            'avatar_position_y' => 50,
            'profile_frame' => array_key_first(config('profiles.frames')),
            'primary_badge' => array_key_first(config('profiles.badges')),
        ], $extra));
    }

    // ---- "Blocos" não é mais uma opção ----------------------------------------------

    public function test_blocks_is_no_longer_offered_when_a_visitor_picks_a_style(): void
    {
        $this->get(route('guest.create'))->assertInertia(fn ($page) => $page
            ->where('styles', ['pixel', 'robot', 'terminal']));

        $this->assertNotContains('blocks', config('players.selectable_avatar_styles'));
    }

    public function test_the_server_rejects_blocks_as_a_chosen_style(): void
    {
        $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'blocks', 'avatar_seed' => 'abc123xyz'])
            ->assertSessionHasErrors('avatar_style');

        $this->assertDatabaseCount('guest_profiles', 0);
    }

    public function test_a_profile_that_already_uses_blocks_keeps_rendering(): void
    {
        $guest = GuestProfile::create([
            'display_name' => 'Antigo', 'name_key' => 'antigo', 'avatar_style' => 'blocks', 'avatar_seed' => 'abc123xyz',
            'token_hash' => hash('sha256', 'x'), 'last_seen_at' => now(), 'expires_at' => now()->addDay(),
        ]);

        $this->assertSame('blocks', PlayerPresenter::guest($guest)['avatar_style']);
        $this->assertSame('blocks', PlayerPresenter::bot('commit-supremo')['avatar_style'], 'bots mantêm o estilo');
    }

    // ---- conta escolhe um ícone no editor de perfil -----------------------------------

    public function test_an_account_can_pick_a_generated_icon_instead_of_a_photo(): void
    {
        $user = User::factory()->create();

        $this->appearance($user, ['avatar_generated' => 'robot:abc123xyz9'])->assertSessionHasNoErrors();

        $this->assertSame('gen:robot:abc123xyz9', $user->fresh()->avatar);
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('auth.user.avatar', null)
            ->where('auth.user.avatar_style', 'robot')
            ->where('auth.user.avatar_seed', 'abc123xyz9'));
    }

    public function test_all_three_selectable_styles_are_accepted(): void
    {
        $user = User::factory()->create();

        foreach (['pixel', 'robot', 'terminal'] as $style) {
            $this->appearance($user, ['avatar_generated' => "{$style}:seed12345"])->assertSessionHasNoErrors();
            $this->assertSame("gen:{$style}:seed12345", $user->fresh()->avatar);
        }
    }

    public function test_invalid_icon_values_are_rejected_and_nothing_changes(): void
    {
        $user = User::factory()->create(['avatar' => 'gen:pixel:keepthis1']);

        foreach (['blocks:abc123xyz9', 'robot:<script>', 'robot:abc', 'hack:abc123xyz9', 'robot', ':abc123xyz9', 'robot:ABC123XYZ9', 'robot:abc123xyz9:extra'] as $bad) {
            $this->appearance($user, ['avatar_generated' => $bad])->assertSessionHasErrors('avatar_generated');
        }

        $this->assertSame('gen:pixel:keepthis1', $user->fresh()->avatar);
    }

    public function test_an_uploaded_photo_wins_over_a_chosen_icon(): void
    {
        $user = User::factory()->create();

        $this->appearance($user, [
            // PNG 1x1 real: o gerador de imagens do Laravel exige a extensão GD, que nem todo ambiente tem.
            'avatar' => UploadedFile::fake()->createWithContent('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')),
            'avatar_generated' => 'robot:abc123xyz9',
        ])->assertSessionHasNoErrors();

        $this->assertStringStartsWith('data:image/', $user->fresh()->avatar);
        $this->assertStringNotContainsString('gen:', $user->fresh()->avatar);
    }

    public function test_removing_the_avatar_clears_a_chosen_icon_and_goes_back_to_the_default(): void
    {
        $user = User::factory()->create(['avatar' => 'gen:robot:abc123xyz9']);

        $this->appearance($user, ['remove_avatar' => true])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->avatar);
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('auth.user.avatar_style', 'pixel')
            ->where('auth.user.avatar_seed', PlayerPresenter::seed((string) $user->id)));
    }

    public function test_a_chosen_icon_is_the_same_everywhere_the_avatar_appears(): void
    {
        $user = User::factory()->create(['username' => 'iconeiro', 'avatar' => 'gen:terminal:zzz999zzz']);

        // Perfil público
        $this->get('/perfil/iconeiro')->assertInertia(fn ($page) => $page
            ->where('profile.avatar', null)
            ->where('profile.avatar_style', 'terminal')
            ->where('profile.avatar_seed', 'zzz999zzz'));

        // Busca
        $this->actingAs($user)->getJson(route('players.search', ['q' => 'iconeiro']))
            ->assertJsonPath('players.0.avatar_style', 'terminal')
            ->assertJsonPath('players.0.avatar_seed', 'zzz999zzz');

        // Sala
        $response = $this->actingAs($user)->post(route('rooms.store'));
        $room = GameRoom::findOrFail(basename($response->headers->get('Location')));
        $this->actingAs($user)->get(route('rooms.show', $room))->assertInertia(fn ($page) => $page
            ->where('room.participants.0.player.avatar_style', 'terminal')
            ->where('room.participants.0.player.avatar_seed', 'zzz999zzz')
            ->where('room.participants.0.player.avatar', null));
    }

    public function test_the_icon_is_not_exposed_as_a_broken_image_url(): void
    {
        $this->assertNull(AvatarUrl::for('gen:robot:abc123xyz9'));

        // Valor malformado nunca vira uma URL de imagem nem estilo inválido.
        $user = User::factory()->make(['avatar' => 'gen:hack:abc123xyz9']);
        $fields = PlayerPresenter::avatarFields($user);

        $this->assertNull($fields['avatar']);
        $this->assertContains($fields['avatar_style'], config('players.avatar_styles'));
    }

    public function test_the_editor_receives_only_the_selectable_styles(): void
    {
        $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('avatarStyles', ['pixel', 'robot', 'terminal']));
    }

    public function test_the_icon_needs_no_database_change(): void
    {
        // Guardado na coluna users.avatar (já é text): o Render Free não precisa de SQL novo.
        $user = User::factory()->create(['avatar' => 'gen:pixel:abc123xyz9']);
        $this->assertSame('gen:pixel:abc123xyz9', $user->fresh()->avatar);
    }
}
