# Arquitetura, autenticação, multiplayer e operação

Complemento de [`MANUTENCAO/GUIA-DE-MANUTENCAO.md`](MANUTENCAO/GUIA-DE-MANUTENCAO.md) (tecnologias e variáveis). Aqui estão **os fluxos reais do código**, o que
acontece quando um serviço cai e **como investigar** cada problema. Todos os caminhos abaixo existem no repositório.

## 1. Mapa da autenticação

| Fluxo | Rota (`routes/auth.php`) | Controller | Pontos de atenção |
| --- | --- | --- | --- |
| Cadastro | `POST register` (10/min) | `Auth/RegisteredUserController` | valida nome (`PlayerNameAllowed`), domínio do e-mail (`EmailDomainAcceptsMail`), unicidade sem diferenciar maiúsculas |
| Código de e-mail | `GET/POST verificar-email`, `POST verificar-email/reenviar` | `Auth/EmailCodeController` + `Services/EmailVerificationCode` | com `EMAIL_VERIFICATION=true` a conta **só é criada depois do código**; dados esperam na sessão (senha em hash) |
| Login | `POST login` (10/min) | `Auth/AuthenticatedSessionController` | só e-mail e senha; regenera a sessão |
| Google | `auth/google`, `auth/google/callback`, `auth/google/username` | `Auth/GoogleAuthController`, `GoogleUsernameController` | exige `email_verified` do Google; conta existente com o mesmo e-mail entra e fica vinculada; conta nunca confirmada perde sessões antigas |
| Recuperar senha | `forgot-password`, `reset-password/{token}` | `PasswordResetLinkController`, `NewPasswordController` | resposta idêntica para e-mail existente ou não (sem enumeração); token de uso único com validade em `config/auth.php` |
| Trocar senha | `PUT password` | `Auth/PasswordController` | encerra as outras sessões (`logoutOtherDevices`) |
| Sessão | middleware `AuthenticateAccountSession` | | derruba sessões antigas quando a senha muda; visitantes (guard `visitor`) passam direto |
| Visitante | `GET/POST /jogar` | `GuestPlayController` | perfil temporário por cookie HttpOnly, expira (`GUEST_TTL_HOURS`) |

Testes: `tests/Feature/Auth/`, `tests/Feature/EmailCodeVerificationTest.php`, `tests/Feature/Security/AuthAndIsolationTest.php`.

### E-mail e resiliência

O envio passa pelo mailer configurado em `MAIL_MAILER` (`config/mail.php`):

| Valor | Comportamento |
| --- | --- |
| `log` | só grava no log (desenvolvimento) |
| `brevo` | Brevo por API HTTPS ([`BrevoTransport`](../app/Mail/BrevoTransport.php)) |
| `resend-http` | Resend por API HTTPS ([`ResendHttpTransport`](../app/Mail/ResendHttpTransport.php)) |
| `resilient` | tenta o Brevo e, **só se ele estiver fora do ar**, o Resend. Entram os provedores que têm chave |

Regras de falha (iguais nos dois provedores):

- **Transitória** (sem conexão, timeout, HTTP 429 ou 5xx): `TransportException`. O `resilient` passa ao próximo provedor.
- **Permanente** (chave inválida, remetente não verificado, endereço recusado: HTTP 4xx): `RuntimeException`. **Não** troca de provedor nem repete.
- Cada tentativa registra no log `mail.sent` ou `mail.provider_failed` com `provider`, `kind` e `status` (nunca a chave nem o conteúdo).
- No cadastro, se nada enviou, a tela de código diz isso e libera "Reenviar código" (espera curta de 10 s). Nada é afirmado como enviado sem confirmação do provedor.
- O Resend gratuito, sem domínio verificado, só envia ao dono da conta. Para uso real é preciso verificar um domínio.

Para trocar de provedor: ver `MANUTENCAO/GUIA-DE-MANUTENCAO.md`, seção 5.3.

## 2. Multiplayer

### Ciclo de uma partida

1. `GameRoomController` cria a sala; convites em `RoomInvitation`; `RoomJoinService` ocupa as vagas.
2. `GameMatchController@start` chama `Services/GameEngine::create` (4 participantes, Git Login na mão de todos).
3. Cada jogada é `POST games.action` com `action_uuid` (idempotência) e `version` (concorrência otimista). Respostas 409 pedem releitura.
4. Bots: `POST games.bots` joga **um passo por pedido**, na vez de um bot, pelo `GameEngine::botStep`. O ritmo é do cliente (`game.bot_delay_ms`).
5. Fim: `MatchResults`/`MatchStatistics` gravam em `match_results` (único por partida e jogador) e a sala some após `FinishedRoomCleanup`.

### Tempo de jogada, timeouts e saída (regras em `config/game.php`)

| Regra | Valor padrão | Variável |
| --- | --- | --- |
| Duração da vez de um humano | **25 s** | `GAME_TURN_TIMEOUT_SECONDS` |
| Timeouts seguidos até a remoção | **3** | `GAME_MAX_CONSECUTIVE_TIMEOUTS` |
| Pausa do bot antes de jogar | 700 ms | `GAME_BOT_DELAY_MS` |

