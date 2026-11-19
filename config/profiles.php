<?php

return [
    'frames' => [
        'standard' => 'Linha de base',
        'lime' => 'Sinal verde',
        'violet' => 'Branch violeta',
        'pink' => 'Conexão rosa',
        'red' => 'Commit vermelho',
    ],

    'badges' => [
        'terminal' => 'Terminal',
        'branch' => 'Branch',
        'commit' => 'Commit',
        'pull-request' => 'Pull Request',
    ],

    'titles' => [
        'explorer' => 'Explorador',
        'builder' => 'Montador',
        'collaborator' => 'Colaborador',
    ],

    'developer_badges' => [
        'founder' => 'Founder',
        'co-founder' => 'Co-Founder',
        'developer' => 'Developer',
        'core-developer' => 'Core Developer',
        'station-team' => 'Estação Commit Team',
    ],

    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('PROFILE_ADMIN_EMAILS', '')),
    ))),
];
