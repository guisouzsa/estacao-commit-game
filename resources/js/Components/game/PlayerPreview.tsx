import VerifiedBadge from '@/Components/VerifiedBadge';
import PlayerAvatar from '@/Components/PlayerAvatar';
import type { PlayerProfile } from '@/Components/PlayerProfileCard';
import { profileBackgroundStyle } from '@/lib/profileBackgrounds';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';

export type PreviewPlayer = {
    id: number;
    is_host: boolean;
    is_bot: boolean;
    is_viewer: boolean;
    hand_count: number;
    step: number;
    blocked: boolean;
    player: PlayerProfile & {
        username: string;
        profile_title?: string | null;
        primary_badge?: string | null;
        matches_won?: number | null;
        kind?: 'user' | 'guest' | 'bot';
    };
};

const WIDTH = 248;
const GAP = 10;

/**
 * Prévia compacta do perfil de um jogador, perto do indicador clicado.
 *
 * - Usa só dados que a partida já trouxe (nenhuma consulta nova): avatar, nome, título, selo, fundo.
 * - Não é modal: não bloqueia a mesa. Fecha ao clicar fora (sem "engolir" o clique: os controles das
 *   cartas continuam funcionando), no botão ✕ ou com Esc.
 * - Posição calculada pela tela (fixed) a partir do elemento clicado: funciona com o tabuleiro inclinado
 *   e no celular, sempre dentro da janela.
 */
export default function PreviewPopover({
    target,
    anchor,
    labels,
    onClose,
}: {
    target: PreviewPlayer;
    anchor: DOMRect;
    labels: { titles: Record<string, string>; badges: Record<string, string> };
    onClose: () => void;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [place, setPlace] = useState<{ left: number; top: number } | null>(null);

    // Posiciona ao lado do indicador (preferindo o lado de dentro da tela) e nunca fora da janela.
    useLayoutEffect(() => {
        const height = ref.current?.offsetHeight ?? 210;
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const width = Math.min(WIDTH, vw - 16);
        let left = anchor.left + anchor.width / 2 - width / 2;
        let top = anchor.bottom + GAP;
        if (top + height > vh - 8) top = anchor.top - height - GAP; // sem espaço embaixo: abre acima
        if (top < 8) top = Math.max(8, Math.min(vh - height - 8, anchor.top + anchor.height / 2 - height / 2));
        left = Math.max(8, Math.min(vw - width - 8, left));
        setPlace({ left, top });
    }, [anchor, target.id]);

    useEffect(() => {
        const onPointerDown = (event: PointerEvent) => {
            const node = event.target as Node;
            if (ref.current?.contains(node)) return;
            // Clicou em outro indicador: a seleção dele troca a prévia; qualquer outro lugar fecha.
            if ((node as HTMLElement).closest?.('[data-panel]')) return;
            onClose();
        };
        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [onClose]);

    const { player } = target;
    const background = profileBackgroundStyle((player as { profile_background?: string | null }).profile_background);
    const title = player.profile_title ? labels.titles[player.profile_title] ?? null : null;
    const badge = player.primary_badge ? labels.badges[player.primary_badge] ?? null : null;
    const wins = typeof player.matches_won === 'number' ? player.matches_won : null;
    const isUser = player.kind === 'user' || (!player.is_bot && player.kind === undefined);

    return (
        <div
            aria-label={`Prévia do perfil de ${player.username}`}
            className="fixed z-[60] overflow-hidden border-[3px] border-[#1d1d1b] bg-[#f3f1ec] text-[#1d1d1b] shadow-[5px_5px_0_#464498]"
            ref={ref}
            role="dialog"
            style={{ width: Math.min(WIDTH, window.innerWidth - 16), left: place?.left ?? -999, top: place?.top ?? -999, opacity: place ? 1 : 0 }}
        >
            <div
                className="relative h-14"
                style={background ?? { background: 'linear-gradient(135deg,#464498 0 52%,#b4da33 52% 56%,#1d1d1b 56%)' }}
            >
                <button
                    aria-label="Fechar prévia"
                    className="absolute right-1.5 top-1.5 grid size-7 place-items-center border-2 border-[#1d1d1b] bg-white font-mono text-sm font-black leading-none hover:bg-[#b4da33]"
                    onClick={onClose}
                    type="button"
                >
                    ×
                </button>
            </div>

            <div className="-mt-8 px-3 pb-3">
                <PlayerAvatar profile={player} size="md" />
                <p className="mt-2 truncate text-base font-black leading-tight">
                    {target.is_viewer ? 'Você' : `@${player.username}`}
                </p>
                {target.is_viewer ? <p className="font-mono text-[11px] font-bold text-[#464498]">@{player.username}{player.is_verified && <VerifiedBadge className="ml-1" size={15} />}</p> : null}

                <div className="mt-1.5 flex flex-wrap gap-1.5">
                    {player.is_bot ? <Chip tone="lime">Bot</Chip> : null}
                    {target.is_host ? <Chip>Anfitrião</Chip> : null}
                    {title ? <Chip tone="violet">{title}</Chip> : null}
                    {badge ? <Chip>{badge}</Chip> : null}
                    {target.blocked ? <Chip tone="red">Merge Conflict</Chip> : null}
                </div>

                <dl className="mt-2.5 grid grid-cols-2 gap-x-3 gap-y-1 border-t border-[#1d1d1b]/15 pt-2 font-mono text-[11px]">
                    <dt className="text-[#68665f]">Etapa</dt>
                    <dd className="text-right font-black">{target.step}/18</dd>
                    <dt className="text-[#68665f]">Cartas na mão</dt>
                    <dd className="text-right font-black">{target.hand_count}</dd>
                    {isUser && wins !== null ? (
                        <>
                            <dt className="text-[#68665f]">Vitórias</dt>
                            <dd className="text-right font-black">{wins}</dd>
                        </>
                    ) : null}
                </dl>

                {isUser && !player.is_bot ? (
                    <a
                        className="mt-2.5 block border-2 border-[#1d1d1b] bg-white py-1.5 text-center font-mono text-[10px] font-black uppercase hover:bg-[#b4da33]"
                        href={`/perfil/${player.username}`}
                        rel="noopener"
                        target="_blank"
                    >
                        Ver perfil completo
                    </a>
                ) : null}
            </div>
        </div>
    );
}

function Chip({ children, tone = 'plain' }: { children: React.ReactNode; tone?: 'plain' | 'lime' | 'violet' | 'red' }) {
    const tones = {
        plain: 'bg-white',
        lime: 'bg-[#b4da33]',
        violet: 'bg-[#464498] text-white',
        red: 'bg-[#d01600] text-white',
    };
    return (
        <span className={`border border-[#1d1d1b] px-1.5 py-0.5 font-mono text-[9px] font-black uppercase leading-3 ${tones[tone]}`}>
            {children}
        </span>
    );
}
