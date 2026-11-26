import assert from 'node:assert/strict';
import { test } from 'node:test';
import { judge } from '../../resources/js/lib/imageSafety.ts';

test('fotos comuns, desenhos e paisagens passam', () => {
    assert.equal(judge({ Neutral: 0.9, Drawing: 0.05, Sexy: 0.03, Porn: 0.01, Hentai: 0.01 }), true);
    assert.equal(judge({ Drawing: 0.8, Neutral: 0.15, Hentai: 0.03, Sexy: 0.01, Porn: 0.01 }), true);
    assert.equal(judge({ Neutral: 0.5, Sexy: 0.3, Porn: 0.1, Hentai: 0.05 }), true); // soma 0,45
});

test('pornografia, hentai e conteúdo sexual são barrados', () => {
    assert.equal(judge({ Porn: 0.55, Neutral: 0.4 }), false);
    assert.equal(judge({ Hentai: 0.5, Drawing: 0.5 }), false);
    assert.equal(judge({ Sexy: 0.62, Neutral: 0.38 }), false);
});

test('a soma das classes impróprias também conta', () => {
    // Nenhuma passa do limite sozinha, mas juntas chegam a 0,78.
    assert.equal(judge({ Porn: 0.3, Sexy: 0.3, Hentai: 0.18, Neutral: 0.22 }), false);
});

test('sem probabilidades, nada é barrado', () => {
    assert.equal(judge({}), true);
});
