<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

/**
 * Autenticação dos canais privados `rooms.{id}` em /broadcasting/auth.
 *
 * O broadcaster padrão dos testes é "null", que não autentica nada. Estes
 * testes usam o driver "reverb" com credenciais fictícias (nunca reais) e
 * registram novamente os canais, porque eles são registrados no driver ativo
 * no momento do boot.
 */
class BroadcastingAuthTest extends TestCase
{
    use RefreshDatabase;

    private const CSRF_TOKEN = 'csrf-token-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        Broadcast::purge('null');
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
    }

    public function test_participant_can_authenticate_on_the_private_channel_of_their_room(): void
    {
        [$host, $room] = $this->roomWithHost();

        $this->authorizeChannel($host, $room)
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_every_participant_of_the_room_can_authenticate(): void
    {
        [, $room] = $this->roomWithHost();
        $guest = User::factory()->create();
        $room->participants()->create(['user_id' => $guest->id, 'seat' => 2, 'joined_at' => now()]);

        $this->authorizeChannel($guest, $room)->assertOk();
    }

    public function test_user_outside_the_room_cannot_authenticate_on_its_channel(): void
    {
        [, $room] = $this->roomWithHost();

        $this->authorizeChannel(User::factory()->create(), $room)->assertForbidden();
    }

    public function test_participant_of_another_room_cannot_authenticate_on_this_channel(): void
    {
        [, $room] = $this->roomWithHost();
        [$otherHost] = $this->roomWithHost();

        $this->authorizeChannel($otherHost, $room)->assertForbidden();
    }

    public function test_guest_cannot_authenticate_on_a_private_channel(): void
    {
        [, $room] = $this->roomWithHost();

        $this->authorizeChannel(null, $room)->assertForbidden();
    }

    public function test_non_private_channel_names_are_not_authorized_for_the_room(): void
    {
        [$host, $room] = $this->roomWithHost();

        $this->actingAs($host)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-rooms.'.$room->id.'.other',
            ])
            ->assertForbidden();
    }

    /**
     * O Laravel registra /broadcasting/auth sem o middleware de CSRF
     * (BroadcastManager::routes). O Echo/pusher-js não envia token, então o
     * endpoint precisa funcionar assim, e a autorização continua por sessão.
     */
    public function test_auth_endpoint_works_without_csrf_header_while_csrf_protection_is_active(): void
    {
        [$host, $room] = $this->roomWithHost();
        $this->enableCsrfProtection();

        $this->authorizeChannel($host, $room)
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_csrf_exemption_does_not_weaken_room_authorization(): void
    {
        [, $room] = $this->roomWithHost();
        $this->enableCsrfProtection();

        $this->authorizeChannel(User::factory()->create(), $room)->assertForbidden();
        $this->authorizeChannel(null, $room)->assertForbidden();
    }

    public function test_control_csrf_protection_is_really_active_for_other_post_routes(): void
    {
        $this->enableCsrfProtection();

        $this->actingAs(User::factory()->create())
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->post(route('rooms.store'))
            ->assertStatus(419);
    }

    public function test_user_can_authenticate_only_on_their_own_notices_channel(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.$user->id])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.'.$other->id])
            ->assertForbidden();
    }

    /** @return array{0: User, 1: GameRoom} */
    private function roomWithHost(): array
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting']);
        $room->participants()->create(['user_id' => $host->id, 'seat' => 1, 'joined_at' => now()]);

        return [$host, $room];
    }

    private function authorizeChannel(?User $user, GameRoom $room, array $headers = [])
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request
            ->withSession(['_token' => self::CSRF_TOKEN])
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-rooms.'.$room->id,
            ], $headers);
    }

    /**
     * O Laravel só dispensa a verificação de CSRF em testes de console.
     * Fingir outro ambiente mantém o middleware real ativo.
     */
    private function enableCsrfProtection(): void
    {
        $this->app->instance('env', 'production');
    }
}
