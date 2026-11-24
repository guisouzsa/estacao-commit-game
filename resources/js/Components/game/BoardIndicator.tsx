import PlayerAvatar from '@/Components/PlayerAvatar';
import type { PanelPlayer } from '@/Components/game/PlayerPanel';
import { INDICATOR_POSITION, PATH_LENGTH, type Side } from '@/lib/gameGeometry';
import { memo } from 'react';

/**
 * Indicador circular de um jogador, junto à área dele no tabuleiro: avatar, anel de vez, quantidade
 * de cartas e marcações de progresso. Substitui os painéis grandes do topo. Clicar abre a prévia do perfil.
 * `data-panel` é o ponto de partida das cartas em voo dos outros jogadores.
 */
const RING_RADIUS = 46;

export default memo(function BoardIndicator({
    player,
    side,
    active,
    you,
    onSelect,
}: {
    player: PanelPlayer;
    side: Side;
    active: boolean;
    you: boolean;
    onSelect: (id: number, element: HTMLElement) => void;
}) {
    const position = INDICATOR_POSITION[side];
    // Progresso REAL: etapas já cumpridas do percurso (estado do servidor), nunca inventado no front.
    const progress = Math.min(1, Math.max(0, player.step / PATH_LENGTH));
    const circumference = 2 * Math.PI * RING_RADIUS;
    const ringColor = player.blocked ? '#d01600' : active ? '#f481c6' : '#b4da33';

    return (
        <button
            aria-label={`${you ? 'Você' : `@${player.username}`}: etapa ${player.step} de 18, ${player.hand_count} cartas na mão${active ? ', jogando agora' : ''}. Abrir prévia do perfil`}
            className="group absolute -translate-x-1/2 -translate-y-1/2 rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#b4da33]"
            data-panel={player.id}
            onClick={(event) => onSelect(player.id, event.currentTarget)}
            style={{ left: `${position.left}%`, top: `${position.top}%`, width: 'max(2.35rem, 9.4%)' }}
            title={you ? 'Você' : `@${player.username}`}
            type="button"
        >
            <span
                className={`relative block aspect-square rounded-full bg-[#1d1d1b]/70 ${active ? 'turn-glow' : ''} ${player.blocked ? 'impact' : ''}`}
                key={player.blocked ? 'blocked' : 'free'}
            >
                {/* Anel de progresso: o arco cresce a cada etapa cumprida; rosa = jogando agora; vermelho = Merge Conflict. */}
                <svg aria-hidden="true" className="absolute inset-0 size-full -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" fill="none" r={RING_RADIUS} stroke="rgba(255,255,255,.22)" strokeWidth="6" />
                    <circle
                        className="transition-[stroke-dashoffset,stroke] duration-500 motion-reduce:transition-none"
                        cx="50"
                        cy="50"
                        fill="none"
                        r={RING_RADIUS}
                        stroke={ringColor}
                        strokeDasharray={circumference}
                        strokeDashoffset={circumference * (1 - progress)}
                        strokeLinecap="round"
                        strokeWidth="6"
                    />
                </svg>
                <span className="absolute inset-[9%] block overflow-hidden rounded-full">
                    <PlayerAvatar profile={player.profile} size="xs" className="!size-full" />
                </span>
            </span>

            {/* Cartas na mão (discreto, no canto). */}
            <span
                aria-hidden="true"
                className="absolute -right-1 -top-1 grid min-w-4 place-items-center rounded-full border border-[#1d1d1b] bg-white px-1 font-mono text-[9px] font-black leading-4 text-[#1d1d1b]"
                key={`count-${player.hand_count}`}
                style={{ animation: 'fade-in .25s ease-out' }}
            >
                {player.hand_count}
            </span>

        </button>
    );
});
