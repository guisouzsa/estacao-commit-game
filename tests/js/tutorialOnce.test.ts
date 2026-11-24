import assert from 'node:assert/strict';
import { beforeEach, test } from 'node:test';
import { claimTutorial, resetTutorialClaims } from '../../resources/js/lib/tutorialOnce.ts';

const memory = () => {
    const data = new Map<string, string>();
    return {
        getItem: (k: string) => data.get(k) ?? null,
        setItem: (k: string, v: string) => void data.set(k, v),
    };
};

beforeEach(() => resetTutorialClaims());

test('abre na primeira vez que a partida é vista e nunca mais para a mesma partida', () => {
    const store = memory();
    assert.equal(claimTutorial('partida-1', store), true);
    // re-render, reconexão, atualização de estado: mesma partida
    for (let i = 0; i < 5; i++) assert.equal(claimTutorial('partida-1', store), false);
});

test('uma partida NOVA abre o tutorial de novo', () => {
    const store = memory();
    assert.equal(claimTutorial('partida-1', store), true);
    assert.equal(claimTutorial('partida-2', store), true);
    assert.equal(claimTutorial('partida-2', store), false);
});

test('recarregar a página (mesmo armazenamento, módulo novo) não reabre', () => {
    const store = memory();
    assert.equal(claimTutorial('partida-1', store), true);
    resetTutorialClaims(); // simula a página recarregada: o Set some, o armazenamento fica
    assert.equal(claimTutorial('partida-1', store), false);
});

test('sem armazenamento disponível: abre uma vez e não repete enquanto a página vive', () => {
    assert.equal(claimTutorial('partida-1', null), true);
    assert.equal(claimTutorial('partida-1', null), false);
    const broken = {
        getItem: () => {
            throw new Error('bloqueado');
        },
        setItem: () => {
            throw new Error('bloqueado');
        },
    };
    assert.equal(claimTutorial('partida-9', broken), true);
    assert.equal(claimTutorial('partida-9', broken), false);
});
