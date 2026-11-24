<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\GuestProfile;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Páginas de erro com a identidade do jogo, sem vazar nada técnico. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    private const INERTIA = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];

    private function roomOf(User $host): GameRoom
    {
        $response = $this->actingAs($host)->post(route('rooms.store'));

        return GameRoom::findOrFail(basename($response->headers->get('Location')));
    }

    // ---- 404 ---------------------------------------------------------------------

    public function test_a_nonexistent_room_shows_the_friendly_room_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/salas/'.Str::uuid())
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('Error')
                ->where('status', 404)
                ->where('kind', 'room')
                ->where('canDashboard', true));
    }

    public function test_someone_elses_private_room_looks_exactly_like_a_nonexistent_one(): void
    {
        $room = $this->roomOf(User::factory()->create());
        $stranger = User::factory()->create();

        $real = $this->actingAs($stranger)->get(route('rooms.show', $room));
        $fake = $this->actingAs($stranger)->get('/salas/'.Str::uuid());

        $real->assertNotFound();
        $this->assertSame($real->status(), $fake->status());
        $this->assertSame(
            $real->viewData('page')['props']['kind'] ?? null,
            $fake->viewData('page')['props']['kind'] ?? null,
            'sala privada e sala inexistente precisam ser indistinguíveis',
        );
        $real->assertInertia(fn ($page) => $page->where('kind', 'room')->where('status', 404));
    }

    public function test_a_malformed_room_id_is_a_404_without_touching_the_rooms_table(): void
    {
        $user = User::factory()->create();
        DB::enableQueryLog();

        $this->actingAs($user)->get('/salas/isto-nao-e-um-uuid')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'room'));

        $touched = collect(DB::getQueryLog())->contains(fn ($query) => str_contains($query['query'], 'game_rooms'));
        $this->assertFalse($touched, 'um id inválido não deve gerar consulta a game_rooms');
    }

    public function test_a_mistyped_room_address_shows_the_room_page_even_for_visitors_without_a_session(): void
    {
        $this->get('/sala1223123')
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page
                ->component('Error')
                ->where('kind', 'room')
                ->where('canDashboard', false));
    }

    public function test_the_room_page_does_not_claim_to_know_why_the_room_is_missing(): void
    {
        // O texto é desenhado pelo React; conferimos o texto da própria página.
        $page = file_get_contents(base_path('resources/js/Pages/Error.tsx'));

        $this->assertStringContainsString("title: 'Ops! Essa sala não foi encontrada.'", $page);
        $this->assertStringContainsString('Talvez ela tenha sido excluída, expirado ou o endereço esteja incorreto.', $page);
        $this->assertStringNotContainsString('Essa sala foi excluída', $page, 'não afirma o motivo');
    }

    public function test_every_error_kind_the_server_can_send_has_a_message_in_the_page(): void
    {
        $page = file_get_contents(base_path('resources/js/Pages/Error.tsx'));
        $kinds = [
            'invite', 'room', 'profile', 'page', 'bad_request', 'unauthenticated',
            'forbidden', 'method', 'request_timeout', 'gone', 'conflict', 'session',
            'invalid_data', 'throttle', 'server', 'bad_gateway', 'maintenance',
            'gateway_timeout', 'http_version',
        ];

        foreach ($kinds as $kind) {
            $this->assertMatchesRegularExpression('/\b'.$kind.': \{/', $page, "falta texto para '$kind'");
        }
    }

    public function test_every_supported_http_status_is_mapped_to_its_own_kind(): void
    {
        config(['app.debug' => false]);
        $expected = [
            400 => 'bad_request', 401 => 'unauthenticated', 403 => 'forbidden',
            404 => 'page', 405 => 'method', 408 => 'request_timeout',
            409 => 'conflict', 410 => 'gone', 419 => 'session',
            422 => 'invalid_data', 429 => 'throttle', 500 => 'server',
            502 => 'bad_gateway', 503 => 'maintenance', 504 => 'gateway_timeout',
            505 => 'http_version',
        ];

        foreach ($expected as $status => $kind) {
            Route::get("/_teste/status-{$status}", fn () => abort($status));
            $this->get("/_teste/status-{$status}")
                ->assertStatus($status)
                ->assertInertia(fn ($page) => $page
                    ->component('Error')
                    ->where('status', $status)
                    ->where('kind', $kind));
        }
    }

    public function test_internal_errors_receive_a_support_code_in_the_page_and_header(): void
    {
        config(['app.debug' => false]);
        Route::get('/_teste/codigo-suporte', fn () => throw new \RuntimeException('falha interna'));

        $response = $this->get('/_teste/codigo-suporte')->assertStatus(500);
        $requestId = $response->headers->get('X-Request-Id');

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', (string) $requestId);
        $response->assertInertia(fn ($page) => $page->where('requestId', $requestId));
    }

    public function test_unknown_pages_and_profiles_have_their_own_messages(): void
    {
        $this->get('/nao-existe-mesmo')->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'page'));

        $this->get('/perfil/ninguem_assim')->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'profile'));
    }

    public function test_a_visitor_sees_the_dashboard_button_and_the_error_page_works_signed_in_as_guest(): void
    {
        $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz']);
        $token = GuestProfile::firstOrFail();
        $cookie = $this->app['encrypter']->encrypt(CookieValuePrefix::create(config('players.guest_cookie'), $this->app['encrypter']->getKey()).'x', false);

        // Sem cookie válido: visitante "deslogado" não vê a Dashboard.
        $this->withCookie(config('players.guest_cookie'), str_repeat('a', 64))->get('/salas/'.Str::uuid())
            ->assertRedirect(route('guest.create'));
        $this->assertNotNull($token);
        $this->assertNotEmpty($cookie);
    }

    // ---- outros status -----------------------------------------------------------

    public function test_403_422_and_410_pages_use_the_friendly_page(): void
    {
        Route::get('/_teste/proibido', fn () => abort(403));
        Route::get('/_teste/sumiu', fn () => abort(410));

        $this->get('/_teste/proibido')->assertForbidden()
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'forbidden'));
        $this->get('/_teste/sumiu')->assertStatus(410)
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'gone'));
    }

    public function test_an_expired_session_on_a_form_shows_the_session_page_with_csrf_active(): void
    {
        $this->app->instance('env', 'production'); // mantém o middleware de CSRF real

        $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz'])
            ->assertStatus(419)
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'session')->where('status', 419));
    }

    public function test_too_many_attempts_show_the_throttle_page(): void
    {
        foreach (range(1, 6) as $i) {
            $this->post(route('guest.store'), ['name' => "Jogador{$i}", 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz']);
            $this->defaultCookies = [];
            $this->app['auth']->forgetGuards();
        }

        $this->post(route('guest.store'), ['name' => 'Jogador7', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz'])
            ->assertStatus(429)
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'throttle'));
    }

    public function test_internal_errors_in_production_never_leak_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/_teste/boom', fn () => throw new \RuntimeException('segredo-interno-DB_PASSWORD=abc123 /var/www/html/app/X.php'));

        $response = $this->get('/_teste/boom');

        $response->assertStatus(500)
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'server')->where('status', 500));
        $response->assertDontSee('segredo-interno', false);
        $response->assertDontSee('DB_PASSWORD', false);
        $response->assertDontSee('/var/www/html', false);
        $response->assertDontSee('RuntimeException', false);
        $response->assertDontSee('Stack trace', false);
    }

    public function test_in_development_internal_errors_keep_the_debug_screen(): void
    {
        config(['app.debug' => true]);
        Route::get('/_teste/boom', fn () => throw new \RuntimeException('detalhe-para-o-desenvolvedor'));

        $this->get('/_teste/boom')->assertStatus(500)->assertSee('detalhe-para-o-desenvolvedor', false);
    }

    public function test_service_unavailable_has_its_own_page(): void
    {
        config(['app.debug' => false]);
        Route::get('/_teste/fora', fn () => abort(503));

        $this->get('/_teste/fora')->assertStatus(503)
            ->assertInertia(fn ($page) => $page->component('Error')->where('kind', 'maintenance'));
    }

    public function test_json_clients_still_get_json(): void
    {
        $this->getJson('/nao-existe-mesmo')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    // ---- ações do Inertia viram aviso amigável --------------------------------------

    private function assertErrorToast(TestResponse $response, string $message): void
    {
        $response->assertRedirect();
        $this->assertSame('error', session('toast.tone'));
        $this->assertSame($message, session('toast.message'));
        $this->assertNotEmpty(session('toast.id'));
    }

    public function test_a_failed_join_becomes_a_friendly_toast_not_an_error_page(): void
    {
        $host = User::factory()->create();
        $room = $this->roomOf($host);
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $room->invitations()->update(['revoked_at' => now()]);

        $this->assertErrorToast(
            $this->actingAs(User::factory()->create())->withHeaders(self::INERTIA)->post(route('rooms.join', $code)),
            'Este convite foi revogado.',
        );
    }

    public function test_joining_with_an_unknown_code_explains_it_without_technical_words(): void
    {
        $this->assertErrorToast(
            $this->actingAs(User::factory()->create())->withHeaders(self::INERTIA)->post(route('rooms.join', 'A2B3C')),
            'Não encontramos uma sala com esse código.',
        );
    }

    public function test_a_full_room_is_explained(): void
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'), ['bots' => 3]);
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        $this->assertErrorToast(
            $this->actingAs(User::factory()->create())->withHeaders(self::INERTIA)->post(route('rooms.join', $code)),
            'A sala está cheia.',
        );
    }

    public function test_lacking_permission_on_an_action_uses_the_default_friendly_message(): void
    {
        $host = User::factory()->create();
        $room = $this->roomOf($host);
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $guest = User::factory()->create();
        $this->actingAs($guest)->post(route('rooms.join', $code));

        $this->assertErrorToast(
            $this->actingAs($guest)->withHeaders(self::INERTIA)->delete(route('rooms.cancel', $room)),
            'Você não tem permissão para realizar essa ação.',
        );
    }

    public function test_a_missing_record_on_an_action_never_shows_the_framework_message(): void
    {
        $response = $this->actingAs(User::factory()->create())->withHeaders(self::INERTIA)
            ->delete(route('rooms.cancel', Str::uuid()->toString()));

        $response->assertRedirect();
        $message = session('toast.message');
        $this->assertSame('Não encontramos o que você procurava. Pode ter sido removido.', $message);
        $this->assertStringNotContainsString('query', strtolower($message));
        $this->assertStringNotContainsString('App\\Models', $message);
    }

    public function test_wrong_method_errors_never_leak_framework_messages(): void
    {
        $this->actingAs(\App\Models\User::factory()->create());

        $response = $this->get('/salas')->assertStatus(405);

        $html = $response->getContent();
        $this->assertStringNotContainsString('not supported for route', $html);
        $this->assertStringNotContainsString('Supported methods', $html);
    }
}
