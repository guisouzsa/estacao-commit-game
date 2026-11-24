<?php

return [
    // Segundos que um humano pode ficar parado na própria vez antes de um bot assumir seu lugar.
    'turn_timeout_seconds' => (int) env('GAME_TURN_TIMEOUT_SECONDS', 120),

    // Minutos que uma sala com partida concluída continua existindo (tela de vitória).
    // Depois disso ela é excluída sozinha; o histórico de resultados fica em match_results.
    'finished_room_ttl_minutes' => (int) env('FINISHED_ROOM_TTL_MINUTES', 2),
];
