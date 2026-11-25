import AchievementPopup from '@/Components/AchievementPopup';
import BrandLogo from '@/Components/BrandLogo';
import ConfirmDialog from '@/Components/ConfirmDialog';
import NotificationBell from '@/Components/NotificationBell';
import PlayerAvatar from '@/Components/PlayerAvatar';
import PlayerSearch from '@/Components/PlayerSearch';
import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

export default function Authenticated({
    header,
    children,
    immersive = false,
}: PropsWithChildren<{ header?: ReactNode; immersive?: boolean }>) {
    // Na partida o celular precisa de toda a altura: a barra do site some (< lg). A própria
    // partida oferece "Sair" e o Registro.
    const [searchOpen, setSearchOpen] = useState(false);
    const {
        user,
        kind,
        unread_notifications: unreadCount = 0,
    } = usePage().props.auth;
    // Visitante: joga sem conta. Rankings, busca e perfil são só de contas permanentes.
    const isGuest = kind === 'guest';
    const signedIn = Boolean(user);
    const [leaving, setLeaving] = useState(false);
    const [leavingBusy, setLeavingBusy] = useState(false);
    const dashboard = route().current('dashboard');
    const profile = route().current('profile.*');
    const ranking = route().current('rankings');

    return (
        <div
            className={`flex min-h-[100dvh] w-full flex-col bg-[#e8e5dc] ${immersive ? 'max-lg:h-[100dvh] max-lg:overflow-hidden' : ''}`}
        >
            <header
                className={`sticky top-0 z-30 border-b-[3px] border-[#b4da33] bg-[#1d1d1b] text-white shadow-[0_4px_0_rgba(29,29,27,.12)] ${immersive ? 'max-lg:hidden' : ''}`}
            >
                <div className="flex w-full flex-wrap items-center gap-x-3 gap-y-2 px-3 py-3 sm:px-4 lg:gap-5 lg:px-6">
                    <Link
                        aria-label="Estação Commit — início"
                        className="order-1 flex shrink-0 items-center gap-2"
                        href={signedIn ? route('dashboard') : '/'}
                    >
                        <BrandLogo compact />
                    </Link>
                    {/* Visitante (celular): um botão colorido ao lado da logo. Quem tem conta usa a barra abaixo. */}
                    {isGuest && (
                        <Link
                            aria-current={dashboard ? 'page' : undefined}
                            className="order-1 inline-flex min-h-11 items-center border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-xs font-black uppercase text-[#1d1d1b] shadow-[3px_3px_0_#f481c6] md:hidden"
                            href={route('dashboard')}
                        >
                            Inicial
                        </Link>
                    )}

                    {/* Desktop e celular usam os mesmos atalhos compactos (Inicial, Rankings, busca, avisos e perfil), sem a faixa de links de texto. */}

                    {signedIn && !isGuest && (
                        <div
                            className={`order-3 min-w-0 max-w-[420px] basis-full max-md:order-[9] md:flex-1 md:basis-auto ${searchOpen ? '' : 'max-md:hidden'}`}
                        >
                            <PlayerSearch />
                        </div>
                    )}

                    {/* Conta no celular: linha 1 = logo + Sair; linha 2 = Inicial, Rankings, Busca, Avisos e, por último, o Perfil. */}
                    {signedIn && !isGuest && (
                        <>
                            <div
                                aria-hidden="true"
                                className="order-3 hidden h-0 basis-full max-md:block"
                            />
                            <Link
                                aria-current={dashboard ? 'page' : undefined}
                                className={`inline-flex min-h-11 items-center gap-1.5 border-2 px-3 font-mono text-xs font-black uppercase transition focus:outline-none focus:ring-2 focus:ring-[#b4da33] max-md:order-4 md:order-2 ${dashboard ? 'border-[#b4da33] text-[#b4da33]' : 'border-white/25 hover:border-[#b4da33]'}`}
                                href={route('dashboard')}
                            >
                                <svg
                                    aria-hidden="true"
                                    className="size-4"
                                    fill="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                                Inicial
                            </Link>
                            <Link
                                aria-current={ranking ? 'page' : undefined}
                                aria-label="Rankings"
                                className={`grid size-11 place-items-center border-2 transition focus:outline-none focus:ring-2 focus:ring-[#b4da33] max-md:order-5 md:order-2 ${ranking ? 'border-[#b4da33] text-[#b4da33]' : 'border-white/25 hover:border-[#b4da33]'}`}
                                href={route('rankings')}
                            >
                                <svg
                                    aria-hidden="true"
                                    className="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="1.8"
                                    viewBox="0 0 24 24"
                                >
                                    <path d="M8 21h8m-4-4v4m-5-17h10v5a5 5 0 0 1-10 0V4Zm0 2H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4" />
                                </svg>
                            </Link>
                        </>
                    )}
                    <div
                        className={
                            isGuest
                                ? 'order-2 ml-auto flex shrink-0 items-center gap-2 md:order-4'
                                : 'contents md:order-4 md:ml-auto md:flex md:shrink-0 md:items-center md:gap-2'
                        }
                    >
                        {signedIn && !isGuest && (
                            <button
                                aria-expanded={searchOpen}
                                aria-label="Buscar jogadores"
                                className="grid size-11 place-items-center border-2 border-white/25 transition hover:border-[#b4da33] focus:outline-none focus:ring-2 focus:ring-[#b4da33] max-md:order-6 md:hidden"
                                onClick={() => setSearchOpen((open) => !open)}
                                type="button"
                            >
                                <svg
                                    aria-hidden="true"
                                    className="size-5"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeLinecap="round"
                                    strokeWidth="1.8"
                                    viewBox="0 0 24 24"
                                >
                                    <circle cx="11" cy="11" r="6" />
                                    <path d="m20 20-4-4" />
                                </svg>
                            </button>
                        )}
                        {!user ? (
                            <Link
                                className="inline-flex min-h-11 items-center border-2 border-[#b4da33] px-3 font-mono text-xs font-bold uppercase text-[#b4da33]"
                                href={route('login')}
                            >
                                Entrar
                            </Link>
                        ) : isGuest ? (
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
                                <div className="max-md:order-7">
                                    <NotificationBell
                                        unreadCount={unreadCount}
                                    />
                                </div>
                                <Link
                                    aria-label="Meu perfil"
                                    className={`grid size-14 place-items-center border-2 transition focus:outline-none focus:ring-2 focus:ring-[#b4da33] max-md:order-8 max-md:ml-auto max-md:size-12 ${
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
                                    className="min-h-11 border-2 border-white/25 px-3 font-mono text-xs font-bold uppercase text-white/85 transition hover:border-[#f481c6] hover:text-white focus:outline-none focus:ring-2 focus:ring-[#b4da33] max-md:order-2 max-md:ml-auto"
                                    href={route('logout')}
                                    method="post"
                                >
                                    Sair
                                </Link>
                            </>
                        )}
                    </div>
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
                    router.post(
                        route('guest.leave'),
                        {},
                        {
                            onFinish: () => {
                                setLeavingBusy(false);
                                setLeaving(false);
                            },
                        },
                    );
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
            <main
                className={`flex min-h-0 w-full flex-1 flex-col ${immersive ? 'max-lg:overflow-y-auto' : ''}`}
            >
                {children}
            </main>
            {signedIn && !isGuest && <AchievementPopup />}
        </div>
    );
}
