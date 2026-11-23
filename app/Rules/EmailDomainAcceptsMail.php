<?php

namespace App\Rules;

use App\Support\MailDomainChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejeita endereços cujo domínio não existe ou não recebe e-mail.
 * O formato em si é validado pelas outras regras do campo.
 */
class EmailDomainAcceptsMail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $domain = substr((string) strrchr($value, '@'), 1);

        if (! app(MailDomainChecker::class)->acceptsMail($domain)) {
            $fail('Não encontramos este domínio de e-mail. Confira se o endereço está escrito corretamente.');
        }
    }
}
