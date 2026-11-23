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
        ['key' => 'bugado-exe', 'name' => 'Bugado.exe', 'style' => 'terminal'],
        ['key' => 'commitzilla', 'name' => 'CommitZilla', 'style' => 'robot'],
        ['key' => 'null-pointer', 'name' => 'NullPointer', 'style' => 'pixel'],
        ['key' => 'senhor-semicolon', 'name' => 'Senhor Semicolon', 'style' => 'blocks'],
        ['key' => 'git-ninja', 'name' => 'GitNinja', 'style' => 'pixel'],
        ['key' => 'stack-overflow', 'name' => 'StackOverflow', 'style' => 'blocks'],
        ['key' => 'loop-infinito', 'name' => 'LoopInfinito', 'style' => 'robot'],
        ['key' => 'cafe-com-codigo', 'name' => 'CaféComCódigo', 'style' => 'pixel'],
        ['key' => 'kernel-panic', 'name' => 'KernelPanic', 'style' => 'terminal'],
        ['key' => 'byte-me', 'name' => 'ByteMe', 'style' => 'robot'],
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
