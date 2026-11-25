# Deploy no Render

Guia do deploy do Estação Commit em um **Web Service Docker** do Render, usando o Neon como banco. Para a lista completa de variáveis,
veja [`VARIAVEIS-DE-AMBIENTE.md`](VARIAVEIS-DE-AMBIENTE.md). Rotinas agendadas: [`CRON-JOBS.md`](CRON-JOBS.md). Voltar ao [índice](../README.md).

> Não há `render.yaml` no repositório: o serviço é configurado no painel. Este guia descreve o que o repositório espera.

## 1. O que roda no contêiner

Um único contêiner (imagem do [`Dockerfile`](../../Dockerfile)), com o Supervisor mantendo estes processos
([`docker/supervisor/supervisord.conf`](../../docker/supervisor/supervisord.conf)):

| Processo | Função |
| --- | --- |
| `apache` | serve o site (PHP) e encaminha `/app/` ao Reverb (WebSocket) |
| `reverb` | servidor WebSocket interno (`127.0.0.1:8080`) |
| `scheduler` | `php artisan schedule:work` (rotinas agendadas) |
| `essential-watchdog.py` | vigia `apache` e `reverb`; se um cair, derruba o contêiner e o Render reinicia |

**Limite do plano gratuito:** ~512 MB de RAM. O Apache está limitado a **2 processos** (`docker/apache/mpm-tuning.conf`) de propósito.
Não aumente sem medir a memória. Cada WebSocket aberto ocupa um deles (por isso o Echo só conecta quando uma página precisa). O aviso
`AH00161: server reached MaxRequestWorkers` indica que os 2 estão ocupados; veja [`../ARQUITETURA.md`](../ARQUITETURA.md) (investigação).

## 2. Criar o serviço

1. No Render: **New → Web Service**, conecte o repositório e escolha **Runtime: Docker** (o Render usa o `Dockerfile` da raiz).
2. **Branch:** a que será publicada (normalmente `main`).
3. **Health check / porta:** a imagem escuta na porta definida pela variável `PORT` do Render; não é preciso configurar.
4. **Environment:** crie as variáveis obrigatórias de [`VARIAVEIS-DE-AMBIENTE.md`](VARIAVEIS-DE-AMBIENTE.md), seção 1 (e o pacote da seção 12).
5. **Banco:** crie um projeto no Neon, copie os dados de conexão (host com `-pooler`) para `DB_*` e use `DB_SSLMODE=require`.
6. Salve e faça o primeiro deploy.

## 3. O que acontece em cada deploy

1. **Build** (multi-stage): Node compila o front (`npm run build`); depois a imagem PHP instala dependências sem pacotes de desenvolvimento.
2. **Boot** ([`docker-entrypoint.sh`](../../docker-entrypoint.sh)): prepara permissões, gera `config:cache`/`event:cache`/`view:cache` e roda
   `php artisan migrate --force` (até 5 tentativas). Se a migration falhar de vez, o deploy falha e o Render **mantém a versão anterior**.
3. O Supervisor inicia `apache`, `reverb`, `scheduler` e o vigia.

**Só migrations aditivas** devem ir para produção. Antes do deploy, confira `git diff --stat main -- database/migrations`.

## 3.1 Variáveis `VITE_*`

Entram no JavaScript **durante o build**. Mudou o valor? **Manual Deploy → Clear build cache & deploy**. Elas são opcionais (o front usa
a chave pública e o endereço do próprio site se faltarem).

## 4. Depois do deploy: conferir

- A página inicial abre e as imagens carregam.
- `Entrar` / `Criar conta` funciona (e o e-mail chega, se ligado).
- Criar sala → lobby → iniciar partida com bots.
- Logs do Render sem `integration.failure` nem erros de banco.
- Linhas `Running [artisan games:sweep]` por minuto (scheduler vivo).

## 5. Plano gratuito: o serviço dorme

O Render Free suspende o serviço por inatividade e o Neon pode "acordar" em seguida. O primeiro acesso demora enquanto os dois sobem.
Isso é comportamento da hospedagem, não do aplicativo. O projeto **não** faz requisições periódicas para manter o serviço acordado.
Erros de infraestrutura (502/503/504 antes de chegar ao Laravel) usam as páginas estáticas de `public/errors/` (`docker/apache/error-pages.conf`).

## 6. Domínio e URL

Se a URL mudar: atualize `APP_URL`, `REVERB_HOST` e `GOOGLE_REDIRECT_URI` (e cadastre a nova URI no Google Cloud); com Cloudflare, também
`APP_ORIGIN`, `LARAVEL_CALLBACK_URL` e `ALLOWED_ORIGINS`. Faça deploy com rebuild se usar `VITE_*`.

## 7. Problemas comuns

Veja a tabela de solução de problemas em [`../MANUTENCAO/GUIA-DE-MANUTENCAO.md`](../MANUTENCAO/GUIA-DE-MANUTENCAO.md) (seção 6) e o guia de
investigação em [`../ARQUITETURA.md`](../ARQUITETURA.md) (seções 4 e 10).
