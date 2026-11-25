// Dica de troca para quem joga: quais cartas da SUA mão o seu percurso não usa mais.
// É a mesma regra que os bots usam para escolher o que trocar (app/Services/BotBrain.php e
// worker/src/bot.ts), calculada só com a sua mão e a sua etapa. Não revela nada dos outros
// jogadores nem do baralho. Sem dependências de React: testável com o runner do Node.

import { PATH_SEQUENCE } from './gameGeometry.ts';

/** Primeiro passo depois do Treinamento (login, clone, checkout). */
const TRAINING_END = 3;

/**
 * Índices das cartas "sobrando": cópias além do que o percurso ainda vai pedir, ou cartas que não
 * servem mais na etapa atual. Ataques e cartas ainda necessárias nunca são marcados.
 */
export function spareCardIndexes(hand: string[], step: number): Set<number> {
    const upcoming = PATH_SEQUENCE.slice(step).filter(
        (card) => !card.startsWith('block_'),
    );
    const spare = new Set<number>();

    hand.forEach((card, index) => {
        if (card === 'merge_conflict') return;
        if (card === 'clear_credentials') {
            if (step >= TRAINING_END) spare.add(index);
            return;
        }
        // git pull ainda vale no Treinamento (compra duas cartas).
        if (card === 'git_pull' && step < TRAINING_END) return;
        const stillNeeded = upcoming.filter((c) => c === card).length;
        const copyRank = hand.slice(0, index).filter((c) => c === card).length;
        if (copyRank >= stillNeeded) spare.add(index);
    });

    return spare;
}
