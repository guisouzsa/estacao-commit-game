<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Toda mensagem de validação que a pessoa vê precisa estar em português do Brasil. */
class PortugueseValidationTest extends TestCase
{
    use RefreshDatabase;

    private function messages(array $errors): string
    {
        return implode(' | ', array_map(fn ($m) => implode(' ', (array) $m), $errors));
    }

    public function test_register_errors_are_in_portuguese_and_keep_the_typed_values(): void
    {
        User::factory()->create(['email' => 'ja@existe.test']);

        $response = $this->from('/register')->post('/register', [
            'username' => 'novo_jogador', 'email' => 'nao-e-email', 'password' => 'senha-forte-123', 'password_confirmation' => 'diferente-456',
        ]);
        $response->assertSessionHasErrors(['email', 'password']);
        $text = $this->messages(session('errors')->getBag('default')->toArray());
        $this->assertStringContainsString('Informe um endereço de e-mail válido.', $text);
        $this->assertStringContainsString('As senhas não coincidem.', $text);
        $this->assertStringNotContainsString('must', $text);
        $this->assertSame('novo_jogador', old('username', session()->getOldInput('username')));

        $dup = $this->from('/register')->post('/register', [
            'username' => 'outro_nome', 'email' => 'ja@existe.test', 'password' => 'senha-forte-123', 'password_confirmation' => 'senha-forte-123',
        ]);
        $dup->assertSessionHasErrors('email');
        $this->assertStringContainsString('Este e-mail já está cadastrado.', $this->messages(session('errors')->getBag('default')->toArray()));
    }

    public function test_login_with_wrong_credentials_and_empty_fields_speak_portuguese(): void
    {
        User::factory()->create(['email' => 'a@b.test']);

        $this->post('/login', ['email' => 'a@b.test', 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('incorretos', $this->messages(session('errors')->getBag('default')->toArray()));

        $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
        $this->assertStringContainsString('Informe', $this->messages(session('errors')->getBag('default')->toArray()));
    }

    public function test_forgot_password_and_password_update_speak_portuguese(): void
    {
        $this->post('/forgot-password', ['email' => 'invalido'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('e-mail válido', $this->messages(session('errors')->getBag('default')->toArray()));

        $user = User::factory()->create();
        $this->actingAs($user)->put('/password', ['current_password' => 'errada', 'password' => 'nova-senha-123', 'password_confirmation' => 'outra-456'])
            ->assertSessionHasErrors();
        $text = $this->messages(session('errors')->getBag('default')->toArray());
        $this->assertStringContainsString('senha atual está incorreta', $text);
        $this->assertStringContainsString('As senhas não coincidem.', $text);
    }
}
