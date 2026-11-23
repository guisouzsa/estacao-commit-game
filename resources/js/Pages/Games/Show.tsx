import ConfirmDialog from '@/Components/ConfirmDialog';
import GameCard, { cardLabel } from '@/Components/GameCard';
import PlayerAvatar from '@/Components/PlayerAvatar';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { startRoomSync } from '@/lib/roomSync';
import { toast } from '@/lib/toast';
import { Head, Link, router } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { useEffect, useMemo, useRef, useState } from 'react';

type Player = {
    key: string;
    kind: 'user' | 'guest' | 'bot';
    is_bot: boolean;
    username: string;
    avatar?: string | null;
    avatar_style?: string;
    avatar_seed?: string;
    profile_frame?: string;
};
type Participant = {
    id: number;
    seat: number;
    is_host: boolean;
    is_viewer: boolean;
    player: Player;
    hand_count: number;
    path: string[];
    step: number;
    blocked: boolean;
    pending_discard: number;
};
type Game = {
    id: string;
    status: string;
    current_seat: number;
    turn_number: number;
    version: number;
    winner_participant_id?: number | null;
    deck_count: number;
    discard_count: number;
    history: {
        id: string;
        participant_id?: number;
        message: string;
        turn: number;
        notify?: boolean;
    }[];
};