- O prazo de 25 s vale **só para humanos**: o tipo do participante vem do registro no banco (`isBot()`), nunca da requisição; bots passam por `GameEngine::act` sem prazo.
- O relógio é do **servidor**: `game_matches.turn_started_at`, reiniciado em `GameEngine::advance` a cada nova vez. O navegador só exibe a contagem (`turn_seconds_left`, limitada ao máximo).
- `GameInactivityService::sweep` roda quando alguém abre ou recarrega a partida (o front sincroniza periodicamente) **e** pelo comando agendado `games:sweep` a cada minuto, mesmo com todas as páginas fechadas (seção 5). Passou do prazo: a vez é **pulada** e `room_participants.timeout_streak` aumenta (duas verificações no mesmo prazo não contam duas vezes).
- Uma jogada válida zera `timeout_streak` (`GameEngine::act`). Jogar depois do prazo (+3 s de folga de rede) é recusado pelo servidor.
- No 3º timeout seguido: o humano sai da sala e um **bot ocupa o lugar** (mesma mão e percurso). Se não sobrar humano, a sala é destruída (nada de sala órfã).
- **Anfitrião:** quem sai ou é removido passa o comando ao humano que entrou logo depois (`RoomLeaveService::transferHostFrom`, ordem de `joined_at`). Nunca a um bot.
- Vez de bot nunca gera timeout.
- Uma queda curta de rede não remove ninguém: só o **terceiro** timeout seguido, sem nenhuma jogada válida no meio, remove.

Testes: `tests/Feature/TurnTimeoutTest.php`, `GameMatchTest.php`, `RoomLeaveTest.php`, `Security/GameIntegrityTest.php`.

### Dois motores (PHP e Worker)

O motor ativo é o **PHP** (`GAME_ENGINE=laravel`). O Worker (`worker/src/engine.ts`) é uma alternativa **desligada**: só liga com
`GAME_ENGINE=cloudflare` **e** `GAME_ENGINE_PARITY_ACK` igual a `CloudflareMatchGateway::PARITY_VERSION`.
A versão foi **trocada** quando as regras de timeout mudaram no PHP: o Worker ainda usa a regra antiga (um bot assume na primeira
inatividade). Enquanto ele não receber as mesmas regras e a fixture `tests/parity/` não for regerada, **não ative** o motor da Cloudflare. A matriz completa está na seção 6.

## 3. O que acontece quando um serviço cai

| Serviço | Depende dele | O que para | O que continua | Como detectar / recuperar |
| --- | --- | --- | --- | --- |
| **Brevo** | códigos e recuperação de senha | envio de e-mail | login, jogo, ranking | log `mail.provider_failed`; usar `MAIL_MAILER=resilient` com `RESEND_API_KEY`; reenviar código na tela |
| **Google OAuth** | botão "Continuar com Google" | login/cadastro por Google | e-mail e senha | tela mostra aviso e orienta usar e-mail e senha; conferir credenciais e a URI |
| **Neon** | tudo (contas, salas, sessões, cache, fila) | praticamente tudo | página estática de erro do Apache | o entrypoint tenta migrar 5 vezes ao subir; ver status do Neon e logs |
| **Reverb / WebSocket** | avisos em tempo real | atualização instantânea | jogo continua por **polling** do front | indicador "Reconectando…" na partida; ver logs do processo `reverb` |
| **Cloudflare Worker** | só se `REALTIME_DRIVER=cloudflare` | avisos em tempo real | jogo (estado é do Laravel) por polling | voltar para `REALTIME_DRIVER=reverb` |
| **Render** | hospedagem | tudo durante o reinício | dados no Neon intactos | partidas em andamento retomam: o estado está no banco |

Dados que podem ser perdidos: cadastro em andamento guardado só na sessão (se a sessão expirar, repete-se o cadastro) e
avisos em tempo real não entregues (recuperados por polling).

## 4. Procedimentos de investigação

| Problema | Onde olhar |
| --- | --- |
| Erro de login | `Auth/AuthenticatedSessionController`, `Requests/Auth/LoginRequest` (limite de tentativas); logs do Render pelo **código de suporte** da página de erro |
| Falha do Google | `GoogleAuthController` (mensagens em `with('status', ...)`); conferir `GOOGLE_*` e a URI no Google Cloud |
| E-mail não chega | log `mail.sent` / `mail.provider_failed` / `verification.send_failed`; painel Brevo → Transacional → Logs |
| Sala travada | `game_matches.turn_started_at`, `room_participants.timeout_streak`; abrir a partida dispara `GameInactivityService::sweep` |
| Bot não joga | cliente chama `games.bots` com a `version` atual; ver `GameMatchController@botStep` e `game.bot_delay_ms` |
| Timeout incorreto | log `game.timeout` (sala, participante, contagem); `config/game.php` |
| Anfitrião sem comando | log `game.host_transferred`; `RoomLeaveService` |
| Divergência PHP/Worker | `php artisan test --filter=EngineParityTest` e `cd worker && npx vitest run` |
| WebSocket | processo `reverb` nos logs; `REVERB_APP_MAX_CONNECTIONS` |

