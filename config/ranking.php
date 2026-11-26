<?php

return [
    // Fuso que define quando uma semana (segunda a domingo) e um mês começam e terminam.
    // Fixo e explícito: a virada vale igual para todos, independente do servidor.
    'timezone' => 'America/Sao_Paulo',

    // Quantas posições a classificação mostra (o topo). A posição de quem está vendo é calculada à parte,
    // então quem está fora do topo continua vendo onde está em "Sua posição".
    'limit' => 10,

    // true: a página abre na hora e a classificação carrega depois, com esqueleto (props adiadas).
    'defer' => true,
];
