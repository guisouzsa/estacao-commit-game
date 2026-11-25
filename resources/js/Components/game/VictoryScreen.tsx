import PlayerAvatar from '@/Components/PlayerAvatar';
import type { PlayerProfile } from '@/Components/PlayerProfileCard';
import {
    isDarkBackground,
    profileBackgroundStyle,
} from '@/lib/profileBackgrounds';
import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { useEffect, useState } from 'react';

type Standing = {
    id: number;
    step: number;
    is_viewer: boolean;
    player: PlayerProfile & { username: string };
};

const CONFETTI = ['#b4da33', '#f481c6', '#464498', '#f0b429'];

function Trophy({ className = '' }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            className={className}
            fill="none"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="1.8"
            viewBox="0 0 24 24"
        >
            <path d="M8 21h8m-4-4v4m-5-17h10v5a5 5 0 0 1-10 0V4Zm0 2H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4" />
        </svg>
    );
}

/** m:ss até o prazo; nulo quando não há prazo. */
function useCountdown(iso?: string | null) {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        if (!iso) return;
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, [iso]);
    if (!iso) return null;
    const seconds = Math.max(
        0,
        Math.ceil((new Date(iso).getTime() - now) / 1000),
    );
    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

/**
 * Tela de vitória. O servidor é a autoridade: quem venceu vem de `game.winner_participant_id`,
 * e todos os jogadores recebem o mesmo estado final (pelo aviso em tempo real, pelo polling de
 * segurança ou ao reabrir a página), então todos veem o mesmo vencedor. Aparece UMA vez por
 * partida: depois de fechada, fica só um botão discreto para rever o resultado.
 */
