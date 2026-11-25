<div align="center">
  <img src="public/images/estacao-commit/mascots/foguete-gato.png" width="180" alt="Mascote foguete-gato do Estação Commit">

# Estação Commit

**Toda missão começa com uma branch.**

Jogo de tabuleiro multiplayer para aprender e praticar o fluxo de trabalho com Git, do primeiro login à Pull Request.

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![React](https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=111)](https://react.dev/)
[![TypeScript](https://img.shields.io/badge/TypeScript-5-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Neon-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
</div>

---

## Sumário

1. [Sobre o projeto](#sobre-o-projeto)
2. [Como jogar](#como-jogar): [tabuleiro](#o-tabuleiro), [cartas](#as-cartas), [turno](#como-é-um-turno) e [conflitos](#conflitos-entre-jogadores)
3. [Funcionalidades](#funcionalidades)
4. [Tecnologias e arquitetura](#tecnologias-e-arquitetura)
5. [Instalação e execução](#instalação-e-execução)
6. [Configuração e manutenção](#configuração-e-manutenção)
7. [Tempo real](#tempo-real)
8. [Rotinas agendadas](#rotinas-agendadas)
9. [Testes e qualidade](#testes-e-qualidade)
10. [Deploy](#deploy)
11. [Segurança](#segurança)
12. [Contribuição](#contribuição)

> **Documentação:** o índice completo está em [`docs/README.md`](docs/README.md) (visão geral em [`docs/PROJETO.md`](docs/PROJETO.md),
> deploy no Render, variáveis de ambiente, rotinas agendadas e Cloudflare em [`docs/DEPLOY/`](docs/DEPLOY/)).
>
> **Fluxos e operação:** autenticação, multiplayer (tempo de jogada, timeouts, anfitrião) e o que fazer quando um serviço cai estão em
> [`docs/ARQUITETURA.md`](docs/ARQUITETURA.md).
>
> **Documentação de manutenção:** tecnologias, **todas as variáveis de ambiente** e o passo a passo para trocar de
> hospedagem, banco, e-mail ou Google estão em [`docs/MANUTENCAO/GUIA-DE-MANUTENCAO.md`](docs/MANUTENCAO/GUIA-DE-MANUTENCAO.md).

---

## Sobre o projeto

O **Estação Commit** transforma conceitos de Git em uma corrida estratégica para quatro participantes. Cada pessoa monta o
seu percurso de desenvolvimento na ordem correta, administra cartas, enfrenta conflitos e tenta concluir uma Pull Request antes
das demais.

O repositório reúne a aplicação web, o motor de partidas, o sistema social, os perfis públicos, o ranking e duas opções de
infraestrutura em tempo real: **Laravel Reverb** (padrão, dentro do contêiner) e **Cloudflare Workers + Durable Objects** (opcional).

---

## Como jogar

Cada partida tem **quatro participantes** (pessoas ou bots). Vence quem concluir primeiro o percurso de 18 etapas e jogar a **Pull Request**.

### O tabuleiro

O percurso de cada jogador é o mesmo, sempre nesta ordem:

```text
 TREINAMENTO                      ┌──────────────── 4 ciclos de desenvolvimento ────────────────┐
┌───────────┐ ┌───────────┐ ┌──────────────┐ ┌─────────┐ ┌─────────┐ ┌────────────┐
│ 1 Git     │▶│ 2 Git     │▶│ 3 Git        │▶│ Bloco 1 │▶│ git add │▶│ git commit │▶ ─┐
│   Login   │ │   Clone   │ │   Checkout   │ └─────────┘ └─────────┘ └────────────┘   │
└───────────┘ └───────────┘ └──────────────┘                                          │
        ┌──────────────────────────────────────────────────────────────────────────────┘
        ▼   (repete para o Bloco 2, o Bloco 3 e o Bloco 4)
 Bloco 2 ▶ add ▶ commit  ▶  Bloco 3 ▶ add ▶ commit  ▶  Bloco 4 ▶ add ▶ commit
        ▼
 ENTREGA:   git pull  ▶  git push  ▶  Pull Request   (quem jogar a Pull Request vence)
```

| Fase | Etapas | O que acontece |
| --- | --- | --- |
| **Treinamento** | `Git Login` → `Git Clone` → `Git Checkout` | primeiras 3 etapas; é a única fase em que `Limpar Credenciais` funciona e em que `Git Pull` é um curinga |
| **Desenvolvimento** | `Bloco N` → `git add` → `git commit` (4 vezes) | os **Blocos de Código** são peças do tabuleiro: não são cartas e entram sozinhos entre os ciclos de `add` e `commit` |
| **Entrega** | `git pull` → `git push` → `Pull Request` | a última etapa conclui a partida |

Ao todo são **18 etapas**. Cada jogador avança pela própria trilha, e o painel mostra o progresso de todos.

### As cartas

O baralho tem 83 cartas no total (composição em `config/game.php`). As 4 cópias de `Git Login` são entregues uma a cada jogador (não entram no baralho de compra); sobram **67 cartas** para comprar. A matemática do baralho está em [`docs/ARQUITETURA.md`](docs/ARQUITETURA.md), seção 12.

| Carta | Qtd. | Para que serve |
| --- | :-: | --- |
| **Git Login** | 4 | primeira etapa do percurso; **todos começam a partida com uma** |
| **git clone** | 10 | segunda etapa do percurso |
| **git checkout -b** | 10 | terceira etapa do percurso |
| **git add .** | 18 | prepara as mudanças dentro de cada ciclo de desenvolvimento |
| **git commit** | 18 | registra as mudanças e fecha o ciclo |
| **git pull** | 6 | etapa de entrega; durante o **Treinamento** vira curinga e compra 2 cartas |
| **git push** | 8 | envia o trabalho, logo depois do `pull` |
| **Pull Request** | 5 | última etapa: jogá-la **vence a partida** |
| **Limpar Credenciais** | 2 | ataque: manda um adversário **em Treinamento** de volta ao início |
| **Merge Conflict** | 2 | ataque: o alvo **perde a próxima vez** |

### Como é um turno

1. A mão inicial tem **4 cartas**: o `Git Login` e outras 3 sorteadas.
2. Na sua vez, você faz **uma** destas ações:
   - **Jogar a carta da próxima etapa** do percurso (se estiver na mão). Depois de jogar, você compra 1 carta.
   - **Encaixar o Bloco de Código**, quando a próxima etapa é um bloco.
   - **Trocar uma carta**: devolve uma carta da mão e compra outra. Isso encerra a vez. A carta nova **é sorteada**: o jogo evita devolver a mesma carta e evita formar trincas, mas não entrega a carta que você quer.
   - **Atacar** com `Limpar Credenciais` ou `Merge Conflict`, escolhendo o alvo.
3. A mão tem **limite de 6 cartas**. Se passar disso, descarte o excesso antes de terminar a vez.
4. Cada jogador, bots inclusive, joga a própria vez, uma jogada por vez. Se alguém ficar inativo, um bot assume o lugar.

### Conflitos entre jogadores

- **Limpar Credenciais:** só vale entre jogadores que ainda estão no **Treinamento** (etapas 1 a 3). O alvo perde o que já tinha jogado e volta ao início.
- **Merge Conflict:** marca o alvo, que **perde a próxima vez**. Só um conflito por vez em cada jogador.
- Ambos são descartados depois de usados, e você compra 1 carta.

O tutorial interativo dentro da aplicação é a referência visual completa para cartas, fases e conflitos.

---

## Funcionalidades

### Jogo multiplayer

- Salas para exatamente quatro participantes, com bots preenchendo os lugares vazios.
- Convites por código e por link.
- Jogadas idempotentes (uma jogada repetida não conta duas vezes) e proteção contra jogadas fora de ordem.
- Atualização em tempo real, com polling de reserva.
- Substituição de jogador inativo por bot; tela de vitória; histórico de resultados.
- Limpeza automática de salas vazias e encerradas.

### Contas e autenticação

- Cadastro e login por **e-mail e senha**.
- **Verificação de e-mail por código** de 6 dígitos (opcional, `EMAIL_VERIFICATION`): a conta só é criada depois de digitar o código.
- Recuperação e alteração de senha, com e-mails personalizados com a identidade do jogo.
- **Entrar com Google**: quem já tem conta com o mesmo e-mail entra direto, e novos jogadores escolhem o username.
- **Conta temporária** sem cadastro (visitante), que expira após inatividade.
- Trocar a senha encerra as outras sessões.

### Perfis e comunidade

- Perfil público com avatar, banner, bio, links, molduras, fundos, títulos e selos de programação.
- Seguir e deixar de seguir, seguidores, busca e jogadores recomendados.
- Notificações persistentes.

### Conquistas

- Insígnias quadradas em SVG com materiais (bronze, prata, ouro, platina, lenda), concedidas só pelo servidor: Estrela Inicial no cadastro, partidas, vitórias e sequência de vitórias.
- Aviso persistido na central de notificações e popup quando desbloqueadas durante a sessão.
- Até 3 conquistas em destaque no perfil. Detalhes na seção 13 da documentação de operação.

### Ranking

- Classificação geral, semanal e mensal, baseada em resultados concluídos.
- Contabilização idempotente de vitórias e partidas; histórico preservado mesmo após a sala ser removida.

---

## Tecnologias e arquitetura

| Camada | Tecnologias |
| --- | --- |
| Backend | PHP 8.2+, Laravel 12, Eloquent, Sanctum, Socialite |
| Frontend | React 18, TypeScript 5, Inertia.js 2, Tailwind CSS, Framer Motion |
| Build | Vite 7 |
| Banco | PostgreSQL (Neon) em produção; SQLite nos testes |
| Tempo real | Laravel Reverb ou Cloudflare Workers + Durable Objects |
| E-mail | Brevo (API HTTPS) |
| Login social | Google OAuth 2.0 |
| Execução | Docker, Apache, Supervisor, Render |
| Testes | PHPUnit 11, Node Test Runner, Vitest |

```text
┌───────────────────────────┐
│ React + Inertia + Ziggy   │
│ páginas, jogo e perfis    │
└─────────────┬─────────────┘
              │ HTTP / WebSocket
┌─────────────▼─────────────┐        ┌──────────────┐
│ Laravel 12                │───────▶│ Brevo (e-mail)│
│ auth, salas, regras, API  │        └──────────────┘
└───────┬───────────┬───────┘
        │           │
┌───────▼──────┐  ┌─▼──────────────────────────┐
│ PostgreSQL   │  │ Reverb                     │
│ estado/hist. │  │ ou Cloudflare Worker + DO  │
└──────────────┘  └────────────────────────────┘
```

- **Laravel:** autenticação, autorização, perfis, lobby, convites, ranking e motor do jogo.
- **PostgreSQL:** contas, imagens de perfil, salas, partidas, notificações, resultados, sessões, cache e fila.
- **React/Inertia:** interface responsiva sem uma API duplicada para as páginas.
- **Tempo real:** só avisa para recarregar o estado (`room.state.changed`, `user.notices.changed`); o estado do jogo é do Laravel.

A visão geral está em [`docs/PROJETO.md`](docs/PROJETO.md); tecnologias, serviços externos e troca de serviços em [`docs/MANUTENCAO/GUIA-DE-MANUTENCAO.md`](docs/MANUTENCAO/GUIA-DE-MANUTENCAO.md).

### Estrutura do repositório

```text
app/
├── Console/Commands/       # limpeza, expiração e utilitários
├── Http/Controllers/       # páginas e ações HTTP
├── Http/Middleware/        # sessão, cabeçalhos de segurança, Inertia
├── Mail/                   # e-mails (código de verificação, transporte Brevo)
├── Models/                 # entidades Eloquent
├── Services/               # jogo, salas, ranking, e-mail e tempo real
└── Support/                # identidade, avatares, bots e utilitários
config/                     # configurações (bots, perfis, verificação, jogo)
database/
├── migrations/             # esquema versionado
└── manual-sql/             # scripts administrativos documentados
docs/                       # documentação (índice em docs/README.md; regras do jogo em docs/JOGO)
resources/
├── js/                     # componentes, layouts, páginas e bibliotecas do front
└── views/emails/           # modelos dos e-mails
routes/                     # web, autenticação, canais e agendador
tests/                      # Feature, Unit, js, parity
worker/                     # alternativa Cloudflare + Durable Objects
docker/                     # Apache, Supervisor e páginas de erro
```

---

## Instalação e execução

### Requisitos

PHP 8.2+, Composer 2, Node.js 20+ com npm, PostgreSQL 14+ (ou um projeto no Neon), as extensões PHP do Laravel/PostgreSQL/imagens e Git.
O Docker reproduz o ambiente de produção sem instalar toda a stack.

### Instalação

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

### Desenvolvimento

```bash
composer run dev          # Laravel em http://127.0.0.1:8001, fila e Vite
composer run dev:logs     # acompanha os logs separadamente
php artisan reverb:start  # tempo real local, se usar Reverb
```

### Comandos úteis

```bash
php artisan optimize:clear                  # limpa caches
php artisan migrate                         # atualiza o esquema
php artisan player:create-test teste --email=teste@example.com --password="uma-senha-com-12-ou-mais-caracteres"
php artisan rooms:expire-waiting            # rotinas manuais
php artisan rooms:purge-finished
php artisan guests:prune
```

Os scripts em `database/manual-sql/` são ferramentas administrativas: leia cada arquivo e faça backup antes. As migrations são a fonte oficial do esquema.

---

## Configuração e manutenção

O arquivo `.env.example` lista os nomes aceitos. **A referência completa das variáveis** (para que serve cada uma, quais são
segredos, onde configurar no Render, no Worker e no build, com um pacote para colar no Render) está em:

### 👉 [`docs/DEPLOY/VARIAVEIS-DE-AMBIENTE.md`](docs/DEPLOY/VARIAVEIS-DE-AMBIENTE.md)

O passo a passo para **trocar de hospedagem, banco de dados, serviço de e-mail, domínio ou conta do Google**, girar segredos vazados
e a tabela de solução de problemas estão em [`docs/MANUTENCAO/GUIA-DE-MANUTENCAO.md`](docs/MANUTENCAO/GUIA-DE-MANUTENCAO.md).

Resumo do que é preciso para o site funcionar:

| Grupo | Variáveis principais | Obrigatório |
| --- | --- | :-: |
| Aplicação | `APP_KEY`, `APP_URL` | sim |
| Banco | `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | sim |
| Tempo real (Reverb) | `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `VITE_REVERB_*` | sim |
| E-mail e verificação | `MAIL_MAILER`, `BREVO_API_KEY`, `MAIL_FROM_ADDRESS`, `EMAIL_VERIFICATION` | opcional |
| Login com Google | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | opcional |
| Administração | `PROFILE_ADMIN_EMAILS` | opcional |
| Cloudflare | `REALTIME_*`, `GAME_ENGINE`, `GAME_ENGINE_PARITY_ACK` | opcional |

> Nunca versione `.env`, `.dev.vars`, dumps ou capturas com chaves, e nunca coloque segredos em variáveis `VITE_*`.

---

## Tempo real

**Opção A, Laravel Reverb (padrão):** roda no mesmo contêiner. No Render, o Apache recebe HTTPS/WSS e encaminha `/app/` ao Reverb
interno. As variáveis `VITE_REVERB_*` são públicas e entram no bundle durante o build.

**Opção B, Cloudflare Workers:** o diretório [`worker/`](worker/) implementa WebSocket Hibernation, tokens HMAC de uso único,
proteção contra replay e um motor opcional de partidas em Durable Objects. Detalhes, publicação e retorno ao Reverb em
[`worker/README.md`](worker/README.md). O motor de partidas da Cloudflare só liga com `GAME_ENGINE=cloudflare` **e** a trava de
paridade `GAME_ENGINE_PARITY_ACK`; partidas iniciadas continuam no motor em que foram criadas.

---

## Rotinas agendadas

Em produção o `scheduler` (`schedule:work`) fica ativo no mesmo contêiner, mantido pelo Supervisor. **Não há worker de fila.** Detalhes, testes e recuperação em [`docs/DEPLOY/CRON-JOBS.md`](docs/DEPLOY/CRON-JOBS.md):

| Frequência | Comando | Finalidade |
| --- | --- | --- |
| a cada minuto | `rooms:expire-waiting` | remove salas vazias após o prazo |
| a cada minuto | `rooms:purge-finished` | remove salas encerradas, preservando resultados |
| a cada minuto | `games:sweep` | resolve vezes paradas (timeout de humanos e bots), mesmo sem ninguém na página |
| a cada 15 minutos | `guests:prune` | remove visitantes inativos que não estão jogando |

---

## Testes e qualidade

```bash
php artisan test            # backend: regras, segurança, fluxos HTTP
npm run test:js             # bibliotecas do front
npx tsc --noEmit            # tipos
npm run lint && npm run build
cd worker && npm test       # Worker (sobe o Worker real) e paridade com o motor PHP
```

Antes de abrir um Pull Request, rode também `vendor/bin/pint --test`.

Os testes de segurança ficam em `tests/Feature/Security/` (autenticação, isolamento entre contas, integridade do jogo, uploads, cabeçalhos).
O motor de jogo existe em PHP e em TypeScript (Worker); a paridade entre os dois é verificada por cenários compartilhados em `tests/parity/`.

---

## Deploy

O [`Dockerfile`](Dockerfile) é multi-stage: compila os assets com Node, instala as dependências PHP sem pacotes de desenvolvimento,
configura Apache, Supervisor e Reverb, e inicia na porta de `PORT`. O entrypoint prepara permissões e caches, cria o link de storage
e aplica as migrations com até cinco tentativas.

```bash
docker build -t estacao-commit .
docker run --rm -p 10000:10000 --env-file .env estacao-commit
```

No Render: Web Service com runtime Docker, variáveis conforme [`docs/DEPLOY/VARIAVEIS-DE-AMBIENTE.md`](docs/DEPLOY/VARIAVEIS-DE-AMBIENTE.md), e `APP_URL` com a URL pública. Passo a passo: [`docs/DEPLOY/RENDER.md`](docs/DEPLOY/RENDER.md).
Depois de alterar qualquer `VITE_*`, faça um deploy com rebuild. O plano gratuito pode suspender o serviço, então o primeiro acesso
pode demorar enquanto o Render e o Neon acordam.

---

## Segurança

- Sessão por conta: trocar a senha encerra as outras sessões e os "lembrar-me" antigos.
- Cabeçalhos de segurança em todas as respostas (nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS em HTTPS).
- CSRF, limitação de requisições, validação e autorização em todas as rotas; uploads de imagem validados no servidor.
- Códigos de verificação guardados só como hash, com validade, limite de tentativas e intervalo de reenvio.
- Segredos só em variáveis do servidor; nada de segredo em `VITE_*`.
- Execute `composer audit` e `npm audit` periodicamente; trate credenciais expostas como comprometidas e troque-as.

Para relatar uma vulnerabilidade, não abra uma issue pública com detalhes exploráveis: contate o mantenedor em particular.

---

## Contribuição

1. Faça um fork e crie uma branch: `git switch -c feat/minha-melhoria`.
2. Implemente com testes (sucesso, autorização, validação e idempotência).
3. Rode as verificações de qualidade acima.
4. Faça commits objetivos, sem dados sensíveis. Prefixos: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`.
5. Abra um Pull Request descrevendo problema, solução e validação.

Convenções: PHP em PSR-12 com Laravel Pint; React em TypeScript; regras de domínio no backend (o front não decide resultados);
mensagens ao jogador em português.

### Roadmap

- testes ponta a ponta em navegadores e observabilidade de partidas e filas;
- domínio próprio para melhorar a entrega dos e-mails;
- mais cartas, títulos, selos e eventos sazonais;
- acessibilidade por teclado e leitores de tela.

---

## Autoria e licença

Desenvolvido e mantido por **Guilherme Rodrigues Souza ([@guisouzsa](https://github.com/guisouzsa))**.
Distribuído sob a licença MIT (veja [`LICENSE`](LICENSE)); as dependências de terceiros conservam suas próprias licenças.
