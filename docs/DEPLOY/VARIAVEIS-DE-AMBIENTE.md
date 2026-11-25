# Variáveis de ambiente do Estação Commit

Guia para configurar o **Render** (e o Worker da Cloudflare, se usado) copiando os nomes daqui. Os nomes foram extraídos do código
(`config/*.php`, `app/`, `bootstrap/`, `routes/`, `Dockerfile`, `docker/`, `worker/`). Para a lista em formato `.env`, veja
[`.env.example`](../../.env.example) na raiz.

> **Regra de ouro:** este arquivo guarda só **nomes** e exemplos fictícios. Valores reais (senhas, chaves, tokens) ficam
> apenas no painel de cada serviço, nunca no repositório, em issues, prints ou conversas. 🔒 = segredo; se vazar, **troque**
> (procedimento em [`../MANUTENCAO/GUIA-DE-MANUTENCAO.md`](../MANUTENCAO/GUIA-DE-MANUTENCAO.md), seção 5.7).

Voltar ao [índice](../README.md).

---

## 0. Como usar este guia no Render

1. Abra o serviço no Render → **Environment**.
2. Para cada variável **obrigatória** (seção 1) crie a entrada com o valor real.
3. Adicione as **opcionais** conforme os recursos que quer ligar (e-mail, Google, Cloudflare).
4. As variáveis `VITE_*` entram no JavaScript **na hora do build**: se mudar uma, faça **Manual Deploy com rebuild** (reiniciar não basta).
   Elas são **opcionais**: sem elas o front usa a chave pública entregue pelo servidor e o endereço do próprio site.
5. O que a imagem Docker já define **não precisa** ser criado (seção 11).

| Onde configurar | Como |
| --- | --- |
| Local | arquivo `.env` (copie de `.env.example`; nunca versione) |
| Render | *Environment* do Web Service |
| Worker da Cloudflare | `npx wrangler secret put NOME` (segredos) e `worker/wrangler.jsonc` (variáveis públicas) |

---

## 1. Obrigatórias para o site funcionar

Para subir uma instância **sem** e-mail, Google nem Cloudflare:

| Variável | Segredo | Exemplo | Para quê |
| --- | :-: | --- | --- |
| `APP_KEY` | 🔒 | gerada por `php artisan key:generate --show` | criptografia de sessão e cookies (trocar derruba as sessões) |
| `APP_URL` | | `https://estacaocommit.onrender.com` | URL pública: links e logo dos e-mails, callback do Google |
| `DB_CONNECTION` | | `pgsql` | driver do banco |
| `DB_HOST` | | `ep-xxxx-pooler.us-east-2.aws.neon.tech` | host do Neon (use o *pooler*) |
| `DB_PORT` | | `5432` | porta |
| `DB_DATABASE` | | `neondb` | nome do banco |
| `DB_USERNAME` | 🔒 | `neondb_owner` | usuário |
| `DB_PASSWORD` | 🔒 | | senha |
| `DB_SSLMODE` | | `require` | SSL obrigatório no Neon (padrão do código é `prefer`) |
| `BROADCAST_CONNECTION` | | `reverb` | driver de broadcast (padrão do código: `null`, que desliga o tempo real) |
| `REVERB_APP_ID` | | `estacaocommit` | identificador do app Reverb |
| `REVERB_APP_KEY` | | texto aleatório (24+ caracteres) | chave **pública** (vai também ao navegador) |
| `REVERB_APP_SECRET` | 🔒 | texto aleatório (32+ caracteres) | **nunca** repetir em `VITE_*` |
| `REVERB_HOST` | | `estacaocommit.onrender.com` | endereço público do site |
| `REVERB_PORT` | | `443` | porta pública |
| `REVERB_SCHEME` | | `https` | protocolo público |

Gere chaves aleatórias com `openssl rand -hex 16` (key) e `openssl rand -hex 32` (secret), ou o equivalente no PowerShell.

---

## 2. Aplicação Laravel

