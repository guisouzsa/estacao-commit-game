import AchievementBadge, { BadgeIcon, BadgeTier, TIER_LABEL } from '@/Components/AchievementBadge';
import type { AchievementDisplayMode, ProfileBadge } from '@/Components/PlayerProfileCard';
import { Listbox, ListboxButton, ListboxOption, ListboxOptions } from '@headlessui/react';

/** Escolha das conquistas do perfil enquanto edita (salva junto com o resto, pelo "Salvar alterações"). */
export type AchievementDraft = {
    mode: AchievementDisplayMode;
    featured: number[];
    dirty: boolean;
};

const tier = (b: ProfileBadge) => (b.tier ?? 'starter') as BadgeTier;
const icon = (b: ProfileBadge) => (b.icon ?? 'star') as BadgeIcon;

/**
 * Seletor de conquistas no mesmo estilo dos seletores de selo e título (OptionPicker), mas com escolha múltipla:
 * a pessoa liga "Exibir conquistas no perfil" e escolhe até `limit` conquistas desbloqueadas. Só as escolhidas
 * aparecem, dentro do card do perfil. Desligado = nenhuma conquista aparece.
 */
export default function AchievementPicker({
    badges,
    enabled,
    selected,
    limit,
    onChange,
}: {
    badges: ProfileBadge[];
    enabled: boolean;
    selected: number[];
    limit: number;
    onChange: (next: { enabled: boolean; selected: number[] }) => void;
}) {
    const unlocked = badges.filter((badge) => badge.unlocked);
    const chosen = selected
        .map((id) => unlocked.find((badge) => badge.id === id))
        .filter((badge): badge is ProfileBadge => Boolean(badge));
    const full = selected.length >= limit;

    return (
        <div>
            <label className="flex cursor-pointer items-start gap-3 text-sm font-bold">
                <input
                    checked={enabled}
                    className="mt-0.5 size-4 border-2 border-[#1d1d1b] text-[#464498] focus:ring-[#464498]"
                    disabled={unlocked.length === 0}
                    onChange={(event) => onChange({ enabled: event.target.checked, selected })}
                    type="checkbox"
                />
                <span>
                    Exibir conquistas no perfil
                    <span className="mt-0.5 block text-xs font-normal text-[#68665f]">
                        {unlocked.length === 0
                            ? 'Você ainda não desbloqueou conquistas.'
                            : `Escolha até ${limit} conquistas desbloqueadas. Elas aparecem dentro do seu card.`}
                    </span>
                </span>
            </label>

            {enabled && unlocked.length > 0 && (
                <Listbox
                    multiple
                    onChange={(ids: number[]) => onChange({ enabled, selected: ids.slice(0, limit) })}
                    value={selected}
                >
                    <div className="relative mt-3">
                        <Listbox.Label className="sr-only">Conquistas em destaque</Listbox.Label>
                        <ListboxButton className="group flex min-h-12 w-full items-center gap-3 border-2 border-[#1d1d1b] bg-white px-3 py-2 text-left transition hover:bg-[#f3f1ec] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#464498] focus-visible:ring-offset-2 data-[open]:shadow-[3px_3px_0_#b4da33]">
                            <span aria-hidden="true" className="h-6 w-1.5 shrink-0 bg-[#f0b429]" />
                            <span className="flex min-w-0 flex-1 items-center gap-1.5">
                                {chosen.length === 0 ? (
                                    <span className="text-sm font-black">Escolher conquistas</span>
                                ) : (
                                    chosen.map((badge) => (
                                        <AchievementBadge icon={icon(badge)} key={badge.id} size="sm" tier={tier(badge)} />
                                    ))
                                )}
                            </span>
                            <span className="shrink-0 border border-[#1d1d1b]/40 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-[#5b5953]">
                                {selected.length}/{limit}
                            </span>
                            <svg
                                aria-hidden="true"
                                className="size-4 shrink-0 text-[#5b5953] transition group-data-[open]:rotate-180"
                                fill="none"
                                stroke="currentColor"
                                strokeLinecap="square"
                                strokeWidth="2.5"
                                viewBox="0 0 24 24"
                            >
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </ListboxButton>

                        <ListboxOptions
                            anchor="bottom start"
                            className="z-50 mt-1 max-h-72 w-[var(--button-width)] overflow-auto border-2 border-[#1d1d1b] bg-white p-1 shadow-[4px_4px_0_#1d1d1b] focus:outline-none"
                        >
                            {unlocked.map((badge) => {
                                const isChosen = selected.includes(badge.id);
                                const blocked = full && !isChosen;

                                return (
                                    <ListboxOption
                                        className="group flex cursor-pointer items-center gap-3 px-2 py-2 data-[disabled]:cursor-not-allowed data-[disabled]:opacity-40 data-[focus]:bg-[#f3f1ec] data-[selected]:bg-[#eef5d2]"
                                        disabled={blocked}
                                        key={badge.id}
                                        value={badge.id}
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="h-6 w-1.5 shrink-0 bg-transparent group-data-[selected]:bg-[#464498]"
                                        />
                                        <AchievementBadge icon={icon(badge)} size="sm" tier={tier(badge)} />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-black">{badge.name}</span>
                                            <span className="block truncate text-xs text-[#68665f]">
                                                {badge.criterion ?? badge.description}
                                            </span>
                                        </span>
                                        <span className="shrink-0 border border-[#1d1d1b]/40 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-[#5b5953]">
                                            {TIER_LABEL[badge.tier ?? 'starter']}
                                        </span>
                                        <span aria-hidden="true" className="w-4 shrink-0 text-center font-black text-[#464498]">
                                            {isChosen ? '✓' : ''}
                                        </span>
                                    </ListboxOption>
                                );
                            })}
                        </ListboxOptions>
                    </div>
                </Listbox>
            )}
            {enabled && full && (
                <p className="mt-2 text-xs font-bold text-[#464498]">Limite de {limit} conquistas atingido.</p>
            )}
        </div>
    );
}
