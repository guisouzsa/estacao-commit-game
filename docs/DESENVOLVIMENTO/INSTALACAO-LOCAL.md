# Instalação local, comandos e testes

Voltar ao [índice](../README.md). Visão geral do sistema: [`../PROJETO.md`](../PROJETO.md).

## 1. Requisitos

- PHP 8.2+ com as extensões do Laravel, PostgreSQL (`pdo_pgsql`) e imagens (GD).
- Composer 2.
- Node.js 20+ e npm.
- PostgreSQL 14+ **ou** um projeto no Neon (os testes usam SQLite em memória e não precisam de PostgreSQL).
- Git. Docker é opcional (reproduz o ambiente de produção).

## 2. Instalação

```bash
git clone https://github.com/guisouzsa/estacao-commit-game.git
cd estacao-commit-game
composer install
cp .env.example .env          # no PowerShell: Copy-Item .env.example .env
php artisan key:generate
npm install
php artisan migrate
npm run build
```

Atalho: `composer run setup` faz tudo isso, desde que o `.env` já aponte para um PostgreSQL disponível.
Preencha `DB_*` no `.env` (veja [`../DEPLOY/VARIAVEIS-DE-AMBIENTE.md`](../DEPLOY/VARIAVEIS-DE-AMBIENTE.md), seção 3). Para tentar localmente sem e-mail
real, deixe `MAIL_MAILER=log` e `EMAIL_VERIFICATION=false`.

> **Cuidado:** um `.env` apontando para o banco de **produção** faz qualquer comando (`artisan`, testes que não usam SQLite) agir nele.
> Use um banco próprio para desenvolvimento.

## 3. Rodar em desenvolvimento

```bash
composer run dev          # Laravel em http://127.0.0.1:8001, fila e Vite
composer run dev:logs     # acompanha os logs separadamente
php artisan reverb:start  # tempo real local (necessário para WebSocket)
```

Sem o Reverb o jogo funciona por polling (a tela atualiza a cada ~15 s).

## 4. Comandos úteis

```bash
php artisan optimize:clear                  # limpa caches
php artisan migrate                         # atualiza o esquema
php artisan route:list                      # rotas
php artisan player:create-test teste --email=teste@example.com --password="uma-senha-com-12-ou-mais-caracteres"
php artisan games:sweep                     # rotinas manuais (ver ../DEPLOY/CRON-JOBS.md)
php artisan rooms:expire-waiting
php artisan rooms:purge-finished
php artisan guests:prune
```

Os scripts de `database/manual-sql/` são ferramentas administrativas: **leia cada arquivo e faça backup antes**. As migrations são a fonte oficial do esquema.

## 5. Testes e qualidade

```bash
php artisan test            # backend: regras, segurança, fluxos HTTP (SQLite em memória)
npm run test:js             # bibliotecas do front (Node Test Runner)
npx tsc --noEmit            # tipos
npm run lint && npm run build
cd worker && npm test       # Worker (sobe o workerd) e paridade com o motor PHP
vendor/bin/pint --test      # estilo do PHP (antes de abrir um Pull Request)
```

- Testes de segurança: `tests/Feature/Security/` (autenticação, isolamento entre contas, integridade do jogo, uploads, cabeçalhos).
- Paridade PHP × Worker: cenários compartilhados em `tests/parity/` (`EngineParityTest` e `worker/test/parity.test.ts`).
- Teste ao vivo do Worker (pulado por padrão): `LIVE_WORKER=1 php artisan test --filter=CloudflareLiveTest`.
- Teste de uma partida específica: `php artisan test --filter=GameMatchTest`.

## 6. Docker (opcional)

```bash
docker build -t estacao-commit .
docker run --rm -p 10000:10000 --env-file .env estacao-commit
```
