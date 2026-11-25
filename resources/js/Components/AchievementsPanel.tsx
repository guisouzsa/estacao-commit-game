import AchievementBadge, {
    BadgeIcon,
    BadgeTier,
    RARITY_LABEL,
    TIER_LABEL,
} from '@/Components/AchievementBadge';
import Modal from '@/Components/Modal';
import type {
    AchievementDisplayMode,
    ProfileBadge,
} from '@/Components/PlayerProfileCard';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const FEATURED_LIMIT = 5;
const MODES: Array<{
    value: AchievementDisplayMode;
    label: string;
    description: string;
}> = [
    {
        value: 'none',
        label: 'Nenhuma',
        description: 'Não mostrar conquistas no perfil.',
    },
    {
        value: 'custom',
        label: 'Até 5',
        description: 'Escolher manualmente até cinco conquistas.',
    },
    {
        value: 'all',
        label: 'Todas',
        description: 'Mostrar todas, inclusive as desbloqueadas no futuro.',
    },
];
const RARITY_CHIP: Record<string, string> = {
    common: 'bg-[#e8e5dc] text-[#1d1d1b]',
    uncommon: 'bg-[#d9f0a0] text-[#1d1d1b]',
    rare: 'bg-[#dcdcfb] text-[#2a2870]',
    epic: 'bg-[#ffe3f3] text-[#8a1f5b]',
    legendary: 'bg-[#1d1d1b] text-[#f0b429]',
};
const tier = (badge: ProfileBadge) => (badge.tier ?? 'starter') as BadgeTier;
const icon = (badge: ProfileBadge) => (badge.icon ?? 'star') as BadgeIcon;

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

function BadgeCard({
    badge,
    selected = false,
    onClick,
}: {
    badge: ProfileBadge;
    selected?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            aria-label={`${badge.name}: ${badge.unlocked ? 'desbloqueada' : 'bloqueada'}. Ver detalhes`}
            className={`relative flex w-full flex-col items-center gap-2 border-2 p-2.5 text-center transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498] ${selected ? 'border-[#464498] bg-[#eeeeff] shadow-[3px_3px_0_#464498]' : 'border-[#1d1d1b]/20 bg-white hover:border-[#1d1d1b]'}`}
            onClick={onClick}
            type="button"
        >
            {selected && (
                <span className="absolute right-1.5 top-1.5 rounded-full bg-[#464498] px-1.5 py-0.5 font-mono text-[8px] font-black uppercase text-white">
                    Exibida
                </span>
            )}
            <AchievementBadge
                icon={icon(badge)}
                locked={!badge.unlocked}
                size="md"
                tier={tier(badge)}
            />
            <span
                className={`line-clamp-2 text-[11px] font-bold leading-4 ${badge.unlocked ? '' : 'text-[#68665f]'}`}
            >
                {badge.name}
            </span>
        </button>
    );
}

