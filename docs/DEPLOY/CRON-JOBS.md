# Rotinas agendadas (cron) do Estação Commit

> **Resumo em uma frase:** não existe "Cron Job" do Render nem serviço externo. As rotinas rodam pelo **agendador do próprio
> Laravel** (`php artisan schedule:work`), mantido pelo Supervisor **dentro do mesmo contêiner** do site. Nada precisa ser
> configurado no painel do Render para elas funcionarem.

Voltar ao [índice](../README.md). Visão dos processos do contêiner: [`../ARQUITETURA.md`](../ARQUITETURA.md), seção 5.

---

## 1. Estado da implementação (o que é o quê)

| Situação | Item |
| --- | --- |
| **Implementado no código** | tarefas em [`routes/console.php`](../../routes/console.php); comandos em [`app/Console/Commands/`](../../app/Console/Commands/); trava `withoutOverlapping` no `games:sweep`; lotes e índices; testes (`AutoSweepTest`, `FinishedRoomCleanupTest`, `GuestInactivityCleanupTest`) |
| **Configurado na infraestrutura (imagem Docker)** | processo `scheduler` em [`docker/supervisor/supervisord.conf`](../../docker/supervisor/supervisord.conf), com `autorestart`; migrations aplicadas pelo [`docker-entrypoint.sh`](../../docker-entrypoint.sh) (inclui o índice usado pelo `games:sweep`) |
| **Depende de ação manual no Render** | **nada para as rotinas em si.** A única ação possível é opcional: monitorar os logs do serviço |
| **Não existe / não foi implementado** | Render Cron Job, cron externo, *keep-alive* (requisições periódicas para acordar o serviço) e worker de fila (`queue:work`) |

> **Limite do plano gratuito:** o serviço do Render Free **dorme** por inatividade, e o agendador dorme junto. Enquanto o serviço
> está parado, nenhuma rotina roda. Ao acordar, o `schedule:work` volta a rodar e as rotinas são **idempotentes** (só tratam o que
> já venceu), então nada se perde. Para as regras que não podem esperar (vez da partida, expiração de sala), há também verificação
> **sob demanda** quando alguém abre a página. Por decisão do projeto **não** há requisições periódicas para manter o serviço acordado.

---

## 2. Como o agendamento é executado

```text
Render (Docker) ─► Supervisor ─► [program:scheduler]  php artisan schedule:work
                                         │  1x por minuto dispara `schedule:run`
                                         ▼
                              routes/console.php  ─►  comandos artisan (abaixo)
                                         │
                                         ▼
                                   Neon (PostgreSQL)
```

- `schedule:work` é **um único processo** (`numprocs=1`), com `startretries=5` e `autorestart=true`.
- O scheduler **não é essencial**: se ele parar de vez, o site continua no ar (diferente de `apache` e `reverb`, vigiados pelo
  `essential-watchdog.py`, que derruba o contêiner se caírem).
- Cada execução é um processo PHP curto e separado do Apache. Ele **não ocupa os 2 processos do servidor web** (ver
  `docker/apache/mpm-tuning.conf`), então as rotinas não atrasam as páginas.
- Frequência é a do Laravel: a menor unidade é **1 minuto**.

## 3. Tarefas agendadas

Fonte: [`routes/console.php`](../../routes/console.php).

| Frequência | Comando | O que faz | Proteções |
| --- | --- | --- | --- |
| a cada minuto | `rooms:expire-waiting` | exclui salas em espera com só o anfitrião há mais de 5 minutos | idempotente: só remove o que já venceu |
| a cada minuto | `games:sweep` | resolve vezes paradas: pula a vez de humano vencida (3 seguidas removem o jogador) e joga os passos de bots parados | `withoutOverlapping(5)`, trava de linha por partida, lote máximo (`GAME_SWEEP_BATCH`, padrão 50), consulta indexada |
| a cada minuto | `rooms:purge-finished` | exclui salas cuja partida terminou há mais de `FINISHED_ROOM_TTL_MINUTES` (padrão 2). O histórico de resultados (`match_results`) é preservado | idempotente |
| a cada 15 minutos | `guests:prune` | remove perfis de visitante inativos (padrão 6 h, teto `GUEST_MAX_DAYS`), **nunca** quem está em partida ativa | processa em lotes de 200 (`chunkById`) |

