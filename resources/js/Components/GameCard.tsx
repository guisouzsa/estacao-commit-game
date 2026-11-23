import { motion, useReducedMotion } from 'framer-motion';

const CARD_META: Record<
    string,
    { label: string; image?: string; tone: string }
> = {
    git_login: { label: 'Git Login', image: 'git-login.png', tone: '#464498' },
    git_clone: { label: 'git clone', image: 'git-clone.png', tone: '#464498' },
    git_checkout: {
        label: 'git checkout -b',
        image: 'git-checkout.png',
        tone: '#464498',
    },
    git_pull: { label: 'git pull', image: 'git-pull.png', tone: '#464498' },
    git_add: { label: 'git add .', image: 'git-add.png', tone: '#b4da33' },
    git_commit: {
        label: 'git commit',
        image: 'git-commit.png',
        tone: '#f481c6',
    },
    git_push: { label: 'git push', image: 'git-push.png', tone: '#d01600' },
    pull_request: { label: 'Pull Request', tone: '#b4da33' },
    clear_credentials: {
        label: 'Limpar Credenciais',
        image: 'limpar-credenciais.png',
        tone: '#d01600',
    },
    merge_conflict: {
        label: 'Merge Conflict',
        image: 'merge-conflict.png',
        tone: '#f481c6',
    },
    block_1: {
        label: 'Bloco de Código 1',
        image: 'bloco-1.png',
        tone: '#f481c6',
    },
    block_2: {
        label: 'Bloco de Código 2',
        image: 'bloco-2.png',
        tone: '#464498',
    },
    block_3: {
        label: 'Bloco de Código 3',
        image: 'bloco-3.png',
        tone: '#b4da33',
    },
    block_4: {
        label: 'Bloco de Código 4',
        image: 'bloco-4.png',
        tone: '#d01600',
    },
};

export const cardLabel = (card: string) => CARD_META[card]?.label ?? card;

/** Arte e cores de uma carta (usada também pelo tabuleiro e pelas cartas em voo). */
export const cardMeta = (card: string) =>
    CARD_META[card] ?? { label: card, tone: '#464498' };
export const cardImageUrl = (card: string) => {
    const image = CARD_META[card]?.image;
    return image ? `/images/estacao-commit/corrected/${image}` : null;
};

export default function GameCard({
    card,
    selected = false,
    playable = false,
    compact = false,
    dataKey,
    onClick,
}: {
    card: string;
    selected?: boolean;
    playable?: boolean;
    compact?: boolean;
    /** Identifica a carta na mão (de onde a animação de jogada parte). */
    dataKey?: string;
    onClick?: () => void;
}) {
    const reduced = useReducedMotion();
    const meta = CARD_META[card] ?? { label: card, tone: '#464498' };

    return (
        <motion.button
            type="button"
            data-hand-key={dataKey}
            disabled={!onClick}
            onClick={onClick}
            layout={!reduced}
            exit={
                reduced
                    ? undefined
                    : { opacity: 0, transition: { duration: 0.08 } }
            }
            initial={reduced ? false : { opacity: 0, y: -90, scale: 0.7 }}
            animate={{
                opacity: 1,
                y: selected ? -16 : 0,
                scale: selected ? 1.2 : 1,
                zIndex: selected ? 30 : 0,
            }}
            whileHover={
                !reduced && onClick
                    ? { y: -10, scale: 1.14, zIndex: 20 }
                    : undefined
            }
            transition={{ duration: 0.18 }}
            aria-pressed={selected}
            aria-label={`${meta.label}${playable ? ', pode ser jogada' : ''}`}
            className={`focus-visible:outline-3 relative shrink-0 overflow-hidden rounded-xl border-[3px] bg-[#f3f1ec] text-left shadow-[4px_5px_0_#10100f] focus-visible:outline focus-visible:outline-offset-2 focus-visible:outline-[#b4da33] ${compact ? 'h-24 w-[4.25rem]' : 'h-28 w-[5rem] sm:h-32 sm:w-[5.75rem] lg:h-[8.25rem] lg:w-[5.9rem]'} ${onClick && !playable && !selected ? 'brightness-[.7]' : ''} ${selected ? 'border-[#b4da33]' : playable ? 'border-white' : 'border-[#1d1d1b]'} ${onClick ? 'cursor-pointer' : 'cursor-default'}`}
            style={{
                transformOrigin: '50% 100%',
                boxShadow: selected
                    ? `0 0 0 3px ${meta.tone}, 5px 7px 0 #10100f`
                    : undefined,
            }}
        >
            {meta.image ? (
                <img
                    src={`/images/estacao-commit/corrected/${meta.image}`}
                    alt=""
                    className="size-full object-cover"
                    draggable={false}
                />
            ) : (
                <span
                    className="flex size-full flex-col justify-between p-2"
                    style={{
                        background: `linear-gradient(145deg, ${meta.tone}, #1d1d1b)`,
                    }}
                >
                    <span className="font-mono text-[8px] font-black uppercase tracking-[.16em] text-white/70">
                        GitHub
                    </span>
                    <strong className="text-sm leading-tight text-white">
                        {meta.label}
                    </strong>
                    <span className="font-mono text-[8px] uppercase text-white/65">
                        Concluir contribuição
                    </span>
                </span>
            )}
            {playable && (
                <span
                    className="absolute right-1 top-1 size-3 rounded-full border-2 border-[#1d1d1b] bg-[#b4da33]"
                    title="Jogada disponível"
                />
            )}
        </motion.button>
    );
}
