// Simulador de equilíbrio: roda partidas completas no motor REAL do Worker (src/engine.ts, que tem
// paridade verificada com o GameEngine.php) com políticas de jogo diferentes por assento.
//
// Uso (Node 22.18+ / 24):  node --experimental-transform-types sim/balance.ts [partidas] [semente] [grupo]
//   grupo: "bots" (estratégias), "draw" (abordagens de distribuição A/B/C) ou "all" (padrão)
// É uma ferramenta de análise: não roda em produção nem nos testes automáticos.

import { REQUIRED, act, createMatch, type MatchState, type Participant, type PlayerState } from '../src/engine.ts';
import { chooseBotAction, chooseClassicBotAction, leastValuable, type BotDecision } from '../src/bot.ts';

type Rng = () => number;

/** Gerador com semente (mulberry32): resultados reproduzíveis. */
function seeded(seed: number): Rng {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

export type Policy = {
  name: string;
  /** Decide a jogada (com a mesma informação que um jogador teria: a própria mão e o estado público). */
  decide: (m: MatchState, me: string, rng: Rng) => BotDecision;
  /** Chance de perder a vez por tempo (humano distraído/lento). */
  timeoutRate?: number;
  /** Quantas das PRIMEIRAS vezes próprias são perdidas (ex.: lendo o tutorial com o relógio correndo). */
  lostOpeningTurns?: number;
};

const classic: Policy = { name: 'robô atual', decide: (m, me) => chooseClassicBotAction(m.players[me]) };
const improved: Policy = { name: 'robô novo', decide: (m, me) => chooseBotAction(m, me) };
/** Robô novo sem usar ataques (só a escolha de cartas). */
const improvedNoAttack: Policy = {
  name: 'robô novo sem ataques',
  decide: (m, me) => {
    const d = chooseBotAction(m, me);
    if (d.action === 'play_card' && (d.payload.card === 'merge_conflict' || d.payload.card === 'clear_credentials')) {
      const p = m.players[me];
      return { action: 'exchange', payload: { card: p.hand[leastValuable(p)] } };
    }
    return d;
  },
};

/** Humano cuidadoso: guarda o que vai precisar e troca o que não serve (mesma lógica do robô novo). */
const carefulHuman = (extra: Partial<Policy> = {}): Policy => ({ name: 'humano cuidadoso', decide: (m, me) => chooseBotAction(m, me), ...extra });
/** Humano casual: joga a carta da vez quando tem; senão troca uma carta qualquer da mão. */
const casualHuman = (extra: Partial<Policy> = {}): Policy => ({
  name: 'humano casual',
  decide: (m, me, rng) => {
    const p = m.players[me];
    const base = chooseClassicBotAction(p);
    if (base.action !== 'exchange') return base;
    return { action: 'exchange', payload: { card: p.hand[Math.floor(rng() * p.hand.length)] } };
  },
  ...extra,
});

/** Passa a vez sem jogar (o mesmo que GameEngine::skipTurn): segue o assento e respeita Merge Conflict. */
function skipTurn(m: MatchState): void {
  for (let i = 0; i < 4; i++) {
    m.current_seat = (m.current_seat % 4) + 1;
    m.turn_number++;
    const id = Object.keys(m.players).find((k) => m.players[k].seat === m.current_seat);
    if (id !== undefined && m.players[id].blocked) {
      m.players[id].blocked = false;
      continue;
    }
    return;
  }
}

/**
 * Abordagens de distribuição (aplicadas IGUALMENTE a todos os assentos):
 * - A "random": o motor atual, sem nada a mais.
 * - B "weighted": com chance `weight`, a próxima compra puxa para o topo a carta de que o jogador precisa,
 *   SE ela estiver entre as 8 primeiras do baralho (a mesma janela que o motor já olha). Sem garantia.
 * - C "adaptive": igual a B, mas só depois de `after` vezes seguidas sem progresso (evita secas longas).
 */
export type DrawMode = { kind: 'random' } | { kind: 'weighted'; weight: number } | { kind: 'adaptive'; after: number; weight: number };

function nextNeeded(p: PlayerState, offset: number): string | null {
  for (let s = p.step + offset; s < REQUIRED.length; s++) {
    if (!REQUIRED[s].startsWith('block_')) return REQUIRED[s];
  }
  return null;
}

function nudgeDeck(m: MatchState, wanted: string | null, rng: Rng, weight: number): void {
  if (!wanted || rng() >= weight) return;
  const window = Math.min(8, m.deck.length);
  const at = m.deck.slice(0, window).indexOf(wanted);
  if (at > 0) {
    m.deck.splice(at, 1);
    m.deck.unshift(wanted);
  }
}

type SeatStats = { wins: number; noProgressTurns: number; uselessHands: number; ownTurns: number; longestDrought: number; droughtSum: number; droughts: number };

export function simulate(policies: Policy[], matches: number, seed: number, mode: DrawMode = { kind: 'random' }) {
  const rng = seeded(seed);
  const stats: SeatStats[] = policies.map(() => ({ wins: 0, noProgressTurns: 0, uselessHands: 0, ownTurns: 0, longestDrought: 0, droughtSum: 0, droughts: 0 }));
  const stepTurns = Array(REQUIRED.length).fill(0);
  const stepCount = Array(REQUIRED.length).fill(0);
  const lengths: number[] = [];
  let unfinished = 0;

  for (let g = 0; g < matches; g++) {
    const participants: Participant[] = policies.map((_, i) => ({ id: i + 1, seat: i + 1, kind: 'bot' as const }));
    const m = createMatch(participants, rng);
    const own = policies.map(() => 0);
    const since = policies.map(() => 0); // vezes seguidas sem progresso
    const stepStart = policies.map(() => 0);

    let guard = 0;
    while (m.status === 'active' && guard++ < 4000) {
      const i = m.current_seat - 1;
      const me = String(i + 1);
      const policy = policies[i];
      const p = m.players[me];
      own[i]++;
      stats[i].ownTurns++;

      const required = REQUIRED[p.step];
      if (required && !required.startsWith('block_') && !p.hand.includes(required)) stats[i].uselessHands++;

      const lost = own[i] <= (policy.lostOpeningTurns ?? 0) || rng() < (policy.timeoutRate ?? 0);
      if (lost && (p.pending_discard ?? 0) === 0) {
        stats[i].noProgressTurns++;
        since[i]++;
        skipTurn(m);
        continue;
      }

      const before = p.step;
      const decision = policy.decide(m, me, rng);

      if (mode.kind !== 'random' && decision.action !== 'discard_excess' && decision.action !== 'play_block') {
        const eligible = mode.kind === 'weighted' || since[i] >= mode.after;
        if (eligible) {
          const wanted = decision.action === 'play_card' && decision.payload.card === required ? nextNeeded(p, 1) : nextNeeded(p, 0);
          nudgeDeck(m, wanted, rng, mode.weight);
        }
      }

      act(m, i + 1, decision.action, decision.payload, rng);
      const after = m.players[me];
      if (after.step > before) {
        for (let s = before; s < after.step; s++) {
          stepTurns[s] += own[i] - stepStart[i];
          stepCount[s]++;
        }
        stepStart[i] = own[i];
        if (since[i] > 0) {
          stats[i].droughtSum += since[i];
          stats[i].droughts++;
        }
        since[i] = 0;
      } else if (decision.action !== 'discard_excess') {
        stats[i].noProgressTurns++;
        since[i]++;
        stats[i].longestDrought = Math.max(stats[i].longestDrought, since[i]);
      }
    }

    if (m.status === 'finished' && m.winner_participant_id) {
      stats[m.winner_participant_id - 1].wins++;
      lengths.push(m.turn_number);
    } else {
      unfinished++;
    }
  }

  lengths.sort((a, b) => a - b);
  return {
    stats,
    medianTurns: lengths[Math.floor(lengths.length / 2)] ?? 0,
    p90Turns: lengths[Math.floor(lengths.length * 0.9)] ?? 0,
    unfinished,
    stepAvg: stepTurns.map((t, s) => (stepCount[s] ? t / stepCount[s] : 0)),
  };
}

const pct = (n: number, d: number) => (d ? `${((100 * n) / d).toFixed(1)}%` : '-');

/** Intervalo de confiança de 95% (Wilson) para uma proporção. */
function wilson(k: number, n: number) {
  const z = 1.96;
  const p = k / n;
  const den = 1 + (z * z) / n;
  const c = (p + (z * z) / (2 * n)) / den;
  const h = (z * Math.sqrt((p * (1 - p)) / n + (z * z) / (4 * n * n))) / den;
  return `${(100 * (c - h)).toFixed(1)}–${(100 * (c + h)).toFixed(1)}%`;
}

function report(title: string, policies: Policy[], matches: number, seed: number, mode?: DrawMode) {
  const r = simulate(policies, matches, seed, mode);
  console.log(`\n## ${title}  (${matches} partidas; turnos: mediana ${r.medianTurns}, p90 ${r.p90Turns}; sem fim: ${r.unfinished})`);
  policies.forEach((p, i) => {
    const s = r.stats[i];
    console.log(
      `  ${i + 1}. ${p.name.padEnd(24)} vitórias ${pct(s.wins, matches).padStart(6)} [IC95 ${wilson(s.wins, matches)}]` +
        ` | sem progresso ${pct(s.noProgressTurns, s.ownTurns).padStart(6)} | sem a carta da vez ${pct(s.uselessHands, s.ownTurns).padStart(6)}` +
        ` | seca média ${(s.droughts ? s.droughtSum / s.droughts : 0).toFixed(2)} | maior seca ${s.longestDrought}`,
    );
  });
  const names = ['login', 'clone', 'checkout', 'b1', 'add', 'commit', 'b2', 'add', 'commit', 'b3', 'add', 'commit', 'b4', 'add', 'commit', 'pull', 'push', 'PR'];
  console.log('  vezes por etapa: ' + r.stepAvg.map((v, s) => `${names[s]} ${v.toFixed(1)}`).join(' · '));
  return r;
}

const args = process.argv.slice(2);
const N = Number(args[0] ?? 3000);
const SEED = Number(args[1] ?? 20261128);
const GROUP = args[2] ?? 'all';

console.log(`# Estação Commit — simulação de equilíbrio (semente ${SEED})`);

if (GROUP === 'all' || GROUP === 'bots') {
  report('Controle: 4 robôs atuais', [classic, classic, classic, classic], N, SEED);
  report('Controle: 4 robôs novos', [improved, improved, improved, improved], N, SEED + 1);
  report('1 robô novo x 3 robôs atuais', [improved, classic, classic, classic], N, SEED + 2);
  report('1 robô novo sem ataques x 3 robôs atuais', [improvedNoAttack, classic, classic, classic], N, SEED + 3);

  for (const [label, bot] of [['robôs atuais', classic], ['robôs novos', improved], ['robôs novos s/ ataque', improvedNoAttack]] as const) {
    report(`1 humano casual x 3 ${label}`, [casualHuman(), bot, bot, bot], N, SEED + 10);
    report(`1 humano cuidadoso x 3 ${label}`, [carefulHuman(), bot, bot, bot], N, SEED + 11);
    report(`1 humano casual, perde 3 vezes (tutorial) x 3 ${label}`, [casualHuman({ lostOpeningTurns: 3 }), bot, bot, bot], N, SEED + 12);
    report(`1 humano casual, perde 10% das vezes x 3 ${label}`, [casualHuman({ timeoutRate: 0.1 }), bot, bot, bot], N, SEED + 13);
    report(`1 humano casual, 3 no tutorial + 10% x 3 ${label}`, [casualHuman({ lostOpeningTurns: 3, timeoutRate: 0.1 }), bot, bot, bot], N, SEED + 14);
    report(`2 humanos casuais x 2 ${label}`, [casualHuman(), casualHuman(), bot, bot], N, SEED + 15);
    report(`3 humanos casuais x 1 (${label})`, [casualHuman(), casualHuman(), casualHuman(), bot], N, SEED + 16);
  }
}

if (GROUP === 'check') {
  report('Controle: 4 robôs novos (desempate pela ordem da mesa)', [improved, improved, improved, improved], N, SEED + 30);
  report('1 humano cuidadoso x 3 robôs novos', [carefulHuman(), improved, improved, improved], N, SEED + 31);
  report('2 humanos cuidadosos x 2 robôs novos', [carefulHuman(), carefulHuman(), improved, improved], N, SEED + 32);
}

if (GROUP === 'all' || GROUP === 'draw') {
  const modes: [string, DrawMode][] = [
    ['A aleatória (atual)', { kind: 'random' }],
    ['B ponderada 25%', { kind: 'weighted', weight: 0.25 }],
    ['B ponderada 50%', { kind: 'weighted', weight: 0.5 }],
    ['C adaptativa (após 3 secas, 50%)', { kind: 'adaptive', after: 3, weight: 0.5 }],
    ['C adaptativa (após 4 secas, 100%)', { kind: 'adaptive', after: 4, weight: 1 }],
  ];
  for (const [label, mode] of modes) {
    report(`${label} — 4 robôs atuais`, [classic, classic, classic, classic], N, SEED + 20, mode);
    report(`${label} — 1 humano casual x 3 robôs atuais`, [casualHuman(), classic, classic, classic], N, SEED + 21, mode);
    report(`${label} — 1 humano casual x 3 robôs novos`, [casualHuman(), improved, improved, improved], N, SEED + 22, mode);
    report(`${label} — 1 humano casual (3 no tutorial + 10%) x 3 robôs novos`, [casualHuman({ lostOpeningTurns: 3, timeoutRate: 0.1 }), improved, improved, improved], N, SEED + 23, mode);
  }
}
