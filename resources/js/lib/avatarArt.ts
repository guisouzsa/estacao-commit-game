// Avatares gerados localmente a partir de uma semente.
//
// Por que local e não uma API (DiceBear etc.): não há chave, limite de uso nem
// dependência de disponibilidade externa; o mesmo jogador (mesma semente) tem
// SEMPRE o mesmo avatar, em qualquer tela; e nunca existe "imagem quebrada",
// porque o SVG nasce no próprio navegador. Sem imports: testável sem navegador.

export type AvatarStyle = 'pixel' | 'robot' | 'terminal' | 'blocks';

export const AVATAR_STYLES: AvatarStyle[] = [
    'pixel',
    'robot',
    'terminal',
    'blocks',
];

export const AVATAR_STYLE_LABELS: Record<AvatarStyle, string> = {
    pixel: 'Pixel art',
    robot: 'Robôs',
    terminal: 'Terminal',
    blocks: 'Blocos',
};

// Paleta do Estação Commit.
const INK = '#1d1d1b';
const PAPER = '#f3f1ec';
const COLORS = ['#b4da33', '#464498', '#f481c6', '#f0b429', '#7dd3fc', '#d01600'];

/** FNV-1a de 32 bits: rápido, estável e suficiente para espalhar sementes. */
function hash32(text: string): number {
    let hash = 0x811c9dc5;
    for (let index = 0; index < text.length; index++) {
        hash ^= text.charCodeAt(index);
        hash = Math.imul(hash, 0x01000193);
    }
    return hash >>> 0;
}

/** Gerador pseudoaleatório determinístico (xorshift32). */
function createRandom(seed: string) {
    let state = hash32(seed) || 1;
    return () => {
        state ^= state << 13;
        state >>>= 0;
        state ^= state >>> 17;
        state ^= state << 5;
        state >>>= 0;
        return state / 4294967296;
    };
}

const pick = <T>(random: () => number, items: readonly T[]): T =>
    items[Math.floor(random() * items.length)];

function pixel(random: () => number): string {
    const background = pick(random, COLORS);
    const ink = pick(random, [INK, PAPER]);
    const cells: string[] = [];
    // Grade 5x5 espelhada (3 colunas sorteadas): parece um rosto/criatura.
    for (let row = 0; row < 5; row++) {
        for (let column = 0; column < 3; column++) {
            if (random() < 0.55) {
                cells.push(`<rect x="${1 + column * 1.6}" y="${1 + row * 1.6}" width="1.6" height="1.6"/>`);
                if (column < 2) {
                    cells.push(`<rect x="${1 + (4 - column) * 1.6}" y="${1 + row * 1.6}" width="1.6" height="1.6"/>`);
                }
            }
        }
    }
    return `<rect width="10" height="10" fill="${background}"/><g fill="${ink}">${cells.join('')}</g>`;
}

function robot(random: () => number): string {
    const background = pick(random, COLORS);
    const metal = pick(random, ['#f3f1ec', '#d9d6cc']);
    const eye = pick(random, [INK, '#464498', '#d01600']);
    const antenna = random() < 0.5;
    const eyes = Math.floor(random() * 3); // 0 quadrados, 1 visor, 2 redondos
    const mouth = Math.floor(random() * 3);
    const parts = [`<rect width="10" height="10" fill="${background}"/>`];
    if (antenna) {
        parts.push(`<rect x="4.8" y="0.8" width="0.4" height="1.6" fill="${INK}"/><circle cx="5" cy="0.9" r="0.7" fill="#d01600"/>`);
    }
    parts.push(`<rect x="1.8" y="2.4" width="6.4" height="5.8" fill="${metal}" stroke="${INK}" stroke-width="0.5"/>`);
    parts.push(`<rect x="1" y="4.2" width="0.8" height="1.8" fill="${INK}"/><rect x="8.2" y="4.2" width="0.8" height="1.8" fill="${INK}"/>`);
    if (eyes === 0) {
        parts.push(`<rect x="2.9" y="3.8" width="1.4" height="1.4" fill="${eye}"/><rect x="5.7" y="3.8" width="1.4" height="1.4" fill="${eye}"/>`);
    } else if (eyes === 1) {
        parts.push(`<rect x="2.7" y="3.7" width="4.6" height="1.5" fill="${eye}"/>`);
    } else {
        parts.push(`<circle cx="3.7" cy="4.5" r="0.9" fill="${eye}"/><circle cx="6.3" cy="4.5" r="0.9" fill="${eye}"/>`);
    }
    if (mouth === 0) {
        parts.push(`<rect x="3.2" y="6.3" width="3.6" height="0.6" fill="${INK}"/>`);
    } else if (mouth === 1) {
        parts.push(`<path d="M3.2 6.3h3.6v0.6H3.2z M4 6.3v0.9 M5 6.3v0.9 M6 6.3v0.9" stroke="${INK}" stroke-width="0.35" fill="none"/>`);
    } else {
        parts.push(`<rect x="3.6" y="6.2" width="2.8" height="0.9" fill="${INK}"/>`);
    }
    return parts.join('');
}

