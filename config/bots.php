<?php

/*
|--------------------------------------------------------------------------
| Catálogo de bots
|--------------------------------------------------------------------------
|
| Bots não são usuários: não existem na tabela users nem têm login. Cada
| participante bot guarda apenas a `key` abaixo; nome e avatar vêm daqui,
| então são estáveis. O avatar é gerado localmente a partir de `key` (semente).
|
*/

return [

    'max_per_room' => 3,

    'catalog' => [
        ['key' => 'bugado-exe', 'name' => 'Bugado.exe', 'style' => 'terminal', 'title' => 'bug-hunter', 'badge' => 'terminal'],
        ['key' => 'commitzilla', 'name' => 'CommitZilla', 'style' => 'robot', 'title' => 'git-master', 'badge' => 'commit'],
        ['key' => 'null-pointer', 'name' => 'NullPointer', 'style' => 'pixel', 'title' => 'code-guardian', 'badge' => 'api'],
        ['key' => 'senhor-semicolon', 'name' => 'Senhor Semicolon', 'style' => 'blocks', 'title' => 'builder', 'badge' => 'git'],
        ['key' => 'git-ninja', 'name' => 'GitNinja', 'style' => 'pixel', 'title' => 'terminal-wizard', 'badge' => 'branch'],
        ['key' => 'stack-overflow', 'name' => 'StackOverflow', 'style' => 'blocks', 'title' => 'explorer', 'badge' => 'open-source'],
        ['key' => 'loop-infinito', 'name' => 'LoopInfinito', 'style' => 'robot', 'title' => 'full-stack-alchemist', 'badge' => 'docker'],
        ['key' => 'cafe-com-codigo', 'name' => 'CaféComCódigo', 'style' => 'pixel', 'title' => 'coffee-developer', 'badge' => 'coffee-powered'],
        ['key' => 'kernel-panic', 'name' => 'KernelPanic', 'style' => 'terminal', 'title' => 'docker-captain', 'badge' => 'linux'],
        ['key' => 'byte-me', 'name' => 'ByteMe', 'style' => 'robot', 'title' => 'collaborator', 'badge' => 'pull-request'],
        ['key' => 'merge-conflict', 'name' => 'MergeConflict', 'style' => 'blocks'],
        ['key' => 'segmentation-fault', 'name' => 'SegmentationFault', 'style' => 'terminal'],
        ['key' => 'ctrl-alt-del', 'name' => 'CtrlAltDel', 'style' => 'pixel'],
        ['key' => 'pixel-compiler', 'name' => 'PixelCompiler', 'style' => 'pixel'],
        ['key' => 'mestre-dos-loops', 'name' => 'Mestre dos Loops', 'style' => 'robot'],
        ['key' => 'dev-fantasma', 'name' => 'DevFantasma', 'style' => 'blocks'],
        ['key' => 'robo-do-deploy', 'name' => 'Robô do Deploy', 'style' => 'robot'],
        ['key' => 'exceptionzinha', 'name' => 'Exceptionzinha', 'style' => 'terminal'],
        ['key' => 'dona-recursao', 'name' => 'Dona Recursão', 'style' => 'pixel'],
        ['key' => 'commit-supremo', 'name' => 'Commit Supremo', 'style' => 'blocks'],
        ['key' => 'tela-azul', 'name' => 'Tela Azul', 'style' => 'terminal'],
        ['key' => 'branch-perdida', 'name' => 'Branch Perdida', 'style' => 'pixel'],
        ['key' => 'rebase-rebelde', 'name' => 'Rebase Rebelde', 'style' => 'robot'],
        ['key' => 'hotfix-de-sexta', 'name' => 'Hotfix de Sexta', 'style' => 'blocks'],
        ['key' => 'cache-vazio', 'name' => 'Cache Vazio', 'style' => 'terminal'],
    ],
];
