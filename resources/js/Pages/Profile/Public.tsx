import PlayerProfileCard, {
    PlayerProfile,
} from '@/Components/PlayerProfileCard';
import ProfileExtras from '@/Components/ProfileExtras';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
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
                {social.is_me && (
                    <Link
                        className="inline-flex min-h-10 items-center border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-black uppercase"
                        href={route('profile.edit')}
                    >
                        Editar perfil
                    </Link>
                )}
                {social.can_follow && (
                    <button
                        type="button"
                        disabled={busy}
                        onClick={toggle}
                        aria-pressed={social.is_following}
                        title={
                            social.is_following
                                ? 'Você segue este jogador. Clique para deixar de seguir.'
                                : 'Seguir este jogador'
                        }
                        className={`min-h-10 border-2 border-[#1d1d1b] px-4 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b] transition disabled:opacity-60 ${social.is_following ? 'bg-white text-[#1d1d1b] hover:bg-[#f3f1ec]' : 'bg-[#b4da33] text-[#1d1d1b] hover:bg-[#c8ee46]'}`}
                    >
                        {social.is_following ? 'Deixar de seguir' : 'Seguir'}
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
        <AuthenticatedLayout>
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
            <section className="w-full flex-1 bg-white px-3 py-6 sm:px-6 sm:py-10">
                <div className="mx-auto w-full max-w-5xl">
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
                            statistics={profile.statistics ?? null}
                            share
                        />
                    </PlayerProfileCard>
                </div>
            </section>
        </AuthenticatedLayout>
    );
}
