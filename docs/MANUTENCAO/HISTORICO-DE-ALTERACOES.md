# Histórico de alterações

Resumo por tema do que foi entregue, na ordem em que aconteceu. O histórico completo e preciso é o do Git (`git log`). Este arquivo
registra o **estado funcional** do projeto para quem não quer ler commit a commit. Voltar ao [índice](../README.md).

## 25/11/2026 (bots, e-mails de conta e equilíbrio)

- **Bots mais espertos, nos dois motores:** `App\Services\BotBrain` e `worker/src/bot.ts` (espelhos exatos). Trocam ou descartam a carta que o
  percurso não usa mais, guardam a que vão usar antes e atacam só o líder (Merge Conflict na etapa 12+, Limpar Credenciais na regra do Treinamento).
  Decidem só com a própria mão e o estado público; o desempate do alvo é pela ordem da mesa. Seis cenários novos de paridade; `PARITY_VERSION`
  passou para `2026-11-28-1` (uma liberação antiga do motor Cloudflare deixa de valer sozinha).
- **Simulador de equilíbrio** (`worker/sim/balance.ts`): 3.000 partidas por cenário no motor real. Mostrou que a derrota dos humanos vinha de
  **vezes perdidas** (tutorial com o relógio correndo e timeouts), não do baralho. As abordagens de distribuição B (ponderada) e C (adaptativa)
  foram comparadas e descartadas: o baralho continua aleatório. Resultados em `ARQUITETURA.md`, seção 17.
- **Dica "Sobrando"** nas cartas da mão que o percurso não usa mais (`resources/js/lib/cardAdvice.ts`, mesma regra do bot).
- **Tutorial** continua abrindo uma vez por partida, mas espera a vez da pessoa passar e avisa "É a sua vez · Ns · Jogar agora".
- **E-mails de conta:** boas-vindas no cadastro (versão e-mail ou Google) e aviso de **Conta Google vinculada** (pelo perfil ou no primeiro
  login com Google numa conta de e-mail). Uma vez cada, uma única tentativa no Brevo, sem usar o provedor reserva e sem travar nada se falhar;
  desliga com `MAIL_WELCOME=false`. Login comum não envia e-mail.
- **Saída de emergência:** erro inesperado ao entrar ou se cadastrar (login, cadastro, código, Google, recuperação de senha) leva a uma tela com
  **"Jogar com perfil temporário"**, em vez de deixar a pessoa sem caminho.
- **Convites:** abrir o link entra sozinho na sala ("Entrando na sala…"); menos consultas ao banco (abrir: 6 → 4; entrar: 9 → 7, sem contar
  sessão/login); testes de link, código, visitante, inválido, expirado, revogado, cheio, encerrado, duplicado, última vaga, falha do Worker,
  reconexão e inatividade.
- **Tela de partida sem rolagem** de 1024×768 a 1920×1080: tabuleiro como o maior quadrado que cabe, cartas proporcionais à altura da janela com
  piso e teto, aviso de turno ao lado do tabuleiro.
- **Perfil:** editor de capa com zoom (coluna `cover_zoom`, padrão 100 = enquadramento antigo), arrastar 1:1, roda do mouse/teclado,
  centralizar, imagem inteira, redefinir e mapa da área visível; conquistas em destaque dentro do card, escolhidas num seletor; logo do Google
  em login, cadastro e Conta Google.
- **README** reescrito com a galeria das cartas, diagramas e seções recolhíveis.

## 25/11/2026

- Varredura automática de partidas paradas (`games:sweep`), log operacional (`OpsLog`) e índices de partidas ativas.
- Ranking só com quem tem vitórias; botão de Rankings junto do "Inicial".
- Organização da documentação em `docs/` (este conjunto), com modelo de variáveis de ambiente e guia de rotinas.

## 24/11/2026

- **Segurança e contas:** auditoria de segurança; cadastro que só cria a conta depois do código de e-mail; entrada por Google em conta já existente;
  e-mail de recuperação com a marca; e-mail resiliente (Brevo com reserva no Resend).
- **Partida:** vez de 25 s com 3 timeouts (o 3º remove o jogador e um bot assume); bots que jogam um passo por vez; cartas de ataque;
  tabuleiro sem rolagem; carta da Pull Request; bloco novo voando do baralho; etiqueta de "quem está jogando".
- **Ranking e perfis:** ranking geral/semanal/mensal baseado em `match_results`; fundos de perfil; lista de jogadores recomendados; botão "Deixar de seguir".
- **Desempenho:** Echo/WebSocket só quando necessário, sem *prefetch*, cache longo de arquivos, fontes sem bloquear, caches do Laravel no boot,
  Dashboard sem WebSocket.
- **Tutorial e entrada:** tutorial narrativo de estagiário; entrada por convite automática; busca de sala por código cancelável.
- **Erros e espera:** páginas de erro e de espera personalizadas; erros em português sem texto técnico.
- **Autoria:** commits reescritos para remover atribuições automáticas; todos em nome do autor do repositório.

## 23/11/2026

- **Multiplayer:** salas, convites, bots, substituição de inativo, saída do anfitrião, criação guiada de sala, partida mobile.
- **Tempo real:** Reverb com *fallback* por polling; **Cloudflare Workers + Durable Objects** como alternativa (desligada por padrão).
- **Banco:** correções para o pooler do Neon (prepares emulados, booleanos), migrations compatíveis com banco existente, imagens de perfil no PostgreSQL.
- **Perfis:** fotos e capas servidas como imagem cacheável; ícones gerados no editor; visitantes (contas temporárias).
- **Infra:** `HOME` legível para o Reverb e o agendador; páginas de erro estáticas do Apache.

## Antes (resumo)

Estrutura inicial do jogo, login, cadastro, perfis, ranking básico e Docker/Render.