| Variável | Segredo | Padrão do código | Exemplo em produção | Observação |
| --- | :-: | --- | --- | --- |
| `APP_NAME` | | `Laravel` | `Estação Commit` | nome em e-mails e título |
| `APP_ENV` | | `production` | `production` | a imagem Docker já define |
| `APP_DEBUG` | | `false` | `false` | **nunca `true` em produção** |
| `APP_KEY` | 🔒 | vazio | (gerada) | obrigatória |
| `APP_URL` | | `http://localhost` | URL pública com `https` | obrigatória em produção |
| `APP_LOCALE` | | — | — | **não é lida**: o idioma é fixo `pt_BR` em `config/app.php` |
| `APP_FALLBACK_LOCALE` | | `en` | `en` | idioma de reserva |
| `APP_FAKER_LOCALE` | | `en_US` | | só dev/testes |
| `APP_PREVIOUS_KEYS` | 🔒 | vazio | | chaves antigas aceitas ao girar `APP_KEY` |
| `APP_MAINTENANCE_DRIVER` / `APP_MAINTENANCE_STORE` | | `file` / `database` | | modo manutenção |
| `LOG_CHANNEL` | | `stack` | `stderr` | a imagem Docker já define `stderr` (logs no painel do Render) |
| `LOG_STACK` | | `single` | | canais do `stack` |
| `LOG_LEVEL` | | `debug` | `info` ou `error` | detalhe dos logs |
| `LOG_DAILY_DAYS` | | `14` | | só se usar o canal `daily` |
| `LOG_DEPRECATIONS_CHANNEL` | | `null` | | |
| `BCRYPT_ROUNDS` | | `12` | `12` | custo do hash de senha |
| `PROFILE_ADMIN_EMAILS` | | vazio | `voce@exemplo.com,outro@exemplo.com` | e-mails (verificados) que podem atribuir selos de desenvolvedor. Vazio = ninguém |
| `UI_DEFER_LISTS` | | `true` | `true` | listas de notificações/seguidores/ranking carregam depois, com esqueleto |

**Fuso horário (`APP_TIMEZONE`):** não existe variável. O Laravel usa UTC e o ranking usa o fuso fixo `America/Sao_Paulo`, definido em
`config/ranking.php` (não é configurável por variável de ambiente).

---

## 3. Banco de dados (Neon / PostgreSQL)

| Variável | Segredo | Padrão do código | Observação |
| --- | :-: | --- | --- |
| `DB_CONNECTION` | | `sqlite` | use `pgsql` em produção |
| `DB_HOST` | | `127.0.0.1` | host do Neon (`ep-...-pooler...neon.tech`) |
| `DB_PORT` | | `5432` | |
| `DB_DATABASE` | | `laravel` | |
| `DB_USERNAME` | 🔒 | `root` | |
| `DB_PASSWORD` | 🔒 | vazio | |
| `DB_SSLMODE` | | `prefer` | **`require`** no Neon |
| `DB_ENDPOINT` | | vazio | só para clientes PostgreSQL **sem SNI** (comum no PHP para Windows local). Use só o primeiro trecho do host, sem `-pooler`. **Não** é necessário no Render |
| `DB_URL` | 🔒 | vazio | alternativa que junta os campos: `postgresql://USUARIO:SENHA@HOST/BANCO?sslmode=require` |
| `DB_PERSISTENT` | | `false` | `true` reaproveita a conexão entre requisições do mesmo processo Apache. Ligue só medindo (ver [`../ARQUITETURA.md`](../ARQUITETURA.md)) |
| `DB_CHARSET` | | `utf8` | |
| `DB_FOREIGN_KEYS` | | `true` | usado em SQLite (testes) |

**Formato correto no Neon:** copie do painel do Neon (*Connection details*) o host **com `-pooler`** (PgBouncer). O projeto usa
prepares emulados (`PDO::ATTR_EMULATE_PREPARES`) justamente por causa do pooler; veja [`../ARQUITETURA.md`](../ARQUITETURA.md), seção 9.
O SSL é obrigatório: `DB_SSLMODE=require`.

---

## 4. Sessão, cache, fila e arquivos

