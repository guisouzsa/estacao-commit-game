import AchievementBadge, {
    BadgeIcon,
    BadgeTier,
    RARITY_LABEL,
    TIER_LABEL,
} from '@/Components/AchievementBadge';
import Modal from '@/Components/Modal';
import type { ProfileBadge } from '@/Components/PlayerProfileCard';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const FEATURED_LIMIT = 3;

const RARITY_CHIP: Record<string, string> = {
    common: 'bg-[#e8e5dc] text-[#1d1d1b]',
    uncommon: 'bg-[#d9f0a0] text-[#1d1d1b]',
    rare: 'bg-[#dcdcfb] text-[#2a2870]',
    epic: 'bg-[#ffe3f3] text-[#8a1f5b]',
    legendary: 'bg-[#1d1d1b] text-[#f0b429]',
};

const tier = (b: ProfileBadge) => (b.tier ?? 'starter') as BadgeTier;
const icon = (b: ProfileBadge) => (b.icon ?? 'star') as BadgeIcon;

function formatDate(value?: string | null) {
    if (!value) return null;
    return new Date(
        value.replace(' ', 'T') +
            (value.includes('Z') || value.includes('+') ? '' : 'Z'),
    ).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Conquistas no perfil: destaques em cima (até 3), depois todas, bloqueadas em cinza com o critério.
 * Cada insígnia abre um detalhe (nome, descrição, critério, raridade, data). Quem vê o próprio perfil escolhe os destaques;
 * o servidor valida (só conquistas desbloqueadas, no máximo FEATURED_LIMIT).
 */
export default function AchievementsPanel({
    badges,
    editable = false,
}: {
    badges: ProfileBadge[];
    editable?: boolean;
}) {
    const [open, setOpen] = useState<ProfileBadge | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [showAll, setShowAll] = useState(false);

    const unlocked = useMemo(() => badges.filter((b) => b.unlocked), [badges]);
    const featured = useMemo(
        () =>
            unlocked
                .filter((b) => b.featured_order)
                .sort(
                    (a, b) => (a.featured_order ?? 0) - (b.featured_order ?? 0),
                ),
        [unlocked],
    );
    const visible = showAll ? badges : badges.slice(0, 6);

    const saveFeatured = (ids: number[]) => {
        setBusy(true);
        setError(null);
        router.put(
            route('profile.achievements.featured'),
            { featured: ids },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) =>
                    setError(
                        String(
                            errors.featured ?? 'Não foi possível salvar agora.',
                        ),
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    const toggleFeatured = (badge: ProfileBadge) => {
        const current = featured.map((b) => b.id);
        const next = current.includes(badge.id)
            ? current.filter((id) => id !== badge.id)
            : [...current, badge.id];
        if (next.length > FEATURED_LIMIT) {
            setError(`Você pode destacar até ${FEATURED_LIMIT} conquistas.`);
            return;
        }
        saveFeatured(next);
    };

    if (badges.length === 0) return null;

    return (
        <section
            aria-labelledby="conquistas-titulo"
            className="border-t-2 border-[#1d1d1b]/15 pt-5"
        >
            <div className="flex flex-wrap items-end justify-between gap-2">
                <h2 className="text-lg font-black" id="conquistas-titulo">
                    Conquistas
                </h2>
                <p className="font-mono text-xs font-bold text-[#464498]">
                    {unlocked.length} de {badges.length}
                </p>
            </div>

            {featured.length > 0 && (
                <ul
                    aria-label="Conquistas em destaque"
                    className="mt-4 grid grid-cols-3 gap-2 sm:gap-4"
                >
                    {featured.map((b) => (
                        <li className="min-w-0" key={b.id}>
                            <button
                                className="group flex w-full flex-col items-center gap-2 border-2 border-[#1d1d1b] bg-white px-2 py-3 text-center shadow-[3px_3px_0_#1d1d1b] transition hover:-translate-y-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]"
                                onClick={() => setOpen(b)}
                                type="button"
                            >
                                <AchievementBadge
                                    icon={icon(b)}
                                    size="lg"
                                    tier={tier(b)}
                                />
                                <span className="line-clamp-2 text-xs font-black leading-4">
                                    {b.name}
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <ul
                className="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6"
                aria-label="Todas as conquistas"
            >
                {visible.map((b) => (
                    <li className="min-w-0" key={b.id}>
                        <button
                            aria-label={`${b.name}: ${b.unlocked ? 'desbloqueada' : 'bloqueada'}. Ver detalhes`}
                            className="flex w-full flex-col items-center gap-1 border-2 border-transparent p-1.5 text-center transition hover:border-[#1d1d1b]/30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]"
                            onClick={() => setOpen(b)}
                            type="button"
                        >
                            <AchievementBadge
                                icon={icon(b)}
                                locked={!b.unlocked}
                                size="md"
                                tier={tier(b)}
                            />
                            <span
                                className={`line-clamp-2 text-[11px] font-bold leading-4 ${b.unlocked ? '' : 'text-[#68665f]'}`}
                            >
                                {b.name}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
            {badges.length > 6 && (
                <button
                    aria-expanded={showAll}
                    className="mt-3 font-mono text-xs font-bold uppercase text-[#464498] underline underline-offset-4"
                    onClick={() => setShowAll((v) => !v)}
                    type="button"
                >
                    {showAll ? 'Mostrar menos' : `Ver todas (${badges.length})`}
                </button>
            )}
            {error && !open && (
                <p
                    className="mt-3 text-sm font-semibold text-[#9c1000]"
                    role="alert"
                >
                    {error}
                </p>
            )}

            <Modal
                maxWidth="md"
                onClose={() => setOpen(null)}
                show={open !== null}
            >
                {open && (
                    <div className="bg-[#f3f1ec] p-6 text-center text-[#1d1d1b]">
                        <div className="flex justify-center">
                            <AchievementBadge
                                icon={icon(open)}
                                locked={!open.unlocked}
                                size="lg"
                                tier={tier(open)}
                            />
                        </div>
                        <h3 className="mt-4 text-2xl font-black">
                            {open.name}
                        </h3>
                        <p className="mt-1">
                            <span
                                className={`inline-block px-2 py-0.5 font-mono text-[10px] font-black uppercase ${RARITY_CHIP[open.rarity] ?? RARITY_CHIP.common}`}
                            >
                                {RARITY_LABEL[open.rarity] ?? open.rarity} ·{' '}
                                {TIER_LABEL[open.tier ?? 'starter']}
                            </span>
                        </p>
                        <p className="mt-3 text-sm leading-6 text-[#5b5953]">
                            {open.description}
                        </p>
                        <dl className="mt-4 space-y-1 border-t-2 border-[#1d1d1b]/15 pt-3 text-left text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="font-bold">Como conseguir</dt>
                                <dd className="text-right">
                                    {open.criterion ?? open.description}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="font-bold">Situação</dt>
                                <dd className="text-right">
                                    {open.unlocked
                                        ? `Conquistada em ${formatDate(open.unlocked_at) ?? '—'}`
                                        : 'Bloqueada'}
                                </dd>
                            </div>
                        </dl>
                        {error && (
                            <p
                                className="mt-3 text-sm font-semibold text-[#9c1000]"
                                role="alert"
                            >
                                {error}
                            </p>
                        )}
                        <div className="mt-5 flex flex-wrap justify-center gap-2">
                            {editable && open.unlocked && (
                                <button
                                    aria-pressed={Boolean(open.featured_order)}
                                    className="min-h-11 border-2 border-[#1d1d1b] bg-[#464498] px-4 font-mono text-xs font-black uppercase text-white shadow-[3px_3px_0_#1d1d1b] disabled:opacity-50"
                                    disabled={busy}
                                    onClick={() => toggleFeatured(open)}
                                    type="button"
                                >
                                    {busy
                                        ? 'Salvando…'
                                        : open.featured_order
                                          ? 'Remover dos destaques'
                                          : 'Destacar no perfil'}
                                </button>
                            )}
                            <button
                                className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-black uppercase"
                                onClick={() => setOpen(null)}
                                type="button"
                            >
                                Fechar
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </section>
    );
}
