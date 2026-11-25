# Rotinas agendadas (cron) do Estação Commit

> **Resumo em uma frase:** não existe "Cron Job" do Render nem serviço externo. Um **laço de shell leve** dentro do mesmo contêiner
> roda `php artisan schedule:run` **uma vez por minuto** (processo curto, que termina sozinho). Não há processo PHP residente
> (`schedule:work` foi removido). Nada precisa ser configurado no painel do Render para as rotinas funcionarem.

Voltar ao [índice](../README.md). Visão dos processos do contêiner: [`../ARQUITETURA.md`](../ARQUITETURA.md), seção 5.

---

## 1. Estado da implementação (o que é o quê)

| Situação | Item |
| --- | --- |
| **Implementado no código** | rotinas em [`routes/console.php`](../../routes/console.php) (rodam **dentro** do `schedule:run`); executor/log em [`app/Support/ScheduledJob.php`](../../app/Support/ScheduledJob.php); serviços em `app/Services`; comandos manuais em [`app/Console/Commands/`](../../app/Console/Commands/); testes (`CronSchedulerTest`, `AutoSweepTest`, `RealtimeInfrastructureTest`) |
| **Configurado na imagem Docker** | [`docker/supervisor/scheduler-loop.sh`](../../docker/supervisor/scheduler-loop.sh) (copiado pelo `Dockerfile`) e o `[program:scheduler]` em [`supervisord.conf`](../../docker/supervisor/supervisord.conf). As migrations (inclusive o índice do `games:sweep`) rodam no [`docker-entrypoint.sh`](../../docker-entrypoint.sh) |
| **Depende de ação manual no Render** | **nada obrigatório.** Opcional: variáveis `SCHEDULER_*` (seção 8) e acompanhar os logs |
| **Não existe / não foi implementado** | Render Cron Job, cron externo, *keep-alive* (requisições para manter o serviço acordado) e worker de fila (`queue:work`) |
| **Ainda NÃO validado em produção** | tudo acima foi testado **localmente** (testes automatizados, execução com SQLite). A confirmação no Render exige o deploy: veja o checklist da seção 11 |

> **Limite do plano gratuito (importante):** o serviço do Render Free **dorme** por inatividade (~15 min sem requisições) e o
> agendador dorme junto. Com ninguém no site, nenhuma rotina roda. Ao acordar, o laço volta a rodar e as rotinas são **idempotentes**
> (só tratam o que já venceu), então nada se perde. Para as regras que não podem esperar (vez da partida, expiração de sala) também há
> verificação **sob demanda** quando alguém abre a página. Por decisão do projeto **não** há requisições periódicas para manter o
> serviço acordado: isso manteria o Render (e o Neon) ligados 24 h por dia sem necessidade.

---

## 2. Por que esta estratégia (comparação das alternativas)

| Alternativa | Veredito | Motivo |
| --- | --- | --- |
| **Render Cron Job** (serviço separado) | descartada | não é gratuito (cobrança mínima mensal) e é outro contêiner, sem acesso ao arquivo/estado do serviço web e com cópia das variáveis secretas |
| **`cron`/`crond` do sistema** dentro do contêiner | descartada | precisaria instalar pacote e manter outro daemon; o ganho sobre um laço de shell é nenhum |
| **`schedule:work`** (como era) | substituída | mantém um processo PHP/Laravel residente (~37 MB privados / ~50 MB de *working set* medidos localmente) só para acordar a cada minuto, e ainda abre um subprocesso PHP por tarefa |
| **Cron externo / pinger gratuito** (GitHub Actions, cron-job.org) | não adotada | exigiria uma rota HTTP pública protegida que ocupa 1 dos 2 processos do Apache e mantém o serviço acordado 24 h |
| **Laço de shell + `schedule:run` pontual** | **escolhida** | processo residente de ~1 MB; um único `php artisan schedule:run` curto por minuto, com prioridade baixa, limite de tempo e de memória; sem dependência nova |

### Efeito medido (localmente, SQLite, PHP no Windows; os valores absolutos no Linux do Render são menores, a proporção é a que importa)

| Métrica | Antes (`schedule:work`) | Depois (laço + `schedule:run`) |
| --- | --- | --- |
| Processo residente | `php artisan schedule:work`: **~37 MB privados** (50 MB de working set) | `sh`: **~1 MB** |
| Inicializações do Laravel por minuto | 4 (o `schedule:run` + 1 subprocesso por tarefa) | **1** (as tarefas rodam dentro dele) |
| Tempo de CPU por minuto (relativo) | ~4,6 s (4 boots) | **~1,2 s (−74%)** |
| Pico de memória de uma execução | 4 processos PHP em sequência | **30 MB** em 1 processo |
| Processos novos por minuto | 4 | 1 (mais `nice`/`timeout`) |

