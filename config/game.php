<?php

return [
    // A vez de um humano dura menos de 30 s (padrão 25). Passou do prazo: a vez é pulada e a ocorrência conta.
    'turn_timeout_seconds' => (int) env('GAME_TURN_TIMEOUT_SECONDS', 25),

    // Timeouts SEGUIDOS (sem nenhuma jogada válida no meio) até o jogador ser considerado inativo e sair da sala.
    'max_consecutive_timeouts' => (int) env('GAME_MAX_CONSECUTIVE_TIMEOUTS', 3),

    // Pausa do bot antes de jogar, em milissegundos (só para a mesa conseguir ver a jogada).
    'bot_delay_ms' => (int) env('GAME_BOT_DELAY_MS', 700),

    // Varredura automática (comando games:sweep, a cada minuto): cuida das mesas em que NINGUÉM está com a
    // página aberta. Uma vez de bot parada há mais de `bot_stall_seconds` é jogada pelo servidor.
    'bot_stall_seconds' => (int) env('GAME_BOT_STALL_SECONDS', 8),
    // Máximo de partidas verificadas por execução (protege a memória e o banco do plano gratuito).
    'sweep_batch' => (int) env('GAME_SWEEP_BATCH', 50),

    // Composição do baralho (quantidade de CADA carta). Rebalanceada após medir 60 partidas simuladas: com 7/7/4/16/16/16/4
    // o `git clone` levava em média 4,1 trocas e o `checkout` 3,6; agora 2,2 e 2,3 (ver docs/ARQUITETURA.md, seção 12). `git_login` conta as cópias dos 4 jogadores (uma para cada,
    // fora do baralho de compra). O Worker (worker/src/engine.ts, COMMON_COUNTS) precisa ter os mesmos números:
    // a paridade é verificada por tests/parity. Mudou aqui? Mude lá, regere a fixture e suba PARITY_VERSION.
    'deck' => [
        'git_login' => 4, 'git_clone' => 10, 'git_checkout' => 10, 'git_pull' => 6,
        'git_add' => 18, 'git_commit' => 18, 'git_push' => 8, 'pull_request' => 5,
        'clear_credentials' => 2, 'merge_conflict' => 2,
    ],

    // Minutos que uma sala com partida concluída continua existindo (tela de vitória).
    // Depois disso ela é excluída sozinha; o histórico de resultados fica em match_results.
    'finished_room_ttl_minutes' => (int) env('FINISHED_ROOM_TTL_MINUTES', 2),
];
