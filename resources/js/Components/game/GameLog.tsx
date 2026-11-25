import PlayerAvatar from '@/Components/PlayerAvatar';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { memo, useState } from 'react';

export type LogItem = {
    id: string;
    participant_id?: number;
    message: string;
};

export type LogActor = {
    username: string;
    is_viewer: boolean;
    profile: Parameters<typeof PlayerAvatar>[0]['profile'];
};

const PREVIEW = 3;

/**
 * Registro compacto: mostra só as 3 jogadas mais recentes e abre o resto sob demanda.
 * Cada linha diz QUEM fez a ação (avatar + nome); eventos do sistema não têm autor.
 * Os dados são os eventos reais da partida (vêm do servidor, já sem repetição por id).
 */
export default memo(function GameLog({
    items,
    actors,
}: {
    items: LogItem[];
    actors: Record<number, LogActor>;
}) {
    const [expanded, setExpanded] = useState(false);
    const reduced = useReducedMotion();
    const shown = expanded ? items : items.slice(0, PREVIEW);
    const hidden = items.length - PREVIEW;

    return (
        <div>
            <ol
                aria-live="polite"
                className={
                    expanded
                        ? 'max-h-64 space-y-1 overflow-y-auto pr-1'
                        : 'space-y-1'
                }
            >
                <AnimatePresence initial={false}>
                    {shown.map((item) => {
                        const actor = item.participant_id
                            ? actors[item.participant_id]
                            : undefined;
                        return (
                            <motion.li
                                animate={{ opacity: 1, y: 0 }}
                                className="flex items-center gap-2 rounded-md bg-white/[.04] px-2 py-1"
                                exit={{ opacity: 0 }}
                                initial={
                                    reduced ? false : { opacity: 0, y: -6 }
                                }
                                key={item.id}
                                layout={!reduced}
                            >
                                {actor ? (
                                    <span className="shrink-0">
                                        <PlayerAvatar
                                            profile={actor.profile}
                                            size="xs"
                                        />
                                    </span>
                                ) : (
                                    <span
                                        aria-hidden="true"
                                        className="mt-1.5 size-2 shrink-0 rounded-full bg-[#464498]"
                                    />
                                )}
                                <p className="min-w-0 text-[11px] leading-4 text-white/75">
                                    {actor && (
                                        <strong className="mr-1 font-bold text-white">
                                            {actor.is_viewer
                                                ? 'Você'
                                                : `@${actor.username}`}
                                        </strong>
                                    )}
                                    {item.message}
                                </p>
                            </motion.li>
                        );
                    })}
                </AnimatePresence>
            </ol>
            {items.length === 0 && (
                <p className="text-[11px] text-white/50">
                    Nada aconteceu ainda.
                </p>
            )}
            {hidden > 0 && (
                <button
                    aria-expanded={expanded}
                    className="mt-1.5 min-h-9 w-full rounded-md border border-white/15 py-1 font-mono text-[10px] font-bold uppercase text-white/70 hover:border-[#b4da33] hover:text-white"
                    onClick={() => setExpanded((value) => !value)}
                    type="button"
                >
                    {expanded ? 'Mostrar menos' : `Ver mais (${hidden})`}
                </button>
            )}
        </div>
    );
});
