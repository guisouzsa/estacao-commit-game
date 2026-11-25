<?php

return [
    // Desligado por padrão: nada muda até EMAIL_VERIFICATION=true no ambiente.
    'enabled' => (bool) env('EMAIL_VERIFICATION', false),
    'ttl_minutes' => (int) env('EMAIL_CODE_TTL', 10),
    'max_attempts' => (int) env('EMAIL_CODE_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('EMAIL_CODE_COOLDOWN', 60),

    // E-mail de boas-vindas: UM por conta, no momento do cadastro (e-mail ou Google). Nunca a cada login.
    // Para economizar a cota diária do provedor (Brevo grátis: 300/dia), dá para desligar com MAIL_WELCOME=false.
    'welcome_enabled' => (bool) env('MAIL_WELCOME', true),
];
