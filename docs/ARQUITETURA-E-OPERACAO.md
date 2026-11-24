# Arquitetura, autenticação, multiplayer e operação

Complemento de [`MANUTENCAO.md`](MANUTENCAO.md) (tecnologias e variáveis). Aqui estão **os fluxos reais do código**, o que
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

Para trocar de provedor: ver `MANUTENCAO.md`, seção 5.3.

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

- O relógio é do **servidor**: `game_matches.turn_started_at`, reiniciado em `GameEngine::advance` a cada nova vez. O navegador só exibe a contagem (`turn_seconds_left`, limitada ao máximo).
- `GameInactivityService::sweep` roda quando alguém abre ou recarrega a partida (o front sincroniza periodicamente). Passou do prazo: a vez é **pulada** e `room_participants.timeout_streak` aumenta (duas verificações no mesmo prazo não contam duas vezes).
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
inatividade). Enquanto ele não receber as mesmas regras e a fixture `tests/parity/` não for regerada, **não ative** o motor da Cloudflare.

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
