# Manutenção: tecnologias, variáveis e troca de serviços

Este documento é o guia de **manutenção** do Estação Commit. Ele responde a três perguntas:

1. **O que** o site usa (tecnologias e serviços externos)?
2. **Quais variáveis** precisam existir para a aplicação funcionar, e onde cada uma é configurada?
3. **O que mudar** quando for trocar de serviço (hospedagem, banco, e-mail, Google, tempo real)?

> **Regra de ouro:** este arquivo guarda só **nomes** e exemplos fictícios. Valores reais (senhas, chaves, tokens) ficam
> apenas no painel de cada serviço, nunca no repositório, em issues, em prints ou em conversas.

Voltar ao [README](../../README.md). Fluxos de autenticação, regras do multiplayer, quedas de serviço e investigação: [`ARQUITETURA.md`](../ARQUITETURA.md).

---

## 1. Que tipo de site é

O Estação Commit é uma **aplicação web monolítica** em Laravel que entrega páginas React por meio do Inertia.js. Não existe uma
API separada para o front: o servidor devolve a página já com os dados. O jogo roda em partidas de quatro participantes, com
atualização em tempo real por WebSocket e, se o WebSocket falhar, por consulta periódica (polling).

```text
Navegador (React + Inertia)
      │  HTTPS                      WSS (avisos em tempo real)
      ▼                                   ▼
Render (Docker: Apache + PHP + Supervisor)      Reverb (no mesmo contêiner)
      │                                    ou  Cloudflare Worker + Durable Objects
      ├──► Neon (PostgreSQL): contas, salas, partidas, resultados, sessões, cache, fila
      ├──► Brevo (API HTTPS): e-mails de código e de recuperação de senha
      └──► Google OAuth: "Entrar com Google"
```

Pontos importantes da arquitetura:

- **Tudo roda em um único contêiner** no Render: Apache (PHP), Reverb, agendador (scheduler) e um vigia de processos, todos
  controlados pelo Supervisor.
- **Sessões, cache e fila usam o banco** (`database`). Não há Redis nem disco persistente. Por isso as imagens de perfil são
  guardadas no PostgreSQL e o sistema de arquivos do contêiner pode ser apagado a cada deploy sem perda.
- **O estado do jogo é do Laravel/PostgreSQL.** O tempo real só avisa "recarregue o estado".
- **Plano gratuito:** o serviço pode "dormir" por inatividade. O primeiro acesso pode demorar enquanto o Render e o Neon acordam.

---

## 2. Tecnologias usadas

### Aplicação

| Camada | Tecnologia | Uso |
| --- | --- | --- |
| Linguagem do servidor | PHP 8.2+ | regras de jogo, autenticação, rotas |
| Framework | Laravel 12 | rotas, Eloquent (banco), filas, agendador, e-mail, validação |
| Ponte servidor ↔ React | Inertia.js 2 + Ziggy | páginas React sem API duplicada; rotas nomeadas no front |
| Login social | Laravel Socialite | "Entrar com Google" |
| Tokens de API | Laravel Sanctum | autenticação auxiliar |
| Tempo real (padrão) | Laravel Reverb + Laravel Echo + pusher-js | WebSocket para `room.state.changed` e `user.notices.changed` |
| Interface | React 18 + TypeScript 5 | telas, jogo, perfis |
| Estilo | Tailwind CSS 3 | visual |
| Animações e menus | Framer Motion, Headless UI | transições, listas e seletores |
| Build do front | Vite 7 | gera `public/build` |

### Dados e infraestrutura

| Tecnologia | Uso |
| --- | --- |
| PostgreSQL (Neon, com PgBouncer) | banco de produção; SQLite em memória nos testes |
| Docker (multi-stage) | imagem de produção: Node compila o front, PHP 8.2 + Apache serve |
| Apache (prefork) | serve o Laravel, termina WSS e encaminha `/app/` ao Reverb interno |
| Supervisor | mantém Apache, Reverb, scheduler e vigia essencial |
| Render (Web Service, runtime Docker) | hospedagem atual |
| Cloudflare Workers + Durable Objects (opcional) | alternativa ao Reverb e motor de partidas em [`worker/`](../../worker/README.md) |
| Brevo (API transacional) | envio dos e-mails (o Render Free bloqueia SMTP) |
| Google Cloud (OAuth 2.0) | credenciais do login com Google |
| GitHub | código-fonte; o Render faz deploy a cada push na `main` |

### Qualidade

| Ferramenta | Comando | Cobre |
| --- | --- | --- |
| PHPUnit 11 | `php artisan test` | regras, segurança, fluxos HTTP |
| Node Test Runner | `npm run test:js` | bibliotecas do front |
| Vitest (Worker real) | `cd worker && npx vitest run` | Worker e paridade com o motor PHP |
| TypeScript | `npx tsc --noEmit` | tipos do front |
| Laravel Pint / ESLint | `vendor/bin/pint --test`, `npm run lint` | estilo |

