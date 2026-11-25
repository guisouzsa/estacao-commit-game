import VerifiedBadge from '@/Components/VerifiedBadge';
import ConfirmDialog from '@/Components/ConfirmDialog';
import JoinByCodeForm from '@/Components/JoinByCodeForm';
import PlayerAvatar from '@/Components/PlayerAvatar';
import { PlayerSummary } from '@/Components/PlayerSearch';
import RoomCountdown from '@/Components/RoomCountdown';
import RoomCreationModal, { BotPreview } from '@/Components/RoomCreationModal';
import WelcomeTutorial from '@/Components/WelcomeTutorial';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { startChannelSync } from '@/lib/roomSync';
import { toast } from '@/lib/toast';
import { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Room = {
    id: string;
    status: string;
    participants_count: number;
    bot_slots: number;
    expires_at: string | null;
    can_delete: boolean;
};

type Notice = {
    id: string;
    reason: string;
    message: string;
};

const TITLE_LABEL: Record<string, string> = {
    explorer: 'Explorador',
    builder: 'Montador',
    collaborator: 'Colaborador',
    'coffee-developer': 'Dev Cafeteiro',
    'git-master': 'Mestre do Git',
    'docker-captain': 'Capitão Docker',
    'bug-hunter': 'Caçador de Bugs',
    'code-guardian': 'Guardião do Código',
    'api-architect': 'Arquiteto de APIs',
    'container-tamer': 'Domador de Containers',
    'terminal-wizard': 'Mago do Terminal',
    'open-source-hero': 'Herói Open Source',
    'full-stack-alchemist': 'Alquimista Full Stack',
};
const titleLabel = (key: string) => TITLE_LABEL[key] ?? key;

export default function Dashboard({
    rooms = [],
    notices = [],
    inviteUrl,
    recommendedPlayers = [],
    botPreviews = [],
}: {
    rooms: Room[];
    notices?: Notice[];
    inviteUrl?: string;
    recommendedPlayers: PlayerSummary[];
    botPreviews: BotPreview[];
}) {
    const { auth } = usePage<PageProps>().props;
    const channel = auth.channel;
    const [createOpen, setCreateOpen] = useState(false);
    const [roomToDelete, setRoomToDelete] = useState<Room | null>(null);
    const [deleting, setDeleting] = useState(false);
    // A Dashboard NÃO usa WebSocket: no Render Free o Apache atende só 2 requisições ao mesmo tempo
    // e cada WebSocket aberto ocupa uma delas por inteiro (uma pessoa parada aqui travaria o site
    // para os outros). Os avisos chegam por consulta leve a cada 30 s, só com a aba visível, e
    // também ao voltar para a aba ou à internet. Salas e partidas continuam em tempo real.
    useEffect(
        () =>
            startChannelSync({
                echo: null,
                channelName: channel ?? '',
                event: '.user.notices.changed',
                reload: (done) =>
                    router.reload({
                        only: ['rooms', 'notices', 'auth'],
                        onFinish: done,
                    }),
                shouldPoll: () => document.visibilityState === 'visible',
                pollIntervalMs: 30000,
                document,
                window,
            }),
        [channel],
    );

    // Avisos persistidos (ex.: sala excluída automaticamente). O servidor os
    // marca como lidos ao entregar; o id evita exibir o mesmo aviso duas vezes.
    useEffect(() => {
        notices.forEach((notice) =>
            toast.show({
                id: `notice:${notice.id}`,
                tone: notice.reason === 'empty_timeout' ? 'warning' : 'info',
                message: notice.message,
            }),
        );
    }, [notices]);

    // Erros desta ação (sala já excluída, sem permissão...) chegam do servidor
    // como um aviso amigável; aqui só fechamos o modal ao terminar.
    const confirmDelete = () => {
        if (!roomToDelete) return;
        setDeleting(true);
        router.delete(route('rooms.cancel', roomToDelete.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setRoomToDelete(null);
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Vamos jogar?" />
            {auth.kind === 'user' && <WelcomeTutorial />}
            <main className="min-h-[calc(100vh-4rem)] bg-[#e8e5dc] px-3 py-8 text-[#1d1d1b] sm:px-4 lg:px-6">
                <div className="w-full">
                    <header className="border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-6 shadow-[8px_8px_0_#1d1d1b]">
                        <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-[#464498]">
                            Estação Commit / central de jogo
                        </p>
                        <h1 className="mt-2 text-4xl font-black">
                            Vamos jogar?
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-[#5b5953]">
                            Crie uma sala privada ou entre com um convite. A
                            partida começa somente com quatro participantes.
                        </p>
                    </header>
                    {inviteUrl && (
                        <div className="mt-6 border-2 border-[#1d1d1b] bg-[#b4da33] p-4 shadow-[4px_4px_0_#464498]">
                            <strong>Convite criado.</strong>
                            <p className="mt-1 break-all font-mono text-xs">
                                {inviteUrl}
                            </p>
                        </div>
                    )}
                    <section className="mt-8 grid gap-5 lg:grid-cols-2">
                        <article
                            id="criar-sala"
                            className="border-[3px] border-[#1d1d1b] bg-[#464498] p-6 text-white shadow-[6px_6px_0_#f481c6]"
                        >
                            <p className="font-mono text-xs uppercase tracking-[.16em] text-[#b4da33]">
                                Sala privada
                            </p>
                            <h2 className="mt-2 text-2xl font-black">
                                Criar uma nova sala
                            </h2>
                            <p className="mt-2 text-sm leading-6 text-white/80">
                                Você será o anfitrião e receberá um convite
                                seguro válido por 24 horas.
                            </p>
                            <button
                                type="button"
                                onClick={() => setCreateOpen(true)}
                                className="mt-5 min-h-12 border-2 border-white bg-[#b4da33] px-5 font-mono text-sm font-black uppercase text-[#1d1d1b] shadow-[4px_4px_0_#1d1d1b] disabled:opacity-60"
                            >
                                Criar sala
                            </button>
                        </article>
                        <article
                            id="entrar-convite"
                            className="border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-6 shadow-[6px_6px_0_#1d1d1b]"
                        >
                            <p className="font-mono text-xs uppercase tracking-[.16em] text-[#464498]">
                                Tenho um convite
                            </p>
                            <h2 className="mt-2 text-2xl font-black">
                                Entrar em uma sala
                            </h2>
                            <JoinByCodeForm />
                        </article>
                    </section>
                    <section className="mt-10">
                        <p className="font-mono text-xs font-bold uppercase tracking-[.16em] text-[#464498]">
                            Suas salas
                        </p>
                        <h2 className="mt-1 text-2xl font-black">
                            Continuar de onde parou
                        </h2>
                        {rooms.length ? (
                            <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {rooms.map((room) => (
                                    <div key={room.id} className="relative">
                                        <Link
                                            href={route('rooms.show', room.id)}
                                            className="block border-2 border-[#1d1d1b] bg-white p-4 shadow-[3px_3px_0_#1d1d1b]"
                                        >
                                            <div className="flex justify-between gap-2">
                                                <strong className="font-mono text-xs uppercase">
                                                    {room.status === 'waiting'
                                                        ? 'Aguardando jogadores'
                                                        : room.status ===
                                                            'expired'
                                                          ? 'Sala expirada'
                                                          : room.status}
                                                </strong>
                                                <span>
                                                    {room.participants_count}/4
                                                </span>
                                            </div>
                                            {(room.expires_at ||
                                                room.status === 'expired') && (
                                                <div className="mt-3 border-t border-[#1d1d1b]/10 pt-2">
                                                    <RoomCountdown
                                                        expiresAt={
                                                            room.expires_at
                                                        }
                                                        expired={
                                                            room.status ===
                                                            'expired'
                                                        }
                                                    />
                                                </div>
                                            )}
                                            <p className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                                Sala {room.id.slice(0, 8)}
                                                {room.bot_slots > 0 && (
                                                    <span className="border border-[#1d1d1b] bg-[#b4da33] px-1.5 py-0.5 font-mono text-[9px] font-black uppercase">
                                                        {room.bot_slots}{' '}
                                                        {room.bot_slots === 1
                                                            ? 'bot'
                                                            : 'bots'}
                                                    </span>
                                                )}
                                            </p>
                                        </Link>
                                        {room.can_delete && (
                                            <button
                                                type="button"
                                                title="Excluir sala"
                                                aria-label={`Excluir sala ${room.id.slice(0, 8)}`}
                                                onClick={() =>
                                                    setRoomToDelete(room)
                                                }
                                                className="absolute bottom-3 right-3 grid size-8 place-items-center border-2 border-[#1d1d1b] bg-white text-[#5b5953] hover:bg-[#d01600] hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]"
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
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="mt-4 border-l-4 border-[#464498] bg-white p-4 text-sm">
                                Você ainda não participa de nenhuma sala.
                            </p>
                        )}
                    </section>
                    <section id="comunidade" className="mt-10">
                        <article className="border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#f481c6]">
                            <h2 className="text-xl font-black">
                                Jogadores recomendados
                            </h2>
                            <p className="mt-1 text-sm text-[#5b5953]">
                                Encontre qualquer jogador pela busca no topo da
                                página.
                            </p>
                            <ul className="mt-4 grid gap-2 sm:max-w-xl">
                                {recommendedPlayers.map((player) => (
                                    <li key={player.username}>
                                        {player.is_bot ? (
                                            // Bot do catálogo: identificado como bot, sem perfil público nem seguir.
                                            <div className="flex items-center gap-3 border-2 border-[#1d1d1b] bg-white p-2.5 shadow-[3px_3px_0_#1d1d1b]">
                                                <PlayerAvatar
                                                    profile={player}
                                                    size="sm"
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <strong className="block truncate">
                                                        @{player.username}{player.is_verified && <VerifiedBadge className="ml-1" size={15} />}
                                                    </strong>
                                                    {player.profile_title ? (
                                                        <span className="block truncate font-mono text-[10px] uppercase text-[#68665f]">
                                                            {titleLabel(
                                                                player.profile_title,
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <span className="shrink-0 border border-[#1d1d1b] bg-[#464498] px-1.5 py-0.5 font-mono text-[9px] font-black uppercase text-white">
                                                    Bot
                                                </span>
                                            </div>
                                        ) : (
                                            <Link
                                                className="group flex items-center gap-3 border-2 border-[#1d1d1b] bg-white p-2.5 shadow-[3px_3px_0_#1d1d1b] transition hover:-translate-y-0.5 hover:bg-[#b4da33]"
                                                href={route(
                                                    'profile.public',
                                                    player.username,
                                                )}
                                            >
                                                <PlayerAvatar
                                                    profile={player}
                                                    size="sm"
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <strong className="block truncate">
                                                        @{player.username}{player.is_verified && <VerifiedBadge className="ml-1" size={15} />}
                                                    </strong>
                                                    {player.profile_title ? (
                                                        <span className="block truncate font-mono text-[10px] uppercase text-[#68665f]">
                                                            {titleLabel(
                                                                player.profile_title,
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </span>
                                            </Link>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            <Link
                                className="mt-3 inline-flex min-h-11 items-center gap-2 border-2 border-[#1d1d1b] bg-[#464498] px-4 font-mono text-xs font-black uppercase text-white shadow-[3px_3px_0_#f481c6] transition hover:-translate-y-0.5 hover:bg-[#343273]"
                                href={route('players.recommended')}
                            >
                                Ver mais recomendados
                                <span aria-hidden="true">→</span>
                            </Link>
                        </article>
                    </section>
                </div>
            </main>
            <ConfirmDialog
                show={roomToDelete !== null}
                title="Excluir esta sala?"
                confirmLabel="Excluir sala"
                busyLabel="Excluindo..."
                busy={deleting}
                onConfirm={confirmDelete}
                onClose={() => setRoomToDelete(null)}
            >
                Tem certeza de que deseja excluir esta sala? Essa ação não
                poderá ser desfeita.
            </ConfirmDialog>
            <RoomCreationModal
                show={createOpen}
                host={auth.user}
                botPreviews={botPreviews}
                onClose={() => setCreateOpen(false)}
            />
        </AuthenticatedLayout>
    );
}
