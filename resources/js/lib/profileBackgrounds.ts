// Fundos de perfil: só CSS (gradientes) e SVGs minúsculos embutidos. Sem imagem para baixar,
// funcionam igual em desktop e celular. As chaves precisam bater com config/profiles.php.

import type { CSSProperties } from 'react';

const svg = (body: string, size = 40) =>
    `url("data:image/svg+xml,${encodeURIComponent(
        `<svg xmlns='http://www.w3.org/2000/svg' width='${size}' height='${size}' viewBox='0 0 ${size} ${size}'>${body}</svg>`,
    )}")`;

type Preset = { style: CSSProperties; dark: boolean };

const stars = (n: number, seed: number) => {
    let s = seed;
    const rnd = () => ((s = (s * 9301 + 49297) % 233280) / 233280);
    return Array.from({ length: n }, () => {
        const r = (0.5 + rnd() * 1.1).toFixed(1);
        return `<circle cx='${Math.round(rnd() * 120)}' cy='${Math.round(rnd() * 120)}' r='${r}' fill='white' opacity='${(0.35 + rnd() * 0.65).toFixed(2)}'/>`;
    }).join('');
};

export const PROFILE_BACKGROUNDS: Record<string, Preset> = {
    nebula: {
        dark: true,
        style: {
            background:
                'radial-gradient(60% 80% at 20% 30%, #f481c6aa, transparent 60%), radial-gradient(50% 70% at 80% 70%, #464498cc, transparent 65%), #14122b',
        },
    },
    galaxy: {
        dark: true,
        style: {
            background: `${svg(stars(26, 7), 120)}, conic-gradient(from 210deg at 70% 60%, #1d1d4d, #464498, #f481c655, #1d1d4d)`,
            backgroundSize: '120px 120px, auto',
        },
    },
    aurora: {
        dark: true,
        style: {
            background:
                'linear-gradient(115deg, #0b1d2a 0%, #0f5f55 35%, #b4da33 60%, #464498 85%, #14122b 100%)',
        },
    },
    starfield: {
        dark: true,
        style: { background: `${svg(stars(34, 21), 120)}, #0d0d1a`, backgroundSize: '120px 120px, auto' },
    },
    sunrise: {
        dark: false,
        style: { background: 'linear-gradient(180deg, #464498 0%, #f481c6 45%, #f0b429 80%, #fff3c4 100%)' },
    },
    'ocean-depth': {
        dark: true,
        style: { background: 'linear-gradient(180deg, #7dd3fc 0%, #2a6fb0 35%, #14325c 70%, #0a1428 100%)' },
    },
    'lime-violet': {
        dark: false,
        style: { background: 'linear-gradient(135deg, #b4da33 0%, #7a9f1b 30%, #464498 100%)' },
    },
    candy: {
        dark: false,
        style: { background: 'linear-gradient(120deg, #f481c6, #ffb3de 40%, #7dd3fc 100%)' },
    },
    grid: {
        dark: true,
        style: {
            background: `${svg("<path d='M40 0H0V40' fill='none' stroke='%23ffffff22' stroke-width='1'/>".replace(/%23/g, '#'))}, #1d1d1b`,
            backgroundSize: '40px 40px, auto',
        },
    },
    dots: {
        dark: false,
        style: {
            background: `${svg("<circle cx='10' cy='10' r='2' fill='#464498'/>", 20)}, #f3f1ec`,
            backgroundSize: '20px 20px, auto',
        },
    },
    diagonal: {
        dark: false,
        style: {
            background: 'repeating-linear-gradient(135deg, #b4da33 0 14px, #1d1d1b 14px 28px)',
        },
    },
    'terminal-green': {
        dark: true,
        style: {
            background:
                'repeating-linear-gradient(0deg, #00ff6a14 0 2px, transparent 2px 4px), linear-gradient(180deg, #02120a, #041f12)',
        },
    },
    'code-lines': {
        dark: true,
        style: {
            background: `${svg(
                "<rect x='4' y='6' width='22' height='3' rx='1.5' fill='#b4da33' opacity='.5'/><rect x='12' y='16' width='34' height='3' rx='1.5' fill='#f481c6' opacity='.5'/><rect x='12' y='26' width='18' height='3' rx='1.5' fill='#7dd3fc' opacity='.5'/><rect x='4' y='36' width='28' height='3' rx='1.5' fill='#b4da33' opacity='.35'/>",
                60,
            )}, #1d1d1b`,
            backgroundSize: '60px 60px, auto',
        },
    },
    'git-graph': {
        dark: true,
        style: {
            background: `${svg(
                "<path d='M10 0V60M10 20C10 34 40 26 40 40V60' fill='none' stroke='#b4da33' stroke-width='3'/><circle cx='10' cy='12' r='4' fill='#1d1d1b' stroke='#b4da33' stroke-width='3'/><circle cx='10' cy='44' r='4' fill='#1d1d1b' stroke='#b4da33' stroke-width='3'/><circle cx='40' cy='44' r='4' fill='#1d1d1b' stroke='#f481c6' stroke-width='3'/>",
                60,
            )}, #14122b`,
            backgroundSize: '60px 60px, auto',
        },
    },
    'circuit-board': {
        dark: true,
        style: {
            background: `${svg(
                "<path d='M0 20H18L26 28H44M30 0V14L38 22V44M0 40H10' fill='none' stroke='#b4da33' stroke-opacity='.45' stroke-width='2'/><circle cx='44' cy='28' r='3' fill='#b4da33' fill-opacity='.7'/><circle cx='38' cy='44' r='3' fill='#f481c6' fill-opacity='.7'/>",
                48,
            )}, #0f2a1d`,
            backgroundSize: '48px 48px, auto',
        },
    },
    'hex-tech': {
        dark: true,
        style: {
            background: `${svg(
                "<path d='M14 2L26 9V23L14 30L2 23V9Z' fill='none' stroke='#7dd3fc' stroke-opacity='.4' stroke-width='1.5'/>",
                28,
            )}, linear-gradient(135deg, #0a1428, #14325c)`,
            backgroundSize: '28px 32px, auto',
        },
    },
    'night-mode': {
        dark: true,
        style: { background: 'linear-gradient(160deg, #1d1d1b, #2a2a3a 60%, #464498)' },
    },
    paper: {
        dark: false,
        style: {
            background: `${svg("<path d='M20 0H0V20' fill='none' stroke='#464498' stroke-opacity='.18'/>", 20)}, #f3f1ec`,
            backgroundSize: '20px 20px, auto',
        },
    },
};

/** Estilo CSS do fundo escolhido, ou undefined (perfil antigo / chave desconhecida). */
export function profileBackgroundStyle(key?: string | null): CSSProperties | undefined {
    return key ? PROFILE_BACKGROUNDS[key]?.style : undefined;
}

export function isDarkBackground(key?: string | null): boolean {
    return key ? (PROFILE_BACKGROUNDS[key]?.dark ?? false) : false;
}
