-- TESTE DO RANKING (temporário). NÃO roda sozinho: execute no SQL Editor do Neon quando quiser testar.
-- Dá 2 vitórias ao usuário "thb" e 1 ao "guinho" no ranking GERAL (colunas matches_* da tabela users).
-- Os rankings Semanal e Mensal vêm de partidas jogadas de verdade e não mudam com isto.

-- 1) APLICAR ----------------------------------------------------------------------------
UPDATE users
SET matches_played = COALESCE(matches_played, 0) + 2,
    matches_won    = COALESCE(matches_won, 0) + 2
WHERE lower(username) = 'thb';

UPDATE users
SET matches_played = COALESCE(matches_played, 0) + 1,
    matches_won    = COALESCE(matches_won, 0) + 1
WHERE lower(username) = 'guinho';

-- Conferir:
-- SELECT username, matches_played, matches_won, matches_lost FROM users WHERE lower(username) IN ('thb','guinho');

-- 2) REMOVER (rode SÓ quando você mandar tirar) -----------------------------------------
-- UPDATE users SET matches_played = GREATEST(COALESCE(matches_played,0) - 2, 0),
--                  matches_won    = GREATEST(COALESCE(matches_won,0) - 2, 0)
-- WHERE lower(username) = 'thb';
-- UPDATE users SET matches_played = GREATEST(COALESCE(matches_played,0) - 1, 0),
--                  matches_won    = GREATEST(COALESCE(matches_won,0) - 1, 0)
-- WHERE lower(username) = 'guinho';
