import PlayerAvatar from '@/Components/PlayerAvatar';
import type { PanelPlayer } from '@/Components/game/PlayerPanel';
import { INDICATOR_POSITION, PHASE_ENDS, phasesDone, type Side } from '@/lib/gameGeometry';
import { memo } from 'react';

/**
 * Indicador circular de um jogador, junto à área dele no tabuleiro: avatar, anel de vez, quantidade
 * de cartas e marcações de progresso. Substitui os painéis grandes do topo. Clicar abre a prévia do perfil.
 * `data-panel` é o ponto de partida das cartas em voo dos outros jogadores.
 */
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
    const done = phasesDone(player.step);

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
                className={`block aspect-square rounded-full ${active ? 'turn-glow' : ''} ${player.blocked ? 'impact ring-2 ring-[#d01600]' : ''}`}
                key={player.blocked ? 'blocked' : 'free'}
            >
                <PlayerAvatar profile={player.profile} size="xs" className="!size-full" />
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

            {/* Progresso: 6 bolinhas (Treinamento, 4 ciclos, Envio). Sem barra. */}
            <span
                aria-hidden="true"
                className="absolute -bottom-1 left-1/2 flex -translate-x-1/2 gap-[2px] rounded-full border border-[#1d1d1b] bg-[#1d1d1b] px-[3px] py-[2px]"
            >
                {PHASE_ENDS.map((end, index) => (
                    <span
                        className={`size-[5px] rounded-full ${index < done ? 'bg-[#b4da33]' : 'bg-white/25'}`}
                        key={end}
                    />
                ))}
            </span>
        </button>
    );
});
