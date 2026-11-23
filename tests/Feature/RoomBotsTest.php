<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\GuestProfile;
use App\Models\User;
use App\Services\RoomExpirationService;
use App\Support\Bots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bots escolhidos na criação da sala: ocupam vagas, não são usuários. */
class RoomBotsTest extends TestCase
{
    use RefreshDatabase;

    private function createRoom(User $host, ?int $bots = null): GameRoom
    {
        $response = $this->actingAs($host)->post(route('rooms.store'), $bots === null ? [] : ['bots' => $bots]);
        $response->assertRedirect();

        // A sala criada é a da URL de redirecionamento (várias salas podem nascer no mesmo segundo).
        return GameRoom::findOrFail(basename($response->headers->get('Location')));
    }

    public function test_zero_bots_works_as_before(): void
    {
        $room = $this->createRoom(User::factory()->create());

        $this->assertSame(0, $room->bot_slots);
        $this->assertSame(1, $room->participants()->count());
        $this->assertSame(4, $room->humanSeats());
    }

    public function test_one_two_and_three_bots_are_accepted_and_fill_the_last_seats(): void
    {
        foreach ([1 => [1, 4], 2 => [1, 3, 4], 3 => [1, 2, 3, 4]] as $bots => $expectedSeats) {
            $room = $this->createRoom(User::factory()->create(), $bots);

            $this->assertSame($bots, $room->bot_slots);
            $this->assertSame($expectedSeats, $room->participants()->pluck('seat')->all(), "{$bots} bots");
            $this->assertSame($bots, $room->participants()->where('kind', 'bot')->count());
            $this->assertSame(1, $room->participants()->where('kind', '!=', 'bot')->count(), 'sempre um humano');
        }
    }

    public function test_four_bots_or_invalid_values_are_rejected_and_no_room_is_created(): void
    {
        $host = User::factory()->create();

        foreach ([4, 5, -1, 'muitos'] as $invalid) {
            $this->actingAs($host)->post(route('rooms.store'), ['bots' => $invalid])->assertSessionHasErrors('bots');
        }

        $this->assertDatabaseCount('game_rooms', 0);
        $this->assertDatabaseCount('room_participants', 0);
    }

    public function test_a_room_never_exceeds_four_participants(): void
    {
        $room = $this->createRoom(User::factory()->create(), 2); // 1 humano + 2 bots = 3 de 4
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        $this->actingAs(User::factory()->create())->post(route('rooms.join', $code))->assertRedirect(); // 4 de 4
        $this->actingAs(User::factory()->create())->post(route('rooms.join', $code))->assertStatus(409);

        $this->assertSame(4, $room->participants()->count());
        $this->assertSame(2, $room->participants()->where('kind', '!=', 'bot')->count());
    }

    public function test_a_room_full_of_bots_has_no_seat_for_other_humans(): void
    {
        $this->createRoom(User::factory()->create(), 3);
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));

        $this->actingAs(User::factory()->create())->post(route('rooms.join', $code))->assertStatus(409);
    }

    public function test_bots_are_not_users_and_have_no_accounts(): void
    {
        $usersBefore = User::count();
        $room = $this->createRoom(User::factory()->create(), 3);

        $this->assertSame($usersBefore + 1, User::count(), 'só o anfitrião existe em users');
        foreach ($room->participants()->where('kind', 'bot')->get() as $bot) {
            $this->assertNull($bot->user_id);
            $this->assertNull($bot->guest_id);
            $this->assertNotNull(Bots::find($bot->bot_key), 'vem do catálogo');
            $this->assertFalse($bot->is_host);
        }
    }

    public function test_bot_names_are_distinct_inside_a_room(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $room = $this->createRoom(User::factory()->create(), 3);
            $keys = $room->participants()->where('kind', 'bot')->pluck('bot_key');

            $this->assertCount(3, $keys->unique(), 'nomes de bots repetidos na mesma sala');
        }
    }

    public function test_the_catalog_has_unique_fun_names_and_valid_styles(): void
    {
        $catalog = collect(config('bots.catalog'));

        $this->assertGreaterThanOrEqual(20, $catalog->count());
        $this->assertCount($catalog->count(), $catalog->pluck('key')->unique());
        $this->assertCount($catalog->count(), $catalog->pluck('name')->unique());
        $this->assertEmpty($catalog->pluck('style')->unique()->diff(config('players.avatar_styles'))->all());
        $this->assertContains('Bugado.exe', $catalog->pluck('name')->all());
        $this->assertContains('CommitZilla', $catalog->pluck('name')->all());
    }

    public function test_the_room_page_marks_bots_and_humans(): void
    {
        $host = User::factory()->create(['username' => 'humano']);
        $room = $this->createRoom($host, 2);

        $this->actingAs($host)->get(route('rooms.show', $room))->assertOk()->assertInertia(fn ($page) => $page
            ->where('room.bot_slots', 2)
            ->where('room.human_seats', 2)
            ->where('room.participants.0.player.kind', 'user')
            ->where('room.participants.0.player.is_bot', false)
            ->where('room.participants.1.player.is_bot', true)
            ->where('room.participants.2.player.is_bot', true)
            ->has('room.participants.1.player.avatar_seed')
            ->missing('room.participants.1.player.email'));
    }

    public function test_bots_do_not_count_as_company_for_the_empty_room_rule(): void
    {
        $room = $this->createRoom(User::factory()->create(), 3);

        $this->travel(5)->minutes();

        $this->assertSame(1, app(RoomExpirationService::class)->expireDueRooms(), 'só bots + anfitrião = sala vazia');
        $this->assertDatabaseMissing('game_rooms', ['id' => $room->id]);
        // Os bots saem junto com a sala: nenhum registro órfão.
        $this->assertDatabaseMissing('room_participants', ['room_id' => $room->id]);
    }

    public function test_a_human_joining_cancels_the_expiration_even_with_bots_in_the_room(): void
    {
        $room = $this->createRoom(User::factory()->create(), 1);
        $code = basename(parse_url(session('invite_url'), PHP_URL_PATH));
        $this->actingAs(User::factory()->create())->post(route('rooms.join', $code))->assertRedirect();

        $this->assertNull($room->fresh()->empty_since);
        $this->travel(30)->minutes();
        $this->assertSame(0, app(RoomExpirationService::class)->expireDueRooms());
    }

    public function test_bots_cannot_authenticate_or_call_routes(): void
    {
        // Bots não têm credencial: nenhum guard os reconhece. Só existe o participante.
        $room = $this->createRoom(User::factory()->create(), 1);
        $bot = $room->participants()->where('kind', 'bot')->firstOrFail();

        $this->assertNull($bot->user);
        $this->assertNull($bot->guest);
        // Nenhum guard nem provider de autenticação aponta para bots.
        $this->assertArrayNotHasKey('bot', config('auth.guards'));
        $models = collect(config('auth.providers'))->pluck('model')->all();
        $this->assertSame([User::class, GuestProfile::class], array_values($models));
    }

    public function test_the_dashboard_tells_how_many_bots_a_room_has(): void
    {
        $host = User::factory()->create();
        $this->createRoom($host, 2);

        $this->actingAs($host)->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('rooms.0.bot_slots', 2)
            ->where('rooms.0.participants_count', 3));
    }
}
