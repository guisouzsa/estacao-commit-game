import AchievementsPanel from '@/Components/AchievementsPanel';
import { useState } from 'react';
import type {
    AchievementDisplayMode,
    ProfileBadge,
    ProfileLink,
} from './PlayerProfileCard';

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
    achievementDisplayMode = 'none',
    share = false,
}: {
    username: string;
    links?: ProfileLink[];
    badges?: ProfileBadge[];
    achievementDisplayMode?: AchievementDisplayMode;
    statistics?: Statistics | null;
    share?: boolean;
}) {
    const [message, setMessage] = useState('');
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
            <AchievementsPanel
                badges={badges}
                displayMode={achievementDisplayMode}
            />
        </div>
    );
}
