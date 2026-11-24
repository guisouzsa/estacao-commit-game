<?php

return [
    // Fuso que define quando uma semana (segunda a domingo) e um mês começam e terminam.
    // Fixo e explícito: a virada vale igual para todos, independente do servidor.
    'timezone' => 'America/Sao_Paulo',

    'limit' => 50,

    // true: a página abre na hora e a classificação carrega depois, com esqueleto (props adiadas).
    'defer' => true,
];
