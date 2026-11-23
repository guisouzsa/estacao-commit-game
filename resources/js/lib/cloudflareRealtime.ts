// Cliente de tempo real da Cloudflare (Worker + Durable Objects).
//
// Implementa o mesmo contrato mínimo que roomSync.ts usa do Laravel Echo
// (private/listen/stopListening/leave), então as páginas não mudam. Cada canal abre
// um WebSocket próprio no Worker com um token curto emitido pelo Laravel. Ao
// (re)conectar dispara '.pusher:subscription_succeeded', o que faz a página buscar
// o estado de novo e recuperar qualquer aviso perdido durante a queda.

type Listener = (...args: unknown[]) => void;
type SocketLike = {
    onopen: null | (() => void);
    onmessage: null | ((e: { data: unknown }) => void);
    onclose: null | ((e: { code: number }) => void);
    close(code?: number): void;
};

export type CloudflareRealtimeDeps = {
    fetchToken: (channel: string) => Promise<{ url: string; token: string }>;
    createSocket: (url: string) => SocketLike;
    onConnectionChange?: (connected: boolean) => void;
    setTimer?: (fn: () => void, ms: number) => ReturnType<typeof setTimeout>;
    clearTimer?: (t: ReturnType<typeof setTimeout>) => void;
};

type ChannelState = {
    listeners: Map<string, Set<Listener>>;
    socket?: SocketLike;
    retry?: ReturnType<typeof setTimeout>;
    attempts: number;
    closed: boolean;
};

const REPLACED = 4000; // o servidor encerrou por uma conexão mais nova do mesmo ator
const AUTH_FAILED = 4401;

export function createCloudflareEcho(deps: CloudflareRealtimeDeps) {
    const setTimer = deps.setTimer ?? setTimeout;
    const clearTimer = deps.clearTimer ?? clearTimeout;
    const channels = new Map<string, ChannelState>();

    const emit = (state: ChannelState, event: string, payload?: unknown) =>
        state.listeners.get(event)?.forEach((fn) => fn(payload));

    const connect = async (name: string, state: ChannelState) => {
        if (state.closed) return;
        try {
            const { url, token } = await deps.fetchToken(name);
            if (state.closed) return;
            const socket = deps.createSocket(`${url}?token=${encodeURIComponent(token)}`);
            state.socket = socket;
            socket.onopen = () => {
                state.attempts = 0;
                deps.onConnectionChange?.(true);
                emit(state, '.pusher:subscription_succeeded');
            };
            socket.onmessage = (e) => {
                try {
                    const msg = JSON.parse(String(e.data)) as { event?: string; data?: unknown };
                    if (msg.event && msg.event !== 'ready') emit(state, `.${msg.event}`, msg.data);
                } catch {
                    /* mensagem fora do formato: ignora */
                }
            };
            socket.onclose = ({ code }) => {
                if (state.closed || code === REPLACED) return;
                deps.onConnectionChange?.(false);
                if (code === AUTH_FAILED) emit(state, '.pusher:subscription_error', { code });
                schedule(name, state);
            };
        } catch (error) {
            // Token negado (403) ou rede fora: avisa e tenta de novo com recuo.
            emit(state, '.pusher:subscription_error', error);
            deps.onConnectionChange?.(false);
            schedule(name, state);
        }
    };

    const schedule = (name: string, state: ChannelState) => {
        if (state.closed) return;
        const delay = Math.min(30000, 1000 * 2 ** state.attempts++) + Math.random() * 500;
        state.retry = setTimer(() => void connect(name, state), delay);
    };

    const channel = (name: string) => {
        let state = channels.get(name);
        if (!state) {
            state = { listeners: new Map(), attempts: 0, closed: false };
            channels.set(name, state);
            void connect(name, state);
        }
        const s = state;
        const api = {
            listen(event: string, cb: Listener) {
                if (!s.listeners.has(event)) s.listeners.set(event, new Set());
                s.listeners.get(event)!.add(cb);
                return api;
            },
            stopListening(event: string, cb?: Listener) {
                if (cb) s.listeners.get(event)?.delete(cb);
                else s.listeners.delete(event);
                return api;
            },
        };
        return api;
    };

    return {
        private: channel,
        leave(name: string) {
            const state = channels.get(name);
            if (!state) return;
            state.closed = true;
            if (state.retry) clearTimer(state.retry);
            state.socket?.close(1000);
            channels.delete(name);
        },
    };
}