Não há como medir, daqui, o consumo real no Render (RAM/CPU do contêiner, ocorrências de `MaxRequestWorkers`, conexões do Neon).
Depois do deploy, compare os logs conforme a seção 11.

---

## 3. Como o agendamento é executado

```text
Render (Docker) ─► Supervisor ─► [program:scheduler]  /bin/sh scheduler-loop.sh      (residente: ~1 MB)
                                         │  a cada minuto cheio:
                                         ▼
              nice -n 15  timeout 50s  php -d memory_limit=96M artisan schedule:run   (curto: termina sozinho)
                                         │  executa, EM PROCESSO, as rotinas vencidas
                                         ▼
                  routes/console.php ─► ScheduledJob ─► serviços ─► Neon (PostgreSQL)
```

- **Sequencial:** o laço só inicia a próxima execução depois que a anterior termina. Se uma demorar, o minuto seguinte é pulado (todas as
  rotinas são "o que já venceu", então o próximo ciclo recupera o atraso).
- **`nice -n 15`:** prioridade baixa de CPU; o Apache atende as páginas primeiro.
- **`timeout 50s`:** uma execução travada é encerrada (código 124, registrado no log) e a seguinte começa no próximo minuto.
- **`memory_limit=96M`:** teto de memória por execução (o pico medido é ~30 MB).
- **Não é essencial:** se o `scheduler` falhar de vez, o site continua no ar (diferente de `apache` e `reverb`, vigiados pelo
  `essential-watchdog.py`). Ele **não ocupa** os 2 processos do Apache (`docker/apache/mpm-tuning.conf`).
- Frequência mínima do Laravel: **1 minuto**.

## 4. Tarefas agendadas (auditoria)

Fonte: [`routes/console.php`](../../routes/console.php). Cada linha: o que faz, custo no banco e se pode bloquear requisições.

| Rotina | Frequência | O que faz | Consultas por execução | Bloqueia requisição? | Paralelismo | Necessária? / alternativa |
| --- | --- | --- | --- | --- | --- | --- |
| `rooms:expire-waiting` | 1 min | exclui salas em espera com só o anfitrião há mais de 5 min (`RoomExpirationService`) | 1 consulta indexada (`status, empty_since`); transação curta só se houver sala vencida | não (trava só a sala que exclui) | seguro (relê a sala sob `lockForUpdate`) | também ocorre **sob demanda** ao abrir a sala; o agendamento garante a limpeza sem ninguém na página |
| `games:sweep` | 1 min | resolve vezes paradas: pula a vez do humano vencida (3 seguidas removem o jogador) e joga os passos de bots parados (`GameInactivityService`) | 1 consulta indexada (`game_matches_status_turn_clock_index`, `limit` = `GAME_SWEEP_BATCH`) + trabalho só nas partidas vencidas | não (trava só a partida tratada) | `withoutOverlapping(5)` + `lockForUpdate` por partida | **única** que não tem substituto sob demanda quando todos saem da página |
| `rooms:purge-finished` | 1 min | exclui salas cuja partida terminou há mais de `FINISHED_ROOM_TTL_MINUTES` (2); preserva `match_results` | 1 consulta; exclusão em lotes de 100 | não | seguro (idempotente) | também sob demanda (`purgeIfDue`); poderia rodar a cada 2 min sem prejuízo |
| `guests:prune` | 15 min | remove perfis de visitante inativos (6 h; teto 3 dias), nunca quem está em partida ativa (`GuestPruner`) | consultas indexadas em lotes de 200 | não | seguro | necessária; não há como fazer sob demanda sem custo em toda requisição |
| `cron:heartbeat` | 15 min | escreve **uma** linha de log (sinal de vida) | 0 | não | n/a | opcional; é a prova de que o agendador está rodando sem poluir o log |

Não existem: atualização de ranking agendada (o ranking é calculado nas consultas, a partir de `match_results`), filas/jobs, notificações
pendentes a enviar nem e-mails em lote. Tudo que é automático está na tabela acima. Partidas do **motor Cloudflare** são ignoradas pelo
`games:sweep` (o Durable Object tem o próprio alarme).

### Regras dos turnos (resumo)

- O prazo de **25 s** vale **só para humanos**. O tipo do participante vem do banco (`isBot()`), nunca da requisição.
- Vez de **bot** nunca gera timeout: se ficar parada (nenhum cliente pediu o passo) por `GAME_BOT_STALL_SECONDS`, o servidor joga os passos do bot
  até chegar a vez de um humano.
- Uma partida processada por duas execuções ao mesmo tempo é protegida por `lockForUpdate`; a segunda não a encontra vencida.

### Proteções contra sobrecarga e duplicidade

