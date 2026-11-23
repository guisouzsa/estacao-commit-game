<?php

namespace App\Support;

use Closure;

/**
 * Verifica se o domínio de um e-mail existe e pode receber mensagens
 * (registro MX, ou A/AAAA como servidor implícito, como manda a RFC 5321).
 *
 * Não confirma que a CAIXA existe nem que pertence a quem cadastrou: isso só
 * é possível enviando uma mensagem.
 */
class MailDomainChecker
{
    /** @var Closure(string, int): (array<int, array<string, mixed>>|false) */
    private Closure $resolver;

    /** @param (Closure(string, int): (array<int, array<string, mixed>>|false))|null $resolver */
    public function __construct(?Closure $resolver = null)
    {
        // O ponto final evita que o resolvedor anexe domínios de busca.
        $this->resolver = $resolver ?? fn (string $domain, int $type) => @dns_get_record($domain.'.', $type);
    }

    public function acceptsMail(string $domain): bool
    {
        if (! config('auth.email_dns_check', true)) {
            return true;
        }

        $domain = strtolower(trim($domain, ". \t\n\r\0\x0B"));

        if ($domain === '') {
            return false;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            $domain = $ascii !== false ? $ascii : $domain;
        }

        $mx = ($this->resolver)($domain, DNS_MX);

        if (is_array($mx) && $mx !== []) {
            foreach ($mx as $record) {
                // "MX 0 ." (RFC 7505) declara que o domínio NÃO recebe e-mail.
                if (rtrim((string) ($record['target'] ?? ''), '.') !== '') {
                    return true;
                }
            }

            return false;
        }

        $addresses = ($this->resolver)($domain, DNS_A | DNS_AAAA);

        return is_array($addresses) && $addresses !== [];
    }
}
