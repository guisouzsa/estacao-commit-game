import { useState } from 'react';
import type { ProfileBadge, ProfileLink } from './PlayerProfileCard';

const labels: Record<string, string> = {
    github: 'GitHub',
    linkedin: 'LinkedIn',
    instagram: 'Instagram',
    youtube: 'YouTube',
    twitch: 'Twitch',
    portfolio: 'Portfólio',
    website: 'Site',
    other: 'Link',
};
const colors: Record<string, string> = {
    common: '#b4da33',
    uncommon: '#f481c6',
    rare: '#464498',
    epic: '#d01600',
    legendary: '#f0b429',
};
type Statistics = {
    matches: number | null;
    wins: number;
    losses: number;
    win_rate: number | null;
    xp?: number;
    level?: number;
};

export default function ProfileExtras({
    username,
    links = [],
    badges = [],
    statistics = null,
    share = false,
}: {
    username: string;
    links?: ProfileLink[];
    badges?: ProfileBadge[];
    statistics?: Statistics | null;
    share?: boolean;
}) {
    const [message, setMessage] = useState('');
    const unlocked = badges.filter((b) => b.unlocked);
    const showAchievements = Boolean(statistics?.matches && unlocked.length);
    const shareProfile = async () => {
        const url = `${window.location.origin}/u/${username}`;
        const nativeShare = typeof navigator.share === 'function';
        try {
            if (nativeShare)
                await navigator.share({
                    title: `@${username} na Estação Commit`,
                    url,
                });
            else await navigator.clipboard.writeText(url);
            setMessage(nativeShare ? 'Perfil compartilhado.' : 'Link copiado.');
        } catch {
            setMessage('Não foi possível compartilhar o perfil agora.');
        }
    };
    return (
        <div className="mt-6 space-y-6 border-t-2 border-[#1d1d1b] pt-6">
            <section>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h2 className="text-lg font-black">Links do perfil</h2>
                    {share && (
                        <button
                            type="button"
                            onClick={shareProfile}
                            className="min-h-10 border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b]"
                        >
                            Compartilhar perfil
                        </button>
                    )}
                </div>
                {message && (
                    <p
                        role="status"
                        className="mt-3 text-sm font-bold text-[#464498]"
                    >
                        {message}
                    </p>
                )}
                <div className="mt-4">
                    {links.length > 0 ? (
                        <div className="flex flex-wrap gap-2">
                            {links.map((link, i) => (
                                <a
                                    key={`${link.url}-${i}`}
                                    href={link.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex min-h-10 items-center border-2 border-[#1d1d1b] bg-white px-3 text-sm font-bold shadow-[2px_2px_0_#f481c6] hover:-translate-y-0.5"
                                >
                                    <span className="mr-2 font-mono text-xs text-[#464498]">
                                        {labels[link.platform] ?? 'Link'}
                                    </span>
                                    {link.label}
                                </a>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-[#68665f]">
                            Nenhum link público adicionado.
                        </p>
                    )}
                </div>
            </section>
            {showAchievements && (
                <section className="border-t-2 border-[#1d1d1b]/15 pt-5">
                    <div className="flex items-end justify-between gap-3">
                        <h2 className="text-lg font-black">Conquistas</h2>
                        <p className="font-mono text-xs font-bold">
                            {unlocked.length} desbloqueadas
                        </p>
                    </div>
                    <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {unlocked.map((b) => (
                            <article
                                key={b.id}
                                className="flex gap-3 border-2 border-[#1d1d1b] bg-white p-3 shadow-[3px_3px_0_#1d1d1b]"
                            >
                                <div
                                    className="grid size-12 shrink-0 place-items-center border-[3px] border-[#1d1d1b]"
                                    style={{
                                        backgroundColor:
                                            colors[b.rarity] ?? '#b4da33',
                                    }}
                                >
                                    <span className="font-mono font-black">
                                        {b.name.slice(0, 2).toUpperCase()}
                                    </span>
                                </div>
                                <div className="min-w-0">
                                    <h3 className="text-sm font-black">
                                        {b.name}
                                    </h3>
                                    <p className="mt-1 text-xs leading-5 text-[#5b5953]">
                                        {b.description}
                                    </p>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}