Logs úteis (canal `stderr`, visíveis no Render): `game.timeout`, `game.host_transferred`, `mail.sent`, `mail.provider_failed`,
`verification.send_failed`. Nenhum guarda senha, token ou o corpo dos e-mails.

### Testes e rollback

```bash
php artisan test && npm run test:js && npx tsc --noEmit && (cd worker && npx vitest run)
```

Rollback: reverter o commit na `main` (o Render refaz o deploy). As migrations deste conjunto só **adicionam** colunas
(`email_code_*` em `users`, `turn_started_at` em `game_matches`, `timeout_streak` em `room_participants`); reverter o código é seguro
sem desfazer o banco.

---

# Parte 2: padrões, processos, paridade e manutenção

## 5. Processos em segundo plano (Render, um único contêiner)

Definidos em `docker/supervisor/supervisord.conf` e `docker-entrypoint.sh`. **Não existe worker de fila**: a aplicação não tem Jobs nem
notificações em fila (`app/Jobs` não existe; e-mails e avisos são enviados na própria requisição). `QUEUE_CONNECTION=database` só existe como
padrão do Laravel. Se um dia houver `ShouldQueue`, será preciso um processo `queue:work` novo (cuidado com a memória do plano gratuito).

| Processo | Como inicia | Quem mantém | O que faz | Como falha / reinicia | Onde ver |
| --- | --- | --- | --- | --- | --- |
| `apache` | Supervisor | Supervisor (`autorestart`) + `essential-watchdog.py` | serve o site (PHP) e encaminha `/app/` ao Reverb | se cair, o watchdog derruba o contêiner e o Render reinicia | logs do Render (`stderr`/`stdout`) |
| `reverb` | Supervisor | Supervisor + watchdog | WebSocket interno (porta 8080) | idem; sem ele o front cai para polling | logs do Render |
| `scheduler` | Supervisor (`schedule:work`) | Supervisor (`autorestart`, não essencial) | roda os comandos agendados (tabela abaixo) | se parar, o app continua; as limpezas passam a ocorrer sob demanda | linhas `Running [artisan ...]` nos logs |
| `essential-watchdog` | Supervisor | Supervisor | vigia `apache` e `reverb` | se ele cair, o Supervisor o reinicia | logs do Render |
| migrations | `docker-entrypoint.sh` | o próprio script (5 tentativas) | `php artisan migrate --force` antes de subir | falha final impede o deploy (o Render mantém a versão anterior) | logs de deploy |

Comandos agendados (`routes/console.php`):

| Frequência | Comando | Idempotente? | Proteção contra duplicidade |
| --- | --- | :-: | --- |
| a cada minuto | `rooms:expire-waiting` | sim | só remove o que já venceu |
| a cada minuto | `rooms:purge-finished` | sim | só remove salas encerradas há mais de 2 min |
| a cada minuto | **`games:sweep`** | sim | `withoutOverlapping(5)` + trava de linha por partida; uma consulta indexada |
| a cada 15 min | `guests:prune` | sim | consulta indexada em lotes |

### Timeouts sem ninguém na página (`games:sweep`)

`GameInactivityService::sweepStalled()` resolve, para o **motor PHP**, as partidas ativas cujo relógio da vez (`turn_started_at`) passou do
limite, mesmo com todas as páginas fechadas:

- **vez de humano vencida:** a vez é pulada e o timeout é contado; o 3º seguido remove o jogador (regra da seção 2);
- **vez de bot parada** há mais de `GAME_BOT_STALL_SECONDS` (8 s): o servidor joga os passos do bot até chegar a vez de um humano.

Custo: uma consulta por minuto com índice `game_matches_status_turn_clock_index (status, turn_started_at)`, no máximo `GAME_SWEEP_BATCH` (50) mesas por
execução. **Limitação honesta:** o `schedule:work` roda a cada minuto, então a resolução tem até ~1 minuto de atraso em mesas sem ninguém olhando
(com a página aberta, o atraso é de segundos, pois o front sincroniza). Partidas do Worker usam o alarme do Durable Object e são ignoradas pelo comando.
Reinício do contêiner: o estado está no banco; a próxima execução continua de onde parou.

## 6. Matriz de paridade PHP × Worker

O motor ativo é o PHP. O Worker (`worker/src/engine.ts`, `worker/src/match.ts`) está **desligado** e assim deve ficar até as diferenças abaixo serem resolvidas.
A fixture de paridade (`tests/parity/scenarios.json`, `EngineParityTest.php`, `worker/test/parity.test.ts`) cobre as regras do **motor**; as regras de
**tempo e inatividade** não estão na fixture.

