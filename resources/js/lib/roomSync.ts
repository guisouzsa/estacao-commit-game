// Sincronização do estado da sala com o servidor.
//
// O WebSocket só AVISA que algo mudou; quem sabe o estado é o servidor. Este
// módulo decide quando pedir o estado de novo, sem nunca reconstruí-lo a partir
// dos eventos. Não importa nada de propósito: assim é testável sem navegador.

type Listener = (...args: unknown[]) => void;

export type RoomChannel = {
    listen(event: string, callback: Listener): unknown;
    stopListening(event: string, callback?: Listener): unknown;
};

export type RoomEcho = {
    private(channel: string): RoomChannel;
    leave(channel: string): unknown;
};

type Target = Pick<EventTarget, 'addEventListener' | 'removeEventListener'>;

export type ChannelSyncOptions = {
    /** Instância do Echo, ou null quando o tempo real está desativado. */
    echo: RoomEcho | null;
    /** Canal privado a escutar (sem o prefixo "private-"). */
    channelName: string;
    /** Evento do servidor que significa "algo mudou, busque o estado de novo". */
    event: string;
    /**
     * Permite tratar um evento de forma especial. Se retornar true, o evento
     * foi tratado e NÃO provoca reload (ex.: a sala foi excluída).
     */
    onEvent?: (payload: unknown) => boolean | void;
    /** Dispara um reload no servidor e chama `done` ao terminar (com sucesso ou erro). */
    reload: (done: () => void) => void;
    /** Se o polling de segurança deve pedir o estado neste momento. */
    shouldPoll: () => boolean;
    /** Chamado quando a assinatura do canal é recusada ou falha. */
    onSubscriptionError?: (error: unknown) => void;
    pollIntervalMs?: number;
    /** Tempo máximo que um reload pode ficar "em andamento" antes de liberar o próximo. */
    reloadTimeoutMs?: number;
    document?: Target & { visibilityState: string };
    window?: Target;
};

export type RoomSyncOptions = Omit<
    ChannelSyncOptions,
    'channelName' | 'event' | 'onEvent'
> & {
    roomId: string;
    /** Chamado quando o servidor avisa que a sala foi excluída. */
    onRoomDeleted?: () => void;
};

export const ROOM_STATE_EVENT = '.room.state.changed';
const SUBSCRIBED_EVENT = '.pusher:subscription_succeeded';
const SUBSCRIPTION_ERROR_EVENT = '.pusher:subscription_error';

export const roomChannelName = (roomId: string) => `rooms.${roomId}`;

/**
 * Liga uma página ao tempo real e ao polling de segurança.
 * Retorna a função de limpeza, que remove todos os listeners e timers.
 */
export function startChannelSync(options: ChannelSyncOptions): () => void {
    const {
        echo,
        channelName,
        event,
        onEvent,
        reload,
        shouldPoll,
        onSubscriptionError,
        pollIntervalMs = 15000,
        reloadTimeoutMs = 30000,
    } = options;
    const doc = options.document;
    const win = options.window;

    let stopped = false;
    let inFlight = false;
    let pending = false;
    let releaseTimer: ReturnType<typeof setTimeout> | undefined;

    // Pede o estado ao servidor, no máximo um reload por vez. Pedidos feitos
    // durante um reload viram UM reload extra ao final, pois podem refletir
    // algo mais novo do que a resposta que já está a caminho.
    const sync = () => {
        if (stopped) return;
        if (inFlight) {
            pending = true;
            return;
        }

        inFlight = true;
        let finished = false;
        const done = () => {
            if (finished) return;
            finished = true;
            clearTimeout(releaseTimer);
            inFlight = false;
            if (pending && !stopped) {
                pending = false;
                sync();
            }
        };

        // Rede travada não pode bloquear a sincronização para sempre.
        releaseTimer = setTimeout(done, reloadTimeoutMs);

        try {
            reload(done);
        } catch {
            done();
        }
    };

    const onTabVisible = () => {
        if (doc?.visibilityState === 'visible') sync();
    };
    const onOnline = () => sync();
    const onRealtimeError = (error: unknown) => onSubscriptionError?.(error);

    const onChange = (payload: unknown) => {
        if (onEvent?.(payload) === true) return;
        sync();
    };

    let channel: RoomChannel | undefined;

    if (echo) {
        channel = echo.private(channelName);
        channel.listen(event, onChange);
        // Dispara na primeira assinatura e em toda reassinatura após uma
        // reconexão, o que recupera eventos perdidos durante a queda.
        channel.listen(SUBSCRIBED_EVENT, sync);
        channel.listen(SUBSCRIPTION_ERROR_EVENT, onRealtimeError);
    }

    doc?.addEventListener('visibilitychange', onTabVisible);
    win?.addEventListener('online', onOnline);

    const poll = setInterval(() => {
        if (shouldPoll()) sync();
    }, pollIntervalMs);

    return () => {
        stopped = true;
        pending = false;
        clearInterval(poll);
        clearTimeout(releaseTimer);
        doc?.removeEventListener('visibilitychange', onTabVisible);
        win?.removeEventListener('online', onOnline);

        if (echo && channel) {
            channel.stopListening(event, onChange);
            channel.stopListening(SUBSCRIBED_EVENT, sync);
            channel.stopListening(SUBSCRIPTION_ERROR_EVENT, onRealtimeError);
            echo.leave(channelName);
        }
    };
}

/** Sincronização da página de uma sala: canal `rooms.{id}` e evento de estado. */
export function startRoomSync(options: RoomSyncOptions): () => void {
    const { roomId, onRoomDeleted, ...rest } = options;

    return startChannelSync({
        ...rest,
        channelName: roomChannelName(roomId),
        event: ROOM_STATE_EVENT,
        // "deleted" só decide para onde navegar; nenhum estado vem do evento.
        onEvent: onRoomDeleted
            ? (payload) => {
                  const change = (payload as { change?: unknown } | null)?.change;
                  if (change === 'deleted') {
                      onRoomDeleted();
                      return true;
                  }
              }
            : undefined,
    });
}