export default function VictoryScreen({
    gameId,
    winner,
    standings,
    wins,
    cleanupAt,
    reduced,
    dashboardHref,
    newRoomHref,
}: {
    gameId: string;
    winner?: Standing;
    standings: Standing[];
    wins?: number | null;
    cleanupAt?: string | null;
    reduced: boolean;
    dashboardHref: string;
    newRoomHref: string;
}) {
    const storageKey = `victory-seen:${gameId}`;
    const celebrationKey = `victory-celebrated:${gameId}`;
    const [open, setOpen] = useState(() => {
        try {
            return window.sessionStorage.getItem(storageKey) !== '1';
        } catch {
            return true;
        }
    });
    const [celebrating, setCelebrating] = useState(() => {
        if (reduced || !winner) return false;
        try {
            return window.sessionStorage.getItem(celebrationKey) !== '1';
        } catch {
            return true;
        }
    });
    const countdown = useCountdown(cleanupAt);

    const close = () => {
        setOpen(false);
        setCelebrating(false);
        try {
            window.sessionStorage.setItem(storageKey, '1');
        } catch {
            /* sem armazenamento: só não lembra o fechamento */
        }
    };

    useEffect(() => {
        if (!open || !celebrating) return;
        try {
            window.sessionStorage.setItem(celebrationKey, '1');
        } catch {
            // O estado da tela ainda evita repetir ao rever o resultado.
        }
        const timer = window.setTimeout(() => setCelebrating(false), 4500);
        return () => window.clearTimeout(timer);
    }, [celebrationKey, celebrating, open]);

    useEffect(() => {
        if (!open) return;
        const onKey = (event: KeyboardEvent) =>
            event.key === 'Escape' && close();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const name = winner ? `@${winner.player.username}` : 'Jogador';
    const mine = Boolean(winner?.is_viewer);
    const background = profileBackgroundStyle(
        winner?.player.profile_background,
    );
    const dark = isDarkBackground(winner?.player.profile_background);

    if (!open) {
        return (
            <button
                className="fixed bottom-4 right-4 z-30 flex min-h-11 items-center gap-2 border-2 border-[#1d1d1b] bg-[#b4da33] px-3 font-mono text-[11px] font-black uppercase shadow-[3px_3px_0_#1d1d1b]"
                onClick={() => setOpen(true)}
                type="button"
            >
                <Trophy className="size-4" /> Ver resultado
            </button>
        );
    }

    return (
        <div
            aria-labelledby="victory-title"
            aria-modal="true"
            className="fixed inset-0 z-40 grid place-items-center overflow-y-auto bg-[#10100f]/85 p-3 backdrop-blur-sm sm:p-4"
            role="dialog"
        >
            {!reduced &&
                celebrating &&
                Array.from({ length: 48 }, (_, i) => (
                    <span
                        aria-hidden="true"
                        className="confetti-piece"
                        key={i}
                        style={{
                            left: `${(i * 41) % 100}%`,
                            background: CONFETTI[i % CONFETTI.length],
                            animationDuration: `${2.6 + (i % 5) * 0.4}s`,
                            animationDelay: `${(i % 7) * 0.14}s`,
                        }}
                    />
                ))}

            <motion.div
                animate={{ scale: 1, opacity: 1, y: 0 }}
                className="relative my-auto w-full max-w-lg overflow-hidden border-[3px] border-[#1d1d1b] bg-[#f3f1ec] text-center text-[#1d1d1b] shadow-[10px_10px_0_#b4da33]"
                initial={
                    reduced || !celebrating
                        ? false
                        : { scale: 0.88, opacity: 0, y: 16 }
                }
                transition={{ type: 'spring', stiffness: 260, damping: 22 }}
            >
                {/* Faixa com o FUNDO DE PERFIL do vencedor (ou o visual padrão). */}
                <div
                    className="relative h-32 sm:h-36"
                    style={
                        background ?? {
                            background:
                                'linear-gradient(135deg,#464498 0 52%,#b4da33 52% 56%,#1d1d1b 56%)',
                        }
                    }
                >
                    {celebrating && !reduced && (
                        <div
                            aria-hidden="true"
                            className="victory-applause pointer-events-none absolute inset-x-0 top-10 flex justify-center gap-5 text-3xl"
                        >
                            <span>👏</span>
                            <span>👏</span>
                            <span>👏</span>
                        </div>
                    )}
                    <p
                        className={`absolute left-3 top-3 flex items-center gap-1.5 font-mono text-[10px] font-black uppercase tracking-[.18em] ${dark || !background ? 'text-white' : 'text-[#1d1d1b]'}`}
                    >
                        <Trophy className="size-4 text-[#f0b429]" /> Partida
                        finalizada
                    </p>
                </div>

                <div className="-mt-14 px-5 pb-6 sm:px-7">
                    <motion.div
                        animate={
                            reduced || !celebrating
                                ? undefined
                                : { rotate: [0, -3, 3, 0] }
                        }
                        className="mx-auto w-fit rounded-full bg-[#f3f1ec] p-1.5"
                        transition={{ delay: 0.5, duration: 0.8 }}
                    >
                        {winner ? (
                            <PlayerAvatar profile={winner.player} size="lg" />
                        ) : null}
                    </motion.div>

                    <h2
                        className="mt-3 text-3xl font-black leading-tight sm:text-4xl"
                        id="victory-title"
                    >
                        {mine ? 'Você conquistou a missão!' : `${name} venceu!`}
                    </h2>
                    {mine ? (
                        <p className="mt-1 font-mono text-sm font-black text-[#464498]">
                            {name}
                        </p>
                    ) : null}
                    <p className="mt-2 text-sm text-[#5b5953]">
                        A Pull Request foi concluída e o foguete está pronto
                        para decolar.
                    </p>

                    {typeof wins === 'number' ? (
                        <p className="mx-auto mt-3 inline-flex items-center gap-2 border-2 border-[#1d1d1b] bg-[#f0b429] px-3 py-1.5 font-mono text-xs font-black uppercase">
                            <Trophy className="size-4" />
                            {wins} {wins === 1 ? 'vitória' : 'vitórias'} no
                            ranking geral
                        </p>
                    ) : null}

                    <ol className="mx-auto mt-4 max-w-xs space-y-1 text-left text-sm">
                        {standings.map((participant, index) => (
                            <li
                                className={`flex items-center justify-between gap-2 border-b border-[#1d1d1b]/10 px-2 py-1 ${participant.id === winner?.id ? 'bg-[#b4da33] font-black' : ''}`}
                                key={participant.id}
                            >
                                <span className="min-w-0 truncate font-bold">
                                    {index + 1}º{' '}
                                    {participant.is_viewer
                                        ? 'Você'
                                        : `@${participant.player.username}`}
                                </span>
                                <span className="shrink-0 font-mono text-xs text-[#5b5953]">
                                    {participant.step}/18 etapas
                                </span>
                            </li>
                        ))}
                    </ol>

                    {countdown ? (
                        <p
                            className="mt-4 font-mono text-[11px] font-bold uppercase text-[#68665f]"
                            role="timer"
                        >
                            Esta sala será encerrada em {countdown}. O resultado
                            já está salvo no ranking.
                        </p>
                    ) : null}

                    <div className="mt-5 flex flex-wrap justify-center gap-3">
                        <button
                            autoFocus
                            className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-black uppercase"
                            onClick={close}
                            type="button"
                        >
                            Ver a mesa
                        </button>
                        <Link
                            className="grid min-h-11 place-items-center border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-black uppercase"
                            href={dashboardHref}
                        >
                            Inicial
                        </Link>
                        <Link
                            className="grid min-h-11 place-items-center border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-xs font-black uppercase"
                            href={newRoomHref}
                        >
                            Nova sala
                        </Link>
                    </div>
                </div>
            </motion.div>
        </div>
    );
}