| Regra | PHP | Worker | Igual? | Teste |
| --- | --- | --- | :-: | --- |
| Percurso de 18 etapas, Git Login na mão de todos | `GameEngine::REQUIRED`, `create` | `engine.ts` `REQUIRED`, `create` | sim | `EngineParityTest` + `parity.test.ts` |
| Quantidades do baralho | `COMMON_COUNTS` | `COMMON_COUNTS` | sim | idem |
| Efeitos (pull, limpar credenciais, merge conflict) | `playCard`, `attack` | equivalentes | sim | idem (11 cenários) |
| Troca com variedade, limite de 6 cartas | `exchange`, `pickIndex` | equivalentes | sim | idem |
| Bots um passo por vez | `botStep` | `/bots` + `botStep` | sim | idem |
| Vitória e encerramento | `act` | `engine.ts` | sim | idem |
| **Duração da vez** | 25 s (`config/game.php`) | recebe `turn_timeout_seconds` do Laravel | sim (valor) | — |
| **Ação de humano fora do prazo** | recusada (`GameEngine::act`, +3 s de folga) | **não recusa** | **não** | faltante |
| **Timeout de humano** | pula a vez e conta (`timeout_streak`) | **um bot assume já no 1º** | **não** | faltante |
| **3 timeouts seguidos** | remove o jogador, bot no lugar | não existe | **não** | faltante |
| **Transferência de anfitrião** | `RoomLeaveService::transferHostFrom` | só avisa `participant_replaced`; o Laravel não transfere nesse callback | **não** | faltante |
| **Sala sem humanos** | destrói a sala | não trata | **não** | faltante |
| Execução sem página aberta | `games:sweep` (até ~1 min) | alarme do Durable Object (preciso) | diferente por desenho | `TurnTimeoutTest`, `AutoSweepTest` / `worker/test/match.test.ts` |
| Vez de bot nunca tem prazo de humano | `act` só aplica o prazo se `! isBot()` (tipo vem do banco) | `sweepInactivity` ignora `kind === 'bot'` | sim | `AutoSweepTest` / `match.test.ts` |

**Decisão necessária (sua):** alinhar o Worker exige (1) implementar pulo de vez, contagem e remoção em `match.ts`, (2) o callback `/realtime/callback`
transferir o anfitrião e apagar a sala sem humanos, (3) estender a fixture de paridade com cenários de tempo e (4) subir `PARITY_VERSION`. Enquanto isso,
`GAME_ENGINE=laravel`. A versão de paridade atual (`2026-11-26-2`) já invalida qualquer liberação feita antes da mudança de regras.

## 7. Padrões de código e erros

### Convenções (as que já existem no repositório)

| Item | Convenção |
| --- | --- |
| PHP | PSR-12 (Laravel Pint). Controllers `XxxController` (singular, uma responsabilidade), services em `app/Services`, helpers estáticos em `app/Support`, comandos `app/Console/Commands` |
| Rotas | nomes em inglês com ponto (`rooms.store`, `games.action`); URLs em português (`/salas`, `/perfil`, `/jogar`) |
| Config | `config/*.php` lê `env()`; **o resto do código só usa `config()`** (conferido: nenhuma chamada a `env()` fora de `config/`) |
| React | componentes e páginas em `PascalCase.tsx`; libs em `camelCase.ts` (`resources/js/lib`); tipos exportados junto do componente |
| Eventos de log | `categoria.acao` em minúsculas (seção 8) |
| Migrations | só aditivas, com `down()`; nomes `AAAA_MM_DD_HHMMSS_descricao` |
| Testes | `tests/Feature/*Test.php`, métodos `test_descricao_em_ingles`; segurança em `tests/Feature/Security/` |

### Erros e respostas

- Página de erro: `App\Support\ErrorResponses` + `resources/js/Pages/Error.tsx`; toda requisição recebe um **código de suporte** (`X-Request-Id`, `AssignRequestId`) que aparece na tela e em todos os logs da requisição.
- Mensagens ao usuário em português, sem caminhos nem stack trace (testado em `HardeningAndUploadsTest`).
- Validação: `ValidationException` → Inertia mostra o erro no campo. Conflito de versão no jogo: **409**. Sem permissão: **403**; sala/recurso alheio: **404** (não revela existência).
- Falha de integração (Google, e-mail): captura **específica**, mensagem com caminho alternativo e log `integration.failure` / `mail.provider_failed`. Não há `catch` que esconda erro e devolva sucesso.
- Limite de requisições: **429** pelo `throttle` das rotas sensíveis.
- Contrato das páginas: Inertia (não há API JSON pública). Endpoints JSON/POST consumidos pelo front: `players.search`, `realtime/token`, `games.action`, `games.bots`, `jogar/nome`. **Não padronizei um envelope único**: mudar o formato quebraria o front sem ganho; o padrão está documentado aqui.

### Inventário de rotas (`php artisan route:list`, 60 rotas)