export default function AchievementsPanel({
    badges,
    editable = false,
    displayMode = 'none',
}: {
    badges: ProfileBadge[];
    editable?: boolean;
    displayMode?: AchievementDisplayMode;
}) {
    const unlocked = useMemo(
        () => badges.filter((badge) => badge.unlocked),
        [badges],
    );
    const initialSelected = useMemo(
        () =>
            unlocked
                .filter((badge) => badge.featured_order)
                .sort(
                    (a, b) => (a.featured_order ?? 0) - (b.featured_order ?? 0),
                )
                .map((badge) => badge.id),
        [unlocked],
    );
    const [mode, setMode] = useState<AchievementDisplayMode>(displayMode);
    const [selected, setSelected] = useState<number[]>(initialSelected);
    const [open, setOpen] = useState<ProfileBadge | null>(null);
    const [busy, setBusy] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const displayed = useMemo(() => {
        if (mode === 'none') return [];
        if (mode === 'all') return unlocked;
        return selected
            .map((id) => unlocked.find((badge) => badge.id === id))
            .filter((badge): badge is ProfileBadge => Boolean(badge));
    }, [mode, selected, unlocked]);

    if (badges.length === 0) return null;
    if (!editable) {
        const publicBadges = badges
            .filter((badge) => badge.displayed)
            .sort((a, b) =>
                displayMode === 'custom'
                    ? (a.featured_order ?? 0) - (b.featured_order ?? 0)
                    : 0,
            );
        if (publicBadges.length === 0) return null;
        return (
            <section className="border-t-2 border-[#1d1d1b]/15 pt-5">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <h2 className="text-lg font-black">
                        Conquistas em destaque
                    </h2>
                    <p className="font-mono text-xs font-bold text-[#464498]">
                        {publicBadges.length}{' '}
                        {publicBadges.length === 1 ? 'conquista' : 'conquistas'}
                    </p>
                </div>
                <ul className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {publicBadges.map((badge) => (
                        <li key={badge.id}>
                            <BadgeCard
                                badge={badge}
                                onClick={() => setOpen(badge)}
                            />
                        </li>
                    ))}
                </ul>
                <BadgeDetails badge={open} onClose={() => setOpen(null)} />
            </section>
        );
    }

    const toggle = (badge: ProfileBadge) => {
        if (!badge.unlocked || mode !== 'custom') return;
        setSaved(false);
        setError(null);
        setSelected((current) => {
            if (current.includes(badge.id))
                return current.filter((id) => id !== badge.id);
            if (current.length >= FEATURED_LIMIT) {
                setError(
                    `Você pode selecionar até ${FEATURED_LIMIT} conquistas.`,
                );
                return current;
            }
            return [...current, badge.id];
        });
    };
    const save = () => {
        setBusy(true);
        setSaved(false);
        setError(null);
        router.put(
            route('profile.achievements.featured'),
            { mode, featured: mode === 'custom' ? selected : [] },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setSaved(true),
                onError: (errors) =>
                    setError(
                        String(
                            errors.featured ??
                                errors.mode ??
                                'Não foi possível salvar agora.',
                        ),
                    ),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <section className="border-t-2 border-[#1d1d1b]/15 pt-5">
            <div className="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 className="text-lg font-black">
                        Conquistas e destaques
                    </h2>
                    <p className="mt-1 text-sm text-[#68665f]">
                        Desbloquear não publica uma conquista automaticamente.
                    </p>
                </div>
                <p className="font-mono text-xs font-bold text-[#464498]">
                    {unlocked.length} de {badges.length} desbloqueadas
                </p>
            </div>
            <fieldset className="mt-5">
                <legend className="font-mono text-xs font-black uppercase tracking-[0.12em]">
                    Exibição no perfil
                </legend>
                <div className="mt-3 grid gap-3 md:grid-cols-3">
                    {MODES.map((option) => (
                        <label
                            className={`cursor-pointer border-2 p-3 ${mode === option.value ? 'border-[#464498] bg-[#eeeeff] shadow-[3px_3px_0_#464498]' : 'border-[#1d1d1b]/30 bg-white'}`}
                            key={option.value}
                        >
                            <span className="flex items-center gap-2 font-black">
                                <input
                                    checked={mode === option.value}
                                    name="achievement-display-mode"
                                    onChange={() => {
                                        setMode(option.value);
                                        setSaved(false);
                                        setError(null);
                                    }}
                                    type="radio"
                                    value={option.value}
                                />
                                {option.label}
                            </span>
                            <span className="mt-1 block text-xs leading-5 text-[#68665f]">
                                {option.description}
                            </span>
                        </label>
                    ))}
                </div>
            </fieldset>
            <div className="mt-6 border-2 border-dashed border-[#464498] bg-[#f8f8ff] p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h3 className="font-black">Prévia do perfil</h3>
                    <span className="font-mono text-xs font-bold text-[#464498]">
                        {mode === 'custom'
                            ? `${displayed.length} de ${FEATURED_LIMIT} selecionadas`
                            : mode === 'all'
                              ? `${displayed.length} conquistas exibidas`
                              : 'Nenhuma conquista exibida'}
                    </span>
                </div>
                {displayed.length > 0 ? (
                    <div className="mt-4 flex flex-wrap gap-3">
                        {displayed.map((badge) => (
                            <div
                                className="flex w-24 flex-col items-center gap-1 text-center"
                                key={badge.id}
                            >
                                <AchievementBadge
                                    icon={icon(badge)}
                                    size="md"
                                    tier={tier(badge)}
                                />
                                <span className="line-clamp-2 text-[10px] font-bold">
                                    {badge.name}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="mt-3 text-sm text-[#68665f]">
                        O perfil ficará sem conquistas em destaque.
                    </p>
                )}
            </div>
            <AchievementCollection
                badges={unlocked}
                heading="Desbloqueadas"
                mode={mode}
                onOpen={setOpen}
                selected={selected}
                toggle={toggle}
            />
            <AchievementCollection
                badges={badges.filter((badge) => !badge.unlocked)}
                heading="Bloqueadas"
                mode={mode}
                onOpen={setOpen}
                selected={selected}
                toggle={toggle}
            />
            {error && (
                <p
                    className="mt-4 text-sm font-bold text-[#9c1000]"
                    role="alert"
                >
                    {error}
                </p>
            )}
            {saved && (
                <p
                    className="mt-4 text-sm font-bold text-[#247000]"
                    role="status"
                >
                    Preferência de conquistas salva.
                </p>
            )}
            <button
                className="mt-5 min-h-11 border-2 border-[#1d1d1b] bg-[#b4da33] px-5 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b] disabled:opacity-50"
                disabled={busy}
                onClick={save}
                type="button"
            >
                {busy ? 'Salvando…' : 'Salvar destaques'}
            </button>
            <BadgeDetails
                badge={open}
                mode={mode}
                onClose={() => setOpen(null)}
                onToggle={open?.unlocked ? () => toggle(open) : undefined}
                selected={open ? selected.includes(open.id) : false}
            />
        </section>
    );
}

function AchievementCollection({
    badges,
    heading,
    mode,
    onOpen,
    selected,
    toggle,
}: {
    badges: ProfileBadge[];
    heading: string;
    mode: AchievementDisplayMode;
    onOpen: (badge: ProfileBadge) => void;
    selected: number[];
    toggle: (badge: ProfileBadge) => void;
}) {
    if (badges.length === 0) return null;
    return (
        <div className="mt-6">
            <h3 className="font-black">
                {heading}{' '}
                <span className="font-mono text-xs text-[#68665f]">
                    ({badges.length})
                </span>
            </h3>
            <ul className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                {badges.map((badge) => (
                    <li key={badge.id}>
                        <BadgeCard
                            badge={badge}
                            onClick={() => onOpen(badge)}
                            selected={
                                badge.unlocked &&
                                (mode === 'all' ||
                                    (mode === 'custom' &&
                                        selected.includes(badge.id)))
                            }
                        />
                        {mode === 'custom' && badge.unlocked && (
                            <button
                                aria-label={`${selected.includes(badge.id) ? 'Remover' : 'Adicionar'} ${badge.name} dos destaques do perfil`}
                                className="mt-2 min-h-9 w-full border border-[#464498] bg-white px-1 text-xs font-bold text-[#464498]"
                                onClick={() => toggle(badge)}
                                type="button"
                            >
                                {selected.includes(badge.id)
                                    ? 'Remover destaque'
                                    : 'Adicionar destaque'}
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function BadgeDetails({
    badge,
    mode,
    onClose,
    onToggle,
    selected = false,
}: {
    badge: ProfileBadge | null;
    mode?: AchievementDisplayMode;
    onClose: () => void;
    onToggle?: () => void;
    selected?: boolean;
}) {
    return (
        <Modal maxWidth="md" onClose={onClose} show={badge !== null}>
            {badge && (
                <div className="bg-[#f3f1ec] p-6 text-center text-[#1d1d1b]">
                    <div className="flex justify-center">
                        <AchievementBadge
                            icon={icon(badge)}
                            locked={!badge.unlocked}
                            size="lg"
                            tier={tier(badge)}
                        />
                    </div>
                    <h3 className="mt-4 text-2xl font-black">{badge.name}</h3>
                    <span
                        className={`mt-2 inline-block px-2 py-0.5 font-mono text-[10px] font-black uppercase ${RARITY_CHIP[badge.rarity] ?? RARITY_CHIP.common}`}
                    >
                        {RARITY_LABEL[badge.rarity] ?? badge.rarity} ·{' '}
                        {TIER_LABEL[badge.tier ?? 'starter']}
                    </span>
                    <p className="mt-3 text-sm leading-6 text-[#5b5953]">
                        {badge.description}
                    </p>
                    <dl className="mt-4 space-y-2 border-t-2 border-[#1d1d1b]/15 pt-3 text-left text-sm">
                        <div className="flex justify-between gap-3">
                            <dt className="font-bold">Como conseguir</dt>
                            <dd className="text-right">
                                {badge.criterion ?? badge.description}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="font-bold">Situação</dt>
                            <dd className="text-right">
                                {badge.unlocked
                                    ? `Conquistada em ${formatDate(badge.unlocked_at) ?? '—'}`
                                    : 'Bloqueada'}
                            </dd>
                        </div>
                    </dl>
                    <div className="mt-5 flex flex-wrap justify-center gap-2">
                        {onToggle && mode === 'custom' && (
                            <button
                                className="min-h-11 border-2 border-[#1d1d1b] bg-[#464498] px-4 font-mono text-xs font-black uppercase text-white shadow-[3px_3px_0_#1d1d1b]"
                                onClick={() => {
                                    onToggle();
                                    onClose();
                                }}
                                type="button"
                            >
                                {selected
                                    ? 'Remover dos destaques'
                                    : 'Adicionar aos destaques'}
                            </button>
                        )}
                        <button
                            className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-black uppercase"
                            onClick={onClose}
                            type="button"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            )}
        </Modal>
    );
}
