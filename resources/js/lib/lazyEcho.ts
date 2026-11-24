// Echo preguiçoso: a biblioteca (laravel-echo + pusher-js) e a conexão WebSocket só
// existem enquanto alguma página tem um canal assinado.
//
// Por que: no servidor (Render Free) o Apache atende só 2 requisições ao mesmo tempo e
// cada WebSocket aberto ocupa uma delas por inteiro. Conectar na página inicial, no login
// ou no perfil (que não usam tempo real) tirava vagas das páginas que precisam. Também
// tira ~60 kB do JavaScript da primeira carga.

type Listener = (...args: unknown[]) => void;

export type RealChannel = {
    listen(event: string, callback: Listener): unknown;
    stopListening(event: string, callback?: Listener): unknown;
};

export type RealEcho = {
    private(channel: string): RealChannel;
    leave(channel: string): unknown;
    disconnect(): unknown;
};

type Entry = {
    listeners: Array<[string, Listener]>;
    real?: RealChannel;
    left: boolean;
};

export function createLazyEcho(load: () => Promise<RealEcho>) {
    let echo: RealEcho | undefined;
    let loading: Promise<RealEcho> | undefined;
    const entries = new Map<string, Entry>();

    const ensure = () => {
        loading ??= load().then((instance) => {
            echo = instance;
            return instance;
        });
        return loading;
    };

    const attach = (name: string, entry: Entry) => {
        void ensure()
            .then((instance) => {
                if (entry.left || entries.get(name) !== entry) return;
                entry.real = instance.private(name);
                for (const [event, callback] of entry.listeners) entry.real.listen(event, callback);
            })
            .catch(() => {
                // Sem tempo real (biblioteca não carregou): o polling de segurança da página cobre.
                loading = undefined;
            });
    };

    return {
        private(name: string) {
            let entry = entries.get(name);
            if (!entry) {
                entry = { listeners: [], left: false };
                entries.set(name, entry);
                attach(name, entry);
            }
            const current = entry;
            const channel = {
                listen(event: string, callback: Listener) {
                    current.listeners.push([event, callback]);
                    current.real?.listen(event, callback);
                    return channel;
                },
                stopListening(event: string, callback?: Listener) {
                    current.listeners = current.listeners.filter(
                        ([e, cb]) => !(e === event && (callback === undefined || cb === callback)),
                    );
                    current.real?.stopListening(event, callback);
                    return channel;
                },
            };

            return channel;
        },

        leave(name: string) {
            const entry = entries.get(name);
            if (!entry) return;
            entry.left = true;
            entries.delete(name);
            if (entry.real) echo?.leave(name);
            // Última página saiu: fecha a conexão e devolve a vaga ao servidor.
            if (entries.size === 0 && echo) {
                echo.disconnect();
                echo = undefined;
                loading = undefined;
            }
        },
    };
}
