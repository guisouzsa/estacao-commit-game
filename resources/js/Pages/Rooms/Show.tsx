import Modal from '@/Components/Modal';
import RoomCountdown from '@/Components/RoomCountdown';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
type Player = { id: string; username: string };
type Participant = { id: number; seat: number; user: Player };
type Room = {
    id: string;
    status: string;
    host: Player;
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
    expiresAt: string;
}) {
    const [busy, setBusy] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const code = inviteUrl?.split('/').filter(Boolean).pop();
    const hasOtherParticipants = room.participants.some(
        (participant) => participant.user.id !== room.host.id,
    );
    const canDelete =
        isHost && room.status === 'waiting' && !hasOtherParticipants;
    const hasWaitingDeadline =
        room.status === 'waiting' && !hasOtherParticipants;
    const roomTitle =
        room.status === 'expired'
            ? 'Sala expirada'
            : room.participants.length === 4
              ? 'Sala completa'
              : 'Aguardando participantes';

    useEffect(() => {
        const refresh = () =>
            router.reload({
                only: ['room', 'expiresAt'],
            });

        const channelName = `rooms.${room.id}`;
        if (window.Echo) {
            const channel = window.Echo.private(channelName);
            channel.listen('.room.state.changed', refresh);
        }

        const poll = window.setInterval(() => {
            if (room.status === 'waiting') refresh();
        }, 15000);

        return () => {
            window.clearInterval(poll);
            window.Echo?.leave(channelName);
        };
    }, [room.id, room.status]);

    const cancel = () => {
        setBusy(true);
        router.delete(route('rooms.cancel', room.id), {
            onSuccess: () => setConfirming(false),
            onError: () => setBusy(false),
            onFinish: () => setBusy(false),
        });
    };
    return (
        <AuthenticatedLayout>
            <Head title={`Sala ${room.id.slice(0, 8)}`} />
            <main className="min-h-[calc(100vh-4rem)] bg-[#e8e5dc] px-3 py-8 text-[#1d1d1b] sm:px-4 lg:px-6">
                <div className="w-full">
                    <Link
                        href={route('dashboard')}
                        className="inline-flex min-h-10 items-center border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase shadow-[2px_2px_0_#b4da33]"
                    >
                        ← Voltar
                    </Link>
                    <header className="mt-5 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-6 shadow-[8px_8px_0_#464498]">
                        <p className="font-mono text-xs uppercase tracking-[.18em] text-[#464498]">
                            Lobby privado / {room.id.slice(0, 8)}
                        </p>
                        <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h1 className="text-3xl font-black">
                                    {roomTitle}
                                </h1>
                                <p className="mt-1 text-sm">
                                    A partida exige exatamente quatro jogadores.
                                </p>
                                {hasWaitingDeadline && (
                                    <p className="mt-3 border-l-4 border-[#f0b429] bg-[#f0b429]/15 px-3 py-2">
                                        <RoomCountdown expiresAt={expiresAt} />
                                    </p>
                                )}
                                {room.status === 'expired' && (
                                    <p
                                        className="mt-3 border-l-4 border-[#d01600] bg-[#d01600]/10 px-3 py-2 font-mono text-xs font-black uppercase text-[#d01600]"
                                        role="status"
                                    >
                                        Esta sala expirou e não aceita novos
                                        jogadores.
                                    </p>
                                )}
                            </div>
                            <strong className="border-2 border-[#1d1d1b] bg-[#b4da33] px-4 py-2 font-mono">
                                {room.participants.length}/4
                            </strong>
                        </div>
                    </header>
                    <section className="mt-7 border-[3px] border-[#1d1d1b] bg-white shadow-[5px_5px_0_#1d1d1b]">
                        <h2 className="border-b-2 border-[#1d1d1b] bg-[#f3f1ec] px-4 py-3 text-lg font-black">
                            Participantes
                        </h2>
                        <ol>
                            {[1, 2, 3, 4].map((seat) => {
                                const p = room.participants.find(
                                    (x) => x.seat === seat,
                                );
                                return (
                                    <li
                                        key={seat}
                                        className="flex min-h-14 items-center justify-between gap-3 border-b border-[#1d1d1b]/20 px-4 last:border-0"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="grid size-7 place-items-center border-2 border-[#1d1d1b] bg-[#e8e5dc] font-mono text-xs font-black">
                                                {seat}
                                            </span>
                                            <strong
                                                className={
                                                    p ? '' : 'text-[#777]'
                                                }
                                            >
                                                {p
                                                    ? `@${p.user.username}`
                                                    : 'Vaga disponível'}
                                            </strong>
                                        </div>
                                        {p?.user.id === room.host.id && (
                                            <span className="border-2 border-[#464498] bg-white px-2 py-1 font-mono text-[10px] font-black uppercase text-[#464498]">
                                                Anfitrião
                                            </span>
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                    </section>
                    {isHost && room.status === 'waiting' && (
                        <section className="mt-7 border-[3px] border-[#1d1d1b] bg-[#1d1d1b] p-5 text-white">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 className="text-xl font-black">
                                        Convide jogadores
                                    </h2>
                                    <p className="mt-1 text-sm text-white/70">
                                        Se ninguém entrar, a sala expira após
                                        cinco minutos. Convites podem ser
                                        revogados.
                                    </p>
                                </div>
                                {canDelete ? (
                                    <button
                                        aria-label="Excluir sala"
                                        onClick={() => setConfirming(true)}
                                        className="border border-white/50 px-3 py-2 font-mono text-xs font-bold"
                                    >
                                        Excluir sala
                                    </button>
                                ) : (
                                    <p className="max-w-sm text-xs font-bold text-white/60">
                                        Outro jogador já entrou. Esta sala não
                                        pode mais ser excluída.
                                    </p>
                                )}
                            </div>
                            {inviteUrl ? (
                                <div className="mt-5 flex flex-wrap items-center gap-3">
                                    <strong className="border-2 border-white bg-white px-5 py-3 font-mono text-2xl tracking-[.22em] text-[#1d1d1b]">
                                        {code}
                                    </strong>
                                    <button
                                        onClick={() =>
                                            navigator.clipboard.writeText(
                                                inviteUrl,
                                            )
                                        }
                                        className="min-h-11 border-2 border-white bg-[#b4da33] px-4 font-mono text-xs font-black uppercase text-[#1d1d1b]"
                                    >
                                        Copiar convite
                                    </button>
                                </div>
                            ) : (
                                <button
                                    disabled={busy}
                                    onClick={() => {
                                        setBusy(true);
                                        router.post(
                                            route('rooms.invite', room.id),
                                            {},
                                            { onFinish: () => setBusy(false) },
                                        );
                                    }}
                                    className="mt-5 border-2 border-white px-4 py-2 font-mono text-xs font-black uppercase disabled:opacity-60"
                                >
                                    {busy ? 'Gerando...' : 'Gerar novo convite'}
                                </button>
                            )}
                            <button
                                onClick={() =>
                                    router.delete(
                                        route('rooms.invite.revoke', room.id),
                                    )
                                }
                                className="mt-5 block text-xs font-bold text-white/70 underline"
                            >
                                Revogar convite atual
                            </button>
                        </section>
                    )}
                </div>
            </main>
            <Modal
                show={confirming}
                onClose={() => !busy && setConfirming(false)}
            >
                <div className="p-6">
                    <p className="font-mono text-xs font-black uppercase tracking-[.16em] text-[#d01600]">
                        Ação destrutiva
                    </p>
                    <h2 className="mt-2 text-2xl font-black">
                        Excluir esta sala?
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-[#5b5953]">
                        Tem certeza de que deseja excluir esta sala? Essa ação
                        não poderá ser desfeita.
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
        </AuthenticatedLayout>
    );
}
