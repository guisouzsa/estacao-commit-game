-- TESTE DO RANKING (temporário, SÓ NO BANCO — nada disto está no código).
-- Execute no SQL Editor do Neon quando quiser testar. Nenhum trecho roda sozinho.
-- As vitórias aparecem nos perfis e nos rankings como vitórias normais.

-- ============================================================================
-- A) Ranking GERAL: 2 vitórias para "thb" e 1 para "guinho" (colunas matches_* de users)
-- ============================================================================
UPDATE users
SET matches_played = COALESCE(matches_played, 0) + 2,
    matches_won    = COALESCE(matches_won, 0) + 2
WHERE lower(username) = 'thb';

UPDATE users
SET matches_played = COALESCE(matches_played, 0) + 1,
    matches_won    = COALESCE(matches_won, 0) + 1
WHERE lower(username) = 'guinho';

-- ============================================================================
-- B) Ranking SEMANAL e MENSAL: 5 vitórias na semana e 5 no mês para a SUA conta (username 'gui').
--    São "das 102 que você já tem": NÃO mexem em matches_won/matches_played (o total geral fica igual).
--    Gravam 5 resultados recentes em match_results (a mesma 5 contam na semana e no mês).
--    Exige a migration create_match_results_table aplicada; se a tabela não existir, não faz nada.
--    Troque 'gui' abaixo se o seu username for outro.
-- ============================================================================
DO $$
DECLARE
    uid uuid;
BEGIN
    IF to_regclass('public.match_results') IS NULL THEN
        RAISE NOTICE 'match_results ainda não existe: aplique a migration antes (nada foi inserido).';
        RETURN;
    END IF;

    SELECT id INTO uid FROM users WHERE lower(username) = 'gui' LIMIT 1;
    IF uid IS NULL THEN
        RAISE NOTICE 'usuário gui não encontrado (nada foi inserido).';
        RETURN;
    END IF;

    -- ids fixos "de teste": rodar de novo não duplica, e a remoção sabe exatamente o que apagar.
    INSERT INTO match_results (match_id, user_id, is_winner, finished_at, created_at)
    SELECT ('00000000-0000-4000-8000-00000000000' || n)::uuid, uid, true, now() - (n || ' hours')::interval, now()
    FROM generate_series(1, 5) AS n
    ON CONFLICT (match_id, user_id) DO NOTHING;
END $$;

-- Conferir:
-- SELECT username, matches_played, matches_won, matches_lost FROM users WHERE lower(username) IN ('thb','guinho','gui');
-- SELECT count(*) FROM match_results WHERE match_id::text LIKE '00000000-0000-4000-8000-00000000000%';

-- ============================================================================
-- REMOVER — rode SÓ quando você mandar tirar
-- ============================================================================
-- UPDATE users SET matches_played = GREATEST(COALESCE(matches_played,0) - 2, 0),
--                  matches_won    = GREATEST(COALESCE(matches_won,0) - 2, 0)
-- WHERE lower(username) = 'thb';
-- UPDATE users SET matches_played = GREATEST(COALESCE(matches_played,0) - 1, 0),
--                  matches_won    = GREATEST(COALESCE(matches_won,0) - 1, 0)
-- WHERE lower(username) = 'guinho';
-- DELETE FROM match_results WHERE match_id::text LIKE '00000000-0000-4000-8000-00000000000%';
