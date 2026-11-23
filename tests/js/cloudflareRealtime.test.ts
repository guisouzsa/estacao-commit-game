import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createCloudflareEcho } from '../../resources/js/lib/cloudflareRealtime.ts';

class FakeSocket {
    onopen: null | (() => void) = null;
    onmessage: null | ((e: { data: unknown }) => void) = null;
    onclose: null | ((e: { code: number }) => void) = null;
    closed = false;
    url: string;
    constructor(url: string) {
        this.url = url;
    }
    close() {
        this.closed = true;
    }
}

const flush = () => new Promise((r) => setImmediate(r));

function setup(overrides: Partial<Parameters<typeof createCloudflareEcho>[0]> = {}) {
    const sockets: FakeSocket[] = [];
    const timers: Array<() => void> = [];
    const states: boolean[] = [];
    const echo = createCloudflareEcho({
        fetchToken: async (c) => ({ url: `wss://w.test/ws/${c}`, token: 't k' }),
        createSocket: (url) => {
            const s = new FakeSocket(url);
            sockets.push(s);
            return s;
        },
        onConnectionChange: (c) => states.push(c),
        setTimer: ((fn: () => void) => (timers.push(fn), timers.length)) as never,
        clearTimer: (() => {}) as never,
        ...overrides,
    });
    return { echo, sockets, timers, states };
}

test('conecta com token na URL e entrega eventos aos ouvintes', async () => {
    const { echo, sockets } = setup();
    const got: unknown[] = [];
    let subscribed = 0;
    echo.private('rooms.a').listen('.room.state.changed', (p) => got.push(p)).listen('.pusher:subscription_succeeded', () => subscribed++);
    await flush();
    assert.equal(sockets[0].url, 'wss://w.test/ws/rooms.a?token=t%20k');
    sockets[0].onopen!();
    sockets[0].onmessage!({ data: JSON.stringify({ event: 'ready' }) });
    sockets[0].onmessage!({ data: JSON.stringify({ event: 'room.state.changed', data: { change: 'game_updated' } }) });
    sockets[0].onmessage!({ data: 'lixo' });
    assert.equal(subscribed, 1);
    assert.deepEqual(got, [{ change: 'game_updated' }]);
});

test('reconecta com recuo e reassina (recupera estado) após queda', async () => {
    const { echo, sockets, timers, states } = setup();
    let subscribed = 0;
    echo.private('rooms.a').listen('.pusher:subscription_succeeded', () => subscribed++);
    await flush();
    sockets[0].onopen!();
    sockets[0].onclose!({ code: 1006 });
    assert.deepEqual(states, [true, false]);
    assert.equal(timers.length, 1);
    timers[0]();
    await flush();
    sockets[1].onopen!();
    assert.equal(subscribed, 2);
});

test('token negado dispara subscription_error e tenta de novo', async () => {
    let calls = 0;
    const { echo, timers } = setup({
        fetchToken: async () => {
            calls++;
            throw new Error('403');
        },
    });
    let errors = 0;
    echo.private('rooms.a').listen('.pusher:subscription_error', () => errors++);
    await flush();
    assert.equal(errors, 1);
    assert.equal(timers.length, 1);
    timers[0]();
    await flush();
    assert.equal(calls, 2);
});

test('leave fecha o socket e não reconecta', async () => {
    const { echo, sockets, timers } = setup();
    echo.private('rooms.a');
    await flush();
    echo.leave('rooms.a');
    assert.equal(sockets[0].closed, true);
    sockets[0].onclose!({ code: 1000 });
    assert.equal(timers.length, 0);
});

test('conexão substituída (4000) não reconecta em loop', async () => {
    const { echo, sockets, timers } = setup();
    echo.private('rooms.a');
    await flush();
    sockets[0].onclose!({ code: 4000 });
    assert.equal(timers.length, 0);
});
