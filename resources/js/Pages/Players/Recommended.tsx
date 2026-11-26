import BackLink from '@/Components/BackLink';
import VerifiedBadge from '@/Components/VerifiedBadge';
import PlayerAvatar from '@/Components/PlayerAvatar';
import type { PlayerSummary } from '@/Components/PlayerSearch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Person = PlayerSummary & {
    profile_title?: string | null;
    is_following: boolean;
    can_follow: boolean;
};

type PeoplePage = {
    data: Person[];
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

export default function Recommended({ people }: { people: PeoplePage }) {
    const [busy, setBusy] = useState<string | null>(null);
    const [refreshing, setRefreshing] = useState(false);

    const toggleFollow = (person: Person) => {
        setBusy(person.username);
        const options = {
            preserveScroll: true,
            onFinish: () => setBusy(null),
        };

        if (person.is_following) {
            router.delete(route('follow.destroy', person.username), options);
        } else {
            router.post(route('follow.store', person.username), {}, options);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Jogadores recomendados" />
            <main className="min-h-[calc(100vh-4rem)] bg-[#e8e5dc] px-3 py-6 text-[#1d1d1b] sm:px-5 lg:px-8">
                <div className="mx-auto w-full max-w-5xl">
                    <BackLink href={route('dashboard')} />

                    <header className="mt-4 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#464498] sm:p-7">
                        <p className="font-mono text-[10px] font-black uppercase tracking-[.18em] text-[#464498]">
                            Comunidade
                        </p>
                        <h1 className="mt-1 text-2xl font-black sm:text-4xl">
                            Jogadores recomendados
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-[#5b5953] sm:text-base">
                            Conheça pessoas da estação, visite seus perfis e
                            acompanhe quem você quiser.
                        </p>
                    </header>

                    <button
                        className="mt-4 min-h-10 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold disabled:opacity-60"
                        disabled={refreshing || busy !== null}
                        onClick={() => {
                            setRefreshing(true);
                            router.post(
                                route('players.recommended.refresh'),
                                {},
                                {
                                    preserveState: true,
                                    replace: true,
                                    onFinish: () => setRefreshing(false),
                                },
                            );
                        }}
                        type="button"
                    >
                        {refreshing
                            ? 'Atualizando…'
                            : 'Atualizar recomendações'}
                    </button>

                    {people.data.length ? (
                        <ul className="mt-5 grid gap-3 sm:grid-cols-2">
                            {people.data.map((person) => (
                                <li
                                    key={person.username}
                                    className="flex min-w-0 flex-wrap items-center gap-3 border-[3px] border-[#1d1d1b] bg-white p-3 shadow-[4px_4px_0_#1d1d1b]"
                                >
                                    <Link
                                        href={route(
                                            'profile.public',
                                            person.username,
                                        )}
                                    >
                                        <PlayerAvatar
                                            profile={person}
                                            size="sm"
                                        />
                                    </Link>
                                    <Link
                                        className="min-w-0 flex-1 hover:underline"
                                        href={route(
                                            'profile.public',
                                            person.username,
                                        )}
                                    >
                                        <strong className="block truncate">
                                            @{person.username}{person.is_verified && <VerifiedBadge className="ml-1" size={15} />}
                                        </strong>
                                        {person.profile_title ? (
                                            <span className="block truncate font-mono text-[10px] font-bold uppercase text-[#68665f]">
                                                {TITLE_LABEL[
                                                    person.profile_title
                                                ] ?? person.profile_title}
                                            </span>
                                        ) : (
                                            <span className="block text-xs text-[#68665f]">
                                                Ver perfil
                                            </span>
                                        )}
                                    </Link>
                                    {person.can_follow ? (
                                        <button
                                            type="button"
                                            disabled={busy === person.username}
                                            onClick={() => toggleFollow(person)}
                                            className={`min-h-10 w-full shrink-0 border-2 border-[#1d1d1b] px-3 font-mono text-[10px] font-black uppercase disabled:opacity-60 sm:w-auto ${person.is_following ? 'bg-white text-[#d01600]' : 'bg-[#b4da33]'}`}
                                        >
                                            {busy === person.username
                                                ? 'Aguarde…'
                                                : person.is_following
                                                  ? 'Deixar de seguir'
                                                  : 'Seguir'}
                                        </button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-5 border-l-4 border-[#464498] bg-white p-4 text-sm">
                            Ainda não há outros jogadores para recomendar.
                        </p>
                    )}
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
