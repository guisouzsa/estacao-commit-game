<?php

return [
    'frames' => [
        'standard' => 'Linha de base',
        'lime' => 'Sinal verde',
        'violet' => 'Branch violeta',
        'pink' => 'Conexão rosa',
        'red' => 'Commit vermelho',
        'terminal' => 'Terminal clássico',
        'merge' => 'Merge colorido',
        'circuit' => 'Circuito tecnológico',
        'checkout' => 'Git checkout',
        'hotfix' => 'Hotfix especial',
        'neon' => 'Neon da estação',
        'sunset' => 'Pôr do sol digital',
        'ocean' => 'Oceano de código',
        'gold' => 'Commit dourado',
    ],

    'badges' => [
        'terminal' => 'Terminal',
        'branch' => 'Branch',
        'commit' => 'Commit',
        'pull-request' => 'Pull Request',
        'git' => 'Git',
        'github' => 'GitHub',
        'docker' => 'Docker',
        'linux' => 'Linux',
        'open-source' => 'Open Source',
        'code-review' => 'Code Review',
        'ship-it' => 'Ship It!',
        'coffee-powered' => 'Movido a café',
        'bug-squasher' => 'Bug Squasher',
        'api' => 'API',
    ],

    'titles' => [
        'explorer' => 'Explorador',
        'builder' => 'Montador',
        'collaborator' => 'Colaborador',
        'coffee-developer' => 'Dev Cafeteiro',
        'git-master' => 'Mestre do Git',
        'docker-captain' => 'Capitão Docker',
        'bug-hunter' => 'Caçador de Bugs',
        'code-guardian' => 'Guardião do Código',
        'api-architect' => 'Arquiteto de APIs',
        'container-tamer' => 'Domador de Containers',
        'terminal-wizard' => 'Mago do Terminal',
        'open-source-hero' => 'Herói Open Source',
        'full-stack-alchemist' => 'Alquimista Full Stack',
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
