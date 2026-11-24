<?php

namespace Tests\Feature\Security;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Cabeçalhos, erros, configuração, XSS, upload de imagens e callbacks do Worker. */
class HardeningAndUploadsTest extends TestCase
{
    use RefreshDatabase;

    private function appearance(User $user, array $extra)
    {
        return $this->actingAs($user)->patch(route('profile.appearance.update'), array_merge([
            'avatar_position_x' => 50, 'avatar_position_y' => 50,
            'profile_frame' => array_key_first(config('profiles.frames')), 'primary_badge' => array_key_first(config('profiles.badges')),
        ], $extra));
    }

    // ---------------------------------------------------------------- cabeçalhos e erros

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
    }

    public function test_hsts_is_sent_over_https_only(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    public function test_errors_never_leak_internals_in_production(): void
    {
        config(['app.debug' => false]);
        \Illuminate\Support\Facades\Route::get('/__boom', fn () => throw new \RuntimeException('SEGREDO: senha do banco é abc123 em /var/www/html/.env'));

        $html = $this->get('/__boom')->assertStatus(500)->getContent();

        foreach (['SEGREDO', 'abc123', '/var/www', 'RuntimeException', 'Stack trace', 'vendor/laravel'] as $leak) {
            $this->assertStringNotContainsString($leak, $html);
        }
    }

    public function test_production_defaults_and_cors(): void
    {
        $this->assertFalse((bool) config('app.debug_default_unsafe', false));
        $this->assertSame('pt_BR', config('app.locale'));
        $response = $this->withHeaders(['Origin' => 'https://evil.example'])->get('/');
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'), 'nenhuma origem externa é liberada por CORS');
        $this->assertStringContainsString('APP_DEBUG=false', (string) file_get_contents(base_path('Dockerfile')));
    }

    public function test_secrets_are_never_exposed_to_the_front_end_build(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $example = file_get_contents(base_path('.env.example'));

        $this->assertStringNotContainsString('SECRET', strtoupper(implode("\n", array_filter(explode("\n", $dockerfile), fn ($l) => str_contains($l, 'ARG VITE_')))));
        foreach (explode("\n", $example) as $line) {
            if (preg_match('/^VITE_\w+=/', $line)) {
                $this->assertDoesNotMatchRegularExpression('/SECRET|PASSWORD|TOKEN/i', $line, "Segredo em variável VITE_: $line");
            }
        }
        $html = $this->get('/')->getContent();
        foreach (['REVERB_APP_SECRET', 'REALTIME_NOTIFY_SECRET', 'REALTIME_TOKEN_SECRET', 'DB_PASSWORD'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }
    }

    // ---------------------------------------------------------------- XSS e injeção

    public function test_user_text_is_escaped_and_dangerous_link_schemes_are_refused(): void
    {
        $user = User::factory()->create();
        $payload = '<script>alert(1)</script>"><img src=x onerror=alert(1)>';

        $this->appearance($user, ['bio' => $payload, 'links' => [
            ['platform' => 'website', 'label' => 'x', 'url' => 'javascript:alert(1)', 'is_public' => true],
        ]])->assertSessionHasErrors('links.0.url');
        $this->appearance($user, ['bio' => $payload, 'links' => [
            ['platform' => 'website', 'label' => '<b>x</b>', 'url' => 'https://example.com/a', 'is_public' => true],
        ]]);

        // O texto é guardado como texto; a página (HTML inicial) o entrega dentro do JSON escapado, nunca como tag viva.
        $html = $this->actingAs(User::factory()->create())->get(route('profile.show', $user->username))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('onerror=alert(1)>', $html);
    }

    public function test_search_is_safe_against_sql_injection_and_wildcards(): void
    {
        User::factory()->create(['username' => 'normal_user']);
        $viewer = User::factory()->create();

        foreach (["' OR '1'='1", "'; DROP TABLE users; --", '%', '_', '\\', str_repeat('a', 400)] as $q) {
            $this->actingAs($viewer)->getJson(route('players.search', ['q' => $q]))->assertStatus(200);
        }
        $this->assertSame(2, User::count());
        $this->actingAs($viewer)->getJson(route('players.search', ['q' => "' OR '1'='1"]))->assertJsonCount(0, 'players');
    }

    // ---------------------------------------------------------------- upload de imagens

    public function test_disguised_and_dangerous_files_are_refused_as_avatar_or_cover(): void
    {
        $user = User::factory()->create();
        $bad = [
            'php' => UploadedFile::fake()->createWithContent('avatar.png', '<?php system($_GET["c"]); ?>'),
            'svg' => UploadedFile::fake()->createWithContent('avatar.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'svg-as-png' => UploadedFile::fake()->createWithContent('avatar.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'html' => UploadedFile::fake()->createWithContent('avatar.jpg', '<html><script>alert(1)</script></html>'),
            'exe' => UploadedFile::fake()->createWithContent('avatar.png', "MZ\x90\x00\x03\x00\x00\x00"),
            'traversal-name' => UploadedFile::fake()->createWithContent('../../public/shell.php', '<?php echo 1;'),
        ];

        foreach ($bad as $name => $file) {
            $this->appearance($user, ['avatar' => $file]);
            $this->assertTrue(session('errors')?->has('avatar') ?? false, "aceitou como avatar: {$name}");
            $this->appearance($user, ['cover' => $file]);
            $this->assertTrue(session('errors')?->has('cover') ?? false, "aceitou como capa: {$name}");
        }
        $this->assertNull($user->fresh()->avatar);
        $this->assertNull($user->fresh()->profile_cover);
        $this->assertFileDoesNotExist(public_path('shell.php'));
    }

    public function test_oversized_images_are_refused(): void
    {
        $user = User::factory()->create();
        $huge = UploadedFile::fake()->create('grande.png', 20 * 1024, 'image/png'); // 20 MB

        $this->appearance($user, ['avatar' => $huge])->assertSessionHasErrors('avatar');
    }

    public function test_image_urls_are_per_user_public_images_and_cannot_be_used_to_read_other_files(): void
    {
        $user = User::factory()->create(['avatar' => 'data:image/png;base64,'.base64_encode('PNGDATA')]);

        $this->get('/imagens/avatar/'.$user->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'image/png');
        $this->get('/imagens/../../.env')->assertNotFound();
        $this->get('/imagens/avatar/..%2F..%2F.env')->assertNotFound();
        $this->get('/imagens/avatar/not-a-uuid')->assertNotFound();
        $this->get('/imagens/logo/'.$user->id)->assertNotFound(); // só "avatar" e "cover"

        // data URI com conteúdo perigoso (SVG/HTML) nunca é entregue como imagem
        $svg = User::factory()->create(['avatar' => 'data:image/svg+xml;base64,'.base64_encode('<svg><script>1</script></svg>')]);
        $this->get('/imagens/avatar/'.$svg->id)->assertNotFound();
        $html = User::factory()->create(['avatar' => 'data:text/html;base64,'.base64_encode('<script>1</script>')]);
        $this->get('/imagens/avatar/'.$html->id)->assertNotFound();
    }

    public function test_a_user_cannot_replace_another_users_image(): void
    {
        $victim = User::factory()->create(['avatar' => 'data:image/png;base64,'.base64_encode('PNGDATA')]);
        $attacker = User::factory()->create();

        // não existe rota que receba o id do dono: a imagem só muda na conta de quem está autenticado
        $this->appearance($attacker, ['user_id' => $victim->id, 'id' => $victim->id, 'remove_avatar' => true]);

        $this->assertNotNull($victim->fresh()->avatar);
    }

    // ---------------------------------------------------------------- callback do Worker

    public function test_a_callback_cannot_name_a_winner_from_another_room(): void
    {
        config(['realtime.callback_secret' => 'segredo-de-teste']);
        $a = User::factory()->count(4)->create()->values();
        $b = User::factory()->count(4)->create()->values();
        $roomA = GameRoom::create(['host_user_id' => $a[0]->id, 'status' => 'active', 'started_at' => now()]);
        $roomB = GameRoom::create(['host_user_id' => $b[0]->id, 'status' => 'active', 'started_at' => now()]);
        foreach ([[$roomA, $a], [$roomB, $b]] as [$room, $users]) {
            foreach ($users as $i => $u) {
                $room->participants()->create(['user_id' => $u->id, 'kind' => 'user', 'is_host' => $i === 0, 'seat' => $i + 1, 'joined_at' => now()]);
            }
            GameMatch::create(['room_id' => $room->id, 'status' => 'active', 'current_seat' => 1, 'turn_number' => 1, 'version' => 1, 'state' => ['engine' => 'cloudflare']]);
        }
        $foreignWinner = $roomB->participants()->where('seat', 1)->firstOrFail()->id;

        $body = json_encode(['type' => 'match_finished', 'room_id' => $roomA->id, 'winner_participant_id' => $foreignWinner, 'finished_at' => now()->toIso8601String()]);
        $ts = (string) time();
        $nonce = bin2hex(random_bytes(8));
        $sig = hash_hmac('sha256', "$ts.$nonce.".hash('sha256', $body), 'segredo-de-teste');

        $this->call('POST', '/realtime/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TIMESTAMP' => $ts, 'HTTP_X_NONCE' => $nonce, 'HTTP_X_SIGNATURE' => $sig,
        ], $body)->assertStatus(422);

        $this->assertSame('active', GameMatch::where('room_id', $roomA->id)->value('status'));
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('match_results')->count());
        foreach ($b as $u) {
            $this->assertSame(0, (int) $u->fresh()->matches_won);
        }
    }
}
