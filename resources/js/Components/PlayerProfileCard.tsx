export interface PlayerProfile {
    username: string;
    display_name: string;
    bio?: string | null;
    avatar?: string | null;
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
    statistics?: { matches: number; wins: number; losses: number; win_rate: number; xp?: number; level?: number } | null;
}

export interface ProfileLink { platform: string; label: string; url: string; is_public?: boolean }
export interface ProfileBadge { id: number; slug: string; name: string; description: string; category?: string; rarity: string; image_path?: string | null; unlocked?: boolean; unlocked_at?: string | null }

const frameStyles: Record<string, string> = {
    standard: 'border-[#1d1d1b] shadow-[0_0_0_4px_#1d1d1b]',
    lime: 'border-[#b4da33] shadow-[0_0_0_4px_#b4da33]',
    violet: 'border-[#464498] shadow-[0_0_0_4px_#464498]',
    pink: 'border-[#f481c6] shadow-[0_0_0_4px_#f481c6]',
    red: 'border-[#d01600] shadow-[0_0_0_4px_#d01600]',
};

const badgeLabels: Record<string, string> = {
    terminal: 'Terminal',
    branch: 'Branch',
    commit: 'Commit',
    'pull-request': 'Pull Request',
};

const titleLabels: Record<string, string> = {
    explorer: 'Explorador',
    builder: 'Montador',
    collaborator: 'Colaborador',
};

export default function PlayerProfileCard({
    profile,
    compact = false,
}: {
    profile: PlayerProfile;
    compact?: boolean;
}) {
    const frame =
        frameStyles[profile.profile_frame ?? 'standard'] ??
        frameStyles.standard;
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
                    <img src={profile.cover} alt="" className="h-full w-full object-cover" style={{objectPosition: `${profile.cover_position_x ?? 50}% ${profile.cover_position_y ?? 50}%`}} />
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
                <div className="relative -mt-12 flex flex-col gap-4 sm:-mt-14 sm:flex-row sm:items-end">
                    <div
                        className={`h-24 w-24 shrink-0 rounded-full border-[3px] bg-[#f3f1ec] p-1 ${frame}`}
                    >
                        <div className="flex h-full w-full items-center justify-center overflow-hidden rounded-full bg-[#464498] text-3xl font-bold text-white">
                            {profile.avatar ? (
                                <img
                                    src={profile.avatar}
                                    alt={`Avatar de @${profile.username}`}
                                    className="h-full w-full object-cover"
                                    style={{
                                        objectPosition: `${profile.avatar_position_x ?? 50}% ${profile.avatar_position_y ?? 50}%`,
                                        transform: `scale(${(profile.avatar_zoom ?? 100) / 100})`,
                                    }}
                                />
                            ) : (
                                <span aria-hidden="true">
                                    {profile.username
                                        .trim()
                                        .charAt(0)
                                        .toUpperCase() || '?'}
                                </span>
                            )}
                        </div>
                    </div>

                    <div className="min-w-0">
                        <p className="font-mono text-xs font-bold uppercase tracking-[0.16em] text-[#464498]">
                            jogador
                        </p>
                        <h1 className="mt-1 break-words text-3xl font-black leading-tight text-[#1d1d1b] sm:text-4xl">
                            @{profile.username}
                        </h1>
                        {title && (
                            <p className="mt-2 inline-flex border-2 border-[#464498] bg-white px-2 py-1 font-mono text-xs text-[#464498]">
                                {title}
                            </p>
                        )}
                        {profile.achievement_badge && <p className="mt-2 font-mono text-xs font-bold uppercase text-[#d01600]">Conquista em destaque: {profile.achievement_badge.name}</p>}
                    </div>

                </div>
            </div>

            <div
                className={`space-y-5 px-5 py-5 sm:px-7 ${compact ? 'sm:py-5' : 'sm:py-7'}`}
            >
                <div className="flex flex-wrap gap-2">
                    {profile.developer_badge && (
                        <span className="inline-flex items-center gap-2 border-2 border-[#464498] bg-white px-3 py-1.5 font-mono text-xs font-bold uppercase text-[#1d1d1b] shadow-[3px_3px_0_#f481c6]">
                            <span className="text-[#464498]" aria-hidden="true">
                                &lt;/&gt;
                            </span>
                            {profile.developer_badge}
                        </span>
                    )}
                </div>

                <p className="min-h-12 whitespace-pre-wrap break-words text-sm leading-6 text-[#393833]">
                    {profile.bio ||
                        'Este jogador ainda não escreveu uma biografia.'}
                </p>

                <div className="flex flex-wrap items-end justify-between gap-3 border-t border-[#1d1d1b]/20 pt-4">
                    <div>
                        <p className="font-mono text-[10px] uppercase tracking-[0.18em] text-[#68665f]">
                            Identificador público
                        </p>
                        <p className="mt-1 font-mono text-sm font-bold">
                            @{profile.username}
                        </p>
                    </div>
                    {profile.created_at && (
                        <div className="text-left sm:text-right">
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
                    )}
                </div>
            </div>
        </article>
    );
}