---

## 3. Variáveis de ambiente

> **Mudou de lugar.** A lista completa (todas as variáveis, por categoria, com padrões, segredos 🔒, as de build `VITE_*`, as do
> Worker da Cloudflare e o pacote para colar no Render) está em
> [`../DEPLOY/VARIAVEIS-DE-AMBIENTE.md`](../DEPLOY/VARIAVEIS-DE-AMBIENTE.md). O modelo em formato `.env` está em
> [`.env.example`](../../.env.example).

Onde configurar: **local** no `.env`; **Render** em *Environment* do serviço; **Worker** com `npx wrangler secret put` e
`worker/wrangler.jsonc`; variáveis `VITE_*` entram **no build** (mudou? faça deploy com rebuild).

---

## 4. Rotinas e processos do contêiner

O `docker-entrypoint.sh` prepara caches, cria o link de storage e roda `php artisan migrate --force` (até cinco tentativas, porque
o Neon pode estar acordando). Depois o Supervisor mantém:

| Processo | Função |
| --- | --- |
| `apache` | serve o site e encaminha `/app/` ao Reverb |
| `reverb` | WebSocket interno |
| `scheduler` | roda as rotinas abaixo |
| `essential-watchdog` | reinicia processos essenciais que caírem |

| Frequência | Comando | Finalidade |
| --- | --- | --- |
| a cada minuto | `rooms:expire-waiting` | remove salas vazias após o prazo |
| a cada minuto | `rooms:purge-finished` | remove salas encerradas, preservando resultados |
| a cada minuto | `games:sweep` | resolve timeouts e vezes de bot paradas, mesmo sem ninguém na página |
| a cada 15 min | `guests:prune` | remove visitantes inativos que não estão jogando |

Como testar, ver logs, identificar duplicidade/travamento e recuperar: [`../DEPLOY/CRON-JOBS.md`](../DEPLOY/CRON-JOBS.md).

---

## 5. Trocando de serviço

Antes de qualquer troca: **faça backup do banco** e anote os valores atuais das variáveis em um gerenciador de senhas.

### 5.1 Trocar de hospedagem (por exemplo, sair do Render)

O projeto é um contêiner Docker padrão, então qualquer plataforma que rode Docker serve (Fly.io, Railway, VPS, etc.).

1. Crie o serviço apontando para o repositório e para o [`Dockerfile`](../../Dockerfile). A imagem escuta na porta definida em `PORT`.
2. Copie **todas** as variáveis de [`VARIAVEIS-DE-AMBIENTE.md`](../DEPLOY/VARIAVEIS-DE-AMBIENTE.md). Não esqueça as `VITE_*` (devem existir **no build**).
3. Defina `APP_URL` com o novo endereço público.
4. Faça o deploy e confirme nos logs: migrations executadas, Apache e Reverb em "RUNNING".
5. Atualize tudo que guarda o endereço antigo:
   - **Google Cloud:** URI de redirecionamento e `GOOGLE_REDIRECT_URI` (ver 5.4);
   - **Cloudflare Worker (se usado):** `APP_ORIGIN`, `LARAVEL_CALLBACK_URL` e `ALLOWED_ORIGINS` em `worker/wrangler.jsonc`, e novo `npm run deploy`;
   - **Brevo:** nada muda (o remetente é independente do site);
   - `VITE_REVERB_HOST` e `REVERB_HOST` para o novo host.
6. Teste: abrir o site, criar conta, entrar com Google, receber o código por e-mail, criar sala e jogar uma partida com bots.
7. Só então aponte o domínio e desligue o serviço antigo.

Requisito da nova hospedagem: aceitar **WebSocket** (WSS) pela mesma porta pública e conexões de saída HTTPS (Brevo, Google, Neon).

### 5.2 Trocar de banco de dados

1. Crie o novo PostgreSQL (14 ou superior) e exporte o antigo com `pg_dump`.
2. Importe no novo e aponte `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` e `DB_SSLMODE`.
3. Se o novo banco **não** usar PgBouncer, a conexão funciona do mesmo jeito. Remova `DB_ENDPOINT` se tiver sido configurado só por causa do Neon.
4. No primeiro deploy, o entrypoint roda as migrations pendentes. Conferir nos logs.
5. Teste login, uma partida completa e o ranking.

Sessões ficam no banco, então **todo mundo será deslogado** na troca.

### 5.3 Trocar o serviço de e-mail

Hoje o envio usa a classe [`BrevoTransport`](../../app/Mail/BrevoTransport.php) (API HTTPS). Para trocar:

