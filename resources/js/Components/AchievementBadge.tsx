import { useId } from 'react';

export type BadgeTier =
    'starter' | 'bronze' | 'silver' | 'gold' | 'platinum' | 'legend';
export type BadgeIcon =
    'star' | 'rocket' | 'branch' | 'trophy' | 'crown' | 'flame' | 'commit';

/** Paleta por material: do mais simples ao mais raro. `ink` é a cor do símbolo central. */
const PALETTE: Record<
    BadgeTier,
    { from: string; to: string; rim: string; glow: string; ink: string }
> = {
    starter: {
        from: '#7a78dd',
        to: '#464498',
        rim: '#1d1d1b',
        glow: '#b4da33',
        ink: '#ffffff',
    },
    bronze: {
        from: '#f0b585',
        to: '#a65d2b',
        rim: '#3d1c0b',
        glow: '#ffd9b3',
        ink: '#3d1c0b',
    },
    silver: {
        from: '#f6f8fa',
        to: '#8e99a6',
        rim: '#262b32',
        glow: '#ffffff',
        ink: '#262b32',
    },
    gold: {
        from: '#ffe98c',
        to: '#d9960b',
        rim: '#4d3200',
        glow: '#fff4b8',
        ink: '#4d3200',
    },
    platinum: {
        from: '#eaf9ff',
        to: '#63b4d8',
        rim: '#12333f',
        glow: '#d4f3ff',
        ink: '#12333f',
    },
    legend: {
        from: '#ff9ad5',
        to: '#6f2cf0',
        rim: '#1d1d1b',
        glow: '#f0b429',
        ink: '#ffffff',
    },
};

const SIZES = { sm: 'size-10', md: 'size-16', lg: 'size-28' } as const;

/** Símbolos desenhados em uma grade 48×48 (SVG leve, sem imagens nem bibliotecas). */
function Symbol({ icon }: { icon: BadgeIcon }) {
    switch (icon) {
        case 'rocket':
            return (
                <>
                    <path d="M24 5c6 5 8 13 7 22l-3 6H20l-3-6c-1-9 1-17 7-22Z" />
                    <path d="M17 27l-6 6 2 6 7-4M31 27l6 6-2 6-7-4" />
                    <circle
                        cx="24"
                        cy="18"
                        fill="#fff"
                        fillOpacity=".9"
                        r="3.2"
                    />
                    <path d="M21 36h6l-3 8Z" />
                </>
            );
        case 'branch':
            return (
                <>
                    <circle cx="15" cy="11" r="4.5" />
                    <circle cx="15" cy="37" r="4.5" />
                    <circle cx="34" cy="19" r="4.5" />
                    <path
                        d="M15 15v18M15 30c0-7 19-3 19-7"
                        fill="none"
                        stroke="currentColor"
                        strokeLinecap="round"
                        strokeWidth="3.4"
                    />
                </>
            );
        case 'trophy':
            return (
                <>
                    <path d="M14 7h20v13c0 7-5 11-10 11S14 27 14 20V7Z" />
                    <path
                        d="M14 11H7c0 7 3 10 8 10M34 11h7c0 7-3 10-8 10"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="3"
                    />
                    <path d="M21 31h6v6h-6zM15 41h18v3H15z" />
                </>
            );
        case 'crown':
            return (
                <>
                    <path d="M7 35 9 13l9 10 6-14 6 14 9-10 2 22Z" />
                    <path d="M7 39h34v4H7z" />
                    <circle cx="9" cy="11" r="2.6" />
                    <circle cx="24" cy="7" r="2.6" />
                    <circle cx="39" cy="11" r="2.6" />
                </>
            );
        case 'flame':
            return (
                <>
                    <path d="M24 4c3 8 12 12 12 24 0 8-6 14-12 14S12 36 12 28c0-6 4-10 6-14 1 4 3 5 4 4 0-5 0-9 2-14Z" />
                    <path
                        d="M24 26c2 4 5 5 5 9 0 3-2 5-5 5s-5-2-5-5c0-3 3-5 5-9Z"
                        fill="#fff"
                        fillOpacity=".85"
                    />
                </>
            );
        case 'commit':
            return (
                <>
                    <circle
                        cx="24"
                        cy="24"
                        fill="none"
                        r="8"
                        stroke="currentColor"
                        strokeWidth="4.5"
                    />
                    <path
                        d="M5 24h11M32 24h11"
                        stroke="currentColor"
                        strokeLinecap="round"
                        strokeWidth="4.5"
                    />
                </>
            );
        default:
            return (
                <path d="m24 5 5.6 12.6 13.7 1.4-10.3 9.2 3 13.5L24 34l-12 7.7 3-13.5L4.7 19l13.7-1.4Z" />
            );
    }
}

