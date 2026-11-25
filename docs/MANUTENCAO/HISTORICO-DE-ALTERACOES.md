# Histórico de alterações

Resumo por tema do que foi entregue, na ordem em que aconteceu. O histórico completo e preciso é o do Git (`git log`). Este arquivo
registra o **estado funcional** do projeto para quem não quer ler commit a commit. Voltar ao [índice](../README.md).

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
