import PlayerAvatar from '@/Components/PlayerAvatar';
import { PlayerSummary } from '@/Components/PlayerSearch';
import WelcomeTutorial from '@/Components/WelcomeTutorial';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Room = { id: string; status: string; participants_count: number };

export default function Dashboard({
    rooms = [],
    inviteUrl,
    recommendedPlayers = [],
}: {
    rooms: Room[];
    inviteUrl?: string;
    recommendedPlayers: PlayerSummary[];
}) {
    const [invite, setInvite] = useState('');
    const [creating, setCreating] = useState(false);
    const [joining, setJoining] = useState(false);
    const join = (event: FormEvent) => {
        event.preventDefault();
        const token = invite.trim().split('/').filter(Boolean).pop();
        if (!token) return;
        setJoining(true);
        router.post(
            route('rooms.join', token),
            {},
            { onFinish: () => setJoining(false) },
        );
    };
    return (
        <AuthenticatedLayout>
            <Head title="Vamos jogar?" />
            <WelcomeTutorial />
            <main className="min-h-[calc(100vh-4rem)] bg-[#e8e5dc] px-4 py-8 text-[#1d1d1b] sm:px-6">
                <div className="mx-auto max-w-6xl">
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
                                disabled={creating}
                                onClick={() => {
                                    setCreating(true);
                                    router.post(
                                        route('rooms.store'),
                                        {},
                                        { onFinish: () => setCreating(false) },
                                    );
                                }}
                                className="mt-6 min-h-12 border-2 border-white bg-[#b4da33] px-5 font-mono text-sm font-black uppercase text-[#1d1d1b] shadow-[4px_4px_0_#1d1d1b] disabled:opacity-60"
                            >
                                {creating ? 'Criando...' : 'Criar sala privada'}
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
                            <form className="mt-5" onSubmit={join}>
                                <label
                                    className="text-sm font-bold"
                                    htmlFor="invite"
                                >
                                    Link ou código do convite
                                </label>
                                <input
                                    id="invite"
                                    value={invite}
                                    onChange={(e) => setInvite(e.target.value)}
                                    className="mt-2 min-h-12 w-full border-2 border-[#1d1d1b] bg-white px-3"
                                    placeholder="Cole o convite aqui"
                                    required
                                />
                                <button
                                    disabled={joining}
                                    className="mt-3 min-h-11 border-2 border-[#1d1d1b] bg-white px-5 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#b4da33] disabled:opacity-60"
                                >
                                    {joining
                                        ? 'Validando...'
                                        : 'Entrar com convite'}
                                </button>
                            </form>
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
                                    <Link
                                        key={room.id}
                                        href={route('rooms.show', room.id)}
                                        className="border-2 border-[#1d1d1b] bg-white p-4 shadow-[3px_3px_0_#1d1d1b]"
                                    >
                                        <div className="flex justify-between gap-2">
                                            <strong className="font-mono text-xs uppercase">
                                                {room.status === 'waiting'
                                                    ? 'Aguardando jogadores'
                                                    : room.status}
                                            </strong>
                                            <span>
                                                {room.participants_count}/4
                                            </span>
                                        </div>
                                        <p className="mt-3 text-sm">
                                            Sala {room.id.slice(0, 8)}
                                        </p>
                                    </Link>
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
                            <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {recommendedPlayers.map((player) => (
                                    <Link
                                        className="flex items-center gap-3 border-2 border-[#1d1d1b] bg-white p-2 hover:bg-[#b4da33]"
                                        href={route(
                                            'profile.public',
                                            player.username,
                                        )}
                                        key={player.username}
                                    >
                                        <PlayerAvatar
                                            profile={player}
                                            size="sm"
                                        />
                                        <strong>@{player.username}</strong>
                                    </Link>
                                ))}
                            </div>
                        </article>
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
