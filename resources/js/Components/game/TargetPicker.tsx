import VerifiedBadge from '@/Components/VerifiedBadge';
import { cardLabel } from '@/Components/GameCard';
import PlayerAvatar from '@/Components/PlayerAvatar';
import { PATH_LENGTH } from '@/lib/gameGeometry';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';

export type TargetOption = {
    is_verified?: boolean;
    id: number;
    username: string;
    is_bot: boolean;
    hand_count: number;
    step: number;
    blocked: boolean;
    profile: Parameters<typeof PlayerAvatar>[0]['profile'];
};

/** Mesmas regras do servidor (GameEngine::attack), só para explicar por que alguém não pode ser alvo. */
export function targetProblem(
    card: string,
    viewerStep: number,
    target: TargetOption,
): string | null {
    if (card === 'clear_credentials' && (viewerStep >= 3 || target.step >= 3)) {
        return target.step >= 3
            ? 'Já está na Estação de Montagem (imune)'
            : 'Só vale enquanto você está no Treinamento';
    }
    if (card === 'merge_conflict' && target.blocked) {
        return 'Já está resolvendo um Merge Conflict';
    }
    return null;
}

/**
 * Escolha do alvo de um ataque: no celular abre uma folha por baixo com o perfil de cada
 * adversário (foto, nome, cartas, etapa), em vez do seletor nativo do navegador.
 */
export default function TargetPicker({
    card,
    viewerStep,
    options,
    value,
    onChange,
}: {
    card: string;
    viewerStep: number;
    options: TargetOption[];
    value: string;
    onChange: (id: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const reduced = useReducedMotion();
    const trigger = useRef<HTMLButtonElement>(null);
    const selected = options.find((option) => String(option.id) === value);

    useEffect(() => {
        if (!open) return;
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
                trigger.current?.focus();
            }
        };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open]);

    return (
        <>
            <button
                aria-expanded={open}
                aria-haspopup="dialog"
                className="col-span-2 flex min-h-12 items-center gap-3 border-2 border-white/40 bg-[#232323] px-3 text-left"
                onClick={() => setOpen(true)}
                ref={trigger}
                type="button"
            >
                {selected ? (
                    <>
                        <PlayerAvatar profile={selected.profile} size="xs" />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-bold">
                                @{selected.username}{selected.is_verified && <VerifiedBadge className="ml-1" size={15} />}
                            </span>
                            <span className="block font-mono text-[9px] uppercase text-white/55">
                                Alvo escolhido · toque para trocar
                            </span>
                        </span>
                    </>
                ) : (
                    <span className="flex-1 font-mono text-xs font-black uppercase text-[#f481c6]">
                        Escolher alvo
                    </span>
                )}
                <span aria-hidden="true" className="text-white/60">
                    ▾
                </span>
            </button>

            <AnimatePresence>
                {open && (
                    <div className="fixed inset-0 z-[75] flex items-end justify-center sm:items-center">
                        <motion.button
                            animate={{ opacity: 1 }}
                            aria-label="Fechar escolha de alvo"
                            className="absolute inset-0 bg-black/70"
                            exit={{ opacity: 0 }}
                            initial={{ opacity: 0 }}
                            onClick={() => setOpen(false)}
                            type="button"
                        />
                        <motion.div
                            animate={{ y: 0, opacity: 1 }}
                            aria-label={`Escolha o alvo de ${cardLabel(card)}`}
                            aria-modal="true"
                            className="relative max-h-[85dvh] w-full max-w-md overflow-y-auto rounded-t-2xl border-[3px] border-black bg-[#f3f1ec] p-4 pb-[max(1rem,env(safe-area-inset-bottom))] text-[#1d1d1b] shadow-[0_-8px_0_#464498] sm:rounded-2xl"
                            exit={
                                reduced ? { opacity: 0 } : { y: 60, opacity: 0 }
                            }
                            initial={
                                reduced ? { opacity: 0 } : { y: 60, opacity: 0 }
                            }
                            role="dialog"
                            transition={{ duration: 0.18 }}
                        >
                            <div
                                aria-hidden="true"
                                className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-[#1d1d1b]/25 sm:hidden"
                            />
                            <p className="font-mono text-[10px] font-black uppercase tracking-[.16em] text-[#464498]">
                                {cardLabel(card)}
                            </p>
                            <h2 className="text-xl font-black">
                                Quem será o alvo?
                            </h2>

                            <ul className="mt-3 space-y-2">
                                {options.map((option) => {
                                    const problem = targetProblem(
                                        card,
                                        viewerStep,
                                        option,
                                    );
                                    const isSelected =
                                        String(option.id) === value;
                                    return (
                                        <li key={option.id}>
                                            <button
                                                aria-disabled={problem !== null}
                                                className={`flex min-h-16 w-full items-center gap-3 border-[3px] p-2 text-left transition ${
                                                    problem
                                                        ? 'border-[#1d1d1b]/25 bg-[#e8e5dc] opacity-60'
                                                        : isSelected
                                                          ? 'border-[#1d1d1b] bg-[#b4da33] shadow-[3px_3px_0_#464498]'
                                                          : 'border-[#1d1d1b] bg-white hover:bg-[#b4da33]/30'
                                                }`}
                                                disabled={problem !== null}
                                                onClick={() => {
                                                    onChange(String(option.id));
                                                    setOpen(false);
                                                    trigger.current?.focus();
                                                }}
                                                type="button"
                                            >
                                                <PlayerAvatar
                                                    profile={option.profile}
                                                    size="sm"
                                                />
                                                <span className="min-w-0 flex-1">
                                                    <span className="flex items-center gap-1.5">
                                                        <strong className="truncate">
                                                            @{option.username}{option.is_verified && <VerifiedBadge className="ml-1" size={15} />}
                                                        </strong>
                                                        {option.is_bot && (
                                                            <span className="shrink-0 border border-[#1d1d1b] bg-[#b4da33] px-1 font-mono text-[8px] font-black uppercase">
                                                                Bot
                                                            </span>
                                                        )}
                                                    </span>
                                                    <span className="block font-mono text-[10px] uppercase text-[#5b5953]">
                                                        {option.hand_count}{' '}
                                                        cartas · etapa{' '}
                                                        {option.step}/
                                                        {PATH_LENGTH}
                                                        {option.blocked
                                                            ? ' · Merge Conflict'
                                                            : ''}
                                                    </span>
                                                    {problem && (
                                                        <span className="block text-[11px] font-bold text-[#9c1000]">
                                                            {problem}
                                                        </span>
                                                    )}
                                                </span>
                                                {isSelected && (
                                                    <span
                                                        aria-hidden="true"
                                                        className="text-lg font-black"
                                                    >
                                                        ✓
                                                    </span>
                                                )}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>

                            <button
                                className="mt-3 min-h-12 w-full border-2 border-[#1d1d1b] bg-white font-mono text-xs font-black uppercase"
                                onClick={() => setOpen(false)}
                                type="button"
                            >
                                Cancelar
                            </button>
                        </motion.div>
                    </div>
                )}
            </AnimatePresence>
        </>
    );
}
