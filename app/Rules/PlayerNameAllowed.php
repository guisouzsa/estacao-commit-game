<?php

namespace App\Rules;

use App\Support\PlayerNames;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nomes de contas permanentes seguem as mesmas regras dos visitantes:
 * não podem ser reservados, ofensivos nem estar em uso por um visitante ativo.
 * (A unicidade entre contas continua nas regras e no índice que já existiam.)
 */
class PlayerNameAllowed implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        // Formato do username de conta (letras sem acento, números, _ e -; começa com letra...).
        if ($format = PlayerNames::usernameProblem($value)) {
            $fail($format);

            return;
        }

        $names = app(PlayerNames::class);
        $key = PlayerNames::key($value);

        if ($names->isReserved($key) || $names->isBlocked($key)) {
            $fail('Esse nome não pode ser usado. Escolha outro.');

            return;
        }

        if ($names->takenByGuest($key)) {
            $fail('Esse nome já está sendo utilizado.');
        }
    }
}
