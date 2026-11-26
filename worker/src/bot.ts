// Decisão dos bots. Espelho exato de app/Services/BotBrain.php (paridade testada por tests/parity).
// O bot decide SÓ com o que um jogador da mesa poderia saber: a própria mão e o estado público
// (etapa e percurso de cada um, quem está bloqueado). Nunca olha a mão dos outros nem o baralho.
// Determinístico: mesma situação, mesma jogada (sem sorteio aqui).

import { REQUIRED, type ActionName, type ActionPayload, type MatchState, type PlayerState } from './engine.ts';

export type BotDecision = { action: ActionName; payload: ActionPayload };

/** Primeiro passo da Integração (antes dele é o Treinamento: login, clone, checkout). */
const TRAINING_END = 3;

/** Comportamento anterior (mantido só para comparação nas simulações). */
export function chooseClassicBotAction(player: PlayerState): BotDecision {
  if ((player.pending_discard ?? 0) > 0) return { action: 'discard_excess', payload: { card: player.hand[0] ?? '' } };
  const required: string | undefined = REQUIRED[player.step];
  if (required?.startsWith('block_')) return { action: 'play_block', payload: {} };
  if (required !== undefined && player.hand.includes(required)) return { action: 'play_card', payload: { card: required } };
  return { action: 'exchange', payload: { card: player.hand[0] ?? '' } };
}

/**
 * Quanto uma carta da mão vale para quem a tem (maior = guardar). Cartas que o percurso não usa mais,
 * ou cópias além do que ainda falta, valem menos que qualquer carta necessária; entre as necessárias,
 * vale mais a que será usada antes.
 */
export function cardValue(player: PlayerState, index: number): number {
  const card = player.hand[index];
  const upcoming: string[] = REQUIRED.slice(player.step).filter((c) => !c.startsWith('block_'));
  const stillNeeded = upcoming.filter((c) => c === card).length;
  // Quantas cópias iguais aparecem ANTES desta na mão: a 2ª cópia só vale se o percurso usa duas.
  const copyRank = player.hand.slice(0, index).filter((c) => c === card).length;

  if (card === 'merge_conflict') return 30; // útil a qualquer momento contra quem lidera
  if (card === 'clear_credentials') return player.step < TRAINING_END ? 25 : 0;
  if (copyRank >= stillNeeded) return card === 'git_pull' && player.step < TRAINING_END ? 5 : 1;

  const distance = upcoming.indexOf(card);
  return 100 - distance;
}

/** Índice da carta menos valiosa (empate: a mais antiga da mão). */
export function leastValuable(player: PlayerState): number {
  let worst = 0;
  for (let i = 1; i < player.hand.length; i++) {
    if (cardValue(player, i) < cardValue(player, worst)) worst = i;
  }
  return worst;
}

/**
 * Adversário mais adiantado (dado público: a etapa de cada um aparece no tabuleiro). Empate: quem joga
 * primeiro depois de mim. A distância na ordem da mesa não favorece nenhum assento fixo.
 */
function leader(m: MatchState, me: string): [string, PlayerState] | null {
  const mySeat = m.players[me].seat;
  const order = (seat: number) => (seat - mySeat + 4) % 4; // 1 = o próximo a jogar
  let best: [string, PlayerState] | null = null;
  for (const [id, p] of Object.entries(m.players)) {
    if (id === me) continue;
    if (!best || p.step > best[1].step || (p.step === best[1].step && order(p.seat) < order(best[1].seat))) best = [id, p];
  }
  return best;
}

/**
 * Desvantagem dos bots quando há pessoas na mesa (decisão de produto: o jogador real deve vencer cerca de 90%
 * das partidas contra bots). Chance, em %, de o bot "hesitar" numa vez em que poderia avançar: em vez de jogar
 * a carta ou o bloco da etapa, ele troca uma carta. Indexada pelo número de pessoas na mesa (1 a 3).
 * Calibrada com worker/sim/balance.ts (grupo "handicap"). Mesmos números em app/Services/BotBrain.php.
 */
export const BOT_HANDICAP: Record<number, number> = { 1: 67, 2: 50, 3: 40 };

/**
 * Sorteio determinístico (0 a 99) da hesitação: depende só do turno, do assento e da etapa, então o motor PHP e
 * o do Worker chegam ao mesmo resultado (paridade) e ninguém consegue influenciá-lo pelo navegador.
 */
export function handicapRoll(turn: number, seat: number, step: number): number {
  // Combina os três números e embaralha os bits (finalizador do MurmurHash3), tudo em 32 bits sem sinal.
  let h = (turn * 0x9e3779b1 + seat * 0x85ebca77 + step * 0xc2b2ae3d) % 4294967296;
  h ^= h >>> 16;
  h = Math.imul(h, 0x85ebca6b) >>> 0;
  h ^= h >>> 13;
  h = Math.imul(h, 0xc2b2ae35) >>> 0;
  h ^= h >>> 16;
  return (h >>> 0) % 100;
}

export function chooseBotAction(m: MatchState, me: string, humans = 0, handicap: Record<number, number> = BOT_HANDICAP): BotDecision {
  const player = m.players[me];

  if ((player.pending_discard ?? 0) > 0) {
    return { action: 'discard_excess', payload: { card: player.hand[leastValuable(player)] } };
  }

  const required: string | undefined = REQUIRED[player.step];
  const canAdvance = required !== undefined && (required.startsWith('block_') || player.hand.includes(required));
  if (canAdvance && humans > 0 && player.hand.length > 0 && handicapRoll(m.turn_number, player.seat, player.step) < (handicap[Math.min(humans, 3)] ?? 0)) {
    return { action: 'exchange', payload: { card: player.hand[leastValuable(player)] } };
  }
  if (required?.startsWith('block_')) return { action: 'play_block', payload: {} };
  if (required !== undefined && player.hand.includes(required)) return { action: 'play_card', payload: { card: required } };

  // Ataques só contra quem está realmente na frente (informação pública) e quando a regra permite.
  const top = leader(m, me);
  if (top) {
    const [targetId, target] = top;
    if (player.hand.includes('merge_conflict') && !target.blocked && target.step >= 12 && target.step > player.step) {
      return { action: 'play_card', payload: { card: 'merge_conflict', target_id: targetId } };
    }
    if (player.hand.includes('clear_credentials') && player.step < TRAINING_END && target.step < TRAINING_END && target.step >= 2 && target.step > player.step) {
      return { action: 'play_card', payload: { card: 'clear_credentials', target_id: targetId } };
    }
  }

  return { action: 'exchange', payload: { card: player.hand[leastValuable(player)] } };
}
