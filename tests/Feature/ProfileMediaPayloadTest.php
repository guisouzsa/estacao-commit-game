<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Foto e capa ficam no banco (data URI). A capa é grande: só pode ir no JSON da
 * tela de edição do perfil, não no de toda página.
 */
class ProfileMediaPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function userWithImages(): User
    {
        $image = 'data:image/webp;base64,'.str_repeat('QUJD', 20000); // ~80 KB de texto

        return User::factory()->create(['avatar' => $image, 'profile_cover' => $image]);
    }

    public function test_the_cover_is_not_sent_with_every_page(): void
    {
        $user = $this->userWithImages();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('auth.user.cover', null)
            ->missing('auth.user.profile_cover')
            ->where('auth.user.avatar', $user->avatar));
    }

    public function test_the_profile_editor_still_receives_the_cover(): void
    {
        $user = $this->userWithImages();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('auth.user.cover', $user->profile_cover)
            ->missing('auth.user.profile_cover'));
    }

    public function test_images_larger_than_a_varchar_255_are_stored_and_read_back_intact(): void
    {
        // Em produção isso depende da migration que troca as colunas para TEXT.
        $user = $this->userWithImages();

        $this->assertGreaterThan(255, strlen($user->fresh()->avatar));
        $this->assertSame($user->avatar, $user->fresh()->avatar);
        $this->assertSame($user->profile_cover, $user->fresh()->profile_cover);
    }

    public function test_the_banner_keeps_the_same_proportion_in_the_card_and_in_the_crop_editor(): void
    {
        // Altura fixa com object-fit: cover esmagava o banner numa faixa em telas largas (parecia
        // "ampliado"). O cartão e o editor de recorte precisam enquadrar a MESMA proporção.
        $card = file_get_contents(base_path('resources/js/Components/PlayerProfileCard.tsx'));
        $editor = file_get_contents(base_path('resources/js/Components/ProfileImageEditor.tsx'));
        $public = file_get_contents(base_path('resources/js/Pages/Profile/Public.tsx'));

        $this->assertStringContainsString('aspect-[3/1]', $card);
        $this->assertStringContainsString('aspect-[3/1]', $editor);
        $this->assertStringNotContainsString('relative h-40 overflow-hidden', $card, 'sem altura fixa no banner');
        $this->assertStringContainsString('max-w-5xl', $public, 'o cartão não se estica pela tela inteira');
    }

    public function test_the_text_column_migration_is_present_for_postgres(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_11_23_000200_store_profile_images_in_database.php'));

        $this->assertStringContainsString('ALTER COLUMN avatar TYPE text', $migration);
        $this->assertStringContainsString('ALTER COLUMN profile_cover TYPE text', $migration);

        // E o SQL manual do Render Free precisa conter o mesmo comando.
        $manual = file_get_contents(base_path('database/manual-sql/2026_11_23_expansao_postgres.sql'));
        $this->assertStringContainsString('ALTER TABLE users ALTER COLUMN avatar TYPE text, ALTER COLUMN profile_cover TYPE text', $manual);
        $this->assertStringContainsString('2026_11_23_000200_store_profile_images_in_database', $manual);
    }
}
