// Testes do sincronismo da sala. Rodam com o test runner nativo do Node
// (npm run test:js), sem navegador e sem dependências extras.
import assert from 'node:assert/strict';
import { afterEach, beforeEach, describe, it, mock } from 'node:test';
import {
    ROOM_STATE_EVENT,
    roomChannelName,
    startRoomSync,
    type RoomSyncOptions,
} from '../../resources/js/lib/roomSync.ts';

const SUBSCRIBED = '.pusher:subscription_succeeded';
const SUBSCRIPTION_ERROR = '.pusher:subscription_error';

type Handler = (...args: unknown[]) => void;

function createFakeEcho() {
    const handlers = new Map<string, Set<Handler>>();
    const calls = { private: [] as string[], leave: [] as string[] };
    const channel = {
        listen(event: string, callback: Handler) {
            if (!handlers.has(event)) handlers.set(event, new Set());
            handlers.get(event)!.add(callback);
        },
        stopListening(event: string, callback?: Handler) {
            if (callback) handlers.get(event)?.delete(callback);
            else handlers.delete(event);
        },
    };

    return {
        calls,
        echo: {
            private(name: string) {
                calls.private.push(name);
                return channel;
            },
            leave(name: string) {
                calls.leave.push(name);
            },
        },
        count: (event: string) => handlers.get(event)?.size ?? 0,
        emit(event: string, ...args: unknown[]) {
            for (const handler of [...(handlers.get(event) ?? [])]) {
                handler(...args);
            }
        },
    };
}

function createFakeTarget() {
    const target = new EventTarget();
    const listeners = new Map<string, number>();
    return {
        visibilityState: 'visible',
        addEventListener(type: string, cb: EventListenerOrEventListenerObject) {
            listeners.set(type, (listeners.get(type) ?? 0) + 1);
            target.addEventListener(type, cb);
        },
        removeEventListener(type: string, cb: EventListenerOrEventListenerObject) {
            listeners.set(type, (listeners.get(type) ?? 0) - 1);
            target.removeEventListener(type, cb);
        },
        fire: (type: string) => target.dispatchEvent(new Event(type)),
        active: (type: string) => listeners.get(type) ?? 0,
    };
}