Públicas (sem `auth`/`guest`): `/`, `/jogar` (GET/POST), `/jogar/nome`, `/convites/{token}`, `/perfil/{username}`, `/u/{username}`, `/imagens/{kind}/{user}`,
`auth/google/callback`, `realtime/callback` (assinatura HMAC + nonce, sem sessão). Todas com `throttle`, exceto as páginas de leitura `/`, `/jogar` (GET) e os perfis públicos.
As demais exigem conta (`auth`) ou visitante (`auth:visitor`). A rota administrativa `admin/users/{user}/developer-badge` exige o Gate `manage-developer-badges`
(e-mail em `PROFILE_ADMIN_EMAILS` confirmado). Isolamento entre contas, IDOR e mass assignment: `tests/Feature/Security/*`.

## 8. Observabilidade

Todo log operacional passa por `App\Support\OpsLog::event('categoria.acao', [...])`: acrescenta `event` e `env`, esconde campos com nome sensível
(senha, token, código, segredo, cookie, chave, authorization) e remove `chave=valor` de segredos dentro de mensagens de exceção. O `request_id` entra
sozinho (`Log::shareContext`).

| Evento | Quando | Nível |
| --- | --- | --- |
| `auth.login` / `auth.login_failed` | login com sucesso / senha errada (sem e-mail no log) | info / warning |
| `auth.register` | conta criada | info |
| `auth.google` | falhas de validação do retorno do Google | warning |
| `auth.password_reset` | senha redefinida | info |
| `auth.email_verification` | falha no envio do código | error |
| `mail.sent` / `mail.provider_failed` | envio por provedor; falha com `kind` transient/permanent | info / warning |
| `game.created` / `game.finished` | partida criada / encerrada | info |
| `game.turn_timeout` / `game.player_inactive` | timeout contado / 3º timeout | info |
| `game.host_transferred` | anfitrião passou para outro humano | info |
| `game.bot_stalled` | servidor jogou vez de bot parada | info |
| `integration.failure` | Google ou outro serviço externo falhou | error |

Como achar: copie o **código de suporte** da tela de erro (8 letras) e busque nos logs do Render; ou busque pelo nome do evento (`game.turn_timeout`).
Eventos do Worker (`game.worker_error`) ainda **não** foram padronizados: o Worker escreve `console.error` nos logs do próprio Cloudflare.

### Saúde

`GET /up` (padrão do Laravel, em `bootstrap/app.php`) responde 200 quando a aplicação **inicializou**. Ele **não** consulta banco, e-mail nem Worker, de propósito:
assim uma lentidão do Neon ou do Brevo não derruba a aplicação por falso alarme. Para checar o banco: abrir `/` ou `php artisan migrate:status` no contêiner;
e-mail: painel do Brevo e log `mail.*`; WebSocket: indicador "Sincronizado/Reconectando" da partida; agendador: linhas `Running [artisan games:sweep]` nos logs.

## 9. Banco de dados

Revisão das migrations (22 arquivos antes desta rodada): chaves estrangeiras com `cascadeOnDelete`, únicos em `(room_id, user_id|guest_id|bot_key|seat)`,
`follows` único e com índices por data, `match_results` único por `(match_id, user_id)`, índice único `LOWER(username)` e `LOWER(email)`.

Acrescentado nesta rodada (somente índices, sem tocar em dados): `game_matches (status, turn_started_at)` para o `games:sweep` e
`room_participants (user_id)` / `(guest_id)` para as buscas por pessoa (no PostgreSQL, chave estrangeira não cria índice). Colunas novas: `game_matches.turn_started_at`,
`room_participants.timeout_streak`, `users.email_code_*`. Todas nulas ou com padrão seguro. Migrations só rodam no deploy (entrypoint); **nunca** contra o banco de produção a partir
da sua máquina sem decisão explícita.

## 10. Guia de resolução de problemas

