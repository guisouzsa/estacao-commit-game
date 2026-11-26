import BackLink from '@/Components/BackLink';
import AchievementBadge, {
    BadgeIcon,
    BadgeTier,
} from '@/Components/AchievementBadge';
import ListSkeleton from '@/Components/ListSkeleton';
import PaginationNav from '@/Components/PaginationNav';
import PlayerAvatar from '@/Components/PlayerAvatar';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Deferred, Head, Link, router } from '@inertiajs/react';

type Item = {
    id: string;
    message: string;
    username: string | null;
    avatar_seed: string | null;
    achievement?: {
        slug: string;
        name: string;
        description: string;
        rarity: string;
        tier: string;
        icon: string;
    } | null;
    read: boolean;
    created_at: string;
};

type Page = {
    data: Item[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const relative = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

function timeAgo(iso: string): string {
    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const steps: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, size] of steps) {
        if (Math.abs(seconds) >= size) {
            return relative.format(Math.round(seconds / size), unit);
        }
    }
    return 'agora';
}

/** Central de notificações: só o que vale ser consultado depois. */
export default function Index({
    notifications,
    unread,
}: {
    notifications?: Page;
    unread: number;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Notificações" />
            <main className="min-h-[calc(100vh-4rem)] bg-[#e8e5dc] px-3 py-6 sm:px-4 sm:py-10 lg:px-6 text-[#1d1d1b]">
                <div className="w-full">
                    <BackLink className="mb-4" href={route('dashboard')} />
                    <header className="flex flex-wrap items-end justify-between gap-3 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#464498]">
                        <div>
                            <p className="font-mono text-[10px] font-black uppercase tracking-[.16em] text-[#464498]">
                                Central
                            </p>
                            <h1 className="mt-1 text-2xl font-black sm:text-3xl">
                                Notificações
                            </h1>
                            <p
                                className="mt-1 text-sm text-[#5b5953]"
                                aria-live="polite"
                            >
                                {unread
                                    ? `${unread} ${unread === 1 ? 'não lida' : 'não lidas'}`
                                    : 'Tudo em dia.'}
                            </p>
                        </div>
                        <button
                            type="button"
                            disabled={unread === 0}
                            onClick={() =>
                                router.post(
                                    route('notifications.read-all'),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="min-h-10 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-[11px] font-black uppercase hover:bg-[#b4da33] disabled:opacity-40 disabled:hover:bg-white"
                        >
                            Marcar todas como lidas
                        </button>
                    </header>

                    <Deferred
                        data="notifications"
                        fallback={
                            <ListSkeleton label="Carregando notificações" />
                        }
                    >
                        {notifications && notifications.data.length ? (
                            <ul className="mt-4 grid border-[3px] border-[#1d1d1b] bg-white shadow-[4px_4px_0_#1d1d1b] lg:grid-cols-2">
                                {notifications.data.map((item) => (
                                    <li
                                        key={item.id}
                                        className={`flex items-center gap-3 border-b border-[#1d1d1b]/15 p-3 lg:odd:border-r ${item.read ? '' : 'bg-[#b4da33]/15'}`}
                                    >
                                        {item.achievement ? (
                                            <AchievementBadge
                                                icon={
                                                    item.achievement
                                                        .icon as BadgeIcon
                                                }
                                                size="sm"
                                                tier={
                                                    item.achievement
                                                        .tier as BadgeTier
                                                }
                                            />
                                        ) : (
                                            <PlayerAvatar
                                                profile={{
                                                    username:
                                                        item.username ??
                                                        'jogador',
                                                    avatar_seed:
                                                        item.avatar_seed,
                                                }}
                                                size="sm"
                                            />
                                        )}
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm">
                                                {item.username ? (
                                                    <Link
                                                        className="font-bold hover:underline"
                                                        href={route(
                                                            'profile.public',
                                                            item.username,
                                                        )}
                                                    >
                                                        {item.message}
                                                    </Link>
                                                ) : (
                                                    item.message
                                                )}
                                            </p>
                                            <p className="mt-0.5 font-mono text-[10px] uppercase text-[#68665f]">
                                                {timeAgo(item.created_at)}
                                                {!item.read && (
                                                    <span className="ml-2 text-[#464498]">
                                                        · nova
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                        {!item.read && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'notifications.read',
                                                            item.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                className="min-h-10 border-2 border-[#1d1d1b] px-2 font-mono text-[10px] font-black uppercase hover:bg-white"
                                            >
                                                Marcar como lida
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-4 border-l-4 border-[#464498] bg-white p-4 text-sm">
                                Você ainda não tem notificações. Quando alguém
                                começar a seguir você, o aviso aparece aqui.
                            </p>
                        )}

                        {notifications ? (
                            <PaginationNav page={notifications} />
                        ) : null}
                    </Deferred>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