| Variável | Padrão do código | Observação |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` | sessões na tabela `sessions` |
| `SESSION_LIFETIME` | `120` | minutos de inatividade |
| `SESSION_ENCRYPT` | `false` | |
| `SESSION_PATH` | `/` | |
| `SESSION_DOMAIN` | vazio | deixe vazio (cookie do próprio host) |
| `SESSION_SECURE_COOKIE` | vazio | em HTTPS o Laravel marca como seguro; defina `true` para forçar |
| `SESSION_SAME_SITE` | `lax` | |
| `SESSION_HTTP_ONLY` | `true` | |
| `SESSION_EXPIRE_ON_CLOSE` | `false` | |
| `SESSION_CONNECTION` / `SESSION_TABLE` / `SESSION_STORE` | vazio / `sessions` / vazio | |
| `CACHE_STORE` | `database` | cache e **travas** (`withoutOverlapping`) na tabela `cache`/`cache_locks` |
| `CACHE_PREFIX` | derivado de `APP_NAME` | prefixo das chaves |
| `CACHE_LIMITER_STORE` | `file` | limitação de requisições (throttle) e travas de criação de sala |
| `QUEUE_CONNECTION` | `database` | só padrão do Laravel: **não há worker de fila** hoje |
| `FILESYSTEM_DISK` | `local` | não há uploads em disco: imagens de perfil ficam no PostgreSQL |
| `PHP_CLI_SERVER_WORKERS` | — | só desenvolvimento local |

---

## 5. E-mail

Resumo da relação entre os provedores (implementação real em `config/mail.php`, `app/Mail/` e [`../ARQUITETURA.md`](../ARQUITETURA.md)):

| `MAIL_MAILER` | Comportamento |
| --- | --- |
| `log` | só grava no log (desenvolvimento; **padrão do código**) |
| `brevo` | **Brevo** por API HTTPS |
| `resend-http` | **Resend** por API HTTPS |
| `resilient` | **Brevo é o principal**; **o Resend é o reserva e só é usado se o Brevo estiver fora do ar** (sem conexão, timeout, HTTP 429 ou 5xx). Falha permanente (chave inválida, remetente não verificado, HTTP 4xx) **não** troca de provedor. Entram só os provedores que têm chave configurada |
| SMTP (`smtp`) | existe no `config/` (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`), mas **não é usado** hoje |

| Variável | Segredo | Padrão do código | Exemplo | Para quê |
| --- | :-: | --- | --- | --- |
| `MAIL_MAILER` | | `log` | `resilient` (ou `brevo`) | escolhe o provedor |
| `BREVO_API_KEY` | 🔒 | vazio | | chave da API do Brevo (restrita a e-mails transacionais) |
| `RESEND_API_KEY` | 🔒 | vazio | | chave da API do Resend (reserva). Sem domínio verificado só envia ao dono da conta |
| `MAIL_FROM_ADDRESS` | | `hello@example.com` | `estacaocommit@gmail.com` | remetente. **Tem que ser verificado no Brevo** |
| `MAIL_FROM_NAME` | | `${APP_NAME}` | `Estação Commit` | nome do remetente |
| `MAIL_HTTP_TIMEOUT` | | `8` | `8` | segundos esperando a API de e-mail |
| `EMAIL_VERIFICATION` | | `false` | `true` | liga o código de 6 dígitos no cadastro (a conta só é criada depois do código) |
| `EMAIL_CODE_TTL` | | `10` | `10` | minutos de validade do código |
| `EMAIL_CODE_ATTEMPTS` | | `5` | `5` | tentativas antes de pedir um novo código |
| `EMAIL_CODE_COOLDOWN` | | `60` | `60` | segundos entre reenvios |
| `EMAIL_DNS_CHECK` | | `true` | `true` | rejeita domínios de e-mail que não recebem mensagens |

Presentes em `config/` mas **não usadas**: `POSTMARK_API_KEY`, `POSTMARK_MESSAGE_STREAM_ID`, `AWS_*` (SES/S3), `MAIL_URL`, `MAIL_LOG_CHANNEL`,
`MAIL_EHLO_DOMAIN`, `MAIL_SENDMAIL_PATH`.

**Onde obter:** Brevo → *SMTP & API → API Keys* (e *Senders* para verificar o remetente). Resend → *API Keys* e *Domains*.

---

## 6. Login com Google (OAuth)

O botão "Continuar com Google" só aparece quando as três variáveis estão preenchidas.

| Variável | Segredo | Exemplo | Para quê |
| --- | :-: | --- | --- |
| `GOOGLE_CLIENT_ID` | 🔒 | `1234-abc.apps.googleusercontent.com` | identifica o app no Google |
| `GOOGLE_CLIENT_SECRET` | 🔒 | | segredo do cliente OAuth |
| `GOOGLE_REDIRECT_URI` | | `https://estacaocommit.onrender.com/auth/google/callback` | **idêntica** à cadastrada no Google Cloud |

