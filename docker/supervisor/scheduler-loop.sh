#!/bin/sh
# Agendador econômico do Estação Commit (substitui `php artisan schedule:work`).
#
# Por que não `schedule:work`: ele mantém um processo PHP/Laravel RESIDENTE (dezenas de MB) só para acordar a cada minuto,
# e ainda cria um subprocesso por tarefa. Aqui o processo residente é este shell (~1 MB) e, uma vez por minuto, roda UM
# `php artisan schedule:run` curto: ele carrega o Laravel, executa as rotinas vencidas (em processo) e termina.
#
# Proteções, pensadas para o Render Free (512 MB, 2 processos Apache):
#  - sequencial: a próxima execução só começa depois que a anterior termina (nunca duas ao mesmo tempo aqui);
#  - `nice`: prioridade baixa de CPU, então o Apache atende as páginas primeiro;
#  - `timeout`: uma execução travada é encerrada (padrão 50 s) e a seguinte começa no próximo minuto;
#  - `memory_limit` próprio para o PHP desta execução (padrão 96M);
#  - alinhado ao início de cada minuto, sem deriva.
#
# Variáveis opcionais (ambiente do Render): SCHEDULER_ENABLED=false (desliga), SCHEDULER_NICE (15),
# SCHEDULER_TIMEOUT (50, em segundos), SCHEDULER_MEMORY_LIMIT (96M).

set -u
cd /var/www/html || exit 1

if [ "${SCHEDULER_ENABLED:-true}" = "false" ]; then
    echo "scheduler: desativado por SCHEDULER_ENABLED=false (nenhuma rotina agendada será executada)." >&2
    # Fica parado (custo ~0) em vez de sair: assim o Supervisor não tenta reiniciar em laço.
    exec sleep 2147483647
fi

NICE="${SCHEDULER_NICE:-15}"
LIMIT="${SCHEDULER_TIMEOUT:-50}"
MEMORY="${SCHEDULER_MEMORY_LIMIT:-96M}"

echo "scheduler: iniciado (schedule:run a cada minuto, nice=${NICE}, timeout=${LIMIT}s, memory_limit=${MEMORY})." >&2

while true; do
    # Dorme até o próximo minuto cheio.
    now=$(date +%s)
    sleep $((60 - now % 60))

    # stdout descartado (o agendador só imprime "Running..." por tarefa); os eventos úteis saem pelo OpsLog
    # (cron.job / cron.job_failed / cron.heartbeat). Erros do PHP continuam indo para stderr.
    nice -n "$NICE" timeout -k 5 "$LIMIT" php -d "memory_limit=${MEMORY}" artisan schedule:run --no-interaction >/dev/null
    code=$?
    if [ "$code" -ne 0 ]; then
        echo "scheduler: schedule:run terminou com código ${code} (124 = tempo esgotado). A próxima execução começa no minuto seguinte." >&2
    fi
done
