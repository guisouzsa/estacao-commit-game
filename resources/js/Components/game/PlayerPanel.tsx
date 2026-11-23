import PlayerAvatar from '@/Components/PlayerAvatar';
import { PATH_LENGTH } from '@/lib/gameGeometry';
import { memo } from 'react';

export type PanelPlayer = {
    id: number;
    username: string;
    is_host: boolean;
    is_bot: boolean;
    hand_count: number;
    step: number;
    blocked: boolean;
    profile: Parameters<typeof PlayerAvatar>[0]['profile'];
};

/**
 * Painel compacto de um jogador ao redor da mesa: avatar, nome, cartas e vez.
 * Não há "indicador de conexão": o jogo ainda não registra presença, e inventar um status
 * seria mentir. A inatividade é tratada no servidor (um bot assume o lugar).
 */
export default memo(function PlayerPanel({
    player,
    active,
    you = false,
    secondsLeft,
    className = '',
}: {
    player: PanelPlayer;
    active: boolean;
    you?: boolean;
    secondsLeft?: number | null;
    className?: string;
}) {
    const urgent = typeof secondsLeft === 'number' && secondsLeft <= 20;

    return (
        <div
            data-panel={player.id}
            className={`rounded-lg border-2 bg-[#1d1d1b]/90 p-1.5 transition-colors sm:p-2 sm:backdrop-blur-sm ${
                active
                    ? 'turn-glow border-[#b4da33] bg-[#232a12]/90'
                    : 'border-white/15'
            } ${player.blocked ? 'impact' : ''} ${className}`}
            key={player.blocked ? 'blocked' : 'free'}
        >
            <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 sm:flex-nowrap">
                <PlayerAvatar profile={player.profile} size="xs" />
                <div className="order-3 w-full min-w-0 sm:order-none sm:w-auto sm:flex-1">
                    <strong className="block truncate text-[11px] leading-4 sm:text-xs">
                        {you ? 'Você' : `@${player.username}`}
                    </strong>
                    <div className="hidden flex-wrap gap-x-1.5 font-mono text-[8px] font-bold uppercase leading-3 text-white/55 sm:flex">
                        {player.is_host && <span>Anfitrião</span>}
                        {player.is_bot && (
                            <span className="text-[#b4da33]">Bot</span>
                        )}
                        {active && (
                            <span className="text-[#f481c6]">
                                {you ? 'Sua vez' : 'Jogando'}
                            </span>
                        )}
                        {player.blocked && (
                            <span className="text-[#f481c6]">
                                Merge Conflict
                            </span>
                        )}
                    </div>
                </div>
                <span
                    aria-label={`${player.hand_count} cartas na mão`}
                    className="ml-auto shrink-0 rounded border border-white/20 px-1.5 py-0.5 font-mono text-[10px] font-black tabular-nums"
                    key={`count-${player.hand_count}`}
                    style={{ animation: 'fade-in .25s ease-out' }}
                >
                    🂠 {player.hand_count}
                </span>
            </div>

            <div
                aria-label={`Progresso de ${player.username}`}
                aria-valuemax={PATH_LENGTH}
                aria-valuemin={0}
                aria-valuenow={player.step}
                className="mt-1.5 h-1 overflow-hidden rounded-full bg-white/10"
                role="progressbar"
            >
                <div
                    className="h-full bg-[#b4da33] transition-[width] duration-300 motion-reduce:transition-none"
                    style={{
                        width: `${Math.round((player.step / PATH_LENGTH) * 100)}%`,
                    }}
                />
            </div>

            {active && typeof secondsLeft === 'number' && (
                <p
                    aria-live={urgent ? 'polite' : 'off'}
                    className={`mt-1 font-mono text-[9px] font-bold uppercase ${urgent ? 'text-[#f481c6]' : 'text-white/55'}`}
                >
                    {urgent
                        ? `Corre! ${secondsLeft}s`
                        : `${secondsLeft}s para jogar`}
                </p>
            )}
        </div>
    );
});
