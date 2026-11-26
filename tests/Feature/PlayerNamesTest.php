<?php

namespace Tests\Feature;

use App\Models\GameRoom;
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
        $room = GameRoom::create(['host_guest_id' => $guest->id, 'status' => 'playing', 'started_at' => now()]);
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

    public function test_offensive_terms_in_many_languages_and_disguises_are_blocked(): void
    {
        $blocked = [
            // português e abreviações
            'buceta', 'Boceta', 'bct', 'B.C.T', 'c u', 'krl', 'Vsf', 'pqp', 'cacete', 'arrombado', 'putinha', 'piroca',
            // disfarces: separadores, números, símbolos, letras repetidas
            'b u c e t a', 'b.u.c.e.t.a', 'bucccceta', 'buuuceta x', 'Ca-ra-lho', 'p0rra', 'f0d4', 'merrrda',
            // inglês, espanhol, francês, italiano, alemão, outros
            'fuck you', 'shitty', 'b1tch', 'pornstar', 'dick', 'cunt', 'mierda', 'cabron', 'putain', 'salope',
            'Puttana', 'vaffanculo', 'scheisse', 'arschloch', 'kurwa', 'blyat',
            // ódio
            'Hitler88', 'nazi', 'kkk', 'white power',
        ];
        foreach ($blocked as $bad) {
            $this->assertNotNull($this->names->problem($bad), $bad);
            $this->assertTrue(
                $this->names->isBlocked(PlayerNames::key($bad)) || $this->names->problem($bad) !== 'Esse nome não pode ser usado. Escolha outro.',
                $bad,
            );
        }

        // Palavras e nomes comuns que contêm um termo curto por dentro continuam livres.
        $fine = [
            'cuidado', 'Cultura', 'therapist', 'Scunthorpe', 'Analista', 'Assis', 'Paulo Pinto', 'Rolando',
            'Dickson', 'Cocktail', 'Cucumber', 'Sussex', 'Niquel', 'Vergara', 'Computador', 'Nazir',
            'Grape Juice', 'Cumbuca', 'Picanha', 'Figueira',
        ];
        foreach ($fine as $ok) {
            $this->assertFalse($this->names->isBlocked(PlayerNames::key($ok)), $ok);
        }
    }

    public function test_only_the_latin_alphabet_and_a_few_symbols_are_accepted(): void
    {
        // Outros alfabetos, emojis e símbolos estranhos: não.
        foreach (['Сергей', 'Ελένη', '张伟', 'محمد', 'Ana😀', 'jo#ao', 'ana!', 'Ana&Bia', 'ana@bia', 'ana/bia', 'ana+1'] as $bad) {
            $this->assertNotNull(PlayerNames::formatProblem(PlayerNames::display($bad)), $bad);
        }

        // Começa com letra, sem símbolos seguidos ou no fim, sem 4 caracteres iguais seguidos.
        foreach (['1abc', '_abc', 'a__b', 'a. b', 'aaaab', 'zzzz top'] as $bad) {
            $this->assertNotNull(PlayerNames::formatProblem($bad), $bad);
        }

        // Acentos do português, números, espaço, ponto, hífen e sublinhado: sim.
        foreach (['João', 'Conceição Silva', 'ana.maria', 'dev_123', 'Zé-Pequeno', 'Ana Paula 2'] as $ok) {
            $this->assertNull(PlayerNames::formatProblem($ok), $ok);
        }

        // Um @ no começo é só tirado ("@ana" vira "ana").
        $this->assertSame('ana', PlayerNames::display('@ana'));
        $this->assertNull($this->names->problem('@Viajante'));
    }

    public function test_account_usernames_have_strict_rules(): void
    {
        foreach (['1gui', '_gui', 'gui_', 'gu__i', 'guiii-iiii', 'gui.souza', 'joão', 'gui#1'] as $bad) {
            $this->assertNotNull(PlayerNames::usernameProblem($bad), $bad);
        }
        foreach (['gui', 'gui_souza', 'gui-dev-2', 'Guilherme22'] as $ok) {
            $this->assertNull(PlayerNames::usernameProblem($ok), $ok);
        }

        foreach (['buceta_x', 'bct', '1player'] as $username) {
            $this->post('/register', [
                'username' => $username, 'email' => $username.'@example.com',
                'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123',
            ])->assertSessionHasErrors('username');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_renaming_through_the_appearance_editor_also_checks_the_rules(): void
    {
        $user = User::factory()->create(['username' => 'jogador']);
        $base = [
            'avatar_position_x' => 50, 'avatar_position_y' => 50,
            'profile_frame' => 'standard', 'primary_badge' => 'terminal', 'profile_title' => 'explorer',
        ];

        $this->actingAs($user)->patch('/profile/appearance', ['username' => 'buceta_x'] + $base)->assertSessionHasErrors('username');
        $this->actingAs($user)->patch('/profile/appearance', ['username' => 'jogador'] + $base)->assertSessionHasNoErrors();
        $this->assertSame('jogador', $user->fresh()->username);
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