**Onde obter (Google Cloud Console):** *APIs e serviços → Credenciais → Criar credenciais → ID do cliente OAuth* (tipo *Aplicativo da Web*).
Em *URIs de redirecionamento autorizados* cadastre exatamente o valor de `GOOGLE_REDIRECT_URI`. Em *Tela de consentimento OAuth* configure o
app e (em produção) publique-o. O Client ID e o Client Secret aparecem ao final da criação.

---

## 7. Tempo real

### 7.1 Reverb (padrão, dentro do contêiner)

Obrigatórias: seção 1. Opcionais e de ajuste:

| Variável | Segredo | Padrão do código | Observação |
| --- | :-: | --- | --- |
| `REALTIME_DRIVER` | | `reverb` | `reverb` ou `cloudflare` (seção 8) |
| `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` | | `127.0.0.1` / `8080` | onde o Reverb escuta **dentro** do contêiner |
| `REVERB_PUBLISH_HOST` / `REVERB_PUBLISH_PORT` / `REVERB_PUBLISH_SCHEME` | | cai em `REVERB_HOST/PORT/SCHEME` | endereço **interno** para o Laravel publicar eventos. A imagem Docker já define `127.0.0.1` / `8080` / `http` |
| `REVERB_APP_MAX_CONNECTIONS` | | `25` | conexões simultâneas. **Não aumente sem medir a RAM** (Render Free: 512 MB). Acima do limite o front usa polling |
| `REVERB_APP_PING_INTERVAL` / `REVERB_APP_ACTIVITY_TIMEOUT` | | `60` / `30` | segundos |
| `REVERB_APP_MAX_MESSAGE_SIZE` / `REVERB_MAX_REQUEST_SIZE` | | `10000` | bytes |
| `REVERB_APP_MAX_CHANNELS_PER_CONNECTION` | | `1000` | |
| `REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM` | | `members` | |
| `REVERB_APP_RATE_LIMITING_ENABLED` e `REVERB_APP_RATE_LIMIT_*` | | `false`, 60 tentativas/60 s | limite de mensagens por conexão |
| `REVERB_SCALING_ENABLED` / `REVERB_SCALING_CHANNEL` | | `false` / `reverb` | escalonamento com Redis: **não usado** |
| `REVERB_SERVER` / `REVERB_SERVER_PATH` / `REVERB_*_INGEST_INTERVAL` | | `reverb` / vazio / `15` | avançadas; não mexer |

**Variáveis do navegador (`VITE_*`, build):**

| Variável | Exemplo | Observação |
| --- | --- | --- |
| `VITE_APP_NAME` | `Estação Commit` | |
| `VITE_REVERB_APP_KEY` | igual a `REVERB_APP_KEY` | **opcional**: sem ela o front lê a chave de `<meta name="reverb-key">` |
| `VITE_REVERB_HOST` | `estacaocommit.onrender.com` | opcional: padrão é o host da página |
| `VITE_REVERB_PORT` | `443` | opcional: padrão é a porta da página |
| `VITE_REVERB_SCHEME` | `https` | opcional: padrão é o da página |

Nunca ponha `REVERB_APP_SECRET` (nem qualquer segredo) em `VITE_*`. **Echo/pusher-js:** não há variáveis `PUSHER_*` em uso; as `PUSHER_*`
do `config/` existem só por compatibilidade e não são necessárias (o Reverb usa o protocolo Pusher com as chaves `REVERB_*`).
O Echo só conecta quando uma página assina um canal (lobby, partida), para não ocupar os 2 processos do Apache.

---

## 8. Cloudflare Workers + Durable Objects (opcional, desligado por padrão)

O código existe e é testado, mas **só entra em uso** com `REALTIME_DRIVER=cloudflare` (avisos) e/ou `GAME_ENGINE=cloudflare` (motor da partida).
Confirme no painel do Render se algo está ligado antes de assumir. Procedimento de publicação: [`CLOUDFLARE.md`](CLOUDFLARE.md).

### 8.1 No Render (Laravel)