| Problema | Onde investigar | Arquivos | Comando útil | Causa provável | Solução segura |
| --- | --- | --- | --- | --- | --- |
| Login não funciona | logs `auth.login_failed`; código de suporte | `Auth/AuthenticatedSessionController`, `Requests/Auth/LoginRequest` | `php artisan test --filter=AuthAndIsolationTest` | senha errada, limite de tentativas (429), sessão/`APP_KEY` mudou | recuperar senha; conferir `APP_KEY` |
| Google não autentica | log `integration.failure` (`google_oauth`) | `Auth/GoogleAuthController` | `php artisan test --filter=OpsLogAndGoogleFailureTest` | `GOOGLE_*` errados, URI divergente, app não publicado | corrigir variáveis/URI no Google Cloud (ver `MANUTENCAO.md` 6) |
| E-mail não chega | logs `mail.provider_failed`; painel Brevo | `Mail/*Transport`, `config/mail.php` | `php artisan test --filter=EmailCodeVerificationTest` | remetente não verificado, chave errada, spam | verificar remetente; `MAIL_MAILER=resilient` com `RESEND_API_KEY` |
| Recuperação de senha falha | log + painel de e-mail | `PasswordResetLinkController`, `NewPasswordController` | idem | e-mail não enviado ou link expirado | pedir novo link |
| Sala não abre | código de suporte; `game_rooms` | `GameRoomController`, `RoomJoinService` | `php artisan test --filter=RoomLifecycleTest` | convite expirado, sala cheia, sala já removida | criar nova sala |
| Sala fica presa | `game_matches.turn_started_at`, logs `game.turn_timeout` | `GameInactivityService`, `routes/console.php` | `php artisan games:sweep` | scheduler parado | verificar o processo `scheduler`; rodar `games:sweep` manualmente |
| Bot não joga | log `game.bot_stalled`; versão da partida | `GameMatchController@botStep`, `GameEngine::botStep` | `php artisan games:sweep` | cliente sem página aberta (o sweep cobre em ~1 min) | aguardar o sweep; conferir `GAME_BOT_STALL_SECONDS` |
| Timer não avança | `turn_seconds_left` na página; `turn_started_at` | `GameMatchController::turnSecondsLeft` | `php artisan test --filter=TurnTimeoutTest` | `GAME_TURN_TIMEOUT_SECONDS` antigo no Render | ajustar variável |
| Jogador removido incorretamente | log `game.player_inactive`; `timeout_streak` | `GameInactivityService` | `--filter=AutoSweepTest` | 3 timeouts seguidos sem jogada válida | revisar `GAME_MAX_CONSECUTIVE_TIMEOUTS` |
| Anfitrião não transferido | log `game.host_transferred` | `RoomLeaveService::transferHostFrom` | `--filter=TurnTimeoutTest` | sem outro humano na sala (nesse caso a sala fecha) | nenhuma ação |
| Worker não responde | painel Cloudflare, logs do Worker | `worker/src/*`, `CloudflareRealtime` | `cd worker && npx vitest run` | secrets diferentes, URL errada | voltar `REALTIME_DRIVER=reverb` |
| WebSocket desconecta | processo `reverb`, indicador "Reconectando" | `docker/supervisor`, `config/reverb.php` | — | limite de conexões (`REVERB_APP_MAX_CONNECTIONS`) | o front usa polling; medir a RAM antes de aumentar |
| Banco lento | painel Neon; logs de requisição | `PooledPostgresConnection`, índices da seção 9 | `php artisan migrate:status` | Neon acordando, consulta sem índice | aguardar; abrir um chamado com o código de suporte |
| Frontend não compila | saída do `npm run build` | `resources/js/**`, `tsconfig.json` | `npx tsc --noEmit` | erro de tipo | corrigir o tipo apontado |
| Build do Docker falha | log de build do Render | `Dockerfile` | `docker build -t estacao-commit .` | `npm ci`/`composer` falhou, `VITE_*` ausente | reproduzir localmente |
| Deploy não inicia | log do entrypoint | `docker-entrypoint.sh` | — | migration falhou 5 vezes | corrigir a migration e refazer o deploy |

## 11. Rotinas de manutenção

**Periódica (mensal)**
- [ ] `php artisan test`, `npm run test:js`, `npx tsc --noEmit`, `cd worker && npx vitest run`.
- [ ] `composer audit`, `npm audit` e `cd worker && npm audit`; atualizar só o que tem aviso real (corrigir em branch, rodar testes).
- [ ] Logs: buscar `integration.failure`, `mail.provider_failed` e `game.bot_stalled` frequentes.
- [ ] Conferir no painel do Brevo a taxa de entrega e o limite diário.
- [ ] Conferir o espaço do banco no Neon e a janela de backup/restauração do plano.
- [ ] Revisar `PROFILE_ADMIN_EMAILS`.

**Antes de alterar**: `git status`; confirmar a branch; ler esta documentação; escrever o teste; mudança pequena; rodar a suíte; revisar o `git diff`.

**Antes do deploy**: listar migrations novas (`git diff --stat main -- database/migrations`) e confirmar que só adicionam; conferir variáveis no Render (`MANUTENCAO.md` 3); conferir `npm run build`; ter o commit anterior à mão para reverter.

**Depois do deploy**: logs sem erro; `GET /up` 200; entrar, sair, cadastrar com código; criar sala e jogar uma partida com bots; ver nos logs `Running [artisan games:sweep]`.

---

# Parte 3: baralho, conquistas, contas temporárias e padrões visuais

## 12. Baralho: matemática e justiça

