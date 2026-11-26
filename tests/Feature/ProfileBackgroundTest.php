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

    public function test_animated_backgrounds_unlock_by_tier_10_20_and_50_wins(): void
    {
        $tiers = collect(config('profiles.premium_background_tiers'));
        $this->assertSame([10, 20, 50], $tiers->pluck('min_wins')->all());
        $this->assertGreaterThanOrEqual(14, count(\App\Support\PremiumBackgrounds::all()));
        foreach (\App\Support\PremiumBackgrounds::all() as $key => $label) {
            $this->assertLessThanOrEqual(32, strlen($key), 'cabe na coluna profile_background');
        }

        $cases = [
            ['premium-galaxy', 9, false], ['premium-galaxy', 10, true],
            ['elite-meteor', 19, false], ['elite-meteor', 20, true],
            ['legend-blackhole', 49, false], ['legend-blackhole', 50, true],
        ];
        foreach ($cases as [$background, $wins, $allowed]) {
            $user = User::factory()->create(['matches_won' => $wins]);
            $response = $this->appearance($user, ['profile_background' => $background]);
            $allowed ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('profile_background');
            $this->assertSame($allowed ? $background : null, $user->fresh()->profile_background, "{$background} com {$wins}");
        }

        // Os fundos de espaço comuns continuam livres para todos.
        $this->appearance(User::factory()->create(['matches_won' => 0]), ['profile_background' => 'red-stars'])->assertSessionHasNoErrors();
    }

    public function test_the_edit_page_sends_the_tiers_and_progress(): void
    {
        $this->actingAs(User::factory()->create(['matches_won' => 4]))->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('appearanceOptions.wins', 4)
                ->where('appearanceOptions.premiumTiers.0.min_wins', 10)
                ->where('appearanceOptions.premiumTiers.2.name', 'Lendário')
                ->has('appearanceOptions.premiumTiers.1.backgrounds.elite-meteor'));
    }

    public function test_photo_and_cover_reject_animated_images(): void
    {
        $user = User::factory()->create();
        // GIF (tipo não aceito) e um PNG animado (APNG, com o bloco acTL).
        $gif = \Illuminate\Http\UploadedFile::fake()->createWithContent('a.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='));
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/U3QAAAAASUVORK5CYII=');
        $apng = substr($png, 0, 33)."\x00\x00\x00\x08acTL\x00\x00\x00\x02\x00\x00\x00\x00\x00\x00\x00\x00".substr($png, 33);

        $this->appearance($user, ['avatar' => $gif])->assertSessionHasErrors('avatar');
        $this->appearance($user, ['cover' => \Illuminate\Http\UploadedFile::fake()->createWithContent('a.png', $apng)])->assertSessionHasErrors('cover');
        $this->appearance($user, ['cover' => \Illuminate\Http\UploadedFile::fake()->createWithContent('b.png', $png)])->assertSessionHasNoErrors();
    }

    public function test_every_background_has_a_drawing_and_every_animated_one_an_animation(): void
    {
        $drawings = file_get_contents(base_path('resources/js/lib/profileBackgrounds.ts'));
        $css = file_get_contents(base_path('resources/css/app.css'));

        foreach (array_keys(config('profiles.backgrounds')) as $key) {
            $this->assertMatchesRegularExpression("/['\"]?".preg_quote($key, '/')."['\"]?\s*:/", $drawings, "desenho de {$key}");
        }
        foreach (array_keys(\App\Support\PremiumBackgrounds::all()) as $key) {
            $this->assertStringContainsString("'{$key}':", $drawings, "desenho de {$key}");
            $this->assertStringContainsString("[data-profile-background=\"{$key}\"]", $css, "animação de {$key}");
        }
    }
}
