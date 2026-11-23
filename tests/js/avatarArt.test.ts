import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    AVATAR_STYLES,
    avatarDataUri,
    avatarSvg,
    randomSeed,
} from '../../resources/js/lib/avatarArt.ts';

describe('avatares locais por semente', () => {
    it('o mesmo estilo e a mesma semente geram exatamente o mesmo avatar', () => {
        for (const style of AVATAR_STYLES) {
            assert.equal(avatarSvg(style, 'abc123xyz'), avatarSvg(style, 'abc123xyz'));
            assert.equal(avatarDataUri(style, 'abc123xyz'), avatarDataUri(style, 'abc123xyz'));
        }
    });

    it('sementes diferentes geram avatares diferentes (variedade real)', () => {
        for (const style of AVATAR_STYLES) {
            const seeds = Array.from({ length: 60 }, (_, index) => `semente${index}`);
            const distinct = new Set(seeds.map((seed) => avatarSvg(style, seed)));
            assert.ok(distinct.size >= 55, `${style}: só ${distinct.size} de 60 distintos`);
        }
    });

    it('estilos diferentes desenham diferente para a mesma semente', () => {
        const drawings = AVATAR_STYLES.map((style) => avatarSvg(style, 'mesma-semente'));
        assert.equal(new Set(drawings).size, AVATAR_STYLES.length);
    });

    it('produz um SVG completo e autocontido', () => {
        for (const style of AVATAR_STYLES) {
            const svg = avatarSvg(style, 'abc');
            assert.ok(svg.startsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"'));
            assert.ok(svg.endsWith('</svg>'));
            assert.ok(!svg.includes('<script') && !svg.includes('href=') && !svg.includes('http://www.w3.org/1999/xlink'));
            assert.ok(!/undefined|NaN/.test(svg), `${style} tem valor inválido`);
        }
    });

    it('a semente nunca é inserida no desenho (sem injeção)', () => {
        const svg = avatarSvg('pixel', '<script>alert(1)</script>');
        assert.ok(!svg.includes('alert'));
        assert.ok(!svg.includes('<script'));
    });

    it('um estilo desconhecido cai no padrão em vez de quebrar', () => {
        assert.equal(avatarSvg('inexistente', 'abc'), avatarSvg('pixel', 'abc'));
    });

    it('a URL data: é segura para o atributo src', () => {
        const uri = avatarDataUri('robot', 'abc');
        assert.ok(uri.startsWith('data:image/svg+xml;utf8,'));
        assert.ok(!/[<>"\s]/.test(uri.slice('data:image/svg+xml;utf8,'.length)));
    });

    it('a semente aleatória tem o formato que o servidor aceita', () => {
        for (let index = 0; index < 200; index++) {
            assert.match(randomSeed(), /^[a-z0-9]{6,32}$/);
        }
        assert.match(randomSeed(32), /^[a-z0-9]{32}$/);
        assert.notEqual(randomSeed(), randomSeed());
    });
});
