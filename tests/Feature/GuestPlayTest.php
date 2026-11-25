<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\GuestProfile;
use App\Models\User;
use App\Support\GuestIdentity;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Jogar sem conta: perfil temporário, cookie seguro e integração com as salas. */
class GuestPlayTest extends TestCase
{
    use RefreshDatabase;

    private function cookieName(): string
    {
        return config('players.guest_cookie');
    }

    /** @return array{0: GuestProfile, 1: string} perfil e token (o token só existe no cookie) */
    private function becomeGuest(string $name = 'Viajante'): array
    {
        // withCookie() é persistente no Laravel: começa sempre como um navegador novo.
        $this->defaultCookies = [];
        $this->app['auth']->forgetGuards();

        $response = $this->post(route('guest.store'), [
            'name' => $name,
            'avatar_style' => 'pixel',
            'avatar_seed' => 'abc123xyz',
        ]);
        $response->assertRedirect(route('dashboard'));

        $token = $response->getCookie($this->cookieName())->getValue();

        return [GuestProfile::where('display_name', $name)->firstOrFail(), $token];
    }

    private function asGuest(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withCookie($this->cookieName(), $token);
    }

    public function test_visitor_can_open_the_play_now_screen_without_logging_in(): void
    {
        $this->get(route('guest.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guest/Create')
                ->where('styles', config('players.selectable_avatar_styles')));
    }

    public function test_creating_the_profile_issues_a_secure_http_only_cookie_and_stores_only_the_token_hash(): void
    {
        $response = $this->post(route('guest.store'), [
            'name' => 'Viajante',
            'avatar_style' => 'robot',
            'avatar_seed' => 'seed12345',
        ]);

        $cookie = $response->getCookie($this->cookieName(), false);
        $this->assertTrue($cookie->isHttpOnly(), 'o JavaScript não pode ler o token');
        $this->assertSame('lax', $cookie->getSameSite());

        $token = $response->getCookie($this->cookieName())->getValue();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token, '256 bits aleatórios');