Detalhes do `games:sweep` (o mais sensível): [`../ARQUITETURA.md`](../ARQUITETURA.md), seção "Timeouts sem ninguém na página".
Partidas do motor Cloudflare são **ignoradas** por esse comando (o próprio Durable Object tem alarme).

### Mecanismos de segurança

| Mecanismo | Onde | Efeito |
| --- | --- | --- |
| **Lock** | `withoutOverlapping(5)` (trava de cache no banco, `CACHE_STORE=database`) e `lockForUpdate` por partida | uma execução do `games:sweep` não começa se a anterior ainda roda; duas execuções nunca mexem na mesma partida ao mesmo tempo |
| **Idempotência** | todos os comandos | rodar duas vezes seguidas dá o mesmo resultado; reiniciar o contêiner no meio não deixa estado pela metade |
| **Lotes** | `GAME_SWEEP_BATCH` (50 partidas) e `chunkById(200)` | custo por execução limitado, mesmo com muitos registros |
| **Limite de execução** | consultas com índice (`game_matches_status_turn_clock_index`, índices por ator) e `limit` | cada execução faz poucas consultas curtas; o timeout de 5 min do lock é o teto do `games:sweep` |
| **Proteção contra concorrência** | processo único (`numprocs=1`) + locks acima | um único scheduler por instância; se um dia houver mais de uma instância, o `withoutOverlapping` e os locks de linha continuam valendo |

---

## 4. Como adicionar uma tarefa nova

1. Crie o comando: `php artisan make:command NomeDoComando` e defina `$signature` (por exemplo `exemplo:rodar`).
2. Faça o comando **idempotente** e **em lotes** (use `limit`, `chunkById`, `lockForUpdate`). Nunca dependa de "rodou uma vez só".
3. Registre em `routes/console.php`:
   ```php
   Schedule::command('exemplo:rodar')->everyFiveMinutes()->withoutOverlapping(5);
   ```
4. Escreva um teste em `tests/Feature/` (veja `AutoSweepTest`). Depois de fazer deploy, nada mais a configurar: o `schedule:work`
   já lê o arquivo.
5. Atualize a tabela da seção 3 deste documento e a de [`../ARQUITETURA.md`](../ARQUITETURA.md), seção 5.

Evite tarefas pesadas: o plano gratuito tem ~512 MB de RAM compartilhados com Apache e Reverb.

## 5. Testar manualmente

```bash
php artisan games:sweep              # imprime: Partidas verificadas · timeouts · passos de bot
php artisan rooms:expire-waiting
php artisan rooms:purge-finished
php artisan guests:prune
php artisan schedule:list            # lista as tarefas e a próxima execução (precisa de banco acessível)
php artisan schedule:run             # roda agora o que estiver na hora, uma vez
php artisan test --filter=AutoSweepTest
```

No Render, a aba **Shell** só existe em instâncias pagas. No plano gratuito, rode os comandos **localmente** com o `.env` apontando
para o banco desejado (cuidado: é o banco de produção), ou use os comandos SQL de [`../ARQUITETURA.md`](../ARQUITETURA.md) (banco de dados). Todos os comandos são seguros de repetir.

## 6. Consultar logs

- **Render → Logs** do serviço. Cada execução aparece como `Running [artisan games:sweep]` seguida do tempo (`... 120ms DONE`).
- Eventos de negócio saem em JSON pelo `OpsLog`: `game.turn_timeout`, `game.player_inactive`, `game.bot_stalled`, `game.host_transferred`,
  `game.finished`. Filtre pelo nome do evento. Veja a lista em [`../ARQUITETURA.md`](../ARQUITETURA.md) (observabilidade).
- Falha de um comando aparece como erro com o código de suporte (`request_id`) no mesmo fluxo de logs.