| Mecanismo | Onde | Efeito |
| --- | --- | --- |
| Laço sequencial | `scheduler-loop.sh` | nunca duas execuções do agendador na mesma instância |
| `withoutOverlapping(5)` | `games:sweep` (trava no cache do banco, `cache_locks`) | durante um deploy, duas instâncias não tratam as mesmas partidas; a trava expira sozinha em 5 min |
| `lockForUpdate` por partida/sala | serviços | nenhuma partida é alterada por duas execuções ao mesmo tempo |
| Idempotência | todas as rotinas | repetir não duplica nada; reiniciar o contêiner no meio não deixa estado pela metade |
| Lotes e `limit` | `GAME_SWEEP_BATCH`, `chunkById` | custo por execução limitado |
| Isolamento de falhas | `ScheduledJob` | uma rotina que falha é registrada e **não impede as outras** nem a próxima execução |
| `timeout`/`memory_limit`/`nice` | `scheduler-loop.sh` | execução travada é encerrada; uso de CPU e RAM limitado |

---

## 5. Logs e monitoramento (econômicos)

Cada execução de `schedule:run` **não imprime nada** por padrão. O que aparece no **Render → Logs** (formato JSON do `OpsLog`):

| Evento | Quando | Campos |
| --- | --- | --- |
| `cron.heartbeat` | a cada 15 min | `peak_memory_mb` |
| `cron.job` | só quando uma rotina **processou algo** (ou demorou mais de 2 s) | `job`, contadores (`processed`, ou `timeouts`, `bot_steps`, `checked`), `duration_ms`, `peak_memory_mb` |
| `cron.job_failed` | falha de uma rotina (nível `error`) | `job`, `duration_ms`, `error`, `message` (sem segredos) |
| `game.turn_timeout`, `game.player_inactive`, `game.bot_stalled`, `game.host_transferred` | eventos de partida | ver [`../ARQUITETURA.md`](../ARQUITETURA.md) |
| texto do laço (`scheduler: ...`) | ao iniciar, ao desativar, ou quando o `schedule:run` termina com erro/tempo esgotado | código de saída |

Volume esperado: **~4 linhas por hora** em repouso (heartbeats) e uma linha extra por rotina que trabalhar. Sem senhas, tokens, códigos nem dados pessoais.

**Como saber se está funcionando:** procure `cron.heartbeat` nos logs: deve aparecer a cada 15 minutos enquanto o serviço está acordado.

---

## 6. Como adicionar uma tarefa nova

1. Crie um serviço (ou use um existente) com um método **idempotente** e **em lotes** que devolva um inteiro (registros tratados) ou um array de contadores.
2. Registre em `routes/console.php` pelo padrão (rodando em processo, com log e isolamento de falhas):
   ```php
   Schedule::call(fn () => ScheduledJob::run('exemplo:rodar', fn () => app(MeuServico::class)->executar()))
       ->name('exemplo:rodar')->everyFiveMinutes();            // + ->withoutOverlapping(5) se não for idempotente
   ```
3. (Opcional) crie também um comando artisan que chame o mesmo serviço, para execução manual.
4. Escreva um teste em `tests/Feature/` (veja `CronSchedulerTest`, que confere cada tarefa e a cadência) e atualize a tabela da seção 4.
5. Depois do deploy não há mais nada a configurar: o laço já executa `schedule:run`.

Evite tarefas pesadas: o plano gratuito tem ~512 MB de RAM compartilhados com Apache e Reverb.

## 7. Executar manualmente e testar

```bash
php artisan games:sweep              # imprime: Partidas verificadas · timeouts · passos de bot
php artisan rooms:expire-waiting
php artisan rooms:purge-finished
php artisan guests:prune
php artisan schedule:list            # lista as tarefas e a próxima execução
php artisan schedule:run             # roda agora o que estiver na hora, uma vez
php artisan test --filter=CronSchedulerTest
```

> **Cuidado:** o `.env` local pode apontar para o banco de produção (Neon). Para testar com segurança use um SQLite temporário:
> `DB_CONNECTION=sqlite DB_DATABASE=/caminho/teste.sqlite CACHE_STORE=array php artisan schedule:run` (rode `migrate` nele antes).
> `schedule:list` e `schedule:run` consultam o cache/banco configurado.

No Render, a aba **Shell** só existe em instâncias pagas. No plano gratuito, a validação é pelos logs (seção 5). Todos os comandos são seguros de repetir.

## 8. Variáveis do agendador (opcionais, ambiente do Render)

