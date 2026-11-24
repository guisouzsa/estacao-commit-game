<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Fundos de perfil: presets leves, salvos na conta, compatíveis com perfis antigos. */
class ProfileBackgroundTest extends TestCase
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

    public function test_there_are_many_background_options_and_the_old_frames_remain(): void
    {
        $this->assertGreaterThanOrEqual(15, count(config('profiles.backgrounds')));
        $this->assertArrayHasKey('terminal-green', config('profiles.backgrounds'));
        $this->assertArrayHasKey('nebula', config('profiles.backgrounds'));
        $this->assertGreaterThanOrEqual(14, count(config('profiles.frames'))); // nada foi removido
    }

    public function test_the_edit_page_offers_the_backgrounds(): void
    {
        $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->has('appearanceOptions.backgrounds')
            ->where('appearanceOptions.backgrounds.nebula', 'Nebulosa'));
    }

    public function test_the_choice_is_persisted_and_can_be_changed_or_cleared(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->profile_background); // perfis antigos: sem fundo, visual de sempre

        $this->appearance($user, ['profile_background' => 'terminal-green'])->assertSessionHasNoErrors();
        $this->assertSame('terminal-green', $user->fresh()->profile_background);

        $this->appearance($user, ['profile_background' => 'nebula'])->assertSessionHasNoErrors();
        $this->assertSame('nebula', $user->fresh()->profile_background);

        $this->appearance($user, ['profile_background' => null])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->profile_background);
    }

    public function test_unknown_backgrounds_are_rejected_in_portuguese(): void
    {
        $user = User::factory()->create(['profile_background' => 'grid']);

        $this->appearance($user, ['profile_background' => 'imagem-externa.png'])->assertSessionHasErrors('profile_background');
        $this->assertSame('O fundo selecionado não está disponível.', session('errors')->first('profile_background'));
        $this->assertSame('grid', $user->fresh()->profile_background);
    }

    public function test_the_background_reaches_the_public_profile_and_player_cards(): void
    {
        $user = User::factory()->create(['username' => 'fundoso', 'profile_background' => 'circuit-board']);

        $this->actingAs(User::factory()->create())->get(route('profile.show', 'fundoso'))->assertInertia(fn (Assert $page) => $page
            ->where('profile.profile_background', 'circuit-board'));
        $this->assertSame('circuit-board', \App\Support\PlayerPresenter::user($user)['profile_background']);
    }

    public function test_every_configured_background_has_a_drawing_in_the_front_end(): void
    {
        $source = file_get_contents(resource_path('js/lib/profileBackgrounds.ts'));

        foreach (array_keys(config('profiles.backgrounds')) as $key) {
            $this->assertMatchesRegularExpression('/(^|\s)\'?'.preg_quote($key, '/').'\'?:\s*\{/m', $source, "Falta o desenho do fundo {$key}");
        }
    }
}
