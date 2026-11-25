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
2. [Como jogar](#como-jogar): [tabuleiro](#o-tabuleiro), [cartas](#as-cartas) (detalhe de cada uma), [turno](#como-é-um-turno) e [conflitos](#conflitos-entre-jogadores)
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
13. [Autoria e créditos](#autoria-e-créditos)

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

Cada partida tem **quatro participantes** (pessoas ou bots) e é jogada **por turnos: um jogador de cada vez**, bots inclusive.
Vence quem concluir primeiro o percurso de 18 etapas e jogar a **Pull Request**: a partida termina na hora, sem esperar os demais.
As regras oficiais (PDF fechado, tabuleiro e cartas originais) estão em [`docs/JOGO/`](docs/JOGO/); a aplicação segue esse documento.

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
| **Treinamento** (etapas 1 a 3) | `Git Login` → `git clone` → `git checkout -b` | é a única fase em que `Limpar Credenciais` funciona e em que `git pull` é um curinga |
| **Desenvolvimento** (etapas 4 a 15) | `Bloco N` → `git add .` → `git commit`, quatro vezes | os **Blocos de Código** são peças do tabuleiro, não do baralho: entram sozinhos entre os ciclos |
| **Entrega** (etapas 16 a 18) | `git pull` → `git push` → `Pull Request` | a última etapa conclui a partida |

Ao todo são **18 etapas** (3 + 4 × 3 + 3). Cada jogador avança pela própria trilha e o painel mostra o progresso de todos, com a etiqueta de quem está jogando.
No computador o tabuleiro cabe na tela sem rolagem; no celular a partida tem layout próprio.

### As cartas

O baralho tem **83 cartas**. As 4 cópias de `Git Login` não entram na compra: cada jogador **começa a partida com uma**. Restam **67 cartas**
para comprar, e a composição vem de [`config/game.php`](config/game.php) (chave `deck`). Por que esses números e quais chances eles dão:
[`docs/ARQUITETURA.md`, seção 12](docs/ARQUITETURA.md#12-baralho-matemática-e-justiça).

| Carta | Qtd. | Etapa | Detalhes |
| --- | :-: | :-: | --- |
| **Git Login** | 4 | 1 | primeira etapa; **uma por jogador, entregue na mão inicial**, fora do baralho de compra |
| **git clone** | 10 | 2 | segunda etapa do Treinamento |
| **git checkout -b** | 10 | 3 | terceira etapa do Treinamento; fecha a fase |
| **git add .** | 18 | meio de cada ciclo | prepara as mudanças do bloco recém-encaixado |
| **git commit** | 18 | fim de cada ciclo | registra as mudanças; depois dele chega o próximo bloco |
| **git pull** | 6 | 16 | abre a Entrega. Durante o **Treinamento** vira **curinga** e compra 2 cartas |
| **git push** | 8 | 17 | envia o trabalho; só vale depois do `pull` |
| **Pull Request** | 5 | 18 | última etapa: jogá-la **vence a partida** |
| **Limpar Credenciais** | 2 | ataque | manda um adversário **em Treinamento** de volta ao início |
| **Merge Conflict** | 2 | ataque | o alvo **perde a próxima vez** |
| **Blocos de Código 1 a 4** | 4 | peças | não são cartas de compra: o bloco da vez já fica na mão, e o seguinte chega ao jogar o atual |

**Detalhes de cada carta**

- **Git Login.** Todos começam com ela, então ninguém precisa "sortear" a primeira etapa. É a regra mais recente do jogo: antes, a abertura dependia do acaso.
- **git clone e git checkout -b.** São as cartas mais disputadas do começo (10 cópias cada). Ficam no baralho de propósito em maior número para que o Treinamento não vire espera.
- **git add . e git commit.** São 18 cópias de cada para 16 necessárias na mesa (4 jogadores × 4 ciclos), de modo que sempre há folga. O `commit` é o que libera o próximo bloco.
- **git pull.** Tem dois papéis: etapa da Entrega e **curinga no Treinamento** (quem está no Treinamento pode usá-lo para comprar 2 cartas e acelerar).
- **git push.** Só 8 cópias: são necessárias 4, e o excesso antigo (16) deixava o baralho cheio de cartas quase mortas.
- **Pull Request.** Cinco cópias para quatro jogadores; cada carta tem arte própria. Quem a joga **na ordem certa** encerra a partida e aparece a tela de vitória.
- **Limpar Credenciais.** Só alcança quem ainda está nas etapas 1 a 3. O alvo perde o que já jogou no Treinamento e volta ao início. O jogador escolhe o alvo no seletor de alvos.
- **Merge Conflict.** O alvo **perde a próxima vez**. Não acumula: um jogador só pode ter um conflito marcado por vez.
- **Blocos de Código.** São quatro (1 a 4), cada um com cor e arte próprias. Não podem ser trocados. Ao encaixar um, o seguinte é entregue; depois do Bloco 4, o jogador compra 1 carta comum.

A arte das cartas está em [`public/images/estacao-commit/corrected/`](public/images/estacao-commit/corrected/) (PNG e WebP) e é desenhada pelo componente
[`GameCard.tsx`](resources/js/Components/GameCard.tsx) (rótulo, imagem e cor de cada carta). As cartas originais impressas estão em
[`docs/JOGO/cartas.pdf`](docs/JOGO/cartas.pdf) e [`docs/JOGO/extracartas.pdf`](docs/JOGO/extracartas.pdf).

### Como é um turno

1. **Mão inicial: 5 cartas.** `Git Login`, 3 cartas comuns sorteadas e o **Bloco de Código** da vez.
2. Na sua vez (**25 segundos** para quem é humano), você faz **uma** destas ações:
   - **Jogar a carta da próxima etapa** do percurso, se estiver na mão. Depois de jogar, você compra 1 carta.
   - **Encaixar o Bloco de Código**, quando a próxima etapa é um bloco.
   - **Trocar uma carta:** descarta exatamente 1 e compra 1. **Conta como a jogada do turno** e passa a vez. A carta nova é sorteada: o jogo evita devolver a mesma e evita formar trincas, mas não entrega a carta que você quer.
   - **Atacar** com `Limpar Credenciais` ou `Merge Conflict`, escolhendo o alvo.
3. A mão aceita **no máximo 6 cartas comuns**. Se passar disso, descarte o excesso antes de terminar a vez.
4. **Tempo e inatividade:** se os 25 s acabarem, a vez é pulada. Com **3 timeouts seguidos** (sem nenhuma jogada válida no meio), o jogador sai e **um bot assume o lugar** com a mesma mão e o mesmo percurso. Uma queda curta de rede não remove ninguém. Regras completas:
   [`docs/ARQUITETURA.md`, seção 2](docs/ARQUITETURA.md#2-multiplayer).
5. **Bots** jogam um passo por vez, na vez deles, sem prazo e sem nunca gerar timeout. Se todos os humanos saírem, a sala é destruída.

### Conflitos entre jogadores

- **Limpar Credenciais:** só vale contra jogadores ainda no **Treinamento** (etapas 1 a 3). O alvo perde o que já tinha jogado e volta ao início.
- **Merge Conflict:** marca o alvo, que **perde a próxima vez**. Só um conflito por vez em cada jogador.
- Ambos são descartados depois de usados, e quem atacou compra 1 carta.

### Tutorial dentro do jogo

O **tutorial narrativo "Você é um estagiário!"** (`MissionTutorial`) ensina o percurso, as cartas e os conflitos em uma pequena missão, e o botão
"Como jogar" fica disponível a qualquer momento. É a referência visual completa para quem está começando.

---
## Funcionalidades

Resumo de tudo o que foi construído, com o link para a parte da documentação que detalha cada assunto.

### Jogo multiplayer

Detalhes técnicos: [`docs/ARQUITETURA.md`, seção 2](docs/ARQUITETURA.md#2-multiplayer).

- **Salas para quatro participantes.** O anfitrião cria a sala por um **modal guiado** (`RoomCreationModal`), escolhendo amigos e bots; os lugares vazios são preenchidos por bots (até 3 por sala, catálogo em [`config/bots.php`](config/bots.php)).
- **Convites** por código e por link. A busca de sala por código (`JoinByCodeForm`) pode ser **cancelada**, e o link de convite entra na sala automaticamente.
- **Jogadas seguras.** Cada jogada leva um `action_uuid` (uma jogada repetida não conta duas vezes) e uma `version` (jogada fora de ordem recebe 409 e o cliente relê o estado). O servidor é a única autoridade: o navegador não decide nada.
- **Um jogador por vez, com relógio do servidor.** 25 s por vez humana; 3 timeouts seguidos removem o jogador e um bot assume. O varredor `games:sweep` resolve vezes paradas **mesmo com todas as páginas fechadas**.
- **Bots** que jogam um passo por pedido, com pausa de 700 ms para dar tempo de ver a jogada.
- **Saída e anfitrião.** O anfitrião pode sair: o comando passa ao próximo humano (nunca a um bot). Sair no meio da partida entrega o lugar a um bot; sem humanos, a sala é destruída (`RoomLeaveService`).
- **Interface de partida:** tabuleiro 3D, cartas animadas, carta voando do baralho, bloco novo entrando, etiqueta "quem está jogando", log da partida, seletor de alvo e tela de vitória (componentes em [`resources/js/Components/game/`](resources/js/Components/game/)). Respeita `prefers-reduced-motion`.
- **Estado "jogando"** exibido no perfil e nas listas de quem está em partida.
- **Limpeza automática** de salas vazias, expiradas e encerradas, sem perder resultados. Veja [Rotinas agendadas](#rotinas-agendadas).

### Contas e autenticação

Mapa completo de rotas, controllers e pontos de atenção: [`docs/ARQUITETURA.md`, seção 1 (Mapa da autenticação)](docs/ARQUITETURA.md#1-mapa-da-autenticação).

- **Cadastro e login** por e-mail e senha, com limitação de tentativas (10/min), validação do nome (`PlayerNameAllowed`) e do domínio do e-mail, e unicidade sem diferenciar maiúsculas.
- **Verificação por código de 6 dígitos** (opcional, `EMAIL_VERIFICATION`): a conta **só é criada depois do código**. O código é guardado apenas como hash, com validade, limite de tentativas e intervalo de reenvio.
- **Recuperação e troca de senha** com e-mails da marca. A resposta é idêntica para e-mails existentes ou não (sem enumeração) e **trocar a senha encerra as outras sessões**.
- **Entrar com Google:** quem já tem conta com o mesmo e-mail entra direto; novos jogadores escolhem o username.
- **E-mail resiliente:** Brevo como principal e Resend como reserva **só se o Brevo estiver fora do ar**. Detalhes em [E-mail e resiliência](docs/ARQUITETURA.md#e-mail-e-resiliência).
- **Contas temporárias (visitantes)** sem cadastro, por cookie HttpOnly com token em hash, com prazo de inatividade e teto absoluto de dias. Auditoria: [seção 14](docs/ARQUITETURA.md#14-contas-temporárias-auditoria).

### Perfis e comunidade

- **Perfil público** com avatar, banner, bio, links, molduras, fundos, títulos e selos de programação, mais o estado de "jogando agora".
- **Edição organizada em blocos:** Identidade | Avatar e Moldura | Banner e Fundo, com editor de imagem (`ProfileImageEditor`) e ícones gerados no próprio editor.
- **Imagens no banco** (data URI) servidas como imagem cacheável em `/imagens/{tipo}/{usuário}?v=`, com cache longo e versão na URL.
- **Seguir e deixar de seguir** (o botão vira "Deixar de seguir" quando já segue), seguidores, busca de jogadores e **jogadores recomendados**.
- **Notificações persistentes** com sino em popover, central de notificações e avisos de conquistas.

### Conquistas

Catálogo, critérios e regras de concessão: [`docs/ARQUITETURA.md`, seção 13](docs/ARQUITETURA.md#13-conquistas).

- **11 conquistas** em SVG, de "Estrela Inicial" (no cadastro) a "Lenda da Estação" (100 vitórias), com materiais inicial, bronze, prata, ouro, platina e lenda.
- Concedidas **só pelo servidor**, de forma idempotente, e nunca derrubam o fim de uma partida se falharem.
- Aviso na central de notificações e **popup** quando desbloqueadas durante a sessão; até **3 conquistas em destaque** no perfil.

### Ranking

- **Geral, semanal e mensal**, no fuso `America/Sao_Paulo` ([`config/ranking.php`](config/ranking.php)). O geral usa os contadores da conta; semanal e mensal saem da tabela permanente `match_results`.
- **Só aparece quem tem pelo menos 1 vitória.** A ordem é vitórias (maior primeiro), derrotas (menor primeiro) e nome.
- Contabilização idempotente (um resultado por partida e jogador); o histórico é preservado mesmo depois de a sala ser removida.

### Desempenho, erros e operação

- **Echo/WebSocket só conecta quando a página precisa**; o Dashboard usa polling a cada 30 s, sem *prefetch*, com cache longo de arquivos e fontes que não bloqueiam. Contexto: [`docs/ARQUITETURA.md`, seção 5](docs/ARQUITETURA.md#5-processos-em-segundo-plano-render-um-único-contêiner).
- **Páginas de erro e de espera** personalizadas (inclusive estáticas no Apache para 502/503/504), com mensagens em português e sem texto técnico; o modal de carregamento só aparece após 2 s.
- **Log operacional** (`OpsLog`) e observabilidade: [seção 8](docs/ARQUITETURA.md#8-observabilidade).
- **Histórico do que foi entregue**, por tema e data: [`docs/MANUTENCAO/HISTORICO-DE-ALTERACOES.md`](docs/MANUTENCAO/HISTORICO-DE-ALTERACOES.md).

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

Em produção o `scheduler` (um laço leve que roda `schedule:run` a cada minuto, sem processo PHP residente) fica no mesmo contêiner, mantido pelo Supervisor. **Não há worker de fila.** Detalhes, testes e recuperação em [`docs/DEPLOY/CRON-JOBS.md`](docs/DEPLOY/CRON-JOBS.md):

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

## Autoria e créditos

O **Estação Commit** nasceu como um jogo de tabuleiro físico. A versão online é a digitalização dele.

### Larissa Félix ([@larissa-felixl](https://github.com/larissa-felixl)): idealização, regras, cartas e design

**Idealizadora do jogo.** Criou as **regras**, as **cartas** e o **design** do Estação Commit, em parceria com outra pessoa que também fez parte
da criação. Tudo o que o jogo online reproduz (o percurso de 18 etapas, o Treinamento, os Blocos de Código, os ataques e a identidade visual)
vem do material dela, reunido em [`docs/JOGO/`](docs/JOGO/):

- documento de regras fechado: [`docs/JOGO/`](docs/JOGO/) (PDF "Estação Commit — Documento de jogo (versão fechada)");
- cartas: [`cartas.pdf`](docs/JOGO/cartas.pdf) e [`extracartas.pdf`](docs/JOGO/extracartas.pdf);
- tabuleiro: [`TabuleiroPrincipal.pdf`](docs/JOGO/TabuleiroPrincipal.pdf).

### Guilherme Rodrigues Souza ([@guisouzsa](https://github.com/guisouzsa)): digitalização, documentação e desenvolvimento do jogo online

Pegou o jogo criado por Larissa e o transformou em uma aplicação web multiplayer, **seguindo os estilos, as regras e o design originais**:

- **Digitalização:** extração e tratamento das artes das cartas e do tabuleiro (script em [`scripts/`](scripts/), arte em [`public/images/estacao-commit/`](public/images/estacao-commit/)), adaptadas para a tela sem mudar a identidade.
- **Desenvolvimento do jogo online:** motor de partidas (turnos, bots, timeouts, ataques), salas e convites, contas e login, perfis, ranking, conquistas, notificações, tempo real (Reverb e Cloudflare opcional), Docker e deploy.
- **Documentação:** este README e toda a pasta [`docs/`](docs/README.md) (arquitetura, deploy, variáveis de ambiente, rotinas, manutenção e histórico).
- **Manutenção:** o repositório é mantido por ele. Veja [Contribuição](#contribuição).

### Licença

Distribuído sob a licença MIT (veja [`LICENSE`](LICENSE)); as dependências de terceiros conservam suas próprias licenças.
