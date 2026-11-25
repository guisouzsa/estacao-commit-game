# Documentação do Estação Commit

Índice da documentação. Porta de entrada do repositório: [`README.md`](../README.md).

## Comece por aqui

| Documento | Para quê |
| --- | --- |
| [`PROJETO.md`](PROJETO.md) | visão geral: o que é, tecnologias, arquitetura, pastas, fluxo do jogo e segurança |
| [`ARQUITETURA.md`](ARQUITETURA.md) | técnico: mapa de autenticação, multiplayer e timeouts, quedas de serviço, processos, paridade PHP × Worker, observabilidade, banco, baralho, conquistas, contas temporárias e padrão visual |

## Deploy

| Documento | Para quê |
| --- | --- |
| [`DEPLOY/RENDER.md`](DEPLOY/RENDER.md) | publicar no Render (Docker), o que acontece em cada deploy e como conferir |
| [`DEPLOY/VARIAVEIS-DE-AMBIENTE.md`](DEPLOY/VARIAVEIS-DE-AMBIENTE.md) | **todas** as variáveis, por categoria, com pacote para colar no Render |
| [`DEPLOY/CRON-JOBS.md`](DEPLOY/CRON-JOBS.md) | rotinas agendadas: como rodam, como testar, travas, logs e recuperação |
| [`DEPLOY/CLOUDFLARE.md`](DEPLOY/CLOUDFLARE.md) | Worker + Durable Objects (opcional): segredos, variáveis, publicação e retorno ao Reverb |

## Desenvolvimento

| Documento | Para quê |
| --- | --- |
| [`DESENVOLVIMENTO/INSTALACAO-LOCAL.md`](DESENVOLVIMENTO/INSTALACAO-LOCAL.md) | requisitos, instalação, comandos e testes |
| [`../worker/README.md`](../worker/README.md) | detalhes técnicos do Worker (segurança, fases, limites) |

## Manutenção

| Documento | Para quê |
| --- | --- |
| [`MANUTENCAO/GUIA-DE-MANUTENCAO.md`](MANUTENCAO/GUIA-DE-MANUTENCAO.md) | tecnologias, trocar de serviço (hospedagem, banco, e-mail, Google), girar segredos, solução de problemas |
| [`MANUTENCAO/HISTORICO-DE-ALTERACOES.md`](MANUTENCAO/HISTORICO-DE-ALTERACOES.md) | resumo do que foi entregue, por tema e data |

## Jogo (regras e arte)

| Pasta | Conteúdo |
| --- | --- |
| [`JOGO/`](JOGO/) | documento de regras fechado (PDF), cartas, tabuleiro e o relatório de refinamento visual. **Fonte das regras do jogo** |

## Convenções

- Os documentos guardam **nomes** de variáveis, nunca valores reais.
- Nada é descrito como pronto se depende de ativação manual: quando for o caso, o texto diz **"desligado por padrão"** ou **"pendente de ativação"**.
- Ao mover ou criar um documento, atualize este índice e os links internos.
