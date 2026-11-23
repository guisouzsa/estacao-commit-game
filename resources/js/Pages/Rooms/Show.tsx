import Modal from '@/Components/Modal';
import PlayerAvatar from '@/Components/PlayerAvatar';
import RoomCountdown from '@/Components/RoomCountdown';
import ToastStack, { useToasts } from '@/Components/Toast';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { startRoomSync } from '@/lib/roomSync';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Player = {
    key: string;
    kind: 'user' | 'guest' | 'bot';
    is_bot: boolean;
    username: string;
    avatar?: string | null;
    avatar_position_x?: number;
    avatar_position_y?: number;
    avatar_zoom?: number;
    profile_frame?: string | null;
};
type Participant = {
    id: number;
    seat: number;
    is_host: boolean;
    player: Player | null;
};
type Room = {
    id: string;
    status: string;
    started_at?: string | null;
    participants: Participant[];
};

export default function Show({
    room,
    isHost,
    inviteUrl,
    expiresAt,
}: {
    room: Room;
    isHost: boolean;
    inviteUrl?: string;
    expiresAt?: string | null;
}) {
    const [busy, setBusy] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const { toasts, push, dismiss } = useToasts();
    const code = inviteUrl?.split('/').filter(Boolean).pop();
    const hasOtherHumans = room.participants.some(
        (participant) => !participant.is_host && !participant.player?.is_bot,
    );
    const canDelete = isHost && !room.started_at;
    const hasWaitingDeadline =
        room.status === 'waiting' && !hasOtherHumans && Boolean(expiresAt);
    const roomTitle =
        room.status === 'expired'
            ? 'Sala expirada'
            : room.participants.length === 4
              ? 'Sala completa'
              : 'Aguardando participantes';
    const statusRef = useRef(room.status);
    statusRef.current = room.status;

    useEffect(
        () =>
            startRoomSync({
                echo: window.Echo,
                roomId: room.id,
                reload: (done) =>
                    router.reload({
                        only: ['room', 'expiresAt'],
                        onFinish: done,
                    }),
                shouldPoll: () => statusRef.current === 'waiting',
                onSubscriptionError: () =>
                    console.warn(
                        'Tempo real indisponível; usando atualização periódica.',
                    ),
                onRoomDeleted: () => router.visit(route('dashboard')),
                document,
                window,
            }),
        [room.id],
    );

    useEffect(
        () =>
            router.on('invalid', (event) => {
                if (event.detail.response.status !== 404) return;
                event.preventDefault();
                router.visit(route('dashboard'));
            }),
        [],
    );

    const copyInvite = async () => {
        if (!inviteUrl) return;
        try {
            await navigator.clipboard.writeText(inviteUrl);
            push({
                id: `copy:${Date.now()}`,
                tone: 'success',
                message: 'Convite copiado. Agora é só enviar aos jogadores.',
            });
        } catch {
            push({
                id: `copy-error:${Date.now()}`,
                tone: 'warning',
                message:
                    'Não foi possível copiar. Selecione o link manualmente.',
            });
        }
    };
    const createInvite = () => {
        setBusy(true);
        router.post(
            route('rooms.invite', room.id),
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };
    const revokeInvite = () => {
        setBusy(true);
        router.delete(route('rooms.invite.revoke', room.id), {
            preserveScroll: true,
            onSuccess: () =>
                push({
                    id: `revoke:${Date.now()}`,
                    tone: 'info',
                    message:
                        'Convite revogado. O código antigo não funciona mais.',
                }),
            onFinish: () => setBusy(false),
        });
    };
    const cancel = () => {
        setBusy(true);
        router.delete(route('rooms.cancel', room.id), {
            onSuccess: () => setConfirming(false),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Sala ${room.id.slice(0, 8)}`} />
            <main className="min-h-[calc(100vh-4rem)] bg-[#e8e5dc] px-3 py-4 text-[#1d1d1b] sm:px-4 sm:py-5 lg:px-6">
                <div className="mx-auto w-full max-w-7xl">
                    <Link
                        href={route('dashboard')}
                        className="inline-flex min-h-10 items-center border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase shadow-[2px_2px_0_#b4da33] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]"
                    >
                        ← Voltar
                    </Link>

                    <header className="mt-3 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-4 shadow-[5px_5px_0_#464498] sm:p-5">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b-2 border-[#1d1d1b]/15 pb-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-mono text-[10px] font-black uppercase tracking-[.16em] text-[#464498]">
                                    Lobby privado
                                </span>
                                <span className="border-2 border-[#1d1d1b] bg-white px-2 py-1 font-mono text-xs font-black uppercase">
                                    Sala {room.id.slice(0, 8)}
                                </span>
                            </div>
                            {canDelete && (
                                <button
                                    type="button"
                                    aria-label="Excluir sala"
                                    onClick={() => setConfirming(true)}
                                    className="inline-flex min-h-10 items-center gap-2 border-2 border-transparent px-2 font-mono text-[11px] font-black uppercase text-[#d01600] hover:border-[#d01600] hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]"
                                >
                                    <svg
                                        aria-hidden="true"
                                        viewBox="0 0 24 24"
                                        className="size-4"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2.5"
                                        strokeLinecap="square"
                                    >
                                        <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                                    </svg>
                                    Excluir sala
                                </button>
                            )}
                        </div>
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                            <div className="min-w-0">
                                <h1 className="text-2xl font-black leading-tight sm:text-3xl">
                                    {roomTitle}
                                </h1>
                                <p className="mt-1 text-sm text-[#5b5953]">
                                    A partida começa quando os quatro lugares
                                    estiverem ocupados.
                                </p>
                            </div>
                            <strong
                                className="shrink-0 border-2 border-[#1d1d1b] bg-[#b4da33] px-3 py-1.5 font-mono text-lg shadow-[2px_2px_0_#1d1d1b]"
                                aria-label={`${room.participants.length} de 4 participantes`}
                            >
                                {room.participants.length}/4
                            </strong>
                        </div>
                        {hasWaitingDeadline && (
                            <div className="mt-3 inline-flex flex-wrap items-center gap-x-2 gap-y-1 border-l-4 border-[#f0b429] bg-[#f0b429]/15 px-3 py-2 text-xs">
                                <span>
                                    A sala vazia é removida em 5 minutos.
                                </span>
                                <RoomCountdown expiresAt={expiresAt} />
                            </div>
                        )}
                        {room.status === 'expired' && (
                            <p
                                className="mt-3 border-l-4 border-[#d01600] bg-[#d01600]/10 px-3 py-2 font-mono text-xs font-black uppercase text-[#d01600]"
                                role="status"
                            >
                                Esta sala expirou e não aceita novos jogadores.
                            </p>
                        )}
                    </header>

                    <div className="mt-4 grid items-start gap-4 lg:grid-cols-[minmax(0,1.65fr)_minmax(19rem,0.85fr)]">
                        <section className="border-[3px] border-[#1d1d1b] bg-white shadow-[4px_4px_0_#1d1d1b]">
                            <div className="flex items-center justify-between border-b-2 border-[#1d1d1b] bg-[#f3f1ec] px-4 py-2.5">
                                <h2 className="text-lg font-black">
                                    Participantes
                                </h2>
                                <span className="font-mono text-[10px] font-bold uppercase text-[#68665f]">
                                    4 lugares
                                </span>
                            </div>
                            <ol className="grid gap-px bg-[#1d1d1b]/20 sm:grid-cols-2">
                                {[1, 2, 3, 4].map((seat) => {
                                    const participant = room.participants.find(
                                        (item) => item.seat === seat,
                                    );
                                    const player = participant?.player;
                                    return (
                                        <li
                                            key={seat}
                                            className="flex min-h-[4.75rem] items-center gap-3 bg-white p-3"
                                        >
                                            <span
                                                className="grid size-7 shrink-0 place-items-center border-2 border-[#1d1d1b] bg-[#e8e5dc] font-mono text-xs font-black"
                                                aria-label={`Lugar ${seat}`}
                                            >
                                                {seat}
                                            </span>
                                            {player ? (
                                                <>
                                                    <PlayerAvatar
                                                        profile={player}
                                                        size="sm"
                                                    />
                                                    <div className="min-w-0 flex-1">
                                                        <strong className="block truncate text-sm">
                                                            @{player.username}
                                                        </strong>
                                                        <div className="mt-1 flex flex-wrap gap-1">
                                                            {participant?.is_host && (
                                                                <span className="border border-[#464498] px-1.5 py-0.5 font-mono text-[9px] font-black uppercase text-[#464498]">
                                                                    Anfitrião
                                                                </span>
                                                            )}
                                                            {player.is_bot && (
                                                                <span className="border border-[#1d1d1b] bg-[#b4da33] px-1.5 py-0.5 font-mono text-[9px] font-black uppercase">
                                                                    Bot
                                                                </span>
                                                            )}
                                                            {player.kind ===
                                                                'guest' && (
                                                                <span className="font-mono text-[9px] font-bold uppercase text-[#68665f]">
                                                                    Visitante
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </>
                                            ) : (
                                                <div className="min-w-0">
                                                    <strong className="block text-sm text-[#68665f]">
                                                        Vaga disponível
                                                    </strong>
                                                    <span className="font-mono text-[9px] uppercase tracking-[.12em] text-[#8a8881]">
                                                        Aguardando jogador
                                                    </span>
                                                </div>
                                            )}
                                        </li>
                                    );
                                })}
                            </ol>
                        </section>

                        {isHost && room.status === 'waiting' ? (
                            <section className="border-[3px] border-[#1d1d1b] bg-[#1d1d1b] p-4 text-white shadow-[4px_4px_0_#b4da33]">
                                <p className="font-mono text-[10px] font-bold uppercase tracking-[.16em] text-[#b4da33]">
                                    Convite da sala
                                </p>
                                <h2 className="mt-1 text-xl font-black">
                                    Convide jogadores
                                </h2>
                                {inviteUrl ? (
                                    <>
                                        <div className="mt-3 flex items-stretch gap-2">
                                            <strong className="flex min-w-0 flex-1 items-center justify-center border-2 border-white bg-white px-3 py-2 font-mono text-xl tracking-[.2em] text-[#1d1d1b] sm:text-2xl">
                                                {code}
                                            </strong>
                                            <button
                                                type="button"
                                                onClick={copyInvite}
                                                className="min-h-11 border-2 border-white bg-[#b4da33] px-3 font-mono text-[11px] font-black uppercase text-[#1d1d1b] hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f481c6]"
                                            >
                                                Copiar
                                            </button>
                                        </div>
                                        <input
                                            aria-label="Link do convite"
                                            readOnly
                                            value={inviteUrl}
                                            onFocus={(event) =>
                                                event.currentTarget.select()
                                            }
                                            className="mt-2 w-full border border-white/30 bg-white/10 px-2 py-2 font-mono text-[10px] text-white"
                                        />
                                        <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-xs text-white/65">
                                                Válido por até 24 horas ou até
                                                ser revogado.
                                            </p>
                                            <button
                                                type="button"
                                                disabled={busy}
                                                onClick={revokeInvite}
                                                className="min-h-10 px-1 font-mono text-[10px] font-bold uppercase text-white/75 underline decoration-[#f481c6] decoration-2 underline-offset-4 hover:text-white disabled:opacity-50"
                                            >
                                                Revogar convite
                                            </button>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <p className="mt-2 text-sm leading-5 text-white/70">
                                            Gere um código curto para
                                            compartilhar esta sala.
                                        </p>
                                        <button
                                            type="button"
                                            disabled={busy}
                                            onClick={createInvite}
                                            className="mt-4 min-h-11 border-2 border-white bg-[#b4da33] px-4 font-mono text-xs font-black uppercase text-[#1d1d1b] disabled:opacity-60"
                                        >
                                            {busy
                                                ? 'Gerando...'
                                                : 'Gerar novo convite'}
                                        </button>
                                    </>
                                )}
                            </section>
                        ) : (
                            <aside className="border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-4 shadow-[4px_4px_0_#b4da33]">
                                <p className="font-mono text-[10px] font-bold uppercase tracking-[.16em] text-[#464498]">
                                    Sala privada
                                </p>
                                <p className="mt-2 text-sm leading-5 text-[#5b5953]">
                                    O anfitrião controla os convites desta sala.
                                </p>
                            </aside>
                        )}
                    </div>
                </div>
            </main>

            <Modal
                show={confirming}
                onClose={() => !busy && setConfirming(false)}
                maxWidth="md"
            >
                <div className="p-5 sm:p-6">
                    <p className="font-mono text-xs font-black uppercase tracking-[.16em] text-[#d01600]">
                        Ação destrutiva
                    </p>
                    <h2 className="mt-2 text-2xl font-black">
                        Excluir esta sala?
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-[#5b5953]">
                        {hasOtherHumans
                            ? 'Todos os participantes serão removidos e avisados. Essa ação não poderá ser desfeita.'
                            : 'A sala e o convite serão removidos. Essa ação não poderá ser desfeita.'}
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            disabled={busy}
                            onClick={() => setConfirming(false)}
                            className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-bold uppercase"
                        >
                            Cancelar
                        </button>
                        <button
                            disabled={busy}
                            onClick={cancel}
                            className="min-h-11 border-2 border-[#1d1d1b] bg-[#d01600] px-4 font-mono text-xs font-black uppercase text-white disabled:opacity-60"
                        >
                            {busy ? 'Excluindo...' : 'Excluir sala'}
                        </button>
                    </div>
                </div>
            </Modal>
            <ToastStack toasts={toasts} onDismiss={dismiss} durationMs={5000} />
        </AuthenticatedLayout>
    );
}
