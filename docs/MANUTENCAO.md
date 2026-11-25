# Manutenção: tecnologias, variáveis e troca de serviços

Este documento é o guia de **manutenção** do Estação Commit. Ele responde a três perguntas:

1. **O que** o site usa (tecnologias e serviços externos)?
2. **Quais variáveis** precisam existir para a aplicação funcionar, e onde cada uma é configurada?
3. **O que mudar** quando for trocar de serviço (hospedagem, banco, e-mail, Google, tempo real)?

> **Regra de ouro:** este arquivo guarda só **nomes** e exemplos fictícios. Valores reais (senhas, chaves, tokens) ficam
> apenas no painel de cada serviço, nunca no repositório, em issues, em prints ou em conversas.

Voltar ao [README](../README.md). Fluxos de autenticação, regras do multiplayer, quedas de serviço e investigação: [`ARQUITETURA-E-OPERACAO.md`](ARQUITETURA-E-OPERACAO.md).

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
| Cloudflare Workers + Durable Objects (opcional) | alternativa ao Reverb e motor de partidas em [`worker/`](../worker/README.md) |
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

Onde configurar cada uma:

| Onde | Como |
| --- | --- |
| **Local** | arquivo `.env` (copie de `.env.example`; nunca versione) |
| **Render** | *Environment* do Web Service |
| **Worker da Cloudflare** | `npx wrangler secret put NOME` (segredos) e `worker/wrangler.jsonc` (variáveis públicas) |
| **Build do Docker** | variáveis `VITE_*` entram no front **durante o build** (ver 3.9) |

Legenda da coluna **Segredo**: 🔒 = nunca em chat, commit ou print. Um valor 🔒 vazado deve ser trocado.

### 3.1 Aplicação (obrigatórias)

| Variável | Segredo | Exemplo / padrão | Para quê |
| --- | :-: | --- | --- |
| `APP_NAME` | | `Estação Commit` | nome em e-mails e título |
| `APP_ENV` | | `production` (Docker já define) | ambiente |
| `APP_DEBUG` | | `false` (Docker já define) | **nunca `true` em produção** |
| `APP_KEY` | 🔒 | gerada por `php artisan key:generate` | criptografia de sessão e cookies; trocar derruba as sessões |
| `APP_URL` | | `https://estacaocommit.onrender.com` | URL pública: links e logo dos e-mails, callback do Google |
| `APP_LOCALE` | | `pt_BR` | idioma |
| `LOG_CHANNEL` | | `stderr` (Docker já define) | logs aparecem no painel do Render |
| `LOG_LEVEL` | | `debug` ou `error` | detalhe dos logs |
| `BCRYPT_ROUNDS` | | `12` | custo do hash de senha |

### 3.2 Banco de dados (Neon / PostgreSQL)

| Variável | Segredo | Exemplo / padrão | Para quê |
| --- | :-: | --- | --- |
| `DB_CONNECTION` | | `pgsql` | driver |
| `DB_HOST` | | `ep-xxxx-pooler.regiao.aws.neon.tech` | host (use o *pooler* do painel do Neon) |
| `DB_PORT` | | `5432` | porta |
| `DB_DATABASE` | | `neondb` | nome do banco |
| `DB_USERNAME` | 🔒 | | usuário |
| `DB_PASSWORD` | 🔒 | | senha |
| `DB_SSLMODE` | | `require` | SSL obrigatório no Neon |
| `DB_ENDPOINT` | | `ep-xxxx` | só se o cliente PostgreSQL não envia SNI (ver `.env.example`) |
| `DB_URL` | 🔒 | | alternativa que junta todos os campos acima |

### 3.3 Sessão, cache e fila

| Variável | Padrão | Observação |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | guarda sessões na tabela `sessions` |
| `SESSION_LIFETIME` | `120` | minutos de inatividade |
| `CACHE_STORE` | `database` | cache na tabela `cache` |
| `QUEUE_CONNECTION` | `database` | fila na tabela `jobs` |
| `CACHE_LIMITER_STORE` | `file` | limitação de requisições (throttle) |
| `FILESYSTEM_DISK` | `local` | não há uploads em disco: imagens vão ao PostgreSQL |

### 3.4 E-mail (Brevo) e verificação por código

