import BoardIndicator from '@/Components/game/BoardIndicator';
import type { PanelPlayer } from '@/Components/game/PlayerPanel';
import { cardImageUrl, cardMeta } from '@/Components/GameCard';
import { AREAS, PATH_LENGTH, slotRect, type Side } from '@/lib/gameGeometry';
import { memo } from 'react';

export type BoardIndicatorData = {
    player: PanelPlayer;
    side: Side;
    active: boolean;
    you: boolean;
    watching?: boolean;
    won?: boolean;
    reacted?: boolean;
};

export type BoardPlayer = {
    id: number;
    side: Side;
    username: string;
    /** Uma posição por passo; `undefined` = vazio (ou carta ainda em voo). */
    path: (string | undefined)[];
    active: boolean;
};

const pct = (value: number) => `${value}%`;

/** Um espaço do percurso: vazio (esmaecido, com o nome do passo) ou com a carta jogada. */
const Slot = memo(function Slot({
    playerId,
    side,
    index,
    card,
    fresh,
}: {
    playerId: number;
    side: Side;
    index: number;
    card?: string;
    fresh: boolean;
}) {
    const rect = slotRect(side, index);
    const rotate = AREAS[side].rotate;
    const sideways = rotate === 90 || rotate === -90;
    const art = card ? cardImageUrl(card) : null;

    return (
        <div
            data-slot={`${playerId}:${index}`}
            className="absolute"
            style={{
                left: pct(rect.left),
                top: pct(rect.top),
                width: pct(rect.width),
                height: pct(rect.height),
                containerType: 'size',
            }}
        >
            <div
                className="absolute left-1/2 top-1/2"
                style={{
                    width: sideways ? '92cqh' : '92cqw',
                    height: sideways ? '92cqw' : '92cqh',
                    transform: `translate(-50%, -50%) rotate(${rotate}deg)`,
                }}
            >
                {card ? (
                    <div
                        className={`size-full overflow-hidden rounded-[3px] border border-[#1d1d1b] shadow-[1px_2px_0_#0007] ${fresh ? 'card-settle' : ''}`}
                    >
                        {art ? (
                            <img
                                alt=""
                                className="size-full object-cover"
                                draggable={false}
                                src={art}
                            />
                        ) : (
                            <span
                                aria-hidden="true"
                                className="block size-full"
                                style={{
                                    background: `linear-gradient(145deg, ${cardMeta(card).tone}, #1d1d1b)`,
                                }}
                            />
                        )}
                    </div>
                ) : (
                    // Espaço vazio: só a linha de delimitação (sem nome do passo).
                    <div
                        aria-hidden="true"
                        className="size-full rounded-[3px] border border-dashed border-[#1d1d1b]/25 bg-[#1d1d1b]/[.04]"
                    />
                )}
            </div>
        </div>
    );
});

/** Pilha de cartas (baralho ou descarte). Verso desenhado em CSS: nada de arte nova. */
function Pile({
    kind,
    count,
    left,
    top,
}: {
    kind: 'deck' | 'discard';
    count: number;
    left: number;
    top: number;
}) {
    return (
        <div
            data-pile={kind}
            className="absolute -translate-x-1/2 -translate-y-1/2 text-center"
            style={{ left: pct(left), top: pct(top), width: '9%' }}
        >
            <div className="relative aspect-[63/88] w-full">
                {count > 0 && (
                    <div className="absolute inset-0 translate-x-[7%] translate-y-[5%] rounded-[4px] border-2 border-black bg-[#2b2a4d]" />
                )}
                <div
                    className={`absolute inset-0 grid place-items-center rounded-[4px] border-2 border-black ${
                        kind === 'deck'
                            ? 'bg-[#464498]'
                            : 'border-dashed bg-black/30'
                    }`}
                >
                    {kind === 'deck' && count > 0 && (
                        <img
                            alt=""
                            className="h-3/5 object-contain opacity-90"
                            src="/images/estacao-commit/mascots/gato-commit.webp"
                        />
                    )}
                </div>
            </div>
            <p className="mt-[6%] font-mono text-[max(7px,.75vw)] font-black uppercase leading-none text-white/80">
                {kind === 'deck' ? 'Baralho' : 'Descarte'} {count}
            </p>
        </div>
    );
}

/**
 * O tabuleiro original (tabuleiro.webp) como superfície de jogo.
 * As quatro áreas brancas viram os percursos de cada jogador (18 espaços cada).
 * Só o jogador da vez tem a própria área iluminada.
 */
export default memo(function GameBoard({
    players,
    deckCount,
    discardCount,
    freshSlot,
    indicators = [],
    onSelectPlayer,
}: {
    players: BoardPlayer[];
    /** Indicadores circulares dos jogadores, junto à área de cada um. */
    indicators?: BoardIndicatorData[];
    onSelectPlayer?: (id: number, element: HTMLElement) => void;
    deckCount: number;
    discardCount: number;
    /** `${playerId}:${index}` da carta recém-colocada (ganha a animação de encaixe). */
    freshSlot: string | null;
}) {
    return (
        <div className="game-perspective mx-auto w-full">
            <div className="game-board relative aspect-square w-full overflow-hidden bg-[#1d1d1b]">
                <img
                    alt="Tabuleiro do Estação Commit"
                    className="absolute inset-0 size-full select-none object-cover"
                    draggable={false}
                    height={1404}
                    src="/images/estacao-commit/tabuleiro.webp"
                    width={1404}
                />

                {players.map((player) => {
                    const area = AREAS[player.side];
                    return (
                        <div key={player.id}>
                            {player.active && (
                                <div
                                    className="turn-glow pointer-events-none absolute rounded-[4px]"
                                    style={{
                                        left: pct(area.left - 0.6),
                                        top: pct(area.top - 0.6),
                                        width: pct(area.width + 1.2),
                                        height: pct(area.height + 1.2),
                                    }}
                                />
                            )}
                            {Array.from({ length: PATH_LENGTH }, (_, index) => (
                                <Slot
                                    key={index}
                                    card={player.path[index]}
                                    fresh={
                                        freshSlot === `${player.id}:${index}`
                                    }
                                    index={index}
                                    playerId={player.id}
                                    side={player.side}
                                />
                            ))}
                        </div>
                    );
                })}

                <Pile count={deckCount} kind="deck" left={55} top={64} />
                <Pile count={discardCount} kind="discard" left={44} top={64} />

                {indicators.map((indicator) => (
                    <BoardIndicator
                        active={indicator.active}
                        key={indicator.player.id}
                        onSelect={(id, element) =>
                            onSelectPlayer?.(id, element)
                        }
                        player={indicator.player}
                        side={indicator.side}
                        you={indicator.you}
                        watching={indicator.watching}
                        won={indicator.won}
                        reacted={indicator.reacted}
                    />
                ))}
            </div>
        </div>
    );
});
