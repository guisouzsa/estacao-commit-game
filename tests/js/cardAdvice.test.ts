import assert from 'node:assert/strict';
import { test } from 'node:test';
import { spareCardIndexes } from '../../resources/js/lib/cardAdvice.ts';

const spare = (hand: string[], step: number) => [...spareCardIndexes(hand, step)].sort();

test('cartas que o percurso já passou ficam marcadas como sobrando', () => {
    // Etapa 3 (depois do checkout): clone e checkout não servem mais.
    assert.deepEqual(spare(['git_clone', 'git_add', 'git_checkout', 'git_commit'], 3), [0, 2]);
});

test('cópias além do que ainda falta são sobra; as necessárias, não', () => {
    // Faltam 1 add e 1 commit (etapa 13: block_4, add, commit, pull, push, PR).
    assert.deepEqual(spare(['git_add', 'git_add', 'git_commit', 'git_push'], 13), [1]);
    // No início todos os 4 add ainda serão usados.
    assert.deepEqual(spare(['git_add', 'git_add', 'git_add', 'git_add'], 1), []);
});

test('ataques: Merge Conflict nunca é sobra; Limpar Credenciais só depois do Treinamento', () => {
    assert.deepEqual(spare(['merge_conflict', 'clear_credentials'], 1), []);
    assert.deepEqual(spare(['merge_conflict', 'clear_credentials'], 3), [1]);
});

test('git pull vale no Treinamento (compra duas) e no fim do percurso', () => {
    assert.deepEqual(spare(['git_pull', 'git_pull'], 1), []);
    assert.deepEqual(spare(['git_pull', 'git_pull'], 4), [1]);
    assert.deepEqual(spare(['git_pull'], 16), [0]); // já passou do pull
});
