// Aquece o navegador para a partida ENQUANTO a pessoa ainda está no lobby: o pedaço de JavaScript
// da mesa (o maior da aplicação) e as imagens do tabuleiro e das cartas. Quando o anfitrião inicia a
// partida, tudo já está em cache e "Preparando a mesa…" só espera o servidor.
//
// Só dados ESTÁTICOS são pré-carregados (arte e código). Mãos, turno e estado da partida vêm
// sempre do servidor, na hora.

const ART = '/images/estacao-commit';

const CARD_IMAGES = [
    'git-login', 'git-clone', 'git-checkout', 'git-pull', 'git-add', 'git-commit', 'git-push',
    'pull-request', 'limpar-credenciais', 'merge-conflict', 'bloco-1', 'bloco-2', 'bloco-3', 'bloco-4',
];

let started = false;

export const gameImageUrls = (): string[] => [
    `${ART}/tabuleiro.webp`,
    `${ART}/mascots/gato-commit.webp`,
    ...CARD_IMAGES.map((name) => `${ART}/corrected/${name}.webp`),
];

/** Idempotente: a primeira chamada agenda o trabalho para quando o navegador estiver ocioso. */
export function preloadGameAssets(): void {
    if (started || typeof window === 'undefined') return;
    started = true;

    // Quem economiza dados (Data Saver / conexão 2G) não paga por algo que talvez nem use.
    const connection = (navigator as Navigator & { connection?: { saveData?: boolean; effectiveType?: string } }).connection;
    if (connection?.saveData || /(^|-)2g$/.test(connection?.effectiveType ?? '')) return;

    const run = () => {
        // Imagens: o navegador guarda no cache HTTP; <Image> não aparece na tela.
        for (const url of gameImageUrls()) {
            const image = new Image();
            image.decoding = 'async';
            image.src = url;
        }
        // Código da mesa: baixa e compila uma vez; a navegação depois é instantânea.
        void import('@/Pages/Games/Show').catch(() => {
            started = false; // falhou (rede): tenta de novo na próxima oportunidade
        });
    };

    const idle = (window as Window & { requestIdleCallback?: (cb: () => void, o?: { timeout: number }) => number }).requestIdleCallback;
    if (idle) idle(run, { timeout: 4000 });
    else window.setTimeout(run, 1500);
}
