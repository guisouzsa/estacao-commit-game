<?php

return [
    // Segundos que um humano pode ficar parado na própria vez antes de um bot assumir seu lugar.
    'turn_timeout_seconds' => (int) env('GAME_TURN_TIMEOUT_SECONDS', 120),
];
