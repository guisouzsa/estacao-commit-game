# Estação Commit — tempo real e partidas na Cloudflare (Fases 1 e 2)

Worker + Durable Objects (SQLite-backed, WebSocket Hibernation) que substituem o **Laravel Reverb**
como canal de avisos em tempo real. O Laravel continua dono do estado do jogo (Neon), da autenticação e das regras.

```
Navegador ──(1) POST /realtime/token──▶ Laravel   (autoriza como routes/channels.php)
Navegador ◀──────── token HMAC 60 s, uso único ───┘
Navegador ──(2) wss://worker/ws/<canal>?token ──▶ Worker ─▶ ChannelHub (1 DO por canal, hiberna)
Laravel ──(3) POST /notify/<canal> assinado ──▶ Worker ─▶ ChannelHub ─▶ sockets conectados
```

O aviso tem o mesmo contrato do Reverb (`room.state.changed`, `user.notices.changed`): a página só
recarrega o estado no Laravel. Nenhum estado de jogo é duplicado no Worker.

## Segurança
- Token de conexão: HMAC-SHA256, expira em 60 s, vinculado a UM canal e a UM ator, **uso único** (`jti`).
- Aviso Laravel→Worker: HMAC sobre `timestamp.nonce.sha256(corpo)`, janela de 60 s, nonce anti-replay, corpo ≤ 2 KB.
- `Origin` do WebSocket precisa estar em `ALLOWED_ORIGINS`. Canais validados por regex.
- Segredos só em variáveis do servidor/Worker — nunca em `VITE_*`.

## Variáveis e secrets (valores NÃO versionados)
| Onde | Nome | Para quê |
|---|---|---|
| Worker (secret) | `REALTIME_TOKEN_SECRET` | verifica tokens de conexão |
| Worker (secret) | `REALTIME_NOTIFY_SECRET` | verifica avisos do Laravel |
| Worker (var) | `ALLOWED_ORIGINS` | origens do site (em `wrangler.jsonc`) |
| Laravel | `REALTIME_DRIVER` | `reverb` (padrão) ou `cloudflare` |
| Laravel | `REALTIME_WORKER_URL` | URL pública do Worker |
| Laravel | `REALTIME_TOKEN_SECRET`, `REALTIME_NOTIFY_SECRET` | mesmos valores do Worker |

Gere cada segredo com: `openssl rand -hex 32`.

## Local
```powershell
cd worker; npm install
copy .dev.vars.example .dev.vars   # edite os segredos
npm run dev                        # http://127.0.0.1:8787
npm test                           # sobe o Worker real (workerd) e testa HTTP/WebSocket + interop com o PHP
```

## Publicar (só com sua autorização)
```powershell
npx wrangler login
npx wrangler secret put REALTIME_TOKEN_SECRET
npx wrangler secret put REALTIME_NOTIFY_SECRET
npm run deploy
```
Depois, no Render: defina `REALTIME_WORKER_URL`, os dois segredos e `REALTIME_DRIVER=cloudflare`.

## Voltar ao Reverb
`REALTIME_DRIVER=reverb` (ou remover a variável). Nada do Reverb foi removido.

## Limites do plano gratuito (docs oficiais, nov/2026)
Durable Objects Free: 100 mil requisições/dia (mensagens WebSocket recebidas contam 20:1; envio e pings não),
13 mil GB-s/dia, 100 mil linhas escritas/dia, 5 milhões lidas/dia, só DOs com SQLite. Passou do limite do dia:
as operações daquele tipo **falham** até 00:00 UTC (o jogo continua por polling de 15 s do front).
Workers Free: 100 mil requisições/dia. Cada conexão de jogador = 1 requisição; cada aviso do Laravel = 1.

---

# Fase 2 — partidas no Durable Object (`GAME_ENGINE=cloudflare`)

```
Navegador ──POST /salas/{id}/partida/acoes──▶ Laravel ──(assinado)──▶ Worker ─▶ MatchRoom (1 DO por partida)
                                                                         │  valida turno/regras, roda bots, SQLite
Navegador ◀── room.state.changed (WebSocket, ChannelHub) ◀───────────────┤  Alarm: humano parado => bot assume
Laravel (Neon) ◀── callback assinado: match_finished / participant_replaced ┘  (outbox com retentativa)
```
- **No Durable Object:** estado completo da partida (baralho, mãos, percursos, turno, versão, histórico), ids de ações
  já aplicadas (idempotência), bots, prazo de inatividade. Uma ação por vez (serialização do DO) + `version` => sem jogadas conflitantes.
- **No Neon (inalterado):** contas, perfis, salas, convites, participantes, histórico/placar. A partida ganha só um registro leve
  (`state = {"engine":"cloudflare"}`) e, ao terminar, vencedor e estatísticas (callback assinado, idempotente).
- **Continua no Laravel:** lobby (criar sala, convite, entrar/sair), expiração de 5 min da sala em espera (scheduler),
  autenticação, e a página Inertia `Games/Show` (props idênticas; o front não mudou).
- **Cada partida fica no motor em que nasceu:** ligar/desligar a flag não afeta partidas em andamento.
- Sem timers: o único alarme é o prazo da vez de um humano (ou a retentativa de um callback pendente).

Novas variáveis: Worker `REALTIME_CALLBACK_SECRET` (secret), `LARAVEL_CALLBACK_URL` (var, em `wrangler.jsonc`);
Laravel `GAME_ENGINE` (`laravel`|`cloudflare`) e `REALTIME_CALLBACK_SECRET` (mesmo valor do Worker).
Ordem segura de ativação: publicar o Worker → `REALTIME_DRIVER=cloudflare` (Fase 1) → testar → `GAME_ENGINE=cloudflare`.
Voltar: `GAME_ENGINE=laravel` (partidas novas voltam ao PHP).
