<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection;

/**
 * Conexão PostgreSQL para o pooler do Neon (PgBouncer em modo transação).
 *
 * Com prepares NATIVOS o PDO envia `DEALLOCATE` ao destruir cada statement; atrás do pooler
 * esse comando falha em silêncio dentro da transação e a deixa "abortada" (SQLSTATE 25P02) —
 * o comando seguinte quebra. Por isso o projeto usa prepares emulados.
 *
 * Emulados, porém, o Laravel manda booleanos como inteiros (1/0) e o PostgreSQL recusa
 * inteiro em coluna boolean. Aqui booleanos viram 'true'/'false' (texto entre aspas, que o
 * PostgreSQL converte para boolean sem reclamar).
 */
class PooledPostgresConnection extends PostgresConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
