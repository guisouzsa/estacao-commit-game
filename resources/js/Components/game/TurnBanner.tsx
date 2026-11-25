import PlayerAvatar from '@/Components/PlayerAvatar';
import type { PlayerProfile } from '@/Components/PlayerProfileCard';

/**
 * A ÚNICA caixa de estado da partida, no topo: quem está jogando agora. Verde, compacta e sempre do
 * mesmo tamanho (o texto só troca), então a barra de navegação nunca "quebra" quando o turno muda.
 */
export default function TurnBanner({
    finished,
    isTurn,
    current,
    turnNumber,
    secondsLeft,
    hint,
}: {
    finished: boolean;
    isTurn: boolean;
    current?: { username: string; profile: PlayerProfile } | null;
    turnNumber: number;
    secondsLeft: number | null;
    hint: string;
}) {
    const urgent = isTurn && typeof secondsLeft === 'number' && secondsLeft <= 8;

    return (
        <div
            aria-live="polite"
            className={`mx-auto flex w-full max-w-md items-center gap-3 border-[3px] border-[#1d1d1b] px-3 py-2 text-[#1d1d1b] transition-shadow ${
                finished
                    ? 'bg-[#d9d6cc] shadow-[4px_4px_0_#1d1d1b]'
                    : isTurn
                      ? 'bg-[#b4da33] shadow-[4px_4px_0_#f481c6]'
                      : 'bg-[#b4da33] shadow-[4px_4px_0_#464498]'
            }`}
            role="status"
        >
            {current ? <PlayerAvatar profile={current.profile} size="sm" /> : null}

            <div className="min-w-0 flex-1">
                <p className="flex items-center gap-1.5 font-mono text-[10px] font-black uppercase tracking-[.16em]">
                    {finished ? (
                        'Partida encerrada'
                    ) : (
                        <>
                            <span
                                aria-hidden="true"
                                className="size-2 shrink-0 animate-pulse rounded-full bg-[#1d1d1b] motion-reduce:animate-none"
                            />
                            {isTurn ? 'É a sua vez!' : 'Vez de'}
                        </>
                    )}
                </p>
                <p className="truncate text-lg font-black leading-tight xl:whitespace-normal xl:break-words">
                    {finished ? (current ? `@${current.username} venceu` : 'Fim de jogo') : isTurn ? 'Você está jogando' : `@${current?.username ?? '…'}`}
                </p>
                <p className="truncate text-[11px] font-bold leading-4 text-[#1d1d1b]/75 xl:whitespace-normal">{hint}</p>
            </div>

            <div className="shrink-0 text-right font-mono text-[10px] font-black uppercase leading-4">
                <span className="block">Turno {turnNumber}</span>
                {!finished && typeof secondsLeft === 'number' ? (
                    <span className={`block tabular-nums ${urgent ? 'text-[#d01600]' : ''}`}>{secondsLeft}s</span>
                ) : null}
            </div>
        </div>
    );
}
