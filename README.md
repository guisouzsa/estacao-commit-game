<div align="center">
  <img src="public/images/estacao-commit/mascots/foguete-gato.png" width="180" alt="Mascote foguete-gato do Estação Commit">

# Estação Commit

**Toda missão começa com uma branch.**

Jogo de tabuleiro multiplayer para aprender e praticar o fluxo de trabalho com Git — do primeiro login à Pull Request.

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![React](https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=111)](https://react.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Neon-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
</div>

---

## Sobre o projeto

O **Estação Commit** transforma conceitos de Git em uma corrida estratégica para quatro participantes. Cada pessoa monta seu percurso de desenvolvimento na ordem correta, administra cartas, enfrenta conflitos e tenta concluir uma Pull Request antes das demais.

O repositório reúne a aplicação web, o motor de partidas, o sistema social, os perfis públicos, o ranking e duas opções de infraestrutura em tempo real:

- Laravel Reverb, integrado e iniciado no contêiner principal;
- Cloudflare Workers + Durable Objects, implementado como alternativa opcional de infraestrutura.

## Principais funcionalidades

### Jogo multiplayer

- Salas para exatamente quatro participantes.
- Preenchimento de lugares vazios com bots.
- Convites por código e link compartilhável.
- Turnos, compra, troca e descarte de cartas.
- Ações idempotentes para impedir jogadas duplicadas.
- Atualização em tempo real com fallback por polling.
- Substituição de jogador inativo por bot.
- Tela de vitória e histórico persistente de resultados.
- Limpeza automática de salas vazias e partidas encerradas.

### Contas e autenticação

- Cadastro e login por e-mail e senha.
- Recuperação e alteração de senha.
- Entrada com Google por Laravel Socialite.
- Conta temporária sem cadastro, expirada após inatividade.
- Validação de nomes e usernames únicos.
- Onboarding para novos jogadores.

### Perfis e comunidade

- Perfil público com nome, bio, sistema operacional e data de entrada.
- Avatar e banner armazenados no banco de dados.
- Posicionamento e zoom de imagens.
- Molduras, fundos, títulos e selos temáticos de programação.
- Até oito links públicos ordenáveis.
- Sistema de seguir e deixar de seguir.
- Seguidores, seguindo, busca e jogadores recomendados.
- Notificações persistentes.

### Ranking

- Classificação geral baseada nas estatísticas do usuário.
- Ranking semanal e mensal baseado em resultados concluídos.
- Contabilização idempotente de vitórias e partidas.
- Histórico preservado mesmo após a sala ser removida.

## Como jogar

O objetivo é concluir a sequência de desenvolvimento antes dos adversários:

```text
Git Login → Git Clone → Git Checkout
→ Bloco 1 → Git Add → Git Commit
→ Bloco 2 → Git Add → Git Commit
→ Bloco 3 → Git Add → Git Commit
→ Bloco 4 → Git Add → Git Commit
→ Git Pull → Git Push → Pull Request
```

Regras importantes:

1. Todas as pessoas começam com uma carta `Git Login`.
2. Em seu turno, jogue a próxima etapa válida ou troque uma carta.
3. Os blocos de código fazem parte do tabuleiro e entram entre os ciclos de `add` e `commit`.
4. `Limpar credenciais` e `Merge conflict` são cartas de conflito contra outro jogador.
5. `Git Pull` tem um efeito especial durante a fase de treinamento.
6. A mão possui limite; cartas excedentes devem ser descartadas.
7. Vence quem concluir uma Pull Request válida primeiro.

O tutorial interativo dentro da aplicação é a referência visual completa para cartas, fases e conflitos.

## Stack

| Camada         | Tecnologias                                                     |
| -------------- | --------------------------------------------------------------- |
| Backend        | PHP 8.2+, Laravel 12, Eloquent, Sanctum, Socialite              |
| Frontend       | React 18, TypeScript, Inertia.js 2, Tailwind CSS, Framer Motion |
| Build          | Vite 7, TypeScript, ESLint, Prettier                            |
| Banco          | PostgreSQL (produção/Neon), SQLite em testes                    |
| Tempo real     | Laravel Reverb ou Cloudflare Workers/Durable Objects            |
| Filas e agenda | Laravel Queue e Scheduler                                       |
| Deploy         | Docker, Apache, Supervisor e Render                             |
| Testes         | PHPUnit 11, Node Test Runner e Vitest no Worker                 |

## Arquitetura

```text
┌───────────────────────────┐
│ React + Inertia + Ziggy   │
│ páginas, jogo e perfis    │
└─────────────┬─────────────┘
              │ HTTP / WebSocket
┌─────────────▼─────────────┐
│ Laravel 12                │
│ auth, salas, regras, API  │
└───────┬───────────┬───────┘
        │           │
┌───────▼──────┐  ┌─▼──────────────────────────┐
│ PostgreSQL   │  │ Reverb                     │
│ estado/hist. │  │ ou Cloudflare Worker + DO  │
└──────────────┘  └────────────────────────────┘
```

### Responsabilidades

- **Laravel:** autenticação, autorização, perfis, lobby, convites, ranking e motor padrão do jogo.
- **PostgreSQL:** contas, mídias de perfil, salas, participantes, partidas, notificações e resultados.
- **React/Inertia:** interface responsiva sem manter uma API duplicada para as páginas.
- **Reverb:** eventos `room.state.changed` e `user.notices.changed`.
- **Cloudflare (opcional):** canais WebSocket e motor alternativo de partidas com Durable Objects.

## Estrutura do repositório

```text
app/
├── Console/Commands/       # limpeza, expiração e utilitários
├── Http/Controllers/       # páginas e ações HTTP
├── Models/                 # entidades Eloquent
├── Services/               # jogo, salas, ranking e tempo real
└── Support/                # identidade, avatares e utilitários
database/
├── migrations/             # esquema versionado
└── manual-sql/             # scripts administrativos documentados
resources/js/
├── Components/             # componentes compartilhados
├── Layouts/                # layouts Inertia
├── Pages/                  # telas da aplicação
└── lib/                    # sincronização e regras de interface
routes/                     # web, autenticação, canais e scheduler
tests/
├── Feature/                # fluxos completos Laravel
├── Unit/                   # testes unitários PHP
└── js/                     # testes das bibliotecas frontend
worker/                     # alternativa Cloudflare + Durable Objects
docker/                     # Apache, Supervisor e páginas de erro
```

## Requisitos

- PHP 8.2 ou superior;
- Composer 2;
- Node.js 20 ou superior e npm;
- PostgreSQL 14 ou superior;
- extensões PHP exigidas pelo Laravel, PostgreSQL e processamento de imagens;
- Git.

Docker pode ser usado para reproduzir o ambiente de produção sem instalar toda a stack localmente.

## Instalação local

### Instalação automatizada

```bash
git clone https://github.com/guisouzsa/estacao-commit-game.git
cd estacao-commit-game
composer run setup
```

O comando instala dependências, cria o `.env`, gera a chave, executa migrations e compila o frontend. Use esse atalho quando o `.env` já apontar para um PostgreSQL disponível; caso contrário, siga a instalação manual para configurar o banco antes da migration.

### Instalação manual

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan migrate
npm run build
```

No Windows PowerShell, substitua `cp` por:

```powershell
Copy-Item .env.example .env
```

### Executar em desenvolvimento

```bash
composer run dev
```

Esse comando inicia Laravel em `http://127.0.0.1:8001`, o worker da fila e o Vite. Para acompanhar os logs separadamente:

```bash
composer run dev:logs
```

Quando usar Reverb localmente, execute também:

```bash
php artisan reverb:start
```

## Configuração do ambiente

Nunca versione o arquivo `.env`, credenciais, tokens OAuth ou segredos do tempo real. O `.env.example` contém os nomes aceitos pela aplicação.

### Aplicação e banco

| Variável                     | Descrição                                               |
| ---------------------------- | ------------------------------------------------------- |
| `APP_URL`                    | URL pública ou local da aplicação                       |
| `APP_KEY`                    | chave criptográfica gerada pelo Artisan                 |
| `DB_CONNECTION`              | `pgsql` em produção                                     |
| `DB_HOST`, `DB_PORT`         | host e porta do PostgreSQL                              |
| `DB_DATABASE`                | nome do banco                                           |
| `DB_USERNAME`, `DB_PASSWORD` | credenciais do banco                                    |
| `DB_ENDPOINT`                | endpoint Neon sem `-pooler`, quando necessário para SNI |
| `SESSION_DRIVER`             | `database` por padrão                                   |
| `CACHE_STORE`                | `database` por padrão                                   |
| `QUEUE_CONNECTION`           | `database` por padrão                                   |

Para Neon, use SSL e copie os dados do painel do projeto. Não coloque uma URL real de conexão em commits, issues ou logs públicos.

### E-mail

Em desenvolvimento, `MAIL_MAILER=log` grava mensagens no log. Em produção, configure um provedor compatível. `RESEND_API_KEY` está disponível para integração com Resend.

O cadastro pode validar se o domínio informado aceita e-mail por DNS:

```dotenv
EMAIL_DNS_CHECK=true
```

### Google OAuth

Crie um cliente OAuth 2.0 Web no Google Cloud Console e cadastre localmente:

```text
http://127.0.0.1:8001/auth/google/callback
```

Depois configure:

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

O callback precisa ser idêntico ao cadastrado. Usuários novos escolhem o próprio username. Uma conta existente só é vinculada após autenticação explícita.

### Imagens de perfil

Avatares e banners enviados são otimizados e armazenados no PostgreSQL. Isso evita perda de arquivos em deploys com filesystem efêmero. Os endpoints públicos entregam imagens com URL versionada e cache HTTP.

Formatos aceitos: JPG, PNG e WebP, respeitando os limites definidos nas validações do perfil.

## Tempo real

### Opção A — Laravel Reverb

É a opção padrão:

```dotenv
BROADCAST_CONNECTION=reverb
REALTIME_DRIVER=reverb
REVERB_APP_ID=gamehub
REVERB_APP_KEY=troque-esta-chave
REVERB_APP_SECRET=troque-este-segredo
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

As variáveis `VITE_REVERB_*` são públicas e incorporadas ao bundle. **Nunca** exponha `REVERB_APP_SECRET` em uma variável `VITE_*`.

No Render, o Apache recebe HTTPS/WSS na porta pública e encaminha `/app/` ao Reverb interno. Use no build:

```dotenv
VITE_REVERB_HOST=seu-servico.onrender.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

### Opção B — Cloudflare Workers

O diretório [`worker/`](worker/) implementa WebSocket Hibernation, tokens HMAC de uso único, proteção contra replay e um motor opcional de partidas em Durable Objects.

```bash
cd worker
npm install
cp .dev.vars.example .dev.vars
npm run dev
npm test
```

Configuração Laravel:

```dotenv
REALTIME_DRIVER=cloudflare
REALTIME_WORKER_URL=https://seu-worker.workers.dev
REALTIME_TOKEN_SECRET=gere-um-segredo-forte
REALTIME_NOTIFY_SECRET=gere-outro-segredo-forte
```

Para executar partidas novas no Worker:

```dotenv
GAME_ENGINE=cloudflare
REALTIME_CALLBACK_SECRET=gere-um-terceiro-segredo
```

Partidas iniciadas continuam no motor em que foram criadas. Consulte [`worker/README.md`](worker/README.md) para publicação e rollback.

## Filas, scheduler e manutenção

Em produção, mantenha a fila e o scheduler ativos:

```bash
php artisan queue:work --tries=1
php artisan schedule:work
```

| Frequência        | Comando                | Finalidade                                       |
| ----------------- | ---------------------- | ------------------------------------------------ |
| a cada minuto     | `rooms:expire-waiting` | remove salas vazias após o prazo                 |
| a cada minuto     | `rooms:purge-finished` | remove salas encerradas preservando resultados   |
| a cada 15 minutos | `guests:prune`         | remove visitantes inativos que não estão jogando |

Configurações úteis:

```dotenv
GAME_TURN_TIMEOUT_SECONDS=120
FINISHED_ROOM_TTL_MINUTES=2
```

O tempo de vida das contas temporárias é definido em `config/players.php`.

## Comandos úteis

```bash
# Limpar caches
php artisan optimize:clear

# Atualizar o esquema
php artisan migrate

# Criar jogador comum para testes manuais
php artisan player:create-test teste \
  --email=teste@example.com \
  --password="uma-senha-com-12-ou-mais-caracteres"

# Executar rotinas manualmente
php artisan rooms:expire-waiting
php artisan rooms:purge-finished
php artisan guests:prune
```

Os scripts em `database/manual-sql/` são ferramentas administrativas específicas. Leia cada arquivo antes de executá-lo e faça backup. Migrations são a fonte oficial do esquema.

## Testes e qualidade

### Backend

```bash
composer test
# ou
php artisan test
```

### Frontend

```bash
npm run test:js
npm run lint
npm run build
```

### Cloudflare Worker

```bash
cd worker
npm test
npm run typecheck
```

Antes de abrir um Pull Request, execute:

```bash
vendor/bin/pint --test
npm run lint
npm run build
php artisan test
```

## Deploy com Docker e Render

O [`Dockerfile`](Dockerfile) usa build multi-stage:

1. compila os assets com Node;
2. instala dependências PHP sem pacotes de desenvolvimento;
3. configura Apache, Supervisor, Reverb e processos auxiliares;
4. inicia o serviço na porta fornecida por `PORT`.

O entrypoint prepara permissões, cria caches, gera o link de storage e aplica migrations com até cinco tentativas antes de iniciar o Supervisor.

Build local:

```bash
docker build -t estacao-commit .
docker run --rm -p 10000:10000 --env-file .env estacao-commit
```

No Render:

1. crie um Web Service conectado ao repositório;
2. selecione o runtime Docker;
3. configure as variáveis de ambiente;
4. defina a URL pública em `APP_URL`;
5. use **Save, rebuild, and deploy** após alterar qualquer `VITE_*`;
6. acompanhe logs de migration, fila, Reverb e memória.

O plano gratuito pode suspender o serviço. A interface possui reconexão e polling, mas o primeiro acesso pode demorar enquanto o serviço e o Neon despertam.

## Segurança

- Não versione `.env`, `.dev.vars`, cookies, dumps ou credenciais.
- Use senhas e segredos diferentes em cada ambiente.
- Restrinja `PROFILE_ADMIN_EMAILS` às contas administrativas necessárias.
- Preserve CSRF, throttling, validação e autorização nas novas rotas.
- Não exponha segredos backend com prefixo `VITE_`.
- Trate credenciais compartilhadas em logs como comprometidas e rotacione-as.
- Valide uploads no servidor; não confie apenas no navegador.
- Execute `composer audit` e `npm audit` periodicamente.

Para relatar uma vulnerabilidade, não abra uma issue pública com detalhes exploráveis. Entre em contato privadamente com o mantenedor pelo e-mail indicado no perfil do repositório.

## Convenções de desenvolvimento

- PHP segue PSR-12 e é formatado com Laravel Pint.
- React é escrito em TypeScript.
- Componentes compartilhados ficam em `resources/js/Components`.
- Regras de domínio ficam no backend; o frontend não decide resultados.
- Migrations não devem depender de dados locais.
- Novos fluxos devem incluir testes de sucesso, autorização, validação e idempotência.
- Mensagens apresentadas ao jogador permanecem em português.

## Como contribuir

1. Faça um fork do projeto.
2. Crie uma branch: `git switch -c feat/minha-melhoria`.
3. Implemente e adicione testes.
4. Execute as verificações de qualidade.
5. Faça commits objetivos e sem dados sensíveis.
6. Abra um Pull Request descrevendo problema, solução e validação.

Prefixos sugeridos: `feat:`, `fix:`, `refactor:`, `test:`, `docs:` e `chore:`.

## Roadmap

- ampliar testes ponta a ponta em navegadores;
- acompanhar a capacidade do tempo real em produção;
- melhorar observabilidade de partidas e filas;
- expandir cartas, títulos, selos e eventos sazonais;
- evoluir acessibilidade por teclado e leitores de tela;
- internacionalizar a interface mantendo português como idioma principal.

## Autoria

Desenvolvido e mantido por **Guilherme Rodrigues Souza ([@guisouzsa](https://github.com/guisouzsa))**.

## Licença

Distribuído sob a licença MIT. Consulte [`LICENSE`](LICENSE). Dependências de terceiros conservam suas próprias licenças.
