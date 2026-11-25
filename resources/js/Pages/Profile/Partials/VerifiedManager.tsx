import VerifiedBadge from '@/Components/VerifiedBadge';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Found {
    username: string;
    display_name: string;
    is_verified: boolean;
}

const Check = () => <VerifiedBadge size={18} />;

/** Só aparece para a conta administradora: pesquisa um jogador e concede ou remove o selo de verificado. */
export default function VerifiedManager() {
    const me = usePage().props.auth.user as {
        username: string;
        is_verified?: boolean;
    };
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Found[]>([]);
    const [busy, setBusy] = useState<string | null>(null);
    const [searched, setSearched] = useState(false);

    const search = (q: string, signal?: AbortSignal) =>
        fetch(route('players.search', { q }), {
            headers: { Accept: 'application/json' },
            signal,
        })
            .then((r) => (r.ok ? r.json() : { players: [] }))
            .then((d: { players: Found[] }) => {
                setResults(d.players);
                setSearched(true);
            })
            .catch(() => undefined);

    useEffect(() => {
        const q = query.trim();
        if (q.length < 2) {
            setResults([]);
            setSearched(false);
            return;
        }
        const controller = new AbortController();
        const timer = window.setTimeout(
            () => search(q, controller.signal),
            250,
        );

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const toggle = (player: Found) => {
        setBusy(player.username);
        router.patch(
            route('admin.users.verified.update', player.username),
            { verified: !player.is_verified },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => search(query.trim()),
                onFinish: () => setBusy(null),
            },
        );
    };

    return (
        <section className="border-2 border-[#1d1d1b] bg-white p-5 shadow-[5px_5px_0_#168de2] sm:p-6">
            <h2 className="flex items-center gap-2 text-lg font-black">
                <Check /> Verificar jogadores
            </h2>
            <p className="mt-1 text-sm text-[#5b5953]">
                Pesquise pelo nome e conceda ou remova o selo de verificado. Só
                a sua conta vê esta caixa.
            </p>
            <div className="mt-4 flex items-center justify-between gap-3 border-2 border-[#1d1d1b]/15 px-3 py-2">
                <span className="flex min-w-0 items-center gap-2 font-bold">
                    <span className="truncate">
                        Meu perfil (@{me.username})
                    </span>
                    {me.is_verified && <Check />}
                </span>
                <button
                    type="button"
                    disabled={busy === me.username}
                    onClick={() =>
                        toggle({
                            username: me.username,
                            display_name: me.username,
                            is_verified: Boolean(me.is_verified),
                        })
                    }
                    className={`min-h-9 shrink-0 border-2 border-[#1d1d1b] px-3 py-1 font-mono text-xs font-black uppercase disabled:opacity-50 ${me.is_verified ? 'bg-white' : 'bg-[#b4da33]'}`}
                >
                    {me.is_verified ? 'Tirar o meu' : 'Verificar o meu'}
                </button>
            </div>
            <input
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Buscar jogador pelo nome…"
                aria-label="Buscar jogador para verificar"
                className="mt-4 min-h-11 w-full border-2 border-[#1d1d1b] bg-white px-3 py-2"
            />
            {searched && results.length === 0 && (
                <p className="mt-3 text-sm text-[#5b5953]">
                    Nenhum jogador encontrado.
                </p>
            )}
            {results.length > 0 && (
                <ul className="mt-3 divide-y divide-[#1d1d1b]/10 border-2 border-[#1d1d1b]/15">
                    {results.map((player) => (
                        <li
                            key={player.username}
                            className="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <span className="flex min-w-0 items-center gap-2 font-bold">
                                <span className="truncate">
                                    @{player.username}
                                </span>
                                {player.is_verified && <Check />}
                            </span>
                            <button
                                type="button"
                                disabled={busy === player.username}
                                onClick={() => toggle(player)}
                                className={`min-h-9 shrink-0 border-2 border-[#1d1d1b] px-3 py-1 font-mono text-xs font-black uppercase disabled:opacity-50 ${
                                    player.is_verified
                                        ? 'bg-white'
                                        : 'bg-[#b4da33]'
                                }`}
                            >
                                {player.is_verified ? 'Remover' : 'Verificar'}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
