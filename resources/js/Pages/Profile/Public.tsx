import PlayerProfileCard, {
    PlayerProfile,
} from '@/Components/PlayerProfileCard';
import ProfileExtras from '@/Components/ProfileExtras';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Social = {
    followers_count: number;
    following_count: number;
    logged_in: boolean;
    can_follow: boolean;
    is_me: boolean;
    is_following: boolean;
};

/** Ações sociais exibidas dentro do cabeçalho do card, como em redes sociais. */
function SocialBar({ username, social }: { username: string; social: Social }) {
    const [busy, setBusy] = useState(false);

    const toggle = () => {
        setBusy(true);
        const options = {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        };
        if (social.is_following) {
            router.delete(route('follow.destroy', username), options);
        } else {
            router.post(route('follow.store', username), {}, options);
        }
    };

    const counter = (label: string, value: number, href: string) =>
        social.logged_in ? (
            <Link
                className="font-mono text-[10px] uppercase text-[#5b5953] hover:text-[#464498] sm:text-xs"
                href={href}
            >
                <strong className="text-base text-[#1d1d1b] sm:text-lg">
                    {value}
                </strong>{' '}
                {label}
            </Link>
        ) : (
            <span className="font-mono text-[10px] uppercase text-[#5b5953] sm:text-xs">
                <strong className="text-base text-[#1d1d1b] sm:text-lg">
                    {value}
                </strong>{' '}
                {label}
            </span>
        );

    return (
        <div className="flex max-w-full flex-wrap items-center justify-end gap-x-4 gap-y-2 sm:gap-x-5">
            <div className="flex flex-wrap items-center justify-end gap-x-4 gap-y-1 sm:gap-x-5">
                {counter(
                    'seguidores',
                    social.followers_count,
                    route('profile.followers', username),
                )}
                {counter(
                    'seguindo',
                    social.following_count,
                    route('profile.following', username),
                )}
            </div>
            <div>
                {social.can_follow && (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={toggle}
                        aria-pressed={social.is_following}
                        className={`min-h-10 border-2 border-[#1d1d1b] px-4 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b] transition disabled:opacity-60 ${social.is_following ? 'bg-white text-[#1d1d1b] hover:bg-[#f3f1ec]' : 'bg-[#b4da33] text-[#1d1d1b] hover:bg-[#c8ee46]'}`}
                    >
                        {social.is_following ? 'Seguindo' : 'Seguir'}
                    </button>
                )}
                {!social.logged_in && (
                    <Link
                        className="inline-flex min-h-10 items-center border-2 border-[#1d1d1b] bg-white px-3 font-mono text-[10px] font-black uppercase text-[#464498] shadow-[3px_3px_0_#b4da33]"
                        href={route('login')}
                    >
                        Entre para seguir
                    </Link>
                )}
            </div>
        </div>
    );
}

export default function Public({
    profile,
    social,
}: {
    profile: PlayerProfile;
    social: Social;
}) {
    return (
        <div className="min-h-screen bg-[#1d1d1b] px-3 py-8 text-white sm:px-4 sm:py-12 lg:px-6">
            <Head title={`@${profile.username}`}>
                <meta
                    name="description"
                    content={
                        profile.bio ||
                        `Perfil de @${profile.username} na Estação Commit`
                    }
                />
                <meta
                    property="og:title"
                    content={`@${profile.username} — Estação Commit`}
                />
                <meta
                    property="og:description"
                    content={
                        profile.bio || 'Perfil de jogador na Estação Commit'
                    }
                />
                <meta property="og:type" content="profile" />
                <meta property="og:url" content={`/u/${profile.username}`} />
                {profile.cover && (
                    <meta property="og:image" content={profile.cover} />
                )}
                <meta
                    name="twitter:card"
                    content={profile.cover ? 'summary_large_image' : 'summary'}
                />
            </Head>
            <header className="mb-10 flex items-center justify-between border-b border-white/20 pb-4">
                <Link
                    href="/"
                    className="font-mono text-sm font-bold uppercase tracking-[0.16em] text-[#b4da33]"
                >
                    Estação Commit
                </Link>
                <Link
                    href={route('login')}
                    className="font-mono text-xs uppercase tracking-wider text-white/75 underline decoration-[#f481c6] decoration-2 underline-offset-4 hover:text-white"
                >
                    Entrar na estação
                </Link>
            </header>
            <main className="w-full">
                <PlayerProfileCard
                    profile={profile}
                    headerActions={
                        <SocialBar
                            username={profile.username}
                            social={social}
                        />
                    }
                >
                    <ProfileExtras
                        username={profile.username}
                        links={profile.links}
                        badges={profile.badges}
                        statistics={profile.statistics ?? null}
                        share
                    />
                </PlayerProfileCard>
                <p className="mt-8 text-center font-mono text-[10px] uppercase tracking-[0.2em] text-white/40">
                    Perfil público / estação commit
                </p>
            </main>
        </div>
    );
}
