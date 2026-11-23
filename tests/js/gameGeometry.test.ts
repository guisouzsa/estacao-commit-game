import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { AREAS, PATH_LENGTH, PATH_SEQUENCE, sideOf, slotCell, slotRect, type Side } from '../../resources/js/lib/gameGeometry.ts';

const SIDES: Side[] = [0, 1, 2, 3];

describe('geometria da mesa', () => {
    it('o percurso tem 18 passos, como no servidor', () => {
        assert.equal(PATH_LENGTH, 18);
        assert.equal(PATH_SEQUENCE[0], 'git_login');
        assert.equal(PATH_SEQUENCE[17], 'pull_request');
    });

    it('você fica embaixo e o jogo segue no sentido horário (esquerda, topo, direita)', () => {
        assert.equal(sideOf(3, 3), 0);
        assert.equal(sideOf(4, 3), 1);
        assert.equal(sideOf(1, 3), 2);
        assert.equal(sideOf(2, 3), 3);
        assert.equal(sideOf(1, 1), 0);
        assert.equal(sideOf(2, 1), 1);
    });

    it('cada lado tem 18 espaços diferentes dentro da sua área branca', () => {
        for (const side of SIDES) {
            const area = AREAS[side];
            const seen = new Set<string>();
            for (let index = 0; index < PATH_LENGTH; index++) {
                const { col, row } = slotCell(side, index);
                assert.ok(col >= 0 && col < area.cols, `coluna ${col} lado ${side}`);
                assert.ok(row >= 0 && row < area.rows, `linha ${row} lado ${side}`);
                seen.add(`${col}:${row}`);

                const rect = slotRect(side, index);
                assert.ok(rect.left >= area.left - 0.001 && rect.left + rect.width <= area.left + area.width + 0.001);
                assert.ok(rect.top >= area.top - 0.001 && rect.top + rect.height <= area.top + area.height + 0.001);
            }
            assert.equal(seen.size, PATH_LENGTH, `lado ${side} repete espaço`);
        }
    });

    it('o primeiro passo fica no canto de onde cada jogador começa a ler', () => {
        assert.deepEqual(slotCell(0, 0), { col: 0, row: 0 });
        assert.deepEqual(slotCell(0, 9), { col: 0, row: 1 });
        assert.deepEqual(slotCell(2, 0), { col: 8, row: 1 });
        assert.deepEqual(slotCell(1, 0), { col: 0, row: 0 });
        assert.deepEqual(slotCell(3, 0), { col: 1, row: 8 });
    });

    it('as cartas das laterais giram para ficar de frente para o jogador', () => {
        assert.equal(AREAS[0].rotate, 0);
        assert.equal(AREAS[2].rotate, 180);
        assert.equal(AREAS[1].rotate, 90);
        assert.equal(AREAS[3].rotate, -90);
    });
});