### Como funciona (PHP: `app/Services/GameEngine.php`; Worker: `worker/src/engine.ts`)
- A composição vem de `config/game.php` (`deck`). O Worker tem os mesmos números em `COMMON_COUNTS`; `DeckFairnessTest::test_the_php_and_worker_decks_are_the_same_numbers` falha se divergirem.
- O embaralhamento é **Fisher-Yates uniforme**. No PHP agora usa `Random\Randomizer` (motor criptográfico do PHP 8.2); o Worker usa `crypto.getRandomValues`. Nada que o cliente envia entra no sorteio.
- Cada jogador começa com 4 cartas: o `Git Login` (uma cópia por jogador, fora do baralho de compra) e 3 sorteadas.
- Compra: 1 carta depois de jogar uma carta e 1 ao trocar. A compra olha as primeiras 8 cartas do baralho já embaralhado e pula só a que acabou de ser trocada ou que formaria trinca (`pickIndex`). Isso nunca procura a carta "útil" e vale igual para humanos e bots (o código não tem ramo por tipo de jogador; `test_humans_and_bots_draw_by_exactly_the_same_rule` confirma estatisticamente).
- Descartes voltam ao baralho reembaralhados quando a pilha de compra acaba. Cartas jogadas no percurso saem de circulação (exceto as devolvidas por `Limpar Credenciais`).

### Diagnóstico: a percepção de demora estava certa
Chance de **pelo menos uma** cópia da carta necessária em k compras (sem reposição), ignorando as compras dos outros e a mão atual
(`1 − C(N−K, k) / C(N, k)`):

| Carta (antes: 62 no baralho de compra) | Cópias | 1 compra | 3 compras | 5 compras | 8 compras | Compras esperadas |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| git clone / checkout | 7 | 11,3% | 30,6% | **46,2%** | 64,0% | 7,9 |
| git pull / Pull Request | 4 | 6,5% | 18,4% | 29,2% | 43,3% | **12,6** |
| git add / commit / push | 16 | 25,8% | 59,9% | 78,8% | 92,3% | 3,7 |

Ou seja: para o `git clone` ou o `checkout` (os dois primeiros passos úteis), **5 compras dão só 46%**: o "cinco rodadas" relatado é o esperado, e o `pull`
e a `Pull Request` esperavam ~13 compras. Além disso, havia 16 `push` para apenas 4 necessários (um quarto do baralho em cartas quase mortas) e `add`/`commit` tinham
exatamente 16 cópias para 16 necessárias (4 jogadores × 4 ciclos), sem folga.

Medição com o motor real (60 partidas só de bots, trocas de carta até conseguir a etapa; mais trocas = mais espera):

| Etapa | Antes (média de trocas) | Depois | Variação |
| --- | :-: | :-: | :-: |
| git clone | 4,11 | 2,24 | −45% |
| git checkout | 3,55 | 2,32 | −35% |
| git pull | 2,21 | 1,03 | −53% |
| Pull Request | 2,00 | 1,03 | −49% |
| git add | 1,09 | 0,94 | −14% |
| git commit | ~0,8 | 0,77 | igual |
| git push | 0,04 | 0,83 | + (era abundante demais) |
| Turnos de uma partida (mediana) | 118 | 106 | −10% |

### Mudança aplicada (regra de composição, decisão documentada)
Baralho de **83 cartas** (antes 78): `clone 10, checkout 10, pull 6, add 18, commit 18, push 8, Pull Request 5, Limpar Credenciais 2, Merge Conflict 2` (+ 4 `Git Login`).
O baralho de compra passa de 62 para **67** cartas. Nada favorece humanos ou bots; o que mudou foi a oferta de cada carta, de modo que as cartas de que todos precisam aparecem com frequência parecida.
Chance depois (67 no baralho): clone/checkout em 5 compras **56,6%** (era 46,2%); pull 38,4% (era 29,2%); Pull Request 33,0% (era 29,2%).

Como reverter: voltar `config/game.php` (`deck`) e `COMMON_COUNTS` no Worker aos valores antigos, regerar a fixture (`PARITY_UPDATE=1 php artisan test --filter=EngineParityTest`) e subir `PARITY_VERSION`.
**Paridade:** o Worker já tem os novos números e a fixture foi regerada (PHP e Worker passam); `PARITY_VERSION` agora é `2026-11-27-3`. As regras de tempo continuam diferentes (seção 6).

## 13. Conquistas

Catálogo em `config/achievements.php` (fonte única); linhas sincronizadas em `badges` por `slug` (migration `2026_11_27_000000_achievements_catalog_and_featured`, idempotente).
Concessão em `App\Services\Achievements`, sempre no servidor, a partir de dados que o servidor gravou:

| Conquista | Critério | Raridade/material |
| --- | --- | --- |
| Estrela Inicial | concluir o cadastro (e-mail/senha ou Google) | comum / inicial |
| Primeiros Passos | concluir 1 partida | comum / inicial |
| Primeira Vitória | vencer 1 partida | incomum / bronze |
| Frequentador | concluir 10 partidas | incomum / bronze |
| Estrela de Bronze | vencer 5 | incomum / bronze |
| Em Chamas | vencer 3 seguidas | rara / prata |
| Estrela de Prata | vencer 10 | rara / prata |
| Veterano | concluir 25 partidas | rara / prata |
| Estrela de Ouro | vencer 25 | épica / ouro |
| Estrela de Platina | vencer 50 | épica / platina |
| Lenda da Estação | vencer 100 | lendária / lenda |

