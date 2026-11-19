import { Link } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useId, useState } from 'react';
import PlayerAvatar from './PlayerAvatar';

export type PlayerSummary = {
    username: string;
    display_name: string;
    avatar?: string | null;
    profile_frame?: string | null;
    profile_title?: string | null;
};

export default function PlayerSearch() {
    const [query, setQuery] = useState('');
    const [players, setPlayers] = useState<PlayerSummary[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const [focused, setFocused] = useState(false);
    const searchId = `player-search-${useId().replace(/:/g, '')}`;

    useEffect(() => {
        const searchTerm = query.trim();
        if (searchTerm.length < 2) {
            setPlayers([]);
            setLoading(false);
            setFailed(false);

            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            setFailed(false);

            try {
                const response = await axios.get(route('players.search'), {
                    params: { q: searchTerm },
                    signal: controller.signal,
                });
                setPlayers(response.data.players);
            } catch (error) {
                if (!axios.isCancel(error)) {
                    setFailed(true);
                    setPlayers([]);
                }
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const showResults = focused && query.trim().length >= 2;
    const resultId = `${searchId}-results`;

    return (
        <div className="relative w-full">
            <label className="sr-only" htmlFor={searchId}>
                Encontrar jogadores
            </label>
            <div className="relative">
                <svg
                    aria-hidden="true"
                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#464498]"
                    fill="none"
                    stroke="currentColor"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth="2"
                    viewBox="0 0 24 24"
                >
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-4-4" />
                </svg>
                <input
                    aria-autocomplete="list"
                    aria-controls={resultId}
                    aria-expanded={showResults}
                    aria-label="Encontrar jogadores"
                    autoComplete="off"
                    className="min-h-10 w-full border-2 border-white/25 bg-white px-9 py-2 text-sm text-[#1d1d1b] outline-none placeholder:text-[#77756f] focus:border-[#b4da33] focus:ring-2 focus:ring-[#b4da33]/40 sm:min-h-11"
                    id={searchId}
                    onBlur={() => {
                        window.setTimeout(() => setFocused(false), 120);
                    }}
                    onChange={(event) => setQuery(event.target.value)}
                    onFocus={() => setFocused(true)}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            setFocused(false);
                            event.currentTarget.blur();
                        }
                    }}
                    placeholder="Encontrar jogadores..."
                    role="combobox"
                    value={query}
                />
                {loading && (
                    <span
                        aria-label="Buscando"
                        className="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-[#464498]/25 border-t-[#464498]"
                        role="status"
                    />
                )}
            </div>

            {showResults && (
                <div
                    className="absolute left-0 right-0 top-full z-50 mt-2 max-h-[min(70vh,24rem)] overflow-y-auto border-[3px] border-[#1d1d1b] bg-[#f3f1ec] text-[#1d1d1b] shadow-[5px_5px_0_#464498] sm:min-w-[22rem]"
                    id={resultId}
                    role="listbox"
                >
                    {loading && (
                        <p
                            className="px-4 py-3 font-mono text-xs font-bold uppercase text-[#464498]"
                            role="status"
                        >
                            Buscando jogadores...
                        </p>
                    )}
                    {!loading && failed && (
                        <p
                            className="px-4 py-3 text-sm font-bold text-[#d01600]"
                            role="alert"
                        >
                            Não foi possível realizar a busca. Tente novamente.
                        </p>
                    )}
                    {!loading && !failed && players.length === 0 && (
                        <p className="px-4 py-3 text-sm text-[#68665f]">
                            Nenhum jogador encontrado.
                        </p>
                    )}
                    {!loading &&
                        !failed &&
                        players.map((player) => (
                            <Link
                                aria-label={`${player.display_name}, @${player.username}`}
                                className="flex items-center gap-3 border-b border-[#1d1d1b]/10 px-3 py-2.5 last:border-0 hover:bg-[#b4da33]/40 focus:bg-[#b4da33]/40 focus:outline-none"
                                href={route('profile.public', player.username)}
                                key={player.username}
                                onMouseDown={(event) => event.preventDefault()}
                                role="option"
                            >
                                <PlayerAvatar profile={player} size="sm" />
                                <span className="min-w-0">
                                    <strong className="block truncate text-sm">
                                        {player.display_name}
                                    </strong>
                                    <span className="block truncate font-mono text-xs text-[#5b5953]">
                                        @{player.username}
                                    </span>
                                </span>
                            </Link>
                        ))}
                </div>
            )}
        </div>
    );
}
