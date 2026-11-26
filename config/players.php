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

    // Teto absoluto de vida de um perfil de visitante, contado da criação, mesmo com atividade contínua.
    'guest_max_days' => (int) env('GUEST_MAX_DAYS', 3),

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

    // Filtro de nomes ofensivos (palavrões, conteúdo sexual e ódio) em vários idiomas. Não substitui moderação.
    // A comparação ignora maiúsculas, acentos, separadores (b.u.c.e.t.a), letras repetidas (buuuceta) e troca
    // de letras por números ou símbolos (m3rda, f0da, $hit). Ver PlayerNames::isBlocked.
    //
    // Termos longos e inequívocos: bloqueados em QUALQUER posição do nome ("xcaralhox").
    // Só entram aqui termos que não aparecem dentro de palavras comuns.
    'blocked_contains' => [
        // português
        'caralho', 'buceta', 'boceta', 'xoxota', 'xereca', 'arrombad', 'cuzao', 'cusao', 'cuzin', 'filhodaputa',
        'filhadaputa', 'putaria', 'putinha', 'putona', 'piroca', 'punheta', 'siririca', 'chupapau', 'chupapica',
        'bucetinha', 'cacete', 'merda', 'bosta', 'escroto', 'fodase', 'fodasse', 'foder', 'fodid', 'fodao',
        'vagabunda', 'prostitut', 'retardad', 'mongoloid', 'viadinho', 'bichona', 'traveco', 'sapatao', 'tesao',
        'estupro', 'estuprad', 'pedofil', 'nazista', 'hitler', 'heilhitler', 'siegheil', 'whitepower', 'onlyfans',
        'xvideos', 'pornhub', 'hentai',
        // inglês
        'fuck', 'shit', 'bitch', 'pussy', 'asshole', 'bastard', 'whore', 'nigger', 'nigga', 'faggot', 'retard',
        'penis', 'vagina', 'dildo', 'blowjob', 'handjob', 'motherf', 'porn', 'boobs', 'jizz',
        // espanhol
        'mierda', 'pendejo', 'cabron', 'chingad', 'joder', 'gilipollas', 'maricon', 'malparid', 'culero',
        'hijodeputa', 'putamadre',
        // francês
        'putain', 'salope', 'connard', 'connasse', 'encule', 'batard', 'merde',
        // italiano
        'cazzo', 'stronzo', 'vaffanculo', 'puttana', 'minchia', 'coglion', 'porcodio',
        // alemão
        'scheisse', 'arschloch', 'fotze', 'wichser', 'hurensohn', 'schlampe', 'ficken',
        // outros (polonês, russo transliterado, turco)
        'kurwa', 'blyat', 'blyad', 'pizda', 'siktir', 'orospu',
    ],

    // Termos curtos ou que existem dentro de palavras normais ("computador", "cuidado", "therapist"):
    // bloqueados quando são uma PALAVRA inteira do nome ou o nome inteiro ("c u", "b.c.t").
    'blocked_words' => [
        // português e abreviações
        'puta', 'puto', 'porra', 'foda', 'fdp', 'cu', 'viado', 'rola', 'pica', 'piru', 'xota', 'xana', 'corno',
        'otario', 'babaca', 'idiota', 'imbecil', 'vadia', 'quenga', 'gozar', 'sexo', 'nudes', 'pelada', 'pelado',
        'bct', 'krl', 'crl', 'kct', 'vsf', 'vtnc', 'tnc', 'pqp', 'pnc', 'ppk', 'tmnc',
        // inglês
        'rape', 'rapist', 'nazi', 'fag', 'cunt', 'dick', 'cock', 'slut', 'twat', 'wank', 'cum', 'sex', 'nude',
        'milf', 'nsfw', 'stfu', 'kkk', 'anal', 'tits',
        // espanhol, francês, italiano, alemão e outros
        'verga', 'cono', 'chinga', 'marica', 'culo', 'zorra', 'polla', 'hdp', 'pute', 'bite', 'nique', 'troia',
        'figa', 'fick', 'suka', 'chuj', 'amk',
    ],
];
