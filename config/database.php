<?php

'pgsql' => [
    'driver' => 'pgsql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', 'root'),

    'password' => (function () {
        $host = (string) env('DB_HOST', '');
        $password = (string) env('DB_PASSWORD', '');

        if (str_starts_with($host, 'ep-')) {
            $endpointId = explode('.', $host)[0];
            $endpointId = preg_replace('/-pooler$/', '', $endpointId);

            return 'endpoint=' . $endpointId . ';' . $password;
        }

        return $password;
    })(),

    'charset' => env('DB_CHARSET', 'utf8'),
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => env('DB_SSLMODE', 'prefer'),

    // Compatibilidade com o pooler do Neon.
    'options' => extension_loaded('pdo_pgsql') ? [
        PDO::ATTR_EMULATE_PREPARES => true,
    ] : [],
],