| Variável | Segredo | Padrão | Para quê |
| --- | :-: | --- | --- |
| `REALTIME_DRIVER` | | `reverb` | `cloudflare` liga o Worker no lugar do Reverb |
| `REALTIME_WORKER_URL` | | vazio | URL pública do Worker (`https://estacao-commit-realtime.<conta>.workers.dev`) |
| `REALTIME_TOKEN_SECRET` | 🔒 | vazio | assina o token de conexão do navegador. **Mesmo valor no Worker** |
| `REALTIME_NOTIFY_SECRET` | 🔒 | vazio | assina avisos Laravel → Worker. **Mesmo valor no Worker** |
| `REALTIME_CALLBACK_SECRET` | 🔒 | vazio | assina o retorno Worker → Laravel (`/realtime/callback`). **Mesmo valor no Worker** |
| `GAME_ENGINE` | | `laravel` | `laravel` (motor PHP) ou `cloudflare` (motor no Durable Object) |
| `GAME_ENGINE_PARITY_ACK` | | vazio | **trava de segurança**: o motor da Cloudflare só liga se for igual à versão de paridade do código (`CloudflareMatchGateway::PARITY_VERSION`), definida depois dos testes de paridade passarem |

Gere cada segredo com `openssl rand -hex 32` (valores **diferentes** entre si).

### 8.2 No Worker (`worker/`)

| Tipo | Nome | Onde | Valor / função |
| --- | --- | --- | --- |
| **Secret** | `REALTIME_TOKEN_SECRET` | `npx wrangler secret put` | igual ao do Render |
| **Secret** | `REALTIME_NOTIFY_SECRET` | `npx wrangler secret put` | igual ao do Render |
| **Secret** | `REALTIME_CALLBACK_SECRET` | `npx wrangler secret put` | igual ao do Render |
| Variável (pública) | `APP_ORIGIN` | `worker/wrangler.jsonc` → `vars` | `https://estacaocommit.onrender.com` (origem permitida) |
| Variável (pública) | `LARAVEL_CALLBACK_URL` | `worker/wrangler.jsonc` → `vars` | URL do Laravel que recebe o retorno do motor |
| Variável (pública) | `ALLOWED_ORIGINS` | `worker/wrangler.jsonc` → `vars` | lista separada por vírgula (site e localhost) |
| **Binding** (Durable Object) | `CHANNEL` → classe `ChannelHub` | `wrangler.jsonc` → `durable_objects.bindings` | um objeto por canal de avisos |
| **Binding** (Durable Object) | `MATCH` → classe `MatchRoom` | `wrangler.jsonc` → `durable_objects.bindings` | um objeto por partida |
| Configuração | `name`, `main`, `compatibility_date`, `migrations` (`v1` ChannelHub, `v2` MatchRoom, ambos SQLite) | `wrangler.jsonc` | identificação, entrada e migrações de classes |
| Desenvolvimento local | `worker/.dev.vars` (copie de `.dev.vars.example`) | arquivo local, ignorado pelo git | segredos só para `npm run dev` |

Se o domínio do site mudar, **atualize `APP_ORIGIN`, `LARAVEL_CALLBACK_URL` e `ALLOWED_ORIGINS`** e republique o Worker.
Não há identificadores de conta no repositório: o `wrangler login` associa ao seu usuário na hora do deploy.

---

## 9. Regras de jogo e rotinas

| Variável | Padrão | Para quê |
| --- | --- | --- |
| `GAME_TURN_TIMEOUT_SECONDS` | `25` | duração da vez de um humano; passou, a vez é pulada (`0` desliga) |
| `GAME_MAX_CONSECUTIVE_TIMEOUTS` | `3` | timeouts seguidos até o jogador sair da sala |
| `GAME_BOT_DELAY_MS` | `700` | pausa do bot antes de jogar |
| `GAME_BOT_STALL_SECONDS` | `8` | vez de bot parada há mais que isso é jogada pelo servidor (`games:sweep`) (`0` desliga) |
| `GAME_SWEEP_BATCH` | `50` | máximo de partidas verificadas por execução do `games:sweep` |
| `FINISHED_ROOM_TTL_MINUTES` | `2` | quanto tempo a sala encerrada fica visível antes da limpeza |
| `GUEST_TTL_HOURS` | `6` | validade (por inatividade) da conta temporária de visitante |
| `GUEST_MAX_DAYS` | `3` | teto absoluto de vida da conta temporária, contado da criação, mesmo com atividade contínua |
| `GUEST_MAX_DAYS` | `3` | teto de vida de uma conta temporária, mesmo com atividade |

