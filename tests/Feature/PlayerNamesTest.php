<?php

namespace Tests\Feature;

use App\Models\GuestProfile;
use App\Models\User;
use App\Support\GuestIdentity;
use App\Support\PlayerNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nomes únicos entre contas, visitantes e bots, com normalização e reservados. */
class PlayerNamesTest extends TestCase
{
    use RefreshDatabase;

    private PlayerNames $names;

    protected function setUp(): void
    {
        parent::setUp();
        $this->names = app(PlayerNames::class);
    }

    public function test_equivalent_spellings_share_the_same_key(): void
    {
        foreach (['Guilherme', 'guilherme', 'GUILHERME', ' Guilherme ', "\tGuilherme\n"] as $variant) {
            $this->assertSame('guilherme', PlayerNames::key($variant), json_encode($variant));
        }
        $this->assertSame('joao silva', PlayerNames::key('João   Silva'), 'acentos e espaços repetidos');
        $this->assertSame('João Silva', PlayerNames::display('  João    Silva '));
    }

    public function test_a_free_name_is_accepted(): void
    {
        $this->assertNull($this->names->problem('Viajante 42'));
        $this->assertNull($this->names->problem('  Ana.Maria  '));
    }

    public function test_format_and_length_are_enforced(): void
    {
        $this->assertNotNull($this->names->problem('ab'), 'curto demais');
        $this->assertNotNull($this->names->problem(str_repeat('a', 25)), 'longo demais');
        $this->assertNotNull($this->names->problem('<b>oi</b>'), 'caracteres inválidos');
        $this->assertNotNull($this->names->problem('-abc'), 'começa com símbolo');
        $this->assertNotNull($this->names->problem('abc_'), 'termina com símbolo');
        $this->assertNotNull($this->names->problem('   '), 'só espaços');
    }

    public function test_a_name_in_use_by_an_account_is_rejected_ignoring_case_and_spaces(): void
    {
        User::factory()->create(['username' => 'gui']);

        foreach (['gui', 'GUI', ' Gui '] as $variant) {
            $this->assertSame('Esse nome já está sendo utilizado.', $this->names->problem($variant), $variant);
        }
    }

    public function test_a_name_in_use_by_an_active_guest_is_rejected(): void
    {
        app(GuestIdentity::class)->create('Viajante', 'pixel', 'abc123xyz');

        $this->assertSame('Esse nome já está sendo utilizado.', $this->names->problem(' VIAJANTE '));
    }

    public function test_an_expired_guest_releases_its_name(): void
    {
        ['profile' => $guest] = app(GuestIdentity::class)->create('Viajante', 'pixel', 'abc123xyz');
        $guest->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertNull($this->names->problem('Viajante'));

        // E o novo perfil realmente consegue o nome (o antigo expirado é descartado).
        app(GuestIdentity::class)->create('Viajante', 'robot', 'zzz999zzz');
        $this->assertSame(1, GuestProfile::where('name_key', 'viajante')->count());
    }

    public function test_an_expired_guest_still_in_an_active_match_keeps_its_name(): void
    {
        ['profile' => $guest] = app(GuestIdentity::class)->create('Jogando', 'pixel', 'abc123xyz');
        $room = \App\Models\GameRoom::create(['host_guest_id' => $guest->id, 'status' => 'playing', 'started_at' => now()]);
        $room->participants()->create(['guest_id' => $guest->id, 'kind' => 'guest', 'seat' => 1, 'joined_at' => now()]);
        $guest->forceFill(['expires_at' => now()->subHour()])->save();

        $this->assertSame('Esse nome já está sendo utilizado.', $this->names->problem('Jogando'));
    }

    public function test_reserved_names_are_blocked_in_any_spelling(): void
    {
        foreach (['admin', 'ADMIN', ' Admin ', 'Moderador', 'Estação Commit', 'estacao_commit', 'Suporte'] as $reserved) {
            $this->assertSame('Esse nome não pode ser usado. Escolha outro.', $this->names->problem($reserved), $reserved);
        }
    }

    public function test_bot_names_cannot_be_taken_by_players(): void
    {
        foreach (config('bots.catalog') as $bot) {
            $this->assertNotNull($this->names->problem($bot['name']), $bot['name']);
        }
        $this->assertNotNull($this->names->problem('commit zilla'), 'variação com espaço');
        $this->assertNotNull($this->names->problem('COMMITZILLA'));
    }

    public function test_offensive_names_are_blocked_without_blocking_innocent_words(): void
    {
        foreach (['m3rda', 'Merda Seca', 'xcaralhox', 'F0DA', 'p u t a'] as $bad) {
            $this->assertNotNull($this->names->problem($bad), $bad);
        }

        foreach (['computador', 'Disputa', 'Reputação', 'Executor', 'Gamer Cultura'] as $fine) {
            $this->assertNull($this->names->problem($fine), $fine);
        }
    }

    public function test_account_registration_follows_the_same_rules(): void
    {
        app(GuestIdentity::class)->create('Viajante', 'pixel', 'abc123xyz');

        foreach (['viajante', 'admin', 'm3rda_x'] as $username) {
            $this->post('/register', [
                'username' => $username, 'email' => $username.'@example.com',
                'password' => 'password', 'password_confirmation' => 'password',
            ])->assertSessionHasErrors('username');
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_renaming_an_account_checks_the_rules_only_when_the_name_changes(): void
    {
        // Conta antiga com nome hoje reservado: continua podendo salvar o perfil sem trocar o nome.
        $user = User::factory()->create(['username' => 'moderador']);

        $this->actingAs($user)->patch('/profile', ['username' => 'moderador', 'email' => $user->email])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->patch('/profile', ['username' => 'admin', 'email' => $user->email])
            ->assertSessionHasErrors('username');
    }
}