| Variável | Padrão | Efeito |
| --- | --- | --- |
| `SCHEDULER_ENABLED` | `true` | `false` desliga o agendador **sem alterar código** (o laço fica parado, custo ~0) |
| `SCHEDULER_NICE` | `15` | prioridade de CPU da execução (maior = mais "educada" com o Apache) |
| `SCHEDULER_TIMEOUT` | `50` | segundos máximos de uma execução antes de ser encerrada |
| `SCHEDULER_MEMORY_LIMIT` | `96M` | teto de memória do PHP nessa execução |
| `GAME_SWEEP_BATCH` | `50` | máximo de partidas tratadas por execução do `games:sweep` |
| `GAME_TURN_TIMEOUT_SECONDS`, `GAME_BOT_STALL_SECONDS`, `FINISHED_ROOM_TTL_MINUTES`, `GUEST_TTL_HOURS`, `GUEST_MAX_DAYS` | ver [`VARIAVEIS-DE-AMBIENTE.md`](VARIAVEIS-DE-AMBIENTE.md) | regras de cada rotina |

As `SCHEDULER_*` são lidas pelo shell (não pelo Laravel). Alterá-las no Render reinicia o serviço.

## 9. Identificar problemas

**Execução duplicada** (mesma rotina duas vezes no mesmo minuto, `timeouts` em dobro): causa provável, duas instâncias do serviço ao
mesmo tempo (deploy sobreposto). `withoutOverlapping` e os `lockForUpdate` impedem efeito duplo; confira se o `cron.job` do `games:sweep`
aparece uma vez por minuto.

**Execução travada / agendador parado:**
- Sinais: nenhum `cron.heartbeat` há mais de ~20 min com o site no ar; linha `scheduler: schedule:run terminou com código 124`; partidas paradas na vez
  de alguém por mais de ~1 min com todos fora da página.
- A execução travada é encerrada sozinha pelo `timeout` (50 s). O **lock** do `games:sweep` expira em 5 min; para liberar antes:
  `DELETE FROM cache_locks WHERE key LIKE '%games:sweep%';` (só afeta a trava do agendador).
- Se o laço em si caiu, o Supervisor o reinicia (`autorestart`, 5 tentativas). Se persistir: **Manual Deploy → Restart**.

**Neon lento ou acordando:** a primeira execução pode demorar; aparece `cron.job` com `duration_ms` alto (> 2 s é registrado mesmo sem trabalho).

## 10. Desativar, reativar e reverter

| Quer | Faça |
| --- | --- |
| Desligar **todo** o agendamento, sem deploy de código | no Render, `SCHEDULER_ENABLED=false` e salvar (o laço fica parado; o site segue normal) |
| Reativar | remover a variável (ou `true`) |
| Desligar **uma** tarefa | comente-a em `routes/console.php` e faça deploy |
| Pausar só o timeout de partidas | `GAME_TURN_TIMEOUT_SECONDS=0` e `GAME_BOT_STALL_SECONDS=0` |
| **Reverter ao modelo anterior** (`schedule:work`) | reverta o commit desta mudança (restaura `command=... artisan schedule:work` no `supervisord.conf` e `routes/console.php`); nenhum dado ou migration é afetado |

Sem as rotinas, o site continua funcionando; salas vazias/encerradas e visitantes antigos só acumulam até a volta do agendador
(a expiração de sala também ocorre sob demanda ao abrir a sala).

## 11. Validar no Render depois do deploy (checklist)

Esta mudança **não está** no Render até você fazer o deploy. Depois dele:

1. Logs de inicialização: linha `scheduler: iniciado (schedule:run a cada minuto, nice=15, timeout=50s, memory_limit=96M).`
2. Em até 15 minutos: uma linha `cron.heartbeat`.
3. Crie uma sala, inicie com bots e deixe a vez de um humano vencer **com a aba fechada**: em ~1 min aparece `game.turn_timeout`.
4. Nenhuma linha `scheduler: schedule:run terminou com código` nem `cron.job_failed`.
5. Compare com o período anterior: ocorrências de `AH00161: server reached MaxRequestWorkers` e tempo de resposta do site. (O Render Free não expõe RAM/CPU por processo.)

## 12. Recuperar após uma falha

1. **Contêiner reiniciou:** nada a fazer. O estado está no banco; a próxima execução continua de onde parou.
2. **Agendador caiu e não volta:** veja o log do Supervisor; reinicie o serviço (*Manual Deploy → Restart*).
3. **Trava presa:** apague-a (seção 9) e rode `php artisan games:sweep` localmente com o banco certo, se necessário.
4. **Migration do índice não aplicada:** o `games:sweep` fica lento; confira os logs de deploy.
5. **Acúmulo grande depois de um período parado (serviço dormiu):** as rotinas trabalham em lotes e se repetem a cada minuto; o acúmulo é consumido
   em poucas execuções. Se quiser acelerar, rode uma vez cada comando da seção 7.
