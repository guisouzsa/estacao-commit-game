#!/usr/bin/env python3
"""Event listener do supervisord: derruba o supervisor quando um processo
essencial entra em FATAL. Uso: essential-watchdog.py <programa> [<programa>...]

O canal stdout é o protocolo do supervisord; qualquer mensagem de log vai para
stderr, que o supervisord encaminha para os logs do container.
"""
import os
import signal
import sys

ESSENTIAL = set(sys.argv[1:])


def send(text):
    sys.stdout.write(text)
    sys.stdout.flush()


def main():
    while True:
        send("READY\n")
        header = sys.stdin.readline()
        if not header:
            return
        fields = dict(item.split(":", 1) for item in header.split())
        payload = sys.stdin.read(int(fields["len"]))
        data = dict(item.split(":", 1) for item in payload.split() if ":" in item)
        name = data.get("processname")

        if name in ESSENTIAL:
            sys.stderr.write(
                "essential-watchdog: processo essencial '%s' em FATAL; encerrando o container.\n" % name
            )
            sys.stderr.flush()
            # O pai deste listener é o supervisord (PID 1 do container).
            os.kill(os.getppid(), signal.SIGTERM)

        send("RESULT 2\nOK")


if __name__ == "__main__":
    main()
