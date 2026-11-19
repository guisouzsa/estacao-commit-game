import PlayerProfileCard, {
    PlayerProfile,
} from '@/Components/PlayerProfileCard';
import { Head, Link } from '@inertiajs/react';
import ProfileExtras from '@/Components/ProfileExtras';

export default function Public({ profile }: { profile: PlayerProfile }) {
    return (
        <div className="min-h-screen bg-[#1d1d1b] px-4 py-8 text-white sm:px-6 sm:py-12">
            <Head title={`@${profile.username}`}>
                <meta name="description" content={profile.bio || `Perfil de @${profile.username} na Estação Commit`} />
                <meta property="og:title" content={`@${profile.username} — Estação Commit`} />
                <meta property="og:description" content={profile.bio || 'Perfil de jogador na Estação Commit'} />
                <meta property="og:type" content="profile" />
                <meta property="og:url" content={`/u/${profile.username}`} />
                {profile.cover && <meta property="og:image" content={profile.cover} />}
                <meta name="twitter:card" content={profile.cover ? 'summary_large_image' : 'summary'} />
            </Head>
            <header className="mx-auto mb-10 flex max-w-5xl items-center justify-between border-b border-white/20 pb-4">
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
            <main className="mx-auto max-w-5xl">
                <PlayerProfileCard profile={profile} />
                <ProfileExtras username={profile.username} links={profile.links} badges={profile.badges} statistics={profile.statistics ?? null} share />
                <p className="mt-8 text-center font-mono text-[10px] uppercase tracking-[0.2em] text-white/40">
                    Perfil público / estação commit
                </p>
            </main>
        </div>
    );
}