| Variável | Segredo | Exemplo / padrão | Para quê |
| --- | :-: | --- | --- |
| `MAIL_MAILER` | | `brevo` ou `resilient` em produção; `log` local | `resilient` tenta Brevo e, se ele cair, o Resend (ver `ARQUITETURA-E-OPERACAO.md`) |
| `RESEND_API_KEY` | 🔒 | | provedor alternativo (opcional; sem domínio verificado só envia ao dono da conta) |
| `MAIL_HTTP_TIMEOUT` | | `8` | segundos esperando a API de e-mail |
| `BREVO_API_KEY` | 🔒 | chave restrita a "E-mails transacionais" | envia pela API HTTPS |
| `MAIL_FROM_ADDRESS` | | `estacaocommit@gmail.com` | **tem que ser um remetente verificado no Brevo** |
| `MAIL_FROM_NAME` | | `Estação Commit` | nome do remetente |
| `EMAIL_VERIFICATION` | | `false` (padrão) / `true` | liga o código de 6 dígitos no cadastro |
| `EMAIL_CODE_TTL` | | `10` | minutos de validade do código |
| `EMAIL_CODE_ATTEMPTS` | | `5` | tentativas antes de pedir novo código |
| `EMAIL_CODE_COOLDOWN` | | `60` | segundos entre reenvios |
| `EMAIL_DNS_CHECK` | | `true` | rejeita domínios de e-mail que não recebem mensagens |

Como funciona: com `EMAIL_VERIFICATION=true`, o cadastro guarda os dados na sessão, envia o código e **só cria a conta depois
que o código é confirmado**. O login continua sendo só e-mail e senha. O e-mail de recuperação de senha usa o mesmo mailer.

Variáveis de outros provedores (`POSTMARK_API_KEY`, `MAIL_HOST`/`MAIL_PORT` de SMTP, `AWS_*` do SES) existem
no `config/` mas **não são usadas** hoje. Ver 5.3 para trocar de provedor.

### 3.5 Login com Google

| Variável | Segredo | Exemplo | Para quê |
| --- | :-: | --- | --- |
| `GOOGLE_CLIENT_ID` | 🔒 | `1234-abc.apps.googleusercontent.com` | identifica o app no Google |
| `GOOGLE_CLIENT_SECRET` | 🔒 | | segredo do cliente OAuth |
| `GOOGLE_REDIRECT_URI` | | `https://estacaocommit.onrender.com/auth/google/callback` | **idêntica** à cadastrada no Google Cloud |

O botão "Continuar com Google" só aparece quando as três estão preenchidas.

### 3.6 Administração e perfis

| Variável | Exemplo | Para quê |
| --- | --- | --- |
| `PROFILE_ADMIN_EMAILS` | `voce@exemplo.com,outro@exemplo.com` | e-mails (verificados) que podem atribuir selos de desenvolvedor. Vazio = ninguém |

### 3.7 Regras de jogo e interface

| Variável | Padrão | Para quê |
| --- | --- | --- |
| `GAME_TURN_TIMEOUT_SECONDS` | `25` | duração da vez de um humano (menos de 30 s); passou, a vez é pulada |
| `GAME_MAX_CONSECUTIVE_TIMEOUTS` | `3` | timeouts seguidos até o jogador sair da sala |
| `GAME_BOT_DELAY_MS` | `700` | pausa do bot antes de jogar |
| `GAME_BOT_STALL_SECONDS` | `8` | vez de bot parada há mais que isso é jogada pelo servidor (`games:sweep`) |
| `GAME_SWEEP_BATCH` | `50` | máximo de partidas verificadas por execução do `games:sweep` |
| `FINISHED_ROOM_TTL_MINUTES` | `2` | quanto tempo a sala encerrada fica visível antes da limpeza |
| `GUEST_TTL_HOURS` | `6` | validade da conta temporária (visitante) |
| `UI_DEFER_LISTS` | `true` | listas de notificações/seguidores carregam depois, com esqueleto |

### 3.8 Tempo real

**Opção A, Reverb (padrão, dentro do contêiner):**

| Variável | Segredo | Exemplo | Para quê |
| --- | :-: | --- | --- |
| `BROADCAST_CONNECTION` | | `reverb` | driver de broadcast |
| `REALTIME_DRIVER` | | `reverb` | escolhe Reverb ou Cloudflare |
| `REVERB_APP_ID` | | `gamehub` | identificador do app Reverb |
| `REVERB_APP_KEY` | | chave pública | vai também ao navegador (via `VITE_`) |
| `REVERB_APP_SECRET` | 🔒 | | **nunca** repetir em `VITE_*` |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | | `estacaocommit.onrender.com` / `443` / `https` | endereço público |
| `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` | | `127.0.0.1` / `8080` | onde o Reverb escuta dentro do contêiner |
| `REVERB_PUBLISH_HOST/PORT/SCHEME` | | `127.0.0.1` / `8080` / `http` | endereço **interno** para o Laravel publicar eventos (o Docker já define) |
| `REVERB_APP_MAX_CONNECTIONS` | | `25` | conexões simultâneas; não aumente sem medir a RAM (Render Free: 512 MB) |

