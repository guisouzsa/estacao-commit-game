<?php

/*
|--------------------------------------------------------------------------
| Catálogo de conquistas
|--------------------------------------------------------------------------
|
| Fonte única das conquistas. As linhas da tabela `badges` são sincronizadas a partir daqui (migration e
| App\Services\Achievements::syncCatalog), usando o `slug` como chave: rodar de novo nunca duplica nem apaga nada.
|
| Ritmo real do jogo (medido em 60 partidas simuladas): uma partida tem ~27 vezes por jogador (~10 a 12 min com 25 s
| por vez) e a chance de vencer numa mesa de 4 é ~25%. Logo: 5 vitórias ≈ 20 partidas, 10 ≈ 40, 25 ≈ 100, 50 ≈ 200.
| A ordem de raridade é SEMPRE crescente com o esforço: bronze < prata < ouro < platina < lendária.
| (A tabela inicial proposta colocava "10 vitórias = ouro" antes de "50 = prata", o que não é uma progressão.)
|
| Só entram critérios comprovados pelo servidor: cadastro concluído, partidas e vitórias gravadas em
| `match_results`/`users`, e sequência de vitórias lida de `match_results`. Nada depende de dados enviados pelo cliente.
|
| metric: registered | matches_played | wins | streak
| tier (cor/material da insígnia): starter | bronze | silver | gold | platinum | legend
| icon (símbolo central): star | rocket | branch | trophy | crown | flame | commit
|
*/

return [
    // Quantas conquistas podem ser escolhidas individualmente para o perfil.
    'featured_limit' => 11,

    // Conquistas antigas, sem como serem obtidas: ficam ocultas (não apagadas).
    'retired' => ['first-commit', 'contributor'],

    'catalog' => [
        ['slug' => 'first-access', 'name' => 'Estrela Inicial', 'description' => 'Entrou na estação e acendeu a primeira estrela.', 'criterion' => 'Concluir o cadastro.',
            'category' => 'journey', 'rarity' => 'common', 'tier' => 'starter', 'icon' => 'star', 'metric' => 'registered', 'threshold' => 1, 'sort' => 10],
        ['slug' => 'first-step', 'name' => 'Primeiros Passos', 'description' => 'A primeira missão sempre fica na memória.', 'criterion' => 'Concluir 1 partida.',
            'category' => 'game', 'rarity' => 'common', 'tier' => 'starter', 'icon' => 'rocket', 'metric' => 'matches_played', 'threshold' => 1, 'sort' => 20],
        ['slug' => 'first-win', 'name' => 'Primeira Vitória', 'description' => 'Sua primeira Pull Request aceita.', 'criterion' => 'Vencer 1 partida.',
            'category' => 'victory', 'rarity' => 'uncommon', 'tier' => 'bronze', 'icon' => 'branch', 'metric' => 'wins', 'threshold' => 1, 'sort' => 30],
        ['slug' => 'regular-10', 'name' => 'Frequentador', 'description' => 'A estação já é a sua segunda casa.', 'criterion' => 'Concluir 10 partidas.',
            'category' => 'journey', 'rarity' => 'uncommon', 'tier' => 'bronze', 'icon' => 'commit', 'metric' => 'matches_played', 'threshold' => 10, 'sort' => 40],
        ['slug' => '5-wins', 'name' => 'Estrela de Bronze', 'description' => 'Cinco vitórias: o começo de uma boa reputação.', 'criterion' => 'Vencer 5 partidas.',
            'category' => 'victory', 'rarity' => 'uncommon', 'tier' => 'bronze', 'icon' => 'star', 'metric' => 'wins', 'threshold' => 5, 'sort' => 50],
        ['slug' => 'streak-3', 'name' => 'Em Chamas', 'description' => 'Três vitórias seguidas, sem perder o ritmo.', 'criterion' => 'Vencer 3 partidas seguidas.',
            'category' => 'victory', 'rarity' => 'rare', 'tier' => 'silver', 'icon' => 'flame', 'metric' => 'streak', 'threshold' => 3, 'sort' => 60],
        ['slug' => '10-wins', 'name' => 'Estrela de Prata', 'description' => 'Dez vitórias verificadas pelo servidor.', 'criterion' => 'Vencer 10 partidas.',
            'category' => 'victory', 'rarity' => 'rare', 'tier' => 'silver', 'icon' => 'star', 'metric' => 'wins', 'threshold' => 10, 'sort' => 70],
        ['slug' => 'veteran', 'name' => 'Veterano', 'description' => 'Quem chega aqui já viu de tudo na estação.', 'criterion' => 'Concluir 25 partidas.',
            'category' => 'journey', 'rarity' => 'rare', 'tier' => 'silver', 'icon' => 'commit', 'metric' => 'matches_played', 'threshold' => 25, 'sort' => 80],
        ['slug' => '25-wins', 'name' => 'Estrela de Ouro', 'description' => 'Vinte e cinco vitórias: poucos chegam tão longe.', 'criterion' => 'Vencer 25 partidas.',
            'category' => 'victory', 'rarity' => 'epic', 'tier' => 'gold', 'icon' => 'trophy', 'metric' => 'wins', 'threshold' => 25, 'sort' => 90],
        ['slug' => '50-wins', 'name' => 'Estrela de Platina', 'description' => 'Cinquenta vitórias. A estação conhece o seu nome.', 'criterion' => 'Vencer 50 partidas.',
            'category' => 'victory', 'rarity' => 'epic', 'tier' => 'platinum', 'icon' => 'trophy', 'metric' => 'wins', 'threshold' => 50, 'sort' => 100],
        ['slug' => '100-wins', 'name' => 'Lenda da Estação', 'description' => 'Cem vitórias. Uma lenda do Estação Commit.', 'criterion' => 'Vencer 100 partidas.',
            'category' => 'victory', 'rarity' => 'legendary', 'tier' => 'legend', 'icon' => 'crown', 'metric' => 'wins', 'threshold' => 100, 'sort' => 110],
    ],
];
