import ConfirmDialog from '@/Components/ConfirmDialog';
import BrandLogo from '@/Components/BrandLogo';
import NotificationBell from '@/Components/NotificationBell';
import PlayerAvatar from '@/Components/PlayerAvatar';
import PlayerSearch from '@/Components/PlayerSearch';
import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

const navItem =
    'inline-flex min-h-10 items-center border-b-[3px] px-2 font-mono text-[11px] font-bold uppercase tracking-wide transition sm:px-3';

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { user, kind, unread_notifications: unreadCount = 0 } = usePage().props.auth;
    // Visitante: joga sem conta. Rankings, busca e perfil são só de contas permanentes.
    const isGuest = kind === 'guest';
    const [leaving, setLeaving] = useState(false);
    const [leavingBusy, setLeavingBusy] = useState(false);
    const dashboard = route().current('dashboard');
    const profile = route().current('profile.*');
    const ranking = route().current('rankings');

    return (
        <div className="min-h-screen w-full bg-[#e8e5dc]">
            <header className="sticky top-0 z-30 border-b-[3px] border-[#b4da33] bg-[#1d1d1b] text-white shadow-[0_4px_0_rgba(29,29,27,.12)]">
                <div className="flex w-full flex-wrap items-center gap-3 px-3 py-3 sm:px-4 lg:gap-5 lg:px-6">
                    <Link
                        aria-label="Estação Commit — início"
                        className="order-1 flex shrink-0 items-center gap-2"
                        href={route('dashboard')}
                    >
                        <BrandLogo compact />
                    </Link>

                    <nav
                        aria-label="Navegação principal"
                        className="order-2 hidden shrink-0 items-center gap-1 md:flex"
                    >
                        <Link
                            aria-current={dashboard ? 'page' : undefined}
                            className={`${navItem} ${dashboard ? 'border-[#b4da33] text-[#b4da33]' : 'border-transparent text-white/75 hover:border-[#f481c6] hover:text-white'}`}
                            href={route('dashboard')}
                        >
                            Jogar
                        </Link>
                        <Link
                            className={`${navItem} border-transparent text-white/75 hover:border-[#f481c6] hover:text-white`}
                            href={`${route('dashboard')}#criar-sala`}
                        >
                            Criar sala
                        </Link>
                        <Link
                            className={`${navItem} border-transparent text-white/75 hover:border-[#f481c6] hover:text-white`}
                            href={`${route('dashboard')}#entrar-convite`}
                        >
                            Convite
                        </Link>
                        {!isGuest && (
                        <Link
                            aria-current={ranking ? 'page' : undefined}
                            className={`${navItem} gap-1.5 ${ranking ? 'border-[#b4da33] text-[#b4da33]' : 'border-transparent text-white/75 hover:border-[#f481c6] hover:text-white'}`}
                            href={route('rankings')}
                        >
                            <svg
                                aria-hidden="true"
                                className="size-4"
                                fill="none"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="1.8"
                                viewBox="0 0 24 24"
                            >
                                <path d="M8 21h8m-4-4v4m-5-17h10v5a5 5 0 0 1-10 0V4Zm0 2H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4" />
                                <path d="M9 8h6" />
                            </svg>
                            Rankings
                        </Link>
                        )}
                    </nav>

                    {!isGuest && (
                        <div className="order-3 min-w-0 max-w-[420px] basis-full md:flex-1 md:basis-auto">
                            <PlayerSearch />
                        </div>
                    )}

                    <div className="order-2 ml-auto flex shrink-0 items-center gap-2 md:order-4">
                        {isGuest ? (
                            <>
                                <div
                                    className="flex min-h-14 items-center gap-2 border-2 border-white/25 px-2"
                                    title="Você está jogando como visitante"
                                >
                                    <PlayerAvatar profile={user} size="xs" />
                                    <div className="hidden min-w-0 leading-tight sm:block">
                                        <strong className="block max-w-[9rem] truncate text-xs">
                                            {user.username}
                                        </strong>
                                        <span className="font-mono text-[9px] font-bold uppercase text-[#b4da33]">
                                            Visitante
                                        </span>
                                    </div>
                                </div>
                                <Link
                                    className="hidden min-h-11 items-center border-2 border-[#b4da33] px-3 font-mono text-xs font-bold uppercase text-[#b4da33] transition hover:bg-[#b4da33] hover:text-[#1d1d1b] focus:outline-none focus:ring-2 focus:ring-[#b4da33] sm:inline-flex"
                                    href={route('register')}
                                >
                                    Criar conta
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => setLeaving(true)}
                                    className="min-h-11 border-2 border-white/25 px-3 font-mono text-xs font-bold uppercase text-white/85 transition hover:border-[#f481c6] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#b4da33]"
                                >
                                    Sair
                                </button>
                            </>
                        ) : (
                            <>
                                <NotificationBell unreadCount={unreadCount} />
                                <Link
                                    aria-label="Meu perfil"
                                    className={`grid size-14 place-items-center border-2 transition focus:outline-none focus:ring-2 focus:ring-[#b4da33] ${
                                        profile
                                            ? 'border-[#b4da33] bg-white/10'
                                            : 'border-white/25 hover:border-[#b4da33]'
                                    }`}
                                    href={route('profile.edit')}
                                    title="Meu perfil"
                                >
                                    <PlayerAvatar profile={user} size="sm" />
                                </Link>
                                <Link
                                    as="button"
                                    className="min-h-11 border-2 border-white/25 px-3 font-mono text-xs font-bold uppercase text-white/85 transition hover:border-[#f481c6] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#b4da33]"
                                    href={route('logout')}
                                    method="post"
                                >
                                    Sair
                                </Link>
                            </>
                        )}
                    </div>

                    <nav
                        aria-label="Navegação principal"
                        className="order-5 flex w-full items-center gap-1 overflow-x-auto border-t border-white/15 pt-2 md:hidden"
                    >
                        <Link
                            aria-current={dashboard ? 'page' : undefined}
                            className={`shrink-0 border-b-[3px] px-3 py-2 font-mono text-[11px] font-bold uppercase ${dashboard ? 'border-[#b4da33] text-[#b4da33]' : 'border-transparent text-white/75'}`}
                            href={route('dashboard')}
                        >
                            Jogar
                        </Link>
                        <Link
                            className="shrink-0 border-b-[3px] border-transparent px-3 py-2 font-mono text-[11px] font-bold uppercase text-white/75"
                            href={`${route('dashboard')}#criar-sala`}
                        >
                            Criar sala
                        </Link>
                        <Link
                            className="shrink-0 border-b-[3px] border-transparent px-3 py-2 font-mono text-[11px] font-bold uppercase text-white/75"
                            href={`${route('dashboard')}#entrar-convite`}
                        >
                            Entrar com convite
                        </Link>
                        {!isGuest && (
                        <Link
                            aria-current={ranking ? 'page' : undefined}
                            className={`inline-flex shrink-0 items-center gap-1.5 border-b-[3px] px-3 py-2 font-mono text-[11px] font-bold uppercase ${ranking ? 'border-[#b4da33] text-[#b4da33]' : 'border-transparent text-white/75'}`}
                            href={route('rankings')}
                        >
                            <svg
                                aria-hidden="true"
                                className="size-4"
                                fill="none"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="1.8"
                                viewBox="0 0 24 24"
                            >
                                <path d="M8 21h8m-4-4v4m-5-17h10v5a5 5 0 0 1-10 0V4Zm0 2H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4" />
                                <path d="M9 8h6" />
                            </svg>
                            Rankings
                        </Link>
                        )}
                    </nav>
                </div>
            </header>

            <ConfirmDialog
                show={leaving}
                title="Sair do jogo?"
                confirmLabel="Sair do jogo"
                busyLabel="Saindo..."
                eyebrow="Visitante"
                busy={leavingBusy}
                onConfirm={() => {
                    setLeavingBusy(true);
                    router.post(route('guest.leave'), {}, {
                        onFinish: () => {
                            setLeavingBusy(false);
                            setLeaving(false);
                        },
                    });
                }}
                onClose={() => setLeaving(false)}
            >
                Seu perfil temporário será apagado, junto com as salas que você
                criou. Para guardar nome, avatar e histórico, crie uma conta
                antes de sair.
            </ConfirmDialog>

            {header && (
                <section className="bg-[#f3f1ec]">
                    <div className="mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </section>
            )}
            <main className="min-h-[calc(100vh-4.25rem)] w-full">
                {children}
            </main>
        </div>
    );
}
