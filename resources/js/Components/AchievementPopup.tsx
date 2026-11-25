import AchievementBadge, {
    BadgeIcon,
    BadgeTier,
    RARITY_LABEL,
} from '@/Components/AchievementBadge';
import { Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type Popup = {
    id: string;
    slug: string;
    name: string;
    description: string;
    rarity: string;
    tier: string;
    icon: string;
};

/**
 * Aviso de "conquista desbloqueada" durante a sessão. Vem do servidor (notificações não lidas do tipo conquista);
 * ao exibir, marca como lida, então recarregar a página nunca repete o popup. A notificação continua na central.
 * Uma falha aqui (rede, requisição) só deixa o aviso sem aparecer: a conquista já foi concedida e gravada no servidor.
 */
export default function AchievementPopup() {
    const raw = (usePage().props.auth as { achievement_popups?: Popup[] })
        .achievement_popups;
    const popups = useMemo(() => raw ?? [], [raw]);
    const shown = useRef<Set<string>>(new Set());
    const [current, setCurrent] = useState<Popup | null>(null);

    // pega o próximo que ainda não foi exibido nesta sessão do navegador
    useEffect(() => {
        if (current) return;
        const next = popups.find((p) => !shown.current.has(p.id));
        if (next) {
            shown.current.add(next.id);
            setCurrent(next);
            try {
                router.post(
                    route('notifications.read', next.id),
                    {},
                    {
                        preserveScroll: true,
                        preserveState: true,
                        only: ['auth'],
                    },
                );
            } catch {
                /* o aviso não pode quebrar a página */
            }
        }
    }, [popups, current]);

    const close = useCallback(() => setCurrent(null), []);

    useEffect(() => {
        if (!current) return;
        const timer = window.setTimeout(close, 9000);
        const onKey = (event: KeyboardEvent) =>
            event.key === 'Escape' && close();
        window.addEventListener('keydown', onKey);
        return () => {
            window.clearTimeout(timer);
            window.removeEventListener('keydown', onKey);
        };
    }, [current, close]);

    if (!current) return null;

    return (
        <div
            aria-live="polite"
            className="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex justify-center px-3 sm:bottom-6"
            role="status"
        >
            <div className="achievement-pop pointer-events-auto relative flex w-full max-w-md items-center gap-4 border-[3px] border-[#1d1d1b] bg-[#1d1d1b] p-4 text-white shadow-[6px_6px_0_#b4da33]">
                <AchievementBadge
                    icon={current.icon as BadgeIcon}
                    size="lg"
                    tier={current.tier as BadgeTier}
                />
                <div className="min-w-0 flex-1">
                    <p className="font-mono text-[10px] font-black uppercase tracking-[.18em] text-[#b4da33]">
                        Conquista desbloqueada
                    </p>
                    <h2 className="mt-1 text-lg font-black leading-tight">
                        {current.name}
                    </h2>
                    <p className="mt-1 text-xs leading-5 text-white/75">
                        {current.description}
                    </p>
                    <p className="mt-2 text-xs font-bold text-[#f481c6]">
                        {RARITY_LABEL[current.rarity] ?? current.rarity} ·
                        Parabéns, é assim que se constrói uma reputação!
                    </p>
                    <Link
                        className="mt-2 inline-block text-xs font-bold text-[#b4da33] underline underline-offset-4"
                        href={route('profile.edit')}
                        onClick={close}
                    >
                        Ver no meu perfil
                    </Link>
                </div>
                <button
                    aria-label="Fechar aviso de conquista"
                    className="absolute right-1 top-1 grid size-9 place-items-center text-xl text-white/70 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#b4da33]"
                    onClick={close}
                    type="button"
                >
                    ×
                </button>
            </div>
        </div>
    );
}
