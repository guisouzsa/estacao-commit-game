<?php

/*
|--------------------------------------------------------------------------
| Tempo real: Reverb (padrão) ou Cloudflare Workers + Durable Objects
|--------------------------------------------------------------------------
| REALTIME_DRIVER=reverb      -> comportamento atual (nada muda).
| REALTIME_DRIVER=cloudflare  -> avisos de mudança vão ao Worker (worker/) e o
|                                navegador conecta nele. Para voltar: reverb.
| Os segredos NUNCA vão para o front (nenhuma variável VITE_*).
*/

return [
    'driver' => env('REALTIME_DRIVER', 'reverb'),

    // URL pública do Worker (https://...workers.dev). O navegador troca https por wss.
    'worker_url' => rtrim((string) env('REALTIME_WORKER_URL', ''), '/'),

    // Assina o token de conexão do navegador (mesmo valor de REALTIME_TOKEN_SECRET no Worker).
    'token_secret' => env('REALTIME_TOKEN_SECRET'),

    // Assina as chamadas Laravel -> Worker (mesmo valor de REALTIME_NOTIFY_SECRET no Worker).
    'notify_secret' => env('REALTIME_NOTIFY_SECRET'),

    // Assina os callbacks Worker -> Laravel (resultado da partida, bot assumindo vaga).
    'callback_secret' => env('REALTIME_CALLBACK_SECRET'),

    // Quem manda nas PARTIDAS novas: 'laravel' (padrão, motor PHP + Neon) ou 'cloudflare'
    // (Durable Object). Partidas já em andamento continuam no motor em que nasceram.
    'game_engine' => env('GAME_ENGINE', 'laravel'),

    'token_ttl_seconds' => 60,
];