function terminal(random: () => number): string {
    const accent = pick(random, ['#b4da33', '#f481c6', '#7dd3fc', '#f0b429']);
    const dim = '#4a4a46';
    const lines: string[] = [];
    // Três linhas de "código": barras de comprimentos sorteados.
    for (let line = 0; line < 3; line++) {
        const y = 3.1 + line * 1.7;
        const indent = random() < 0.4 ? 1.2 : 0;
        const first = 1.2 + Math.floor(random() * 3);
        const second = 1 + Math.floor(random() * 3);
        lines.push(`<rect x="${1.4 + indent}" y="${y}" width="${first}" height="0.7" fill="${accent}"/>`);
        lines.push(`<rect x="${1.4 + indent + first + 0.5}" y="${y}" width="${second}" height="0.7" fill="${dim}"/>`);
    }
    const cursorOnLine = Math.floor(random() * 3);
    return [
        `<rect width="10" height="10" fill="${INK}"/>`,
        `<rect x="0" y="0" width="10" height="1.8" fill="#2c2c29"/>`,
        `<circle cx="1.2" cy="0.9" r="0.35" fill="#d01600"/><circle cx="2.2" cy="0.9" r="0.35" fill="#f0b429"/><circle cx="3.2" cy="0.9" r="0.35" fill="#b4da33"/>`,
        lines.join(''),
        `<rect x="7.6" y="${2.6 + cursorOnLine * 1.7}" width="0.9" height="1.4" fill="${accent}"/>`,
    ].join('');
}

function blocks(random: () => number): string {
    const background = pick(random, [INK, '#464498', PAPER]);
    const shapes: string[] = [`<rect width="10" height="10" fill="${background}"/>`];
    for (let row = 0; row < 3; row++) {
        for (let column = 0; column < 3; column++) {
            const x = 0.7 + column * 3;
            const y = 0.7 + row * 3;
            const color = pick(random, COLORS);
            switch (Math.floor(random() * 4)) {
                case 0:
                    shapes.push(`<rect x="${x}" y="${y}" width="2.6" height="2.6" fill="${color}"/>`);
                    break;
                case 1:
                    shapes.push(`<circle cx="${x + 1.3}" cy="${y + 1.3}" r="1.3" fill="${color}"/>`);
                    break;
                case 2:
                    shapes.push(`<path d="M${x} ${y + 2.6}L${x + 2.6} ${y + 2.6}L${x + 2.6} ${y}Z" fill="${color}"/>`);
                    break;
                default:
                    shapes.push(`<path d="M${x} ${y + 2.6}A2.6 2.6 0 0 1 ${x + 2.6} ${y}V${y + 2.6}Z" fill="${color}"/>`);
            }
        }
    }
    return shapes.join('');
}

const DRAWERS: Record<AvatarStyle, (random: () => number) => string> = {
    pixel,
    robot,
    terminal,
    blocks,
};

/** SVG do avatar. Mesmo estilo + mesma semente = exatamente o mesmo desenho. */
export function avatarSvg(style: string, seed: string): string {
    const safeStyle: AvatarStyle = (AVATAR_STYLES as string[]).includes(style)
        ? (style as AvatarStyle)
        : 'pixel';
    const random = createRandom(`${safeStyle}:${seed}`);

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" shape-rendering="crispEdges">${DRAWERS[safeStyle](random)}</svg>`;
}

/** URL `data:` pronta para o atributo `src` de uma imagem. */
export function avatarDataUri(style: string, seed: string): string {
    return `data:image/svg+xml;utf8,${encodeURIComponent(avatarSvg(style, seed))}`;
}

/** Semente nova no formato aceito pelo servidor: [a-z0-9]{6,32}. */
export function randomSeed(length = 10, random: () => number = Math.random): string {
    const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let seed = '';
    for (let index = 0; index < length; index++) {
        seed += alphabet[Math.floor(random() * alphabet.length)];
    }
    return seed;
}
