import assert from 'node:assert/strict';
import { test } from 'node:test';
import { needsBlockingLoader } from '../../resources/js/lib/loadingPolicy.ts';

test('entrar em sala, criar sala, entrar como visitante e abrir partida bloqueiam', () => {
    for (const [method, path] of [
        ['post', '/jogar'],
        ['post', '/salas'],
        ['post', '/convites/AB2CD'],
        ['get', '/convites/AB2CD'],
        ['get', '/salas/0190aaaa-0000-7000-8000-000000000001'],
        ['get', '/salas/0190aaaa-0000-7000-8000-000000000001/partida'],
    ]) {
        assert.equal(needsBlockingLoader(method, path), true, `${method} ${path}`);
    }
});

test('telas simples e dados secundários NUNCA abrem modal', () => {
    for (const path of ['/', '/login', '/register', '/jogar', '/rankings', '/notificacoes', '/perfil/gui', '/perfil/gui/seguidores', '/perfil/gui/seguindo', '/profile', '/dashboard', '/forgot-password']) {
        assert.equal(needsBlockingLoader('get', path), false, `get ${path}`);
    }
    for (const [method, path] of [['post', '/login'], ['post', '/register'], ['patch', '/profile'], ['post', '/notificacoes/lidas'], ['post', '/salas/x/partida/acoes']]) {
        assert.equal(needsBlockingLoader(method, path), false, `${method} ${path}`);
    }
});