        $profile = GuestProfile::firstOrFail();
        $this->assertSame(hash('sha256', $token), $profile->token_hash);
        $this->assertNotSame($token, $profile->token_hash);
        $this->assertSame('viajante', $profile->name_key);
        $this->assertSame('robot', $profile->avatar_style);
        $this->assertTrue($profile->expires_at->gt(now()->addHours(5)));
        $this->assertDatabaseCount('users', 0); // não cria conta falsa
    }

    public function test_the_browser_flow_ends_in_a_full_navigation_to_the_dashboard_with_the_cookie(): void
    {
        // É assim que o navegador envia o formulário (visita do Inertia em XHR).
        $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz']);

        // 409 + X-Inertia-Location = o Inertia faz uma navegação completa, que envia o cookie novo.
        $response->assertStatus(409)->assertHeader('X-Inertia-Location', route('dashboard'));
        $token = $response->getCookie(config('players.guest_cookie'))->getValue();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

        // A navegação completa já entra como visitante e mostra as boas-vindas uma única vez.
        $this->defaultCookies = [];
        $this->defaultHeaders = []; // withHeaders() é persistente: agora é uma carga normal de página
        $this->asGuest($token)->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('auth.kind', 'guest')
            ->where('flash.toast.tone', 'success'));
    }

    public function test_every_visitor_gets_a_different_unpredictable_token(): void
    {
        [, $first] = $this->becomeGuest('Primeiro');
        [, $second] = $this->becomeGuest('Segundo');

        $this->assertNotSame($first, $second);
    }

    public function test_the_cookie_keeps_the_visitor_signed_in_across_requests(): void
    {
        [$profile, $token] = $this->becomeGuest();

        foreach ([1, 2] as $_) {
            $this->asGuest($token)->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('auth.kind', 'guest')
                    ->where('auth.user.id', $profile->id)
                    ->where('auth.user.username', 'Viajante')
                    ->where('auth.user.avatar_style', 'pixel')
                    ->missing('auth.user.email')
                    ->where('auth.channel', 'App.Models.GuestProfile.'.$profile->id));
        }
    }

    public function test_without_the_cookie_the_dashboard_requires_login(): void
    {
        // Rotas do JOGO levam à criação do perfil temporário, nunca ao login.
        $this->get(route('dashboard'))->assertRedirect(route('guest.create'));
    }

    public function test_a_forged_or_unknown_token_is_not_accepted(): void
    {
        $this->becomeGuest();

        $this->asGuest(str_repeat('a', 64))->get(route('dashboard'))->assertRedirect(route('guest.create'));
        $this->asGuest('nao-e-um-token')->get(route('dashboard'))->assertRedirect(route('guest.create'));
    }

    public function test_an_expired_profile_is_treated_as_a_signed_out_visitor(): void
    {
        [$profile, $token] = $this->becomeGuest();

        $this->travel(25)->hours();

        $this->asGuest($token)->get(route('dashboard'))->assertRedirect(route('guest.create'));
        $this->assertTrue($profile->fresh()->expires_at->isPast());
    }

    public function test_activity_extends_the_profile_but_not_on_every_request(): void
    {
        [$profile, $token] = $this->becomeGuest();
        $firstExpiry = $profile->expires_at;

        $this->travel(2)->minutes();
        $this->asGuest($token)->get(route('dashboard'))->assertOk();
        $this->assertEquals($firstExpiry->timestamp, $profile->fresh()->expires_at->timestamp, 'dentro de 5 min não grava');

        $this->travel(4)->hours();
        $this->asGuest($token)->get(route('dashboard'))->assertOk();
        $this->assertTrue($profile->fresh()->expires_at->gt($firstExpiry->copy()->addHours(3)), 'janela deslizante');
    }

    public function test_a_visitor_cannot_use_account_only_pages(): void
    {
        [, $token] = $this->becomeGuest();

        $this->asGuest($token)->get('/profile')->assertRedirect(route('login'));
        $this->asGuest($token)->get(route('rankings'))->assertRedirect(route('login'));
    }

    public function test_someone_who_is_already_playing_is_sent_back_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get(route('guest.create'))->assertRedirect(route('dashboard'));

        [, $token] = $this->becomeGuest();
        $this->asGuest($token)->get(route('guest.create'))->assertRedirect(route('dashboard'));
    }

    public function test_invalid_avatar_data_is_rejected(): void
    {
        $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'hack', 'avatar_seed' => 'abc123xyz'])
            ->assertSessionHasErrors('avatar_style');
        $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'pixel', 'avatar_seed' => '<script>'])
            ->assertSessionHasErrors('avatar_seed');

        $this->assertDatabaseCount('guest_profiles', 0);
    }

    public function test_creating_profiles_is_rate_limited(): void
    {
        foreach (range(1, 6) as $i) {
            $this->post(route('guest.store'), ['name' => "Jogador{$i}", 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz'])
                ->assertRedirect(route('dashboard'));
        }

        $this->post(route('guest.store'), ['name' => 'Jogador7', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz'])
            ->assertStatus(429);
    }

    public function test_name_availability_is_checked_while_typing(): void
    {
        $this->getJson(route('guest.name', ['name' => 'Livre123']))->assertOk()->assertJson(['available' => true]);

        $this->becomeGuest('Ocupado');
        $this->getJson(route('guest.name', ['name' => ' OCUPADO ']))
            ->assertOk()
            ->assertJson(['available' => false, 'message' => 'Esse nome já está sendo utilizado.']);
    }

    public function test_leaving_the_game_removes_the_profile_and_frees_the_name(): void
    {
        [$profile, $token] = $this->becomeGuest('Saindo');

        $response = $this->asGuest($token)->post(route('guest.leave'));

        $response->assertRedirect('/');
        $this->assertNull(GuestProfile::find($profile->id));
        $this->post(route('guest.store'), ['name' => 'Saindo', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_guest_cannot_delete_their_identity_during_an_active_match(): void
    {
        [$profile, $token] = $this->becomeGuest('EmPartida');
        $this->asGuest($token)->post(route('rooms.store'), ['bots' => 3]);
        $room = GameRoom::firstOrFail();
        $this->asGuest($token)->post(route('games.start', $room))->assertRedirect();

        $this->asGuest($token)->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])->post(route('guest.leave'))
            ->assertRedirect()
            ->assertSessionHas('toast.message', 'Seu perfil temporário está ligado a uma sala ou partida ativa. Saia ou encerre a sala antes de apagar o perfil.');

        $this->assertNotNull(GuestProfile::find($profile->id));
        $this->assertDatabaseHas('room_participants', [
            'room_id' => $room->id,
            'guest_id' => $profile->id,
        ]);
    }

    // ---- salas com visitantes ----------------------------------------------------

    public function test_a_visitor_can_create_a_room_and_is_its_host(): void
    {
        [$profile, $token] = $this->becomeGuest();

        $this->asGuest($token)->post(route('rooms.store'))->assertRedirect();

        $room = GameRoom::firstOrFail();
        $this->assertSame($profile->id, $room->host_guest_id);
        $this->assertNull($room->host_user_id);
        $this->assertDatabaseHas('room_participants', [
            'room_id' => $room->id, 'guest_id' => $profile->id, 'user_id' => null,
            'kind' => 'guest', 'is_host' => true, 'seat' => 1,
        ]);
        $this->assertNotNull($room->empty_since);
    }

    public function test_accounts_visitors_and_bots_share_one_room(): void
    {
        [$guestHost, $hostToken] = $this->becomeGuest('Anfitriao');
        $this->asGuest($hostToken)->post(route('rooms.store'), ['bots' => 1])->assertRedirect();
        $room = GameRoom::firstOrFail();
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        $user = User::factory()->create(['username' => 'conta_real']);
        $this->actingAs($user)->post(route('rooms.join', $code))->assertRedirect(route('rooms.show', $room));

        $this->app['auth']->forgetGuards();
        [, $otherToken] = $this->becomeGuest('Visitante2');
        $this->asGuest($otherToken)->post(route('rooms.join', $code))->assertRedirect(route('rooms.show', $room));

        $kinds = $room->participants()->pluck('kind')->sort()->values()->all();
        $this->assertSame(['bot', 'guest', 'guest', 'user'], $kinds);
        $this->assertSame(4, $room->participants()->count());
    }

    public function test_the_room_page_describes_every_kind_of_participant(): void
    {
        [$host, $token] = $this->becomeGuest('Anfitriao');
        $this->asGuest($token)->post(route('rooms.store'), ['bots' => 1]);
        $room = GameRoom::firstOrFail();

        $this->asGuest($token)->get(route('rooms.show', $room))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Rooms/Show')
            ->where('isHost', true)
            ->where('viewerKey', 'guest:'.$host->id)
            ->where('room.bot_slots', 1)
            ->where('room.participants.0.player.kind', 'guest')
            ->where('room.participants.0.is_host', true)
            ->where('room.participants.1.player.kind', 'bot')
            ->where('room.participants.1.player.is_bot', true)
            ->missing('room.participants.0.player.email'));
    }

    public function test_a_visitor_cannot_act_as_another_participant(): void
    {
        [, $hostToken] = $this->becomeGuest('Anfitriao');
        $this->asGuest($hostToken)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        $this->app['auth']->forgetGuards();
        [, $guestToken] = $this->becomeGuest('Amigo');
        $this->asGuest($guestToken)->post(route('rooms.join', $code));

        // Participante comum: 403. Os ids no corpo da requisição não valem como identidade.
        $this->asGuest($guestToken)
            ->delete(route('rooms.cancel', $room), ['host_guest_id' => $room->host_guest_id, 'user_id' => 'x'])
            ->assertForbidden();

        // Quem não participa: 404 (não revela a sala).
        $this->app['auth']->forgetGuards();
        [, $strangerToken] = $this->becomeGuest('Estranho');
        $this->asGuest($strangerToken)->delete(route('rooms.cancel', $room))->assertNotFound();
        $this->asGuest($strangerToken)->get(route('rooms.show', $room))->assertNotFound();

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_a_visitor_host_deletes_the_room_and_account_players_are_notified(): void
    {
        [, $hostToken] = $this->becomeGuest('Anfitriao');
        $this->asGuest($hostToken)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('rooms.join', $code));

        $this->app['auth']->forgetGuards();
        $this->asGuest($hostToken)->delete(route('rooms.cancel', $room))->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->assertCount(1, $user->unreadNotifications);
    }

    public function test_an_empty_room_hosted_by_a_visitor_expires_and_warns_the_visitor(): void
    {
        [$profile, $token] = $this->becomeGuest();
        $this->asGuest($token)->post(route('rooms.store'));
        $room = GameRoom::firstOrFail();

        $this->travel(5)->minutes();
        $this->artisan('rooms:expire-waiting')->assertSuccessful();

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->assertCount(1, $profile->fresh()->unreadNotifications);
    }

    // ---- limpeza dos perfis ------------------------------------------------------

    public function test_pruning_removes_only_abandoned_profiles(): void
    {
        [$active] = $this->becomeGuest('Ativo');
        [$abandoned] = $this->becomeGuest('Abandonado');
        $abandoned->forceFill(['expires_at' => now()->subHour()])->save();
        DatabaseNotification::create([
            'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => GuestProfile::class,
            'notifiable_id' => $abandoned->id, 'data' => [],
        ]);

        $this->artisan('guests:prune')->assertSuccessful();

        $this->assertNotNull(GuestProfile::find($active->id));
        $this->assertNull(GuestProfile::find($abandoned->id));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_pruning_never_removes_a_visitor_in_an_active_match(): void
    {
        [$playing, $token] = $this->becomeGuest('Jogando');
        $this->asGuest($token)->post(route('rooms.store'));
        GameRoom::firstOrFail()->forceFill(['status' => 'playing', 'started_at' => now()])->save();
        $playing->forceFill(['expires_at' => now()->subDay()])->save();

        $this->artisan('guests:prune')->assertSuccessful();

        $this->assertNotNull(GuestProfile::find($playing->id));
    }

    public function test_pruning_keeps_a_host_whose_room_has_other_humans(): void
    {
        [$host, $hostToken] = $this->becomeGuest('Anfitriao');
        $this->asGuest($hostToken)->post(route('rooms.store'));
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->actingAs(User::factory()->create())->post(route('rooms.join', $code));
        $host->forceFill(['expires_at' => now()->subDay()])->save();

        $this->artisan('guests:prune')->assertSuccessful();

        $this->assertNotNull(GuestProfile::find($host->id));
        $this->assertDatabaseCount('game_rooms', 1);
    }

    public function test_the_prune_command_is_scheduled(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'guests:prune') || $event->description === 'guests:prune');

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }

    // ---- unicidade ---------------------------------------------------------------

    public function test_the_database_itself_rejects_two_active_profiles_with_the_same_name(): void
    {
        $base = [
            'display_name' => 'Dupla', 'name_key' => 'dupla', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz',
            'last_seen_at' => now(), 'expires_at' => now()->addDay(),
        ];
        GuestProfile::create($base + ['token_hash' => str_repeat('a', 64)]);

        $this->expectException(QueryException::class);
        GuestProfile::create($base + ['token_hash' => str_repeat('b', 64)]);
    }

    public function test_creating_the_same_name_twice_through_the_service_is_rejected(): void
    {
        $identity = app(GuestIdentity::class);
        $identity->create('Guilherme', 'pixel', 'abc123xyz');

        foreach (['guilherme', 'GUILHERME', '  Guilherme  '] as $variant) {
            try {
                $identity->create($variant, 'pixel', 'abc123xyz');
                $this->fail("'$variant' deveria colidir");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('name', $e->errors());
            }
        }

        $this->assertSame(1, GuestProfile::count());
    }

    public function test_no_notification_is_sent_just_for_becoming_a_visitor(): void
    {
        Notification::fake();

        $this->becomeGuest();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_taken_name_gets_free_suggestions_but_is_never_changed_silently(): void
    {
        \App\Models\User::factory()->create(['username' => 'CodeNinja']);

        $taken = $this->getJson(route('guest.name', ['name' => 'codeninja']))->assertOk()->json();

        $this->assertFalse($taken['available']);
        $this->assertNotEmpty($taken['suggestions']);
        foreach ($taken['suggestions'] as $suggestion) {
            $this->assertNotSame('codeninja', strtolower($suggestion));
            $this->getJson(route('guest.name', ['name' => $suggestion]))->assertJsonPath('available', true);
        }

        $free = $this->getJson(route('guest.name', ['name' => 'NomeLivre']))->json();
        $this->assertTrue($free['available']);
        $this->assertSame([], $free['suggestions']);

        // Enviar o nome ocupado continua falhando: nada é trocado sem a pessoa escolher.
        $this->post(route('guest.store'), ['name' => 'codeninja', 'avatar_style' => 'pixel', 'avatar_seed' => 'abcdef12'])
            ->assertSessionHasErrors('name');
    }
}
