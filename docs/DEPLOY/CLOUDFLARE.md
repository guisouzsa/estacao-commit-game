# Cloudflare Workers + Durable Objects (opcional)

> **Estado:** implementado e testado no código, **desligado por padrão**. Enquanto `REALTIME_DRIVER` e `GAME_ENGINE` não forem
> mudados no Render, o site usa **Reverb** (avisos) e o **motor PHP** (partidas). Confirme no painel do Render o que está ligado.

Voltar ao [índice](../README.md). Detalhes técnicos do Worker (segurança, limites, fases): [`worker/README.md`](../../worker/README.md).
Variáveis completas: [`VARIAVEIS-DE-AMBIENTE.md`](VARIAVEIS-DE-AMBIENTE.md), seção 8.

## 1. Para que serve

O Render Free tem só **2 processos Apache** e cada WebSocket aberto ocupa um deles por inteiro. Levar o tempo real (e, se quiser,
o motor das partidas) para a Cloudflare tira os WebSockets do servidor do Render.

| Fase | Variável que liga | O que passa a rodar na Cloudflare |
| --- | --- | --- |
| 1 | `REALTIME_DRIVER=cloudflare` | os **avisos em tempo real** (`room.state.changed`, `user.notices.changed`) saem de um Durable Object (`ChannelHub`) em vez do Reverb |
| 2 | `GAME_ENGINE=cloudflare` | o **motor da partida** (turnos, bots, timeout) roda em um Durable Object (`MatchRoom`); o Laravel guarda só o resultado |

O Laravel continua dono de contas, salas, convites, perfis, ranking e da página Inertia da partida. Cada partida fica no motor em
que nasceu: ligar/desligar a flag não afeta partidas em andamento.

## 2. Arquivos

| Caminho | Função |
| --- | --- |
| `worker/wrangler.jsonc` | nome do Worker, entrada, bindings, migrations, variáveis públicas |
| `worker/src/index.ts` | rotas HTTP/WebSocket, autenticação dos tokens e avisos |
| `worker/src/channel.ts` | `ChannelHub` (um Durable Object por canal, com hibernação) |
| `worker/src/match.ts` | `MatchRoom` (partida: estado, alarmes, bots, *outbox* de callbacks) |
| `worker/src/engine.ts` | motor do jogo em TypeScript (paridade com o PHP) |
| `worker/.dev.vars.example` | segredos de exemplo para desenvolvimento local |
| `app/Services/CloudflareRealtime.php` | Laravel → Worker (avisos assinados) |
| `app/Services/CloudflareMatchGateway.php` | Laravel → Worker (partidas) e trava de paridade |
| `app/Http/Controllers/RealtimeTokenController.php` | emite o token de conexão do navegador |
| `app/Http/Controllers/RealtimeCallbackController.php` | recebe o retorno do Worker (`match_finished`, `participant_replaced`) |
| `config/realtime.php` | lê as variáveis `REALTIME_*` e `GAME_ENGINE` |

## 3. Configuração (resumo)

**Secrets (Worker e Render, mesmo valor nos dois lados):** `REALTIME_TOKEN_SECRET`, `REALTIME_NOTIFY_SECRET`, `REALTIME_CALLBACK_SECRET`.
**Variáveis públicas do Worker** (`worker/wrangler.jsonc`): `APP_ORIGIN`, `LARAVEL_CALLBACK_URL`, `ALLOWED_ORIGINS`.
**Bindings:** `CHANNEL` → `ChannelHub`; `MATCH` → `MatchRoom` (SQLite). **Render:** `REALTIME_DRIVER`, `REALTIME_WORKER_URL`, `GAME_ENGINE`,
`GAME_ENGINE_PARITY_ACK`.

## 4. Publicar (somente com autorização de quem administra a conta)

```bash
cd worker
npm install
npx wrangler login
npx wrangler secret put REALTIME_TOKEN_SECRET
npx wrangler secret put REALTIME_NOTIFY_SECRET
npx wrangler secret put REALTIME_CALLBACK_SECRET
npm run deploy                      # imprime a URL pública (https://....workers.dev)
```

Depois, no Render (**Environment**):

1. `REALTIME_WORKER_URL` = a URL impressa; os três segredos (mesmos valores); `REALTIME_DRIVER=cloudflare`. Salve e aguarde o reinício.
2. Teste o lobby e uma partida (avisos chegam sem polling).
3. Só então, para o motor: `GAME_ENGINE=cloudflare` e `GAME_ENGINE_PARITY_ACK` com o valor de `CloudflareMatchGateway::PARITY_VERSION`
   (a trava existe para só liberar depois de os testes de paridade PHP × Worker passarem: `cd worker && npm test` e `php artisan test --filter=EngineParityTest`).

**Ordem segura:** publicar o Worker → `REALTIME_DRIVER=cloudflare` → testar → `GAME_ENGINE=cloudflare`.

## 5. Voltar ao Reverb / ao motor PHP

- Avisos: `REALTIME_DRIVER=reverb` (ou remova a variável).
- Motor: `GAME_ENGINE=laravel`. Partidas novas voltam ao PHP; as em andamento terminam onde nasceram.

Nada do Reverb foi removido.

## 6. Limites do plano gratuito da Cloudflare (docs oficiais de nov/2026)

Durable Objects Free: 100 mil requisições/dia (mensagens WebSocket recebidas contam 20:1), 13 mil GB-s/dia, 100 mil linhas escritas/dia,
5 milhões lidas/dia, só DOs com SQLite. Passou do limite do dia: as operações daquele tipo **falham** até 00:00 UTC (o jogo continua por polling).
Workers Free: 100 mil requisições/dia.

## 7. Onde ver o que está acontecendo

- Painel da Cloudflare → *Workers & Pages* → o Worker → *Logs* / *Metrics*.
- No Render, eventos de integração em JSON: `integration.failure`.
- No navegador, o indicador **Sincronizado / Reconectando** na partida.
- Teste ao vivo (opcional): `LIVE_WORKER=1 php artisan test --filter=CloudflareLiveTest` com `wrangler dev` rodando (ver o cabeçalho do teste).

## 8. Pendências de ativação

- Publicar o Worker na sua conta e cadastrar os segredos.
- Decidir quando ligar `REALTIME_DRIVER` e `GAME_ENGINE` no Render.
- Atualizar `APP_ORIGIN`, `LARAVEL_CALLBACK_URL` e `ALLOWED_ORIGINS` se o domínio do site mudar.
