import PlayerAvatar from '@/Components/PlayerAvatar';
import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Item = {
    id: string;
    message: string;
    username: string | null;
    avatar_seed: string | null;
    read: boolean;
    created_at: string;
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

/**
 * Sino da navbar com as notificações num popover (sem trocar de tela).
 * A lista só é buscada quando o popover abre (nada de consulta periódica); o contador
 * do sino continua vindo das props compartilhadas.
 */
export default function NotificationBell({ unreadCount }: { unreadCount: number }) {
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<Item[] | null>(null);
    const [failed, setFailed] = useState(false);
    const root = useRef<HTMLDivElement>(null);
    const button = useRef<HTMLButtonElement>(null);

    const load = useCallback(async () => {
        setFailed(false);
        try {
            const response = await fetch(route('notifications.recent'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('falhou');
            setItems(((await response.json()) as { data: Item[] }).data);
        } catch {
            setFailed(true);
        }
    }, []);

    useEffect(() => {
        if (open) void load();
    }, [open, load]);

    useEffect(() => {
        if (!open) return;
        const onPointer = (event: MouseEvent | TouchEvent) => {
            if (!root.current?.contains(event.target as Node)) setOpen(false);
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                button.current?.focus();
            }
        };
        document.addEventListener('mousedown', onPointer);
        document.addEventListener('touchstart', onPointer);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onPointer);
            document.removeEventListener('touchstart', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const markRead = (id: string) => {
        setItems((current) => current?.map((item) => (item.id === id ? { ...item, read: true } : item)) ?? null);
        router.post(route('notifications.read', id), {}, { preserveScroll: true, preserveState: true, only: ['auth'] });
    };

    const markAll = () => {
        setItems((current) => current?.map((item) => ({ ...item, read: true })) ?? null);
        router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true, only: ['auth', 'flash'] });
    };

    return (
        <div className="relative" ref={root}>
            <button
                ref={button}
                type="button"
                aria-haspopup="dialog"
                aria-expanded={open}
                aria-label={unreadCount ? `Notificações, ${unreadCount} não lidas` : 'Notificações'}
                className="relative grid size-11 place-items-center border-2 border-white/25 transition hover:border-[#b4da33] focus:outline-none focus:ring-2 focus:ring-[#b4da33]"
                onClick={() => setOpen((value) => !value)}
                title="Notificações"
            >
                <svg aria-hidden="true" className="size-5" fill="none" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" viewBox="0 0 24 24">
                    <path d="M6 9a6 6 0 1 1 12 0c0 6 2 7 2 7H4s2-1 2-7Zm4 10a2 2 0 0 0 4 0" />
                </svg>
                {unreadCount > 0 && (
                    <span className="absolute -right-1.5 -top-1.5 min-w-5 border-2 border-[#1d1d1b] bg-[#f481c6] px-1 text-center font-mono text-[10px] font-black leading-4 text-[#1d1d1b]">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </button>

            {open && (
                <div
                    role="dialog"
                    aria-label="Notificações"
                    className="toast-in fixed inset-x-3 top-[4.5rem] z-[60] border-[3px] border-[#1d1d1b] bg-[#f3f1ec] text-[#1d1d1b] shadow-[6px_6px_0_#464498] sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-96"
                >
                    <div className="flex items-center justify-between gap-2 border-b-[3px] border-[#1d1d1b] bg-[#1d1d1b] px-3 py-2 text-white">
                        <strong className="font-mono text-[11px] uppercase tracking-wide">Notificações</strong>
                        <button
                            type="button"
                            disabled={unreadCount === 0}
                            onClick={markAll}
                            className="font-mono text-[10px] font-bold uppercase text-[#b4da33] hover:underline disabled:opacity-40"
                        >
                            Marcar todas como lidas
                        </button>
                    </div>

                    <div className="max-h-[min(24rem,60dvh)] overflow-y-auto">
                        {failed ? (
                            <p className="p-4 text-sm">
                                Não conseguimos carregar agora.{' '}
                                <button type="button" onClick={() => void load()} className="font-bold underline">
                                    Tentar de novo
                                </button>
                            </p>
                        ) : items === null ? (
                            <p className="p-4 text-sm text-[#5b5953]" aria-live="polite">Carregando…</p>
                        ) : items.length === 0 ? (
                            <p className="p-4 text-sm text-[#5b5953]">
                                Nada por aqui ainda. Quando alguém começar a seguir você, o aviso aparece neste lugar.
                            </p>
                        ) : (
                            <ul>
                                {items.map((item) => (
                                    <li
                                        key={item.id}
                                        className={`flex items-center gap-3 border-b border-[#1d1d1b]/15 p-3 last:border-0 ${item.read ? '' : 'bg-[#b4da33]/20'}`}
                                    >
                                        <PlayerAvatar
                                            profile={{ username: item.username ?? 'jogador', avatar_seed: item.avatar_seed }}
                                            size="xs"
                                        />
                                        <div className="min-w-0 flex-1">
                                            {item.username ? (
                                                <Link
                                                    className="block text-sm font-bold hover:underline"
                                                    href={route('profile.public', item.username)}
                                                    onClick={() => {
                                                        if (!item.read) markRead(item.id);
                                                        setOpen(false);
                                                    }}
                                                >
                                                    {item.message}
                                                </Link>
                                            ) : (
                                                <p className="text-sm">{item.message}</p>
                                            )}
                                            <p className="mt-0.5 font-mono text-[10px] uppercase text-[#68665f]">
                                                {timeAgo(item.created_at)}
                                                {!item.read && <span className="ml-2 text-[#464498]">· nova</span>}
                                            </p>
                                        </div>
                                        {!item.read && (
                                            <button
                                                type="button"
                                                aria-label="Marcar como lida"
                                                title="Marcar como lida"
                                                onClick={() => markRead(item.id)}
                                                className="grid size-8 shrink-0 place-items-center border-2 border-[#1d1d1b] font-black hover:bg-white"
                                            >
                                                ✓
                                            </button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <Link
                        href={route('notifications.index')}
                        onClick={() => setOpen(false)}
                        className="block border-t-[3px] border-[#1d1d1b] bg-white px-3 py-2 text-center font-mono text-[11px] font-black uppercase hover:bg-[#b4da33]"
                    >
                        Ver todas
                    </Link>
                </div>
            )}
        </div>
    );
}