/**
 * Insígnia quadrada de conquista. Tudo em SVG + CSS (sem JavaScript de animação):
 * brilho diagonal nas raridades altas e leve flutuação na lendária; ambos desligam com prefers-reduced-motion
 * (regras em resources/css/app.css). Bloqueada = cinza com cadeado, sem animação.
 */
export default function AchievementBadge({
    tier,
    icon,
    locked = false,
    size = 'md',
    className = '',
}: {
    tier: BadgeTier;
    icon: BadgeIcon;
    locked?: boolean;
    size?: keyof typeof SIZES;
    className?: string;
}) {
    const id = useId().replace(/:/g, '');
    const p = PALETTE[tier] ?? PALETTE.starter;
    const fancy =
        !locked &&
        (tier === 'gold' || tier === 'platinum' || tier === 'legend');

    return (
        <span
            aria-hidden="true"
            className={`relative inline-block shrink-0 ${SIZES[size]} ${tier === 'legend' && !locked ? 'badge-float' : ''} ${className}`}
        >
            <svg
                className={`size-full overflow-visible ${locked ? 'opacity-60 grayscale' : ''}`}
                viewBox="0 0 96 96"
            >
                <defs>
                    <linearGradient id={`${id}-f`} x1="0" x2="1" y1="0" y2="1">
                        <stop offset="0" stopColor={p.from} />
                        <stop offset="1" stopColor={p.to} />
                    </linearGradient>
                    <radialGradient id={`${id}-g`} cx=".5" cy=".38" r=".6">
                        <stop offset="0" stopColor={p.glow} stopOpacity=".75" />
                        <stop offset="1" stopColor={p.glow} stopOpacity="0" />
                    </radialGradient>
                    <clipPath id={`${id}-c`}>
                        <rect height="84" rx="14" width="84" x="6" y="6" />
                    </clipPath>
                </defs>
                {/* sombra dura, no estilo do resto da interface */}
                <rect
                    fill={p.rim}
                    height="84"
                    rx="14"
                    width="84"
                    x="10"
                    y="10"
                />
                <rect
                    fill={`url(#${id}-f)`}
                    height="84"
                    rx="14"
                    stroke={p.rim}
                    strokeWidth="5"
                    width="84"
                    x="6"
                    y="6"
                />
                {/* moldura interna: mais detalhada nas raridades altas */}
                <rect
                    fill="none"
                    height="72"
                    rx="9"
                    stroke={p.glow}
                    strokeOpacity={fancy ? 0.9 : 0.45}
                    strokeWidth="2"
                    width="72"
                    x="12"
                    y="12"
                />
                {(tier === 'gold' ||
                    tier === 'platinum' ||
                    tier === 'legend') &&
                    [
                        [17, 17],
                        [73, 17],
                        [17, 73],
                        [73, 73],
                    ].map(([x, y]) => (
                        <rect
                            fill={p.glow}
                            height="6"
                            key={`${x}-${y}`}
                            width="6"
                            x={x}
                            y={y}
                        />
                    ))}
                <rect
                    fill={`url(#${id}-g)`}
                    height="84"
                    rx="14"
                    width="84"
                    x="6"
                    y="6"
                />
                <g color={p.ink} fill={p.ink} transform="translate(24 24)">
                    <Symbol icon={icon} />
                </g>
                {fancy && (
                    <g clipPath={`url(#${id}-c)`}>
                        <rect
                            className="badge-shine"
                            fill="#fff"
                            fillOpacity=".5"
                            height="120"
                            transform="rotate(20 48 48)"
                            width="14"
                            x="-30"
                            y="-12"
                        />
                    </g>
                )}
                {locked && (
                    <g transform="translate(60 60)">
                        <rect fill="#1d1d1b" height="24" rx="5" width="26" />
                        <path
                            d="M7 11V8a6 6 0 0 1 12 0v3"
                            fill="none"
                            stroke="#fff"
                            strokeWidth="3"
                        />
                        <rect
                            fill="#b4da33"
                            height="9"
                            rx="2"
                            width="14"
                            x="6"
                            y="11"
                        />
                    </g>
                )}
            </svg>
        </span>
    );
}

export const RARITY_LABEL: Record<string, string> = {
    common: 'Comum',
    uncommon: 'Incomum',
    rare: 'Rara',
    epic: 'Épica',
    legendary: 'Lendária',
};

export const TIER_LABEL: Record<string, string> = {
    starter: 'Inicial',
    bronze: 'Bronze',
    silver: 'Prata',
    gold: 'Ouro',
    platinum: 'Platina',
    legend: 'Lenda',
};