**Opção B, Cloudflare (opcional):**

| Onde | Variável | Segredo | Para quê |
| --- | --- | :-: | --- |
| Render | `REALTIME_DRIVER=cloudflare` | | liga o Worker no lugar do Reverb |
| Render | `REALTIME_WORKER_URL` | | URL pública do Worker (`https://....workers.dev`) |
| Render e Worker | `REALTIME_TOKEN_SECRET` | 🔒 | mesmo valor nos dois; assina o token de conexão |
| Render e Worker | `REALTIME_NOTIFY_SECRET` | 🔒 | mesmo valor nos dois; assina avisos Laravel → Worker |
| Render | `GAME_ENGINE` | | `laravel` (padrão) ou `cloudflare` |
| Render e Worker | `REALTIME_CALLBACK_SECRET` | 🔒 | assina o retorno Worker → Laravel (`/realtime/callback`) |
| Render | `GAME_ENGINE_PARITY_ACK` | | **trava**: só liga o motor da Cloudflare se for igual à versão de paridade do código |
| Worker (`wrangler.jsonc`) | `APP_ORIGIN`, `LARAVEL_CALLBACK_URL`, `ALLOWED_ORIGINS` | | endereços do site; **mudam se o domínio mudar** |

Gere cada segredo com `openssl rand -hex 32`. Detalhes de publicação e retorno ao Reverb em [`worker/README.md`](../worker/README.md).

### 3.9 Variáveis de build (`VITE_*`)

Entram no JavaScript do navegador **na hora do build**. Mudou o valor? Faça **novo deploy com rebuild**; reiniciar não basta.
Nunca coloque segredo nelas.

| Variável | Exemplo em produção |
| --- | --- |
| `VITE_APP_NAME` | `Estação Commit` |
| `VITE_REVERB_APP_KEY` | igual a `REVERB_APP_KEY` |
| `VITE_REVERB_HOST` | `estacaocommit.onrender.com` |
| `VITE_REVERB_PORT` | `443` |
| `VITE_REVERB_SCHEME` | `https` |

### 3.10 Mínimo para o site funcionar

Para subir uma instância nova **sem** e-mail, Google nem Cloudflare, bastam:

```dotenv
APP_KEY=...            # php artisan key:generate --show
APP_URL=https://seu-dominio
DB_CONNECTION=pgsql
DB_HOST=... DB_PORT=5432 DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... DB_SSLMODE=require
REVERB_APP_ID=gamehub
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=seu-dominio  REVERB_PORT=443  REVERB_SCHEME=https
VITE_REVERB_APP_KEY=...  VITE_REVERB_HOST=seu-dominio  VITE_REVERB_PORT=443  VITE_REVERB_SCHEME=https
```

Todo o resto tem padrão seguro ou é opcional (e-mail, Google, Cloudflare, administração).

### 3.11 Pacote completo para o Render hoje

```dotenv
# aplicação
APP_URL=https://estacaocommit.onrender.com
# e-mail
MAIL_MAILER=brevo
BREVO_API_KEY=<segredo>
MAIL_FROM_ADDRESS=<remetente verificado no Brevo>
MAIL_FROM_NAME=Estação Commit
EMAIL_VERIFICATION=true
# google
GOOGLE_CLIENT_ID=<segredo>
GOOGLE_CLIENT_SECRET=<segredo>
GOOGLE_REDIRECT_URI=https://estacaocommit.onrender.com/auth/google/callback
# administração
PROFILE_ADMIN_EMAILS=<e-mails separados por vírgula>
```

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

---

## 5. Trocando de serviço

Antes de qualquer troca: **faça backup do banco** e anote os valores atuais das variáveis em um gerenciador de senhas.

### 5.1 Trocar de hospedagem (por exemplo, sair do Render)

O projeto é um contêiner Docker padrão, então qualquer plataforma que rode Docker serve (Fly.io, Railway, VPS, etc.).

1. Crie o serviço apontando para o repositório e para o [`Dockerfile`](../Dockerfile). A imagem escuta na porta definida em `PORT`.
2. Copie **todas** as variáveis da seção 3. Não esqueça as `VITE_*` (devem existir **no build**).
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

Hoje o envio usa a classe [`BrevoTransport`](../app/Mail/BrevoTransport.php) (API HTTPS). Para trocar:

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
