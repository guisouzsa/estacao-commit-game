<?php

return [
    // A vez de um humano dura menos de 30 s (padrão 25). Passou do prazo: a vez é pulada e a ocorrência conta.
    'turn_timeout_seconds' => (int) env('GAME_TURN_TIMEOUT_SECONDS', 25),

    // Timeouts SEGUIDOS (sem nenhuma jogada válida no meio) até o jogador ser considerado inativo e sair da sala.
    'max_consecutive_timeouts' => (int) env('GAME_MAX_CONSECUTIVE_TIMEOUTS', 3),

    // Pausa do bot antes de jogar, em milissegundos (só para a mesa conseguir ver a jogada).
    'bot_delay_ms' => (int) env('GAME_BOT_DELAY_MS', 700),

    // Minutos que uma sala com partida concluída continua existindo (tela de vitória).
    // Depois disso ela é excluída sozinha; o histórico de resultados fica em match_results.
    'finished_room_ttl_minutes' => (int) env('FINISHED_ROOM_TTL_MINUTES', 2),
];
