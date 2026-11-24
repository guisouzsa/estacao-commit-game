import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createLazyEcho, type RealEcho } from '../../resources/js/lib/lazyEcho.ts';

const flush = () => new Promise((r) => setImmediate(r));

function fakeEcho() {
    const log: string[] = [];
    const handlers = new Map<string, Array<(p: unknown) => void>>();
    const echo: RealEcho = {
        private(name: string) {
            log.push(`private:${name}`);
            return {
                listen(event: string, cb: (p: unknown) => void) {
                    log.push(`listen:${name}:${event}`);
                    handlers.set(`${name}:${event}`, [...(handlers.get(`${name}:${event}`) ?? []), cb]);
                },
                stopListening(event: string) {
                    log.push(`stop:${name}:${event}`);
                },
            };
        },
        leave(name: string) {
            log.push(`leave:${name}`);
        },
        disconnect() {
            log.push('disconnect');
        },
    };
    return { echo, log, handlers };
}

test('nada é carregado nem conectado até alguma página assinar um canal', async () => {
    let loads = 0;
    createLazyEcho(async () => {
        loads++;
        return fakeEcho().echo;
    });
    await flush();
    assert.equal(loads, 0);
});

test('assina o canal e entrega os eventos registrados antes e depois do carregamento', async () => {
    const f = fakeEcho();
    const lazy = createLazyEcho(async () => f.echo);
    const got: unknown[] = [];
    const ch = lazy.private('rooms.a');
    ch.listen('.x', (p) => got.push(p)); // antes de a biblioteca carregar
    await flush();
    ch.listen('.y', (p) => got.push(p)); // depois
    assert.deepEqual(f.log, ['private:rooms.a', 'listen:rooms.a:.x', 'listen:rooms.a:.y']);
    f.handlers.get('rooms.a:.x')![0]('um');
    f.handlers.get('rooms.a:.y')![0]('dois');
    assert.deepEqual(got, ['um', 'dois']);
});

test('uma única biblioteca/conexão para vários canais; desconecta só quando o último sai', async () => {
    let loads = 0;
    const f = fakeEcho();
    const lazy = createLazyEcho(async () => {
        loads++;
        return f.echo;
    });
    lazy.private('rooms.a');
    lazy.private('App.Models.User.1');
    await flush();
    assert.equal(loads, 1);
    lazy.leave('rooms.a');
    assert.equal(f.log.includes('disconnect'), false);
    lazy.leave('App.Models.User.1');
    assert.equal(f.log.at(-1), 'disconnect');
});

test('sair antes de carregar não assina; depois de desconectar, um novo canal reconecta', async () => {
    const instances: ReturnType<typeof fakeEcho>[] = [];
    const lazy = createLazyEcho(async () => {
        const f = fakeEcho();
        instances.push(f);
        return f.echo;
    });
    lazy.private('rooms.a');
    lazy.leave('rooms.a'); // antes do carregamento terminar
    await flush();
    assert.equal(instances[0].log.includes('private:rooms.a'), false);

    lazy.private('rooms.b');
    await flush();
    lazy.leave('rooms.b');
    lazy.private('rooms.c');
    await flush();
    assert.equal(instances.length >= 2, true);
    assert.equal(instances.at(-1)!.log.includes('private:rooms.c'), true);
});

test('falha ao carregar a biblioteca não quebra a página (polling cobre) e permite nova tentativa', async () => {
    let attempts = 0;
    const f = fakeEcho();
    const lazy = createLazyEcho(async () => {
        attempts++;
        if (attempts === 1) throw new Error('rede');
        return f.echo;
    });
    lazy.private('rooms.a');
    await flush();
    lazy.leave('rooms.a');
    lazy.private('rooms.a');
    await flush();
    assert.equal(attempts, 2);
    assert.equal(f.log.includes('private:rooms.a'), true);
});
