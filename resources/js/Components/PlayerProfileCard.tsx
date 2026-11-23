export interface PlayerProfile {
    username: string;
    bio?: string | null;
    avatar?: string | null;
    /** Estilo e semente do avatar gerado localmente (estável por jogador). */
    avatar_style?: string | null;
    avatar_seed?: string | null;
    /** Bots são identificados visualmente com a etiqueta BOT. */
    is_bot?: boolean;
    kind?: 'user' | 'guest' | 'bot';
    avatar_position_x?: number;
    avatar_position_y?: number;
    avatar_zoom?: number;
    cover?: string | null;
    cover_position_x?: number;
    cover_position_y?: number;
    profile_frame?: string | null;
    primary_badge?: string | null;
    profile_title?: string | null;
    developer_badge?: string | null;
    created_at?: string | null;
    links?: ProfileLink[];
    badges?: ProfileBadge[];
    achievement_badge?: ProfileBadge | null;
    statistics?: {
        matches: number | null;
        wins: number;
        losses: number;
        win_rate: number | null;
        xp?: number;
        level?: number;
    } | null;
}

export interface ProfileLink {
    platform: string;
    label: string;
    url: string;
    is_public?: boolean;
}
export interface ProfileBadge {
    id: number;
    slug: string;
    name: string;
    description: string;
    category?: string;
    rarity: string;
    image_path?: string | null;
    unlocked?: boolean;
    unlocked_at?: string | null;
}

const badgeLabels: Record<string, string> = {
    terminal: 'Terminal',
    branch: 'Branch',
    commit: 'Commit',
    'pull-request': 'Pull Request',
    git: 'Git',
    github: 'GitHub',
    docker: 'Docker',
    linux: 'Linux',
    'open-source': 'Open Source',
    'code-review': 'Code Review',
    'ship-it': 'Ship It!',
    'coffee-powered': 'Movido a café',
    'bug-squasher': 'Bug Squasher',
    api: 'API',
};