- **Outro serviço por API (Resend, Postmark, Mailgun, SES):** o projeto já inclui o Resend (`MAIL_MAILER=resend-http` + `RESEND_API_KEY`, ou `resilient` para usar os dois em sequência). Postmark e SES exigem os pacotes do Laravel para esses transportes. Os templates não mudam.
- **SMTP:** funciona fora do Render Free (que bloqueia as portas SMTP). Use `MAIL_MAILER=smtp` com `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`.
- Em qualquer caso, **`MAIL_FROM_ADDRESS` precisa ser um remetente aprovado no novo serviço**.
- Para melhorar a entrega (menos spam), use um **domínio próprio** e configure SPF, DKIM e DMARC no provedor. Endereços Gmail como remetente têm entrega pior.
- Trocar a chave do Brevo: gere uma nova, atualize `BREVO_API_KEY` e **apague a antiga** no painel.

### 5.4 Trocar o domínio ou a URL do site

1. `APP_URL`, `GOOGLE_REDIRECT_URI`, `REVERB_HOST`, `VITE_REVERB_HOST`.
2. **Google Cloud → Clientes → seu cliente → URIs de redirecionamento:** adicione `https://NOVO-DOMINIO/auth/google/callback`.
3. Worker da Cloudflare (se usado): `wrangler.jsonc` e novo deploy.
4. Rebuild do front (as `VITE_*` mudaram).

### 5.5 Trocar a conta do Google

Crie o projeto e o cliente OAuth na nova conta (Google Auth Platform → Clientes → Criar cliente → Aplicativo da Web), informe a URI de
redirecionamento, **publique o app** (Público → Publicar), e atualize `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET`. Contas já
criadas pelo Google continuam funcionando porque são encontradas pelo e-mail.

### 5.6 Alternar entre Reverb e Cloudflare

- **Para Cloudflare:** publicar o Worker, cadastrar segredos iguais nos dois lados e definir `REALTIME_DRIVER=cloudflare`.
- **De volta ao Reverb:** `REALTIME_DRIVER=reverb` (ou remover). Nada do Reverb foi removido do código.
- O motor de partidas na Cloudflare tem uma trava extra (`GAME_ENGINE_PARITY_ACK`). Sem ela, o motor PHP continua valendo.

### 5.7 Girar (trocar) um segredo vazado

| Segredo | Como trocar | Efeito |
| --- | --- | --- |
| `APP_KEY` | gerar nova e atualizar | todos são deslogados |
| `BREVO_API_KEY` | gerar nova no Brevo, atualizar, apagar a antiga | nenhum |
| `GOOGLE_CLIENT_SECRET` | "Adicionar segredo" no cliente, atualizar, desativar o antigo | nenhum |
| `DB_PASSWORD` | alterar no Neon e no Render ao mesmo tempo | breve indisponibilidade |
| `REVERB_APP_SECRET` | atualizar e fazer novo deploy | conexões abertas reconectam |
| `REALTIME_*_SECRET` | atualizar **Render e Worker juntos** | reconexão dos jogadores |

---

## 6. Solução de problemas

| Sintoma | Causa provável | O que fazer |
| --- | --- | --- |
| Página de erro com **código de suporte** | exceção no servidor | procure o código nos **Logs** do Render; a linha de erro está junto |
| Botão do Google não aparece | falta alguma das 3 variáveis `GOOGLE_*` | conferir no Render |
| Google: `redirect_uri_mismatch` | URI diferente da cadastrada | copiar exatamente a mesma nos dois lados, sem barra final |
| Google: "app em teste" | app não publicado | Google Auth Platform → Público → Publicar |
| Código não chega | remetente não verificado, chave errada ou spam | Brevo → Transacional → Logs mostra entregue/recusado |
| Logo some no e-mail | `APP_URL` errada ou cliente bloqueou imagens | conferir `APP_URL`; clientes costumam bloquear imagens de remetentes novos |
| Tela do site em branco após deploy | `VITE_*` errada ou build antigo | corrigir e fazer deploy com rebuild |
| Jogo não atualiza em tempo real | WebSocket bloqueado ou limite do Reverb | o front cai para polling; ver `REVERB_APP_MAX_CONNECTIONS` e logs |
| Primeiro acesso lento | plano gratuito dormindo | normal; Render e Neon acordam |
| Deploy não sobe | migration falhou | logs do entrypoint mostram a tentativa (até 5) |

---

## 7. Checklist rápido de manutenção

- [ ] Rodar `composer audit` e `npm audit` a cada mês.
- [ ] Conferir o limite de e-mails e a entrega no painel do Brevo.
- [ ] Revisar `PROFILE_ADMIN_EMAILS` quando alguém sair da equipe.
- [ ] Manter backup do banco (o plano gratuito do Neon tem janela curta de restauração).
- [ ] Depois de alterar variáveis `VITE_*`: deploy com **rebuild**.
- [ ] Depois de trocar de hospedagem ou domínio: refazer o teste completo (item 7 da seção 5.1).
- [ ] Nunca versionar `.env`, `.dev.vars`, dumps ou capturas de tela com chaves.
