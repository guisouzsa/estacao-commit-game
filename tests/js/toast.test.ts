import assert from 'node:assert/strict';
import { afterEach, beforeEach, describe, it, mock } from 'node:test';
import {
    EXIT_ANIMATION_MS,
    dismissToast,
    friendlyHttpMessage,
    getToastSnapshot,
    resetToasts,
    showToast,
    subscribeToasts,
    toast,
} from '../../resources/js/lib/toast.ts';

describe('fila de toasts', () => {
    beforeEach(() => {
        mock.timers.enable({ apis: ['setTimeout', 'Date'] });
        resetToasts();
    });
    afterEach(() => {
        resetToasts();
        mock.timers.reset();
    });

    it('mostra o aviso e o remove sozinho', () => {
        toast.success('Sala excluída.');
        assert.equal(getToastSnapshot().length, 1);
        assert.equal(getToastSnapshot()[0].tone, 'success');

        mock.timers.tick(5999);
        assert.equal(getToastSnapshot().length, 1);
        assert.equal(getToastSnapshot()[0].leaving, undefined);

        // Passou o tempo: começa a animação de saída (ainda visível) e só depois sai da fila.
        mock.timers.tick(1);
        assert.equal(getToastSnapshot().length, 1);
        assert.equal(getToastSnapshot()[0].leaving, true);
        mock.timers.tick(EXIT_ANIMATION_MS);
        assert.equal(getToastSnapshot().length, 0);
    });

    it('erros ficam mais tempo na tela', () => {
        toast.error('Falhou.');
        mock.timers.tick(6000);
        assert.equal(getToastSnapshot().length, 1);
        assert.equal(getToastSnapshot()[0].leaving, undefined, 'erros não somem com 6 s');
        mock.timers.tick(3000);
        mock.timers.tick(EXIT_ANIMATION_MS); // o timer da animação nasce durante o tick anterior
        assert.equal(getToastSnapshot().length, 0);
    });

    it('duração 0 mantém o aviso até ser fechado', () => {
        const uid = toast.info('Fica.', { durationMs: 0 }) as number;
        mock.timers.tick(60000);
        assert.equal(getToastSnapshot().length, 1);
        toast.dismiss(uid);
        assert.equal(getToastSnapshot()[0].leaving, true);
        mock.timers.tick(EXIT_ANIMATION_MS);
        assert.equal(getToastSnapshot().length, 0);
    });

    it('o mesmo id nunca aparece duas vezes (evento duplicado, recarregamento)', () => {
        assert.notEqual(toast.warning('Sala excluída.', { id: 'notice:1' }), null);
        assert.equal(toast.warning('Sala excluída.', { id: 'notice:1' }), null);

        mock.timers.tick(10000);
        mock.timers.tick(EXIT_ANIMATION_MS);
        assert.equal(getToastSnapshot().length, 0);
        assert.equal(toast.warning('Sala excluída.', { id: 'notice:1' }), null, 'nem depois de sumir');
        assert.notEqual(toast.warning('Sala excluída.', { id: 'notice:2' }), null);
    });

    it('a mesma mensagem repetida em seguida é descartada, mas volta depois de um tempo', () => {
        assert.notEqual(toast.info('Um jogador entrou.'), null);
        assert.equal(toast.info('Um jogador entrou.'), null);
        assert.equal(getToastSnapshot().length, 1);

        mock.timers.tick(3001);
        assert.notEqual(toast.info('Um jogador entrou.'), null);
    });

    it('mensagens iguais com tons diferentes são avisos diferentes', () => {
        toast.info('Atenção.');
        toast.error('Atenção.');
        assert.equal(getToastSnapshot().length, 2);
    });

    it('a mesma chave substitui o aviso em vez de empilhar', () => {
        toast.info('Sua conexão foi interrompida.', { key: 'connection', durationMs: 0 });
        toast.success('Conexão restabelecida.', { key: 'connection' });

        const visible = getToastSnapshot();
        assert.equal(visible.length, 1);
        assert.equal(visible[0].message, 'Conexão restabelecida.');
        assert.equal(visible[0].tone, 'success');
    });

    it('chaves permitem repetir o mesmo ciclo várias vezes', () => {
        for (let cycle = 0; cycle < 3; cycle++) {
            toast.info('Sua conexão foi interrompida.', { key: 'connection', durationMs: 0 });
            toast.success('Conexão restabelecida.', { key: 'connection', durationMs: 0 });
        }
        assert.equal(getToastSnapshot().length, 1);
    });

    it('limita a quatro avisos visíveis, descartando os mais antigos', () => {
        for (let index = 1; index <= 6; index++) toast.info(`Aviso ${index}`, { id: `n${index}` });

        assert.deepEqual(getToastSnapshot().map((item) => item.message), ['Aviso 3', 'Aviso 4', 'Aviso 5', 'Aviso 6']);
        // O timer de um aviso descartado não pode mexer nos demais.
        mock.timers.tick(6000);
        mock.timers.tick(EXIT_ANIMATION_MS);
        assert.equal(getToastSnapshot().length, 0);
    });

    it('guarda o título do aviso (e usa o padrão do tipo quando não há)', () => {
        toast.success('A sala foi criada com sucesso.', { title: 'Sua sala está pronta!' });
        toast.info('Sem título próprio.');

        const [first, second] = getToastSnapshot();
        assert.equal(first.title, 'Sua sala está pronta!');
        assert.equal(second.title, undefined);
    });

    it('fechar manualmente anima a saída; fechar de novo remove na hora', () => {
        const uid = toast.warning('Sala perto de expirar.', { durationMs: 0 }) as number;

        dismissToast(uid);
        assert.equal(getToastSnapshot()[0].leaving, true);

        dismissToast(uid); // segundo pedido durante a animação
        assert.equal(getToastSnapshot().length, 0);
    });

    it('dismiss imediato não anima', () => {
        const uid = toast.info('Some já.', { durationMs: 0 }) as number;

        dismissToast(uid, true);

        assert.equal(getToastSnapshot().length, 0);
    });

    it('fechar um aviso que já saiu não dá erro', () => {
        assert.doesNotThrow(() => dismissToast(999999));
    });

    it('ignora mensagens vazias', () => {
        assert.equal(showToast({ message: '   ' }), null);
        assert.equal(getToastSnapshot().length, 0);
    });

    it('avisa os assinantes a cada mudança e permite cancelar a assinatura', () => {
        let calls = 0;
        const unsubscribe = subscribeToasts(() => {
            calls += 1;
        });

        toast.info('A');
        toast.info('B');
        assert.equal(calls, 2);

        unsubscribe();
        toast.info('C');
        assert.equal(calls, 2);
    });

    it('o instantâneo só muda de identidade quando há mudança (useSyncExternalStore)', () => {
        toast.info('A');
        const first = getToastSnapshot();
        assert.equal(getToastSnapshot(), first);

        toast.info('B');
        assert.notEqual(getToastSnapshot(), first);
    });
});

describe('mensagens amigáveis de erro', () => {
    it('nunca devolve texto técnico', () => {
        for (const status of [401, 403, 404, 409, 410, 419, 422, 429, 500, 502, 503, undefined]) {
            const message = friendlyHttpMessage(status);
            assert.ok(message.length > 10, String(status));
            assert.ok(!/exception|stack|sql|trace|undefined|null/i.test(message), message);
        }
    });

    it('explica o que aconteceu em cada caso conhecido', () => {
        assert.match(friendlyHttpMessage(403), /permissão/);
        assert.match(friendlyHttpMessage(419), /sessão expirou/);
        assert.match(friendlyHttpMessage(429), /Muitas tentativas/);
        assert.match(friendlyHttpMessage(503), /indisponível/);
        assert.match(friendlyHttpMessage(500), /do nosso lado/);
    });
});