const titleLabels: Record<string, string> = {
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

export default function PlayerProfileCard({
    profile,
    compact = false,
    headerActions,
    children,
}: {
    profile: PlayerProfile;
    compact?: boolean;
    headerActions?: ReactNode;
    children?: ReactNode;
}) {
    const title = profile.profile_title
        ? titleLabels[profile.profile_title]
        : null;

    return (
        <article className="overflow-hidden border-[3px] border-[#1d1d1b] bg-[#f3f1ec] text-[#1d1d1b] shadow-[8px_8px_0_#1d1d1b]">
            <div className="flex items-center justify-between bg-[#1d1d1b] px-4 py-2 font-mono text-[10px] uppercase tracking-[0.18em] text-white">
                <span>Estação Commit</span>
                <span className="text-[#b4da33]">Perfil do jogador</span>
            </div>

            <div className="relative h-40 overflow-hidden bg-[#464498] sm:h-52">
                {profile.cover ? (
                    <img
                        src={profile.cover}
                        alt=""
                        className="h-full w-full object-cover"
                        style={{
                            objectPosition: `${profile.cover_position_x ?? 50}% ${profile.cover_position_y ?? 50}%`,
                        }}
                    />
                ) : (
                    <div className="absolute inset-0 bg-[linear-gradient(135deg,#464498_0_52%,#b4da33_52%_56%,#1d1d1b_56%)]" />
                )}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 opacity-25"
                    style={{
                        backgroundImage:
                            'linear-gradient(90deg, transparent 0 42px, #ffffff 43px, transparent 44px), linear-gradient(0deg, transparent 0 30px, #ffffff 31px, transparent 32px)',
                        backgroundSize: '84px 62px',
                    }}
                />
            </div>
            <div className="relative px-5 pb-6 sm:px-7">
                <div className="relative -mt-12 flex items-start justify-between gap-4 sm:-mt-14">
                    <PlayerAvatar profile={profile} size="lg" />
                    {headerActions && (
                        <div className="mt-14 min-w-0 sm:mt-16">
                            {headerActions}
                        </div>
                    )}
                </div>

                <div className="mt-4 flex min-w-0 flex-wrap items-end justify-between gap-4">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                        <h1 className="break-words text-3xl font-black leading-tight text-[#1d1d1b] sm:text-4xl">
                            @{profile.username}
                        </h1>
                        {title && (
                            <span className="inline-flex border-2 border-[#464498] bg-white px-2.5 py-1 font-mono text-xs font-bold text-[#464498] shadow-[2px_2px_0_#f481c6]">
                                {title}
                            </span>
                        )}
                    </div>
                    {profile.statistics && (
                        <div className="shrink-0" aria-label="Desempenho">
                            <div className="grid grid-cols-3 gap-2">
                                {[
                                    ['Vitórias', profile.statistics.wins],
                                    ['Partidas', profile.statistics.matches],
                                    ['Derrotas', profile.statistics.losses],
                                ].map(([label, value]) => (
                                    <div
                                        key={label}
                                        className="min-w-20 border-2 border-[#1d1d1b] bg-white px-3 py-2 text-center shadow-[3px_3px_0_#b4da33]"
                                    >
                                        <p className="font-mono text-[9px] font-bold uppercase tracking-[0.1em] text-[#68665f]">
                                            {label}
                                        </p>
                                        <p className="mt-1 text-xl font-black leading-none">
                                            {value ?? '—'}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            {typeof profile.statistics.xp === 'number' && (
                                <div className="mt-2 flex items-center justify-between border-2 border-[#464498] bg-[#464498] px-3 py-1.5 text-white shadow-[3px_3px_0_#f481c6]">
                                    <span className="font-mono text-[10px] font-bold uppercase tracking-[0.14em]">
                                        XP
                                    </span>
                                    <strong className="font-mono text-sm">
                                        {profile.statistics.xp.toLocaleString(
                                            'pt-BR',
                                        )}
                                    </strong>
                                </div>
                            )}
                        </div>
                    )}
                    {profile.achievement_badge && (
                        <p className="w-full font-mono text-xs font-bold uppercase text-[#d01600]">
                            Conquista em destaque:{' '}
                            {profile.achievement_badge.name}
                        </p>
                    )}
                </div>
            </div>

            <div
                className={`space-y-5 px-5 py-5 sm:px-7 ${compact ? 'sm:py-5' : 'sm:py-7'}`}
            >
                <div className="space-y-3">
                    <div
                        className="flex flex-wrap items-center gap-2"
                        aria-label="Selos do perfil"
                    >
                        {profile.primary_badge && (
                            <span className="inline-flex items-center gap-1.5 border-2 border-[#1d1d1b] bg-[#b4da33] px-2.5 py-1 font-mono text-[11px] font-bold uppercase text-[#1d1d1b] shadow-[2px_2px_0_#1d1d1b]">
                                <span aria-hidden="true">✦</span>
                                {badgeLabels[profile.primary_badge] ??
                                    profile.primary_badge}
                            </span>
                        )}
                        {profile.developer_badge && (
                            <span className="inline-flex items-center gap-1.5 border-2 border-[#464498] bg-white px-2.5 py-1 font-mono text-[11px] font-bold uppercase text-[#1d1d1b] shadow-[2px_2px_0_#f481c6]">
                                <span
                                    className="text-[#464498]"
                                    aria-hidden="true"
                                >
                                    &lt;/&gt;
                                </span>
                                {profile.developer_badge}
                            </span>
                        )}
                    </div>
                    <p className="whitespace-pre-wrap break-words text-sm leading-6 text-[#393833]">
                        {profile.bio ||
                            'Este jogador ainda não escreveu uma biografia.'}
                    </p>
                    {profile.created_at && (
                        <div className="border-t border-[#1d1d1b]/20 pt-3">
                            <div>
                                <p className="font-mono text-[10px] uppercase tracking-[0.18em] text-[#68665f]">
                                    Na estação desde
                                </p>
                                <p className="mt-1 text-sm font-semibold">
                                    {new Date(
                                        profile.created_at,
                                    ).toLocaleDateString('pt-BR', {
                                        month: 'long',
                                        year: 'numeric',
                                    })}
                                </p>
                            </div>
                        </div>
                    )}
                </div>
                {children}
            </div>
        </article>
    );
}
import { ReactNode } from 'react';
import PlayerAvatar from './PlayerAvatar';
