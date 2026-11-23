<?php

namespace Tests\Feature;

use App\Events\RoomStateChanged;
use App\Events\UserNoticesChanged;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\GameRoom;
use App\Models\User;
use App\Notifications\RoomDeleted;
use App\Services\RoomExpirationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Exclusão automática (sala vazia por 5 minutos), exclusão manual e avisos.
 */
class RoomLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const AUTO_MESSAGE = 'A sala foi excluída automaticamente porque permaneceu vazia por 5 minutos.';

    // ---- exclusão automática -------------------------------------------------

    public function test_room_empty_for_less_than_five_minutes_is_kept(): void
    {
        [, $room] = $this->newRoom();

        $this->travel(299)->seconds();

        $this->assertSame(0, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_room_empty_for_five_minutes_is_deleted_with_participants_and_invitations(): void
    {
        [$host, $room] = $this->newRoom();

        $this->travel(5)->minutes();

        $this->assertSame(1, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('room_participants', ['room_id' => $room->id]);
        $this->assertDatabaseMissing('room_invitations', ['room_id' => $room->id]);
        $this->assertDatabaseHas('users', ['id' => $host->id]);
    }

    public function test_room_that_receives_a_player_before_the_deadline_is_not_deleted(): void
    {
        [, $room, $token] = $this->newRoom();
        $this->join(User::factory()->create(), $token);

        $this->assertNull($room->fresh()->empty_since);

        $this->travel(30)->minutes();

        $this->assertSame(0, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_room_that_becomes_empty_again_starts_a_new_five_minute_count(): void
    {
        [, $room, $token] = $this->newRoom();
        $guest = User::factory()->create();
        $this->join($guest, $token);
        $this->assertNull($room->fresh()->empty_since);

        $this->travel(10)->minutes();
        $room->participants()->where('user_id', $guest->id)->delete();
        app(RoomExpirationService::class)->syncEmptiness($room->fresh());
        $this->assertNotNull($room->fresh()->empty_since);

        $this->travel(4)->minutes();
        $this->assertSame(0, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);

        $this->travel(1)->minutes();
        $this->assertSame(1, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
    }

    public function test_room_with_an_active_match_is_never_deleted_by_the_empty_room_rule(): void
    {
        [$host, $room] = $this->newRoom();
        $room->forceFill(['status' => 'playing', 'started_at' => now()])->save();

        $this->travel(60)->minutes();

        $this->assertSame(0, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertFalse(app(RoomExpirationService::class)->expireIfEligible($room->fresh()));
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
        $this->assertCount(0, $host->unreadNotifications);
    }

    public function test_rooms_already_marked_expired_by_the_old_rule_are_left_untouched(): void
    {
        [, $room] = $this->newRoom();
        $room->forceFill(['status' => 'expired', 'empty_since' => null])->save();

        $this->travel(60)->minutes();

        $this->assertSame(0, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'expired']);
    }

    public function test_an_already_deleted_room_is_not_processed_again(): void
    {
        [$host, $room] = $this->newRoom();
        $stale = GameRoom::findOrFail($room->id);
        $this->travel(5)->minutes();
        $service = app(RoomExpirationService::class);

        $this->assertTrue($service->expireIfEligible($room));
        $this->assertFalse($service->expireIfEligible($stale));
        $this->assertSame(0, $service->expireDueRooms());
        $this->assertCount(1, $host->unreadNotifications()->get(), 'um único aviso, mesmo rodando várias vezes');
    }

    public function test_a_late_scheduler_run_with_a_stale_model_does_not_delete_a_room_that_got_a_player(): void
    {
        [, $room, $token] = $this->newRoom();
        $stale = GameRoom::findOrFail($room->id); // lido antes de o jogador entrar

        // O jogador entra no último instante do prazo (4:59)...
        $this->travel(299)->seconds();
        $this->join(User::factory()->create(), $token);

        // ...e o scheduler, atrasado, só roda depois dos 5 minutos, com o modelo antigo.
        $this->travel(10)->minutes();

        $this->assertFalse(app(RoomExpirationService::class)->expireIfEligible($stale));
        $this->assertSame(0, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
        $this->assertSame(2, $room->participants()->count());
    }

    public function test_joining_an_overdue_room_deletes_it_and_rejects_the_player(): void
    {
        [$host, $room, $token] = $this->newRoom();
        $this->travel(5)->minutes();
        $guest = User::factory()->create();

        $this->actingAs($guest)->post(route('rooms.join', $token))->assertGone();

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('room_participants', ['user_id' => $guest->id]);
        $this->assertCount(1, $host->unreadNotifications);
    }

    public function test_a_deleted_room_does_not_accept_new_players(): void
    {
        [$host, $room, $token] = $this->newRoom();
        $this->actingAs($host)->delete(route('rooms.cancel', $room));

        $this->actingAs(User::factory()->create())->post(route('rooms.join', $token))->assertNotFound();
    }

    // ---- exclusão manual e automática x concorrência -------------------------

    public function test_scheduler_after_a_manual_deletion_finds_nothing_to_do(): void
    {
        [$host, $room] = $this->newRoom();
        $stale = GameRoom::findOrFail($room->id);
        $this->travel(5)->minutes();

        $this->actingAs($host)->delete(route('rooms.cancel', $room))->assertRedirect(route('dashboard'));

        $this->assertFalse(app(RoomExpirationService::class)->expireIfEligible($stale));
        $this->assertSame(0, app(RoomExpirationService::class)->expireDueRooms());
        $this->assertCount(0, $host->unreadNotifications, 'exclusão manual não gera aviso de exclusão automática');
    }

    public function test_manual_deletion_after_the_scheduler_deleted_the_room_is_a_clean_404(): void
    {
        [$host, $room] = $this->newRoom();
        $this->travel(5)->minutes();
        app(RoomExpirationService::class)->expireDueRooms();

        $this->actingAs($host)->delete(route('rooms.cancel', $room->id))->assertNotFound();
    }

    // ---- aviso ao criador ------------------------------------------------------

    public function test_host_is_warned_in_real_time_and_with_a_persisted_notice_when_the_room_is_auto_deleted(): void
    {
        Event::fake([RoomStateChanged::class, UserNoticesChanged::class]);
        [$host, $room] = $this->newRoom();
        $this->travel(5)->minutes();

        $this->artisan('rooms:expire-waiting')->assertSuccessful();

        Event::assertDispatched(UserNoticesChanged::class, fn ($e) => $e->userId === $host->id);
        Event::assertDispatched(RoomStateChanged::class, fn ($e) => $e->roomId === $room->id && $e->change === 'deleted');
        $notice = $host->unreadNotifications()->firstOrFail();
        $this->assertSame(self::AUTO_MESSAGE, $notice->data['message']);
        $this->assertSame(RoomDeleted::EMPTY_TIMEOUT, $notice->data['reason']);
    }

    public function test_the_persisted_notice_reaches_an_offline_host_on_the_dashboard_only_once(): void
    {
        [$host] = $this->newRoom();
        $this->travel(5)->minutes();
        app(RoomExpirationService::class)->expireDueRooms();

        $this->actingAs($host)->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('notices', 1)
            ->where('notices.0.message', self::AUTO_MESSAGE)
            ->where('notices.0.reason', 'empty_timeout')
            ->has('rooms', 0));

        $this->actingAs($host)->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('notices', 0));
    }

    public function test_polling_reloads_of_other_props_do_not_consume_the_notice(): void
    {
        [$host] = $this->newRoom();
        $this->travel(5)->minutes();
        app(RoomExpirationService::class)->expireDueRooms();

        $partial = $this->actingAs($host)->get(route('dashboard'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'rooms',
        ]);
        $partial->assertOk();

        $this->assertCount(1, $host->unreadNotifications()->get());
    }

    public function test_opening_the_dashboard_also_deletes_overdue_rooms_when_the_scheduler_is_down(): void
    {
        [$host, $room] = $this->newRoom();
        $this->travel(6)->minutes();

        $this->actingAs($host)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->has('rooms', 0)
            ->has('notices', 1));

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
    }

    // ---- exclusão manual: permissões ---------------------------------------------

    public function test_host_can_delete_their_room_and_the_dashboard_reflects_it(): void
    {
        [$host, $room] = $this->newRoom();

        $this->actingAs($host)->delete(route('rooms.cancel', $room))->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        $this->actingAs($host)->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('rooms', 0));
    }

    public function test_a_regular_participant_cannot_delete_the_room(): void
    {
        [, $room, $token] = $this->newRoom();
        $guest = User::factory()->create();
        $this->join($guest, $token);

        $this->actingAs($guest)->delete(route('rooms.cancel', $room))->assertForbidden();

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_an_outsider_cannot_delete_the_room(): void
    {
        [, $room] = $this->newRoom();

        // 404 (e não 403): quem não participa não descobre que a sala existe.
        $this->actingAs(User::factory()->create())->delete(route('rooms.cancel', $room))->assertNotFound();

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_a_guest_cannot_delete_a_room(): void
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting', 'empty_since' => now()]);
        $room->participants()->create(['user_id' => $host->id, 'seat' => 1, 'joined_at' => now()]);

        // Sem actingAs: requisição de um visitante não autenticado.
        $this->delete(route('rooms.cancel', $room))->assertRedirect(route('login'));

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_deletion_is_protected_by_csrf(): void
    {
        [$host, $room] = $this->newRoom();
        $this->app->instance('env', 'production'); // mantém o middleware real ativo

        $this->actingAs($host)
            ->withSession(['_token' => 'token-de-teste'])
            ->delete(route('rooms.cancel', $room))
            ->assertStatus(419);

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_a_room_with_a_started_match_cannot_be_deleted(): void
    {
        [$host, $room] = $this->newRoom();
        $room->forceFill(['status' => 'playing', 'started_at' => now()])->save();

        $this->actingAs($host)->delete(route('rooms.cancel', $room))->assertStatus(409);

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id]);
    }

    public function test_players_in_the_room_are_notified_when_the_host_deletes_it(): void
    {
        Event::fake([RoomStateChanged::class, UserNoticesChanged::class]);
        [$host, $room, $token] = $this->newRoom();
        $guest = User::factory()->create();
        $this->join($guest, $token);

        $this->actingAs($host)->delete(route('rooms.cancel', $room))->assertRedirect(route('dashboard'));

        Event::assertDispatched(RoomStateChanged::class, fn ($e) => $e->roomId === $room->id && $e->change === 'deleted');
        Event::assertDispatched(UserNoticesChanged::class, fn ($e) => $e->userId === $guest->id);
        Event::assertDispatched(UserNoticesChanged::class, fn ($e) => $e->userId === $host->id);

        $notice = $guest->unreadNotifications()->firstOrFail();
        $this->assertSame(RoomDeleted::HOST_DELETED, $notice->data['reason']);
        $this->assertStringNotContainsString('automaticamente', $notice->data['message']);
        $this->assertCount(0, $host->unreadNotifications);
    }

    public function test_dashboard_tells_who_can_delete_each_room(): void
    {
        [$host, $room, $token] = $this->newRoom();
        $guest = User::factory()->create();
        $this->join($guest, $token);

        $this->actingAs($host)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('rooms.0.id', $room->id)
            ->where('rooms.0.can_delete', true)
            ->where('rooms.0.expires_at', null));
        $this->actingAs($guest)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('rooms.0.can_delete', false));
    }

    public function test_room_page_exposes_the_deadline_only_while_the_room_is_empty(): void
    {
        [$host, $room, $token] = $this->newRoom();

        $this->actingAs($host)->get(route('rooms.show', $room))->assertInertia(fn ($page) => $page
            ->where('expiresAt', $room->fresh()->empty_since->copy()->addMinutes(5)->toIso8601String()));

        $this->join(User::factory()->create(), $token);

        $this->actingAs($host)->get(route('rooms.show', $room))->assertInertia(fn ($page) => $page
            ->where('expiresAt', null));
    }

    /** @return array{0: User, 1: GameRoom, 2: string} */
    private function newRoom(): array
    {
        $host = User::factory()->create();
        $this->actingAs($host)->post(route('rooms.store'));
        $room = GameRoom::latest()->firstOrFail();
        $token = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        return [$host, $room, $token];
    }

    private function join(User $user, string $token): void
    {
        $this->actingAs($user)->post(route('rooms.join', $token))->assertRedirect();
    }
}
