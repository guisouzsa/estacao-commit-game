import PaginationNav from '@/Components/PaginationNav';
import PlayerAvatar from '@/Components/PlayerAvatar';
import type { PlayerProfile } from '@/Components/PlayerProfileCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Person = PlayerProfile & { is_following: boolean; is_me: boolean };

type Page = {
    data: Person[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const tab =
    'inline-flex min-h-10 items-center border-b-[3px] px-3 font-mono text-xs font-black uppercase';

/** Seguidores e pessoas seguidas de um jogador (paginado, 20 por página). */
export default function Connections({
    profile,
    kind,
    people,
    counts,
}: {
    profile: { username: string };
    kind: 'followers' | 'following';
    people: Page;
    counts: { followers: number; following: number };
}) {
    const [busy, setBusy] = useState<string | null>(null);

    const toggle = (person: Person) => {
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

    const title = kind === 'followers' ? 'Seguidores' : 'Seguindo';

    return (
        <AuthenticatedLayout>
            <Head title={`${title} de @${profile.username}`} />
            <main className="min-h-[calc(100vh-4rem)] bg-[#e8e5dc] px-3 py-6 text-[#1d1d1b] sm:px-4 lg:px-6">
                <div className="mx-auto w-full max-w-3xl">
                    <Link
                        href={route('profile.public', profile.username)}
                        className="inline-flex min-h-10 items-center border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase shadow-[2px_2px_0_#b4da33]"
                    >
                        ← @{profile.username}
                    </Link>

                    <header className="mt-4 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#464498]">
                        <p className="font-mono text-[10px] font-black uppercase tracking-[.16em] text-[#464498]">
                            Comunidade
                        </p>
                        <h1 className="mt-1 text-2xl font-black sm:text-3xl">
                            {title} de @{profile.username}
                        </h1>
                        <nav
                            aria-label="Seguidores e seguindo"
                            className="mt-3 flex gap-1 border-b-2 border-[#1d1d1b]/15"
                        >
                            <Link
                                aria-current={kind === 'followers' ? 'page' : undefined}
                                className={`${tab} ${kind === 'followers' ? 'border-[#464498] text-[#464498]' : 'border-transparent text-[#5b5953]'}`}
                                href={route('profile.followers', profile.username)}
                            >
                                Seguidores · {counts.followers}
                            </Link>
                            <Link
                                aria-current={kind === 'following' ? 'page' : undefined}
                                className={`${tab} ${kind === 'following' ? 'border-[#464498] text-[#464498]' : 'border-transparent text-[#5b5953]'}`}
                                href={route('profile.following', profile.username)}
                            >
                                Seguindo · {counts.following}
                            </Link>
                        </nav>
                    </header>

                    {people.data.length ? (
                        <ul className="mt-4 border-[3px] border-[#1d1d1b] bg-white shadow-[4px_4px_0_#1d1d1b]">
                            {people.data.map((person) => (
                                <li
                                    key={person.username}
                                    className="flex items-center gap-3 border-b border-[#1d1d1b]/15 p-3 last:border-0"
                                >
                                    <PlayerAvatar profile={person} size="sm" />
                                    <Link
                                        className="min-w-0 flex-1 truncate font-bold hover:underline"
                                        href={route('profile.public', person.username)}
                                    >
                                        @{person.username}
                                    </Link>
                                    {!person.is_me && (
                                        <button
                                            type="button"
                                            disabled={busy === person.username}
                                            onClick={() => toggle(person)}
                                            className={`min-h-10 border-2 border-[#1d1d1b] px-3 font-mono text-[11px] font-black uppercase disabled:opacity-60 ${person.is_following ? 'bg-white' : 'bg-[#b4da33]'}`}
                                        >
                                            {person.is_following ? 'Seguindo' : 'Seguir'}
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-4 border-l-4 border-[#464498] bg-white p-4 text-sm">
                            {kind === 'followers'
                                ? 'Ainda não há seguidores por aqui.'
                                : 'Ainda não segue ninguém.'}
                        </p>
                    )}

                    <PaginationNav page={people} />
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