export default function Show({
    room,
    game,
    participants,
    hand,
    requiredCard,
}: {
    room: { id: string };
    game: Game;
    participants: Participant[];
    hand: string[];
    requiredCard?: string | null;
}) {
    const [selected, setSelected] = useState<number | null>(null);
    const [target, setTarget] = useState('');
    const [busy, setBusy] = useState(false);
    const [leaveOpen, setLeaveOpen] = useState(false);
    const [online, setOnline] = useState(() => navigator.onLine);
    const reduced = useReducedMotion();
    const statusRef = useRef(game.status);
    statusRef.current = game.status;
    const viewer = participants.find((participant) => participant.is_viewer)!;
    const opponents = participants.filter(
        (participant) => !participant.is_viewer,
    );
    const isTurn =
        game.status === 'active' && game.current_seat === viewer.seat;
    const selectedCard = selected === null ? null : hand[selected];
    const needsTarget =
        selectedCard === 'clear_credentials' ||
        selectedCard === 'merge_conflict';
    const canPlaySelected = Boolean(
        selectedCard &&
        (selectedCard === requiredCard ||
            (selectedCard === 'git_pull' && viewer.step < 3) ||
            needsTarget),
    );
    const blockRequired = requiredCard?.startsWith('block_') ?? false;

    useEffect(
        () =>
            startRoomSync({
                echo: window.Echo,
                roomId: room.id,
                reload: (done) =>
                    router.reload({
                        only: ['game', 'participants', 'hand', 'requiredCard'],
                        onFinish: done,
                    }),
                shouldPoll: () => statusRef.current === 'active',
                onSubscriptionError: () =>
                    toast.warning(
                        'Conexão em tempo real instável. Recuperando o estado da partida…',
                        {
                            id: `realtime:${Date.now()}`,
                        },
                    ),
                onRoomDeleted: () => router.visit(route('dashboard')),
                pollIntervalMs: 15000,
                document,
                window,
            }),
        [room.id],
    );

    useEffect(() => {
        const connected = () => setOnline(true);
        const disconnected = () => {
            setOnline(false);
            toast.warning('Você perdeu a conexão. Tentando reconectar…', {
                id: `offline:${Date.now()}`,
            });
        };
        window.addEventListener('online', connected);
        window.addEventListener('offline', disconnected);
        return () => {
            window.removeEventListener('online', connected);
            window.removeEventListener('offline', disconnected);
        };
    }, []);

    useEffect(() => {
        setSelected(null);
        setTarget('');
    }, [game.version]);

    // Eventos que merecem aviso (ex.: bot assumiu um lugar). O id evita repetir o aviso
    // quando o mesmo estado chega por Reverb e pela sincronização de segurança.
    useEffect(() => {
        game.history
            .filter((item) => item.notify)
            .forEach((item) =>
                toast.info(item.message, {
                    id: `game-event:${item.id}`,
                    title: 'Mudança na mesa',
                }),
            );
    }, [game.history]);

    const send = (action: string, card?: string, targetId?: string) => {
        setBusy(true);
        router.post(
            route('games.action', room.id),
            {
                action_uuid: crypto.randomUUID(),
                action,
                card,
                target_id: targetId || null,
                version: game.version,
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.warning(
                        String(
                            Object.values(errors)[0] ??
                                'Não foi possível realizar essa jogada.',
                        ),
                        {
                            id: `move:${Date.now()}`,
                        },
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    const winner = participants.find(
        (participant) => participant.id === game.winner_participant_id,
    );
    const progress = useMemo(
        () => Math.round((viewer.step / 18) * 100),
        [viewer.step],
    );

    return (
        <AuthenticatedLayout>
            <Head title="Partida em andamento" />
            <main className="min-h-[calc(100dvh-4rem)] overflow-x-hidden bg-[#151515] text-white">
                <header className="flex flex-wrap items-center justify-between gap-2 border-b border-white/15 bg-[#1d1d1b] px-3 py-2 sm:px-5">
                    <div className="flex items-center gap-3">
                        <strong className="font-mono text-xs uppercase text-[#b4da33]">
                            Estação Commit
                        </strong>
                        <span className="text-white/30">/</span>
                        <span className="font-mono text-[10px] uppercase text-white/65">
                            Turno {game.turn_number}
                        </span>
                    </div>
                    <div className="flex items-center gap-3 text-[10px] uppercase">
                        <span
                            className={`size-2 rounded-full ${online ? 'bg-[#b4da33]' : 'bg-[#d01600]'}`}
                        />
                        <span className="font-mono text-white/60">
                            {online ? 'Sincronizado' : 'Reconectando'}
                        </span>
                        <button
                            onClick={() => setLeaveOpen(true)}
                            className="min-h-9 border border-white/25 px-3 font-mono font-bold hover:border-[#f481c6]"
                        >
                            Sair
                        </button>
                    </div>
                </header>

                <section className="relative mx-auto flex min-h-[calc(100dvh-7rem)] w-full max-w-[1500px] flex-col px-2 py-3 sm:px-4">
                    <div className="grid grid-cols-3 gap-2 px-1 sm:gap-4 lg:px-20">
                        {opponents.map((participant) => (
                            <PlayerSeat
                                key={participant.id}
                                participant={participant}
                                active={game.current_seat === participant.seat}
                            />
                        ))}
                    </div>

                    <div className="relative my-3 grid min-h-[19rem] flex-1 place-items-center overflow-hidden rounded-[2rem] border-[3px] border-[#464498] bg-[#25243a] p-3 shadow-[inset_0_0_60px_#0008,0_0_0_4px_#10100f] sm:min-h-[25rem] sm:p-6">
                        <div
                            className="absolute inset-0 opacity-20"
                            style={{
                                backgroundImage:
                                    'radial-gradient(circle at center,#b4da33 1px,transparent 1px)',
                                backgroundSize: '22px 22px',
                            }}
                        />
                        <motion.div
                            initial={
                                reduced ? false : { opacity: 0, scale: 0.94 }
                            }
                            animate={{ opacity: 1, scale: 1 }}
                            className="relative w-full max-w-4xl"
                        >
                            <img
                                src="/images/estacao-commit/tabuleiro.png"
                                alt="Tabuleiro oficial do Estação Commit"
                                className="mx-auto max-h-[52vh] w-full rounded-2xl object-contain drop-shadow-[0_16px_22px_#0009]"
                            />
                            <div className="absolute left-1/2 top-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center gap-2 rounded-full border-2 border-white/30 bg-[#1d1d1b]/90 px-3 py-2 backdrop-blur">
                                <span className="font-mono text-[9px] uppercase text-white/60">
                                    Próxima etapa
                                </span>
                                <strong className="whitespace-nowrap text-xs text-[#b4da33] sm:text-sm">
                                    {requiredCard
                                        ? cardLabel(requiredCard)
                                        : 'Percurso concluído'}
                                </strong>
                            </div>
                        </motion.div>
                        <div className="absolute bottom-2 left-3 flex gap-3 font-mono text-[9px] uppercase text-white/55">
                            <span>Baralho {game.deck_count}</span>
                            <span>Descarte {game.discard_count}</span>
                        </div>
                        <aside className="absolute right-2 top-2 hidden w-56 rounded-xl border border-white/15 bg-[#10100f]/85 p-3 backdrop-blur xl:block">
                            <h2 className="font-mono text-[9px] font-black uppercase tracking-[.14em] text-[#f481c6]">
                                Registro da estação
                            </h2>
                            <ol className="mt-2 space-y-2">
                                {game.history.slice(0, 5).map((item) => (
                                    <li
                                        key={item.id}
                                        className="text-[11px] leading-4 text-white/65"
                                    >
                                        {item.message}
                                    </li>
                                ))}
                            </ol>
                        </aside>
                    </div>

                    <div className="rounded-2xl border-2 border-white/15 bg-[#1d1d1b] p-3 shadow-[0_-8px_30px_#0006]">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <PlayerSeat
                                participant={viewer}
                                active={isTurn}
                                viewer
                            />
                            <div className="min-w-36 flex-1 sm:max-w-xs">
                                <div className="mb-1 flex justify-between font-mono text-[9px] uppercase text-white/55">
                                    <span>Contribuição</span>
                                    <span>{viewer.step}/18</span>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-white/10">
                                    <div
                                        className="h-full bg-[#b4da33] transition-[width]"
                                        style={{ width: `${progress}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                        <div className="flex items-end gap-2 overflow-x-auto px-1 pb-3 pt-4 [scrollbar-width:thin]">
                            {blockRequired && (
                                <GameCard
                                    card={requiredCard!}
                                    playable={isTurn}
                                    onClick={
                                        isTurn && !busy
                                            ? () => send('play_block')
                                            : undefined
                                    }
                                />
                            )}
                            {hand.map((card, index) => (
                                <GameCard
                                    key={`${card}-${index}`}
                                    card={card}
                                    selected={selected === index}
                                    playable={
                                        isTurn &&
                                        (card === requiredCard ||
                                            (card === 'git_pull' &&
                                                viewer.step < 3) ||
                                            [
                                                'clear_credentials',
                                                'merge_conflict',
                                            ].includes(card))
                                    }
                                    onClick={
                                        isTurn && !busy
                                            ? () =>
                                                  setSelected(
                                                      selected === index
                                                          ? null
                                                          : index,
                                                  )
                                            : undefined
                                    }
                                />
                            ))}
                        </div>
                        <div className="flex flex-wrap items-center gap-2 border-t border-white/10 pt-3">
                            <span className="mr-auto font-mono text-[10px] uppercase text-white/60">
                                {viewer.pending_discard > 0
                                    ? 'Descarte o excesso da mão'
                                    : isTurn
                                      ? 'Sua vez'
                                      : 'Aguardando outro jogador'}
                            </span>
                            {needsTarget && (
                                <select
                                    value={target}
                                    onChange={(event) =>
                                        setTarget(event.target.value)
                                    }
                                    className="min-h-10 border-2 border-white/30 bg-[#1d1d1b] px-2 text-xs text-white"
                                >
                                    <option value="">Escolha o alvo</option>
                                    {opponents.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            @{p.player.username}
                                        </option>
                                    ))}
                                </select>
                            )}
                            {selectedCard && viewer.pending_discard > 0 && (
                                <button
                                    disabled={busy}
                                    onClick={() =>
                                        send('discard_excess', selectedCard)
                                    }
                                    className="min-h-10 border-2 border-white bg-[#d01600] px-3 font-mono text-[10px] font-black uppercase"
                                >
                                    Descartar excesso
                                </button>
                            )}
                            {selectedCard && viewer.pending_discard === 0 && (
                                <>
                                    <button
                                        disabled={busy}
                                        onClick={() =>
                                            send('exchange', selectedCard)
                                        }
                                        className="min-h-10 border-2 border-white/40 px-3 font-mono text-[10px] font-black uppercase"
                                    >
                                        Trocar carta
                                    </button>
                                    <button
                                        disabled={
                                            busy ||
                                            !canPlaySelected ||
                                            (needsTarget && !target)
                                        }
                                        onClick={() =>
                                            send(
                                                'play_card',
                                                selectedCard,
                                                target,
                                            )
                                        }
                                        className="min-h-10 border-2 border-white bg-[#b4da33] px-4 font-mono text-[10px] font-black uppercase text-[#1d1d1b] disabled:opacity-35"
                                    >
                                        Jogar carta
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </section>
            </main>

            {game.status === 'finished' && (
                <div className="fixed inset-0 z-40 grid place-items-center bg-[#10100f]/80 p-4 backdrop-blur-sm">
                    <motion.div
                        initial={reduced ? false : { scale: 0.85, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        className="w-full max-w-lg border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-7 text-center text-[#1d1d1b] shadow-[10px_10px_0_#b4da33]"
                    >
                        <p className="font-mono text-xs font-black uppercase text-[#464498]">
                            Partida finalizada
                        </p>
                        <h2 className="mt-2 text-4xl font-black">
                            {winner?.is_viewer
                                ? 'Você conquistou a missão!'
                                : `@${winner?.player.username ?? 'Jogador'} venceu`}
                        </h2>
                        <p className="mt-3 text-sm text-[#5b5953]">
                            A Pull Request foi concluída e o foguete está
                            pronto.
                        </p>
                        <div className="mt-6 flex justify-center gap-3">
                            <Link
                                href={route('dashboard')}
                                className="border-2 border-[#1d1d1b] bg-white px-4 py-3 font-mono text-xs font-black uppercase"
                            >
                                Dashboard
                            </Link>
                            <Link
                                href={`${route('dashboard')}#criar-sala`}
                                className="border-2 border-[#1d1d1b] bg-[#b4da33] px-4 py-3 font-mono text-xs font-black uppercase"
                            >
                                Nova sala
                            </Link>
                        </div>
                    </motion.div>
                </div>
            )}
            <ConfirmDialog
                show={leaveOpen}
                title="Sair da partida?"
                confirmLabel="Sair da mesa"
                onClose={() => setLeaveOpen(false)}
                onConfirm={() => router.visit(route('dashboard'))}
            >
                <p>
                    Seu lugar e seu progresso continuam na partida. Você poderá
                    voltar pela Dashboard enquanto a sala existir.
                </p>
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}

function PlayerSeat({
    participant,
    active,
    viewer = false,
}: {
    participant: Participant;
    active: boolean;
    viewer?: boolean;
}) {
    return (
        <div
            className={`min-w-0 rounded-xl border-2 p-2 transition ${active ? 'border-[#b4da33] bg-[#b4da33]/15 shadow-[0_0_18px_#b4da3355]' : 'border-white/15 bg-[#1d1d1b]/90'}`}
        >
            <div className="flex min-w-0 items-center gap-2">
                <PlayerAvatar profile={participant.player} size="xs" />
                <div className="min-w-0 flex-1">
                    <strong className="block truncate text-xs">
                        {viewer ? 'Você' : `@${participant.player.username}`}
                    </strong>
                    <div className="flex gap-1 font-mono text-[8px] uppercase text-white/55">
                        {participant.is_host && <span>Anfitrião</span>}
                        {participant.player.is_bot && (
                            <span className="text-[#b4da33]">Bot</span>
                        )}
                        {active && (
                            <span className="text-[#f481c6]">Jogando</span>
                        )}
                    </div>
                </div>
                <span className="font-mono text-[9px] text-white/50">
                    {participant.hand_count} cartas
                </span>
            </div>
            <div
                className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10"
                role="progressbar"
                aria-label={`Progresso de ${participant.player.username}`}
                aria-valuemin={0}
                aria-valuemax={18}
                aria-valuenow={participant.step}
            >
                <div
                    className="h-full bg-[#b4da33] transition-[width] motion-reduce:transition-none"
                    style={{ width: `${Math.round((participant.step / 18) * 100)}%` }}
                />
            </div>
            {participant.blocked && (
                <p className="mt-1 font-mono text-[8px] font-black uppercase text-[#f481c6]">
                    Merge Conflict · perde a vez
                </p>
            )}
        </div>
    );
}