## 7. Identificar execução duplicada

- **Sinal:** a mesma linha `Running [artisan games:sweep]` duas vezes no mesmo minuto, ou `timeouts` contados em dobro.
- **Causas prováveis:** duas instâncias do serviço ao mesmo tempo (deploy sobreposto) ou o processo `scheduler` iniciado duas vezes.
- **O que protege:** `withoutOverlapping` (a segunda é ignorada com a mensagem de que a anterior ainda não terminou) e os
  `lockForUpdate`/idempotência (uma vez que a vez foi resolvida, a segunda execução não a encontra vencida).
- **Conferir:** com acesso ao Shell (instância paga), `ps aux | grep "schedule:work"` deve mostrar **um** processo. No plano gratuito, a evidência é a contagem das linhas `Running [artisan ...]` por minuto nos logs.

## 8. Identificar tarefa travada

- **Sinal:** o log não mostra `Running [artisan games:sweep]` há mais de 2 minutos com o site no ar; ou a linha aparece sem o
  `DONE` correspondente; partidas ficam paradas na vez de alguém por mais de ~1 min com todos fora da página.
- **Conferir:** pelos logs (a linha `Running [artisan games:sweep]` sem o `DONE`). Com Shell: `ps aux | grep -E "schedule|artisan"`.
- **Lock preso:** o lock do `games:sweep` expira sozinho em **5 minutos**; passado esse prazo a tarefa volta a rodar. Para liberar antes,
  apague a linha do lock no banco: `DELETE FROM cache_locks WHERE key LIKE '%schedule-%';` (tabela `cache_locks`, só afeta as travas do agendador).
- **Rede/banco:** se o Neon estiver acordando, a primeira execução pode demorar. Veja `connection` nos logs de erro.

## 9. Desativar temporariamente

| Quer | Faça |
| --- | --- |
| Desligar **uma** tarefa | comente a linha dela em `routes/console.php` e faça deploy |
| Desligar **todas** | comente o bloco `[program:scheduler]` do `supervisord.conf` e faça deploy (o `supervisorctl` não está habilitado na imagem) |
| Pausar só o timeout de partidas | `GAME_TURN_TIMEOUT_SECONDS=0` e `GAME_BOT_STALL_SECONDS=0` (o `games:sweep` passa a não fazer nada) |

Sem as rotinas, o site continua funcionando; salas vazias/encerradas e visitantes antigos só acumulam até a volta do scheduler
(a expiração de sala também ocorre sob demanda ao abrir a sala).

## 10. Recuperar após uma falha

1. **Contêiner reiniciou:** nada a fazer. O estado está no banco e a próxima execução continua de onde parou.
2. **Scheduler caiu e não volta:** veja o log do Supervisor; o `autorestart` tenta 5 vezes. Se falhar, reinicie o serviço no Render (*Manual Deploy → Restart*).
3. **Trava de agendador presa:** apague-a como descrito na seção 8 e rode `php artisan games:sweep` manualmente para verificar.
4. **Migration do índice não aplicada:** o `games:sweep` fica lento. Confira os logs de deploy (`docker-entrypoint.sh` aplica `migrate --force`).
5. **Acúmulo grande depois de um período parado (serviço dormiu):** rode os comandos uma vez cada, na ordem
   `games:sweep` → `rooms:expire-waiting` → `rooms:purge-finished` → `guests:prune`. Todos trabalham em lotes e podem ser repetidos.

## 11. Variáveis que afetam as rotinas

`GAME_TURN_TIMEOUT_SECONDS`, `GAME_MAX_CONSECUTIVE_TIMEOUTS`, `GAME_BOT_DELAY_MS`, `GAME_BOT_STALL_SECONDS`, `GAME_SWEEP_BATCH`,
`FINISHED_ROOM_TTL_MINUTES`, `GUEST_TTL_HOURS`, `GUEST_MAX_DAYS`, `CACHE_STORE`. Descrição de cada uma:
[`VARIAVEIS-DE-AMBIENTE.md`](VARIAVEIS-DE-AMBIENTE.md).