Detalhes das rotinas: [`CRON-JOBS.md`](CRON-JOBS.md).

---

## 10. Variáveis do framework que existem mas **não são usadas**

`AWS_*` (S3/SES), `MEMCACHED_*`, `REDIS_*` (não há Redis), `DYNAMODB_*`, `SQS_*`, `BEANSTALKD_*`, `SLACK_*`, `POSTMARK_*`, `ABLY_KEY`,
`PAPERTRAIL_*`, `LOG_SLACK_*`, `LOG_PAPERTRAIL_*`, `LOG_SYSLOG_FACILITY`, `LOG_STDERR_FORMATTER`, `MYSQL_ATTR_SSL_CA`, `DB_SOCKET`,
`DB_COLLATION`, `AUTH_*` (guard e broker padrão), `PUSHER_*`. Não precisam ser criadas.

## 11. O que a imagem Docker já define (não crie no Render)

Em [`Dockerfile`](../../Dockerfile): `APP_ENV=production`, `LOG_CHANNEL=stderr`, `REVERB_PUBLISH_HOST=127.0.0.1`,
`REVERB_PUBLISH_PORT=8080`, `REVERB_PUBLISH_SCHEME=http`, `APACHE_DOCUMENT_ROOT=/var/www/html/public`. O Supervisor define `HOME=/var/www` para o
`reverb` e o `scheduler`. O Render fornece `PORT` (a imagem escuta nela). Os argumentos de build `VITE_REVERB_*` vêm das variáveis de mesmo nome.

---

## 12. Pacote para colar no Render

Substitua os `<...>`. Itens marcados com **(opcional)** podem ser omitidos.

```dotenv
# --- aplicação (obrigatório) ---
APP_NAME=Estação Commit
APP_URL=https://estacaocommit.onrender.com
APP_KEY=<php artisan key:generate --show>
LOG_LEVEL=info

# --- banco Neon (obrigatório) ---
DB_CONNECTION=pgsql
DB_HOST=<ep-xxxx-pooler.regiao.aws.neon.tech>
DB_PORT=5432
DB_DATABASE=<neondb>
DB_USERNAME=<usuario>
DB_PASSWORD=<senha>
DB_SSLMODE=require

# --- tempo real com Reverb (obrigatório) ---
BROADCAST_CONNECTION=reverb
REALTIME_DRIVER=reverb
REVERB_APP_ID=estacaocommit
REVERB_APP_KEY=<chave-aleatoria>
REVERB_APP_SECRET=<segredo-aleatorio>
REVERB_HOST=estacaocommit.onrender.com
REVERB_PORT=443
REVERB_SCHEME=https

# --- e-mail (opcional; sem isso o cadastro não envia código) ---
MAIL_MAILER=resilient
BREVO_API_KEY=<segredo>
RESEND_API_KEY=<segredo, reserva>
MAIL_FROM_ADDRESS=<remetente verificado no Brevo>
MAIL_FROM_NAME=Estação Commit
EMAIL_VERIFICATION=true

# --- Google (opcional) ---
GOOGLE_CLIENT_ID=<segredo>
GOOGLE_CLIENT_SECRET=<segredo>
GOOGLE_REDIRECT_URI=https://estacaocommit.onrender.com/auth/google/callback

# --- administração (opcional) ---
PROFILE_ADMIN_EMAILS=<e-mails separados por vírgula>

# --- Cloudflare (opcional, ver seção 8; deixe fora enquanto não usar) ---
# REALTIME_DRIVER=cloudflare
# REALTIME_WORKER_URL=https://estacao-commit-realtime.<conta>.workers.dev
# REALTIME_TOKEN_SECRET=<segredo>
# REALTIME_NOTIFY_SECRET=<segredo>
# REALTIME_CALLBACK_SECRET=<segredo>
# GAME_ENGINE=cloudflare
# GAME_ENGINE_PARITY_ACK=<versão de paridade>
```

Todo o resto tem padrão seguro. Depois de salvar, o Render reinicia sozinho; se mudou `VITE_*`, faça deploy com rebuild.