describe('startRoomSync', () => {
    let fake: ReturnType<typeof createFakeEcho>;
    let doc: ReturnType<typeof createFakeTarget>;
    let win: ReturnType<typeof createFakeTarget>;
    let reloads: Array<() => void>;
    let status: string;

    const start = (overrides: Partial<RoomSyncOptions> = {}) =>
        startRoomSync({
            echo: fake.echo,
            roomId: 'room-1',
            reload: (done) => reloads.push(done),
            shouldPoll: () => status === 'waiting',
            document: doc,
            window: win,
            ...overrides,
        });
    const finishAll = () => reloads.splice(0).forEach((done) => done());

    beforeEach(() => {
        mock.timers.enable({ apis: ['setInterval', 'setTimeout'] });
        fake = createFakeEcho();
        doc = createFakeTarget();
        win = createFakeTarget();
        reloads = [];
        status = 'waiting';
    });
    afterEach(() => mock.timers.reset());

    it('assina somente o canal privado da própria sala', () => {
        start();

        assert.deepEqual(fake.calls.private, [roomChannelName('room-1')]);
        assert.equal(fake.calls.private[0], 'rooms.room-1');
        assert.equal(fake.count(ROOM_STATE_EVENT), 1);
    });

    it('pede o estado ao servidor quando recebe o evento da sala', () => {
        start();
        assert.equal(reloads.length, 0);

        fake.emit(ROOM_STATE_EVENT, { change: 'participant_joined' });

        assert.equal(reloads.length, 1);
    });

    it('ignora o conteúdo do evento: o reload não recebe nenhum dado dele', () => {
        const received: unknown[][] = [];
        start({
            reload: (...args) => {
                received.push(args);
            },
        });

        fake.emit(ROOM_STATE_EVENT, { room: { status: 'hacked' } });

        assert.equal(received.length, 1);
        assert.equal(received[0].length, 1, 'só o callback de término');
        assert.equal(typeof received[0][0], 'function');
    });

    it('sincroniza ao assinar e a cada reassinatura depois de uma reconexão', () => {
        start();

        fake.emit(SUBSCRIBED);
        assert.equal(reloads.length, 1);
        finishAll();

        // Queda de conexão e retorno: o Pusher reassina o canal.
        fake.emit(SUBSCRIBED);
        assert.equal(reloads.length, 1);
    });

    it('sincroniza ao voltar para a aba, mas não ao escondê-la', () => {
        start();

        doc.visibilityState = 'hidden';
        doc.fire('visibilitychange');
        assert.equal(reloads.length, 0);

        doc.visibilityState = 'visible';
        doc.fire('visibilitychange');
        assert.equal(reloads.length, 1);
    });

    it('sincroniza quando o navegador volta a ficar online', () => {
        start();

        win.fire('online');

        assert.equal(reloads.length, 1);
    });

    it('agrupa pedidos simultâneos: um reload em andamento e no máximo um extra', () => {
        start();

        fake.emit(ROOM_STATE_EVENT);
        fake.emit(ROOM_STATE_EVENT);
        fake.emit(SUBSCRIBED);
        win.fire('online');
        assert.equal(reloads.length, 1, 'só um reload ao mesmo tempo');

        finishAll();
        assert.equal(reloads.length, 1, 'um único reload extra ao final');

        finishAll();
        assert.equal(reloads.length, 0, 'nenhum além disso');
    });

    it('não repete o reload extra se nada foi pedido durante o reload', () => {
        start();
        fake.emit(ROOM_STATE_EVENT);

        finishAll();

        assert.equal(reloads.length, 0);
    });

    it('libera a sincronização se um reload nunca terminar', () => {
        start();
        fake.emit(ROOM_STATE_EVENT);
        assert.equal(reloads.length, 1);

        fake.emit(ROOM_STATE_EVENT);
        assert.equal(reloads.length, 1);

        mock.timers.tick(30000);
        assert.equal(reloads.length, 2, 'o pedido pendente é executado');
    });

    it('continua a sincronizar se o reload lançar uma exceção', () => {
        let attempts = 0;
        start({
            reload: () => {
                attempts += 1;
                throw new Error('falha');
            },
        });

        fake.emit(ROOM_STATE_EVENT);
        fake.emit(ROOM_STATE_EVENT);

        assert.equal(attempts, 2);
    });

    describe('polling de segurança', () => {
        it('atualiza a cada 15 segundos enquanto a sala está em espera', () => {
            start();

            mock.timers.tick(14999);
            assert.equal(reloads.length, 0);

            mock.timers.tick(1);
            assert.equal(reloads.length, 1);
            finishAll();

            mock.timers.tick(15000);
            assert.equal(reloads.length, 1);
        });

        it('não atualiza quando a sala deixou de estar em espera', () => {
            start();
            status = 'expired';

            mock.timers.tick(60000);

            assert.equal(reloads.length, 0);
        });

        it('segue o status atual sem precisar reiniciar o canal', () => {
            start();
            status = 'expired';
            mock.timers.tick(15000);
            assert.equal(reloads.length, 0);

            status = 'waiting';
            mock.timers.tick(15000);
            assert.equal(reloads.length, 1);
            assert.deepEqual(fake.calls.private, ['rooms.room-1']);
        });

        it('continua funcionando sem Echo (tempo real desativado)', () => {
            start({ echo: null });

            mock.timers.tick(15000);
            assert.equal(reloads.length, 1);
            finishAll();

            doc.fire('visibilitychange');
            assert.equal(reloads.length, 1);
        });

        it('continua funcionando quando a assinatura do canal falha', () => {
            const errors: unknown[] = [];
            start({ onSubscriptionError: (error) => errors.push(error) });

            fake.emit(SUBSCRIPTION_ERROR, { status: 403 });
            assert.equal(errors.length, 1);

            mock.timers.tick(15000);
            assert.equal(reloads.length, 1);
        });
    });

    describe('limpeza', () => {
        it('remove todos os listeners, o timer e sai do canal', () => {
            const stop = start();
            assert.equal(fake.count(ROOM_STATE_EVENT), 1);
            assert.equal(fake.count(SUBSCRIBED), 1);
            assert.equal(fake.count(SUBSCRIPTION_ERROR), 1);
            assert.equal(doc.active('visibilitychange'), 1);
            assert.equal(win.active('online'), 1);

            stop();

            assert.equal(fake.count(ROOM_STATE_EVENT), 0);
            assert.equal(fake.count(SUBSCRIBED), 0);
            assert.equal(fake.count(SUBSCRIPTION_ERROR), 0);
            assert.equal(doc.active('visibilitychange'), 0);
            assert.equal(win.active('online'), 0);
            assert.deepEqual(fake.calls.leave, ['rooms.room-1']);

            mock.timers.tick(60000);
            doc.fire('visibilitychange');
            win.fire('online');
            assert.equal(reloads.length, 0);
        });

        it('ignora reload extra pendente e conclusão tardia após desmontar', () => {
            const stop = start();
            fake.emit(ROOM_STATE_EVENT);
            fake.emit(ROOM_STATE_EVENT);

            stop();
            finishAll();

            assert.equal(reloads.length, 0);
        });

        it('montar, desmontar e montar de novo não acumula listeners', () => {
            start()();
            const stop = start();

            assert.equal(fake.count(ROOM_STATE_EVENT), 1);
            assert.equal(doc.active('visibilitychange'), 1);

            fake.emit(ROOM_STATE_EVENT);
            assert.equal(reloads.length, 1);
            stop();
        });
    });
});
