<?php

/*
|--------------------------------------------------------------------------
| Identidade dos jogadores
|--------------------------------------------------------------------------
|
| Contas permanentes (users), perfis temporários de visitante (guest_profiles)
| e bots compartilham as mesmas regras de nome e de avatar.
|
*/

return [

    // Um perfil de visitante expira após este tempo SEM atividade (janela deslizante).
    'guest_ttl_hours' => (int) env('GUEST_TTL_HOURS', 6),

    // Cookie que guarda o token do visitante (HttpOnly; o valor nunca vai para o JavaScript).
    'guest_cookie' => 'ec_visitor',

    // Estilos que o front sabe DESENHAR (ver resources/js/lib/avatarArt.ts). "blocks" segue
    // valendo para bots e para perfis que já o usam, mas não é mais oferecido para escolha.
    'avatar_styles' => ['pixel', 'robot', 'terminal', 'blocks'],

    // Estilos que a pessoa pode ESCOLHER (visitante ao entrar e conta no editor de perfil).
    'selectable_avatar_styles' => ['pixel', 'robot', 'terminal'],

    'name' => ['min' => 3, 'max' => 24],

    // Nomes que ninguém pode usar. A comparação ignora maiúsculas, acentos e espaços.
    // Os nomes dos bots (config/bots.php) também são reservados.
    'reserved' => [
        'admin', 'administrador', 'administrator', 'moderador', 'moderator', 'mod',
        'suporte', 'support', 'sistema', 'system', 'root', 'null', 'undefined',
        'estacao', 'estacaocommit', 'estacao commit', 'commit', 'github', 'git',
        'bot', 'bots', 'visitante', 'convidado', 'guest', 'anonimo', 'anonymous',
    ],

    // Filtro BÁSICO de nomes ofensivos. Não substitui moderação.
    // Termos longos e inequívocos: bloqueados em qualquer posição do nome.
    'blocked_contains' => [
        'caralho', 'buceta', 'arrombado', 'fuck', 'shit', 'bitch', 'nigger', 'nigga',
        'hitler', 'nazista', 'estupro', 'cuzao',
    ],

    // Termos curtos (podem aparecer dentro de palavras normais, como "computador"):
    // bloqueados apenas quando são uma palavra inteira do nome.
    'blocked_words' => [
        'puta', 'porra', 'merda', 'foda', 'fdp', 'cu', 'viado', 'rape', 'nazi', 'fag', 'cunt',
    ],
];