Ritmo real: uma partida dura ~10 a 12 minutos e a chance de vencer em mesa de 4 é ~25%, então 5 vitórias ≈ 20 partidas e 50 ≈ 200. Os marcos 5 → 10 → 25 → 50 → 100 crescem com o esforço; a proposta
inicial ("10 vitórias = ouro" antes de "50 = prata") não era uma progressão. `first-commit` e `contributor` (sem como obter) ficam **inativas**, não apagadas.

- **Gatilhos:** evento `Registered` (cadastro/Google) e `MatchStatistics::record` (fim de partida). Uma falha na concessão é registrada (`achievement.error`) e **nunca** derruba o fim da partida (savepoint).
- **Idempotência:** `badge_user` único (usuário, conquista) + `insertOrIgnore`; o aviso só nasce se a linha foi criada agora, na mesma transação.
- **Aviso:** notificação `AchievementUnlocked` na tabela `notifications` (a mesma da central e do sino), mais o popup (`AchievementPopup`): vem de `auth.achievement_popups` (não lidas) e é marcado como lido ao exibir, então recarregar não repete. A consulta do popup só roda quando há não lidas.
- **Perfil:** `AchievementsPanel` (destaques até 3 + todas, bloqueadas com critério, detalhe em modal). Destaques: `PUT /profile/conquistas/destaques`, validado no servidor (só as suas, desbloqueadas, no máximo `achievements.featured_limit`), guardado em `badge_user.featured_order`.
- **Visual:** `AchievementBadge` é SVG + CSS (sem imagens nem bibliotecas): 6 materiais, 7 símbolos, brilho nas raridades altas e flutuação na lendária; `prefers-reduced-motion` desliga ambos.
- **Custo:** 2 consultas pequenas ao montar o perfil; 1 consulta por evento de concessão; nenhuma tabela nova (só a coluna `featured_order`).

## 14. Contas temporárias (auditoria)

| Tema | Achado | Situação |
| --- | --- | --- |
| Token | 256 bits aleatórios, só o hash no banco, cookie HttpOnly, SameSite=Lax | seguro (já testado) |
| Falsificação | token forjado, adivinhado, maiúsculas, vazio ou após sair é recusado | testado (`GuestLifecycleTest`) |
| Renovação | janela deslizante **sem teto**: com requisições o perfil (e o nome) vivia para sempre | **corrigido**: teto absoluto `GUEST_MAX_DAYS` (3) contado da criação |
| Isolamento | visitante não lê nem altera dados de outra pessoa; páginas de conta redirecionam | testado |
| Conversão em conta | **não existe** (a coluna `converted_user_id` não é usada). Cadastrar com um cookie de visitante cria uma conta separada e não funde nem assume nada | decisão documentada; testado |
| Exclusão e órfãos | apagar o visitante remove as participações (cascata) e **não** apaga a partida encerrada nem resultados (visitantes não entram em `match_results`) | testado |
| Concorrência de nome | `GuestIdentity::create` serializa por nome e o índice único decide | já testado |
| Limpeza | `guests:prune` (15 min) nunca apaga quem está em partida em andamento ou é anfitrião de sala com outros humanos | já testado |

**Vulnerabilidades desta rodada:** uma de gravidade **baixa/média** (renovação sem teto permitia manter nome e perfil temporário indefinidamente, com um script que só fizesse requisições). Corrigida e testada. Login, cadastro, código de e-mail, Google e recuperação de senha foram cobertos nas rodadas
anteriores (`Security/*`, `EmailCodeVerificationTest`, `GoogleAuthenticationTest`, `OpsLogAndGoogleFailureTest`) e continuam passando. Pontos aceitos: o cadastro informa quando um e-mail já existe (trade-off de usabilidade, protegido por `throttle`).

## 15. Padrão visual de botões e etiquetas

Regra aplicada: **o que é clicável parece clicável; o que é só informação não parece botão.**

| Tipo | Aparência | Onde |
| --- | --- | --- |
| Primário | fundo verde-lima, borda 2–3 px e sombra dura, texto em caixa alta | ação principal da tela (`PrimaryButton`, `auth-submit`, "Criar sala") |
| Secundário | fundo branco, borda escura, sombra dura menor | complementar (`SecondaryButton`, "Fechar") |
| Roxo | fundo `#464498`, texto branco | ação de destaque do perfil (ex.: "Destacar no perfil") |
| Destrutivo | vermelho | excluir/sair (`DangerButton`) |
| Etiqueta/selo (não clicável) | **plana**: borda de 1 px, sem sombra e sem efeito de hover | título, selo, "Líder", selo de desenvolvedor no perfil |
| Estados | `disabled:opacity-*`, foco com contorno visível (`focus-visible`), hover só em quem clica | todos os botões novos |

Nesta rodada: as etiquetas do perfil (título, selo principal, "Líder" e selo de desenvolvedor) deixaram de usar a sombra dura e a borda grossa dos botões. **Não** foi feita uma substituição global de classes; os demais botões (login, cadastro, painel, sala, rankings) seguem como estavam e uma varredura completa fica como próxima etapa.
