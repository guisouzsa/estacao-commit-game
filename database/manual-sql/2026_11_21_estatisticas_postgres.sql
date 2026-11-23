-- Estação Commit: colunas de estatísticas de partidas (migration 2026_11_21_000000) em SQL do PostgreSQL.
--
-- Esta migration nunca foi aplicada no Neon (o Render Free não roda migrations), então a
-- coluna matches_won não existia e as estatísticas do perfil nunca apareceram.
-- Execute UMA vez no SQL Editor do Neon. É seguro rodar de novo (IF NOT EXISTS).

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS matches_played integer NULL,
    ADD COLUMN IF NOT EXISTS matches_won    integer NULL,
    ADD COLUMN IF NOT EXISTS matches_lost   integer NULL;

-- Estatísticas do usuário "gui": 100 vitórias e 20 derrotas (120 partidas = 83,3%).
-- Valores ABSOLUTOS: rodar de novo não soma nada.
UPDATE users
SET matches_played = 120, matches_won = 100, matches_lost = 20
WHERE LOWER(username) = 'gui';

-- Confere o resultado.
SELECT username, matches_played, matches_won, matches_lost
FROM users WHERE LOWER(username) = 'gui';

-- Registra a migration como aplicada (só se ainda não estiver registrada).
INSERT INTO migrations (migration, batch)
SELECT '2026_11_21_000000_add_historical_game_statistics_to_users_table',
       (SELECT COALESCE(MAX(batch), 0) FROM migrations)
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_11_21_000000_add_historical_game_statistics_to_users_table'
);

-- ---------------------------------------------------------------------------------------
-- DIAGNÓSTICO (opcional, só leitura): quais migrations do projeto NÃO estão registradas?
-- Lista vazia = tudo registrado. Atenção: "não registrada" não prova que o esquema falta
-- (uma migration pode ter sido aplicada à mão); confira a tabela/coluna correspondente.
-- ---------------------------------------------------------------------------------------
SELECT v.m AS migration_nao_registrada
FROM (VALUES
    ('0001_01_01_000000_create_users_table'),
    ('0001_01_01_000001_create_cache_table'),
    ('0001_01_01_000002_create_jobs_table'),
    ('2026_11_19_000000_add_profile_customization_fields_to_users_table'),
    ('2026_11_19_010000_add_google_id_to_users_table'),
    ('2026_11_19_020000_add_profile_media_fields_to_users_table'),
    ('2026_11_19_030000_create_profile_social_and_badge_tables'),
    ('2026_11_20_000000_create_game_rooms'),
    ('2026_11_20_010000_add_onboarding_to_users'),
    ('2026_11_20_020000_add_password_set_to_users_table'),
    ('2026_11_21_000000_add_historical_game_statistics_to_users_table'),
    ('2026_11_22_000000_add_empty_since_to_game_rooms'),
    ('2026_11_22_000100_create_notifications_table'),
    ('2026_11_23_000000_add_guest_profiles_and_participant_kinds'),
    ('2026_11_23_000100_create_follows_table'),
    ('2026_11_23_000200_store_profile_images_in_database')
) AS v(m)
WHERE NOT EXISTS (SELECT 1 FROM migrations x WHERE x.migration = v.m);
