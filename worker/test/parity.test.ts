import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { RuleError, act, botStep, createMatch, type MatchState, type Participant } from '../src/engine';

// PARIDADE com o motor PHP: os MESMOS cenários de tests/parity/scenarios.json (cujo "expected" vem do
// GameEngine.php) executados no motor do Worker. Cada passo precisa dar o mesmo resultado e o mesmo estado.

type Scenario = {
  name: string;
  unordered?: boolean;
  participants: { seat: number; kind: 'user' | 'bot' }[];
  state: any;
  steps: any[];
  expected: any[];
};

const doc = JSON.parse(readFileSync(resolve(__dirname, '../../tests/parity/scenarios.json'), 'utf8')) as { scenarios: Scenario[] };

function snapshot(m: MatchState, unordered: boolean) {
  const players: Record<string, unknown> = {};
  for (const seat of Object.keys(m.players).map((id) => m.players[id].seat).sort()) {
    const p = Object.values(m.players).find((x) => x.seat === seat)!;
    players[String(seat)] = {
      hand: p.hand, path: p.path, step: p.step, blocked: Boolean(p.blocked),
      pending_discard: p.pending_discard ?? 0, recent_swaps: p.recent_swaps ?? [],
    };
  }
  const base = {
    status: m.status, current_seat: m.current_seat, turn_number: m.turn_number, version: m.version,
    winner_seat: m.winner_participant_id ?? null, finished: m.finished_at !== null,
  };
  if (unordered) {
    const all = [...m.deck, ...m.discard, ...Object.values(players).flatMap((p: any) => p.hand)].sort();
    return {
      ...base, all_cards: all, deck_size: m.deck.length, discard_size: m.discard.length,
      hand_sizes: Object.fromEntries(Object.entries(players).map(([k, p]: any) => [k, p.hand.length])),
      steps: Object.fromEntries(Object.entries(players).map(([k, p]: any) => [k, p.step])),
    };
  }
  return {
    ...base, deck: m.deck, discard: m.discard, players,
    history: m.history.map((h) => ({
      seat: h.participant_id ?? null, message: h.message, turn: h.turn, ...(h.notify ? { notify: true } : {}),
    })),
  };
}

function run(s: Scenario) {
  const participants: Participant[] = s.participants.map((p) => ({ id: p.seat, seat: p.seat, kind: p.kind, bot_key: p.kind === 'bot' ? `bot${p.seat}` : null }));
  const players: Record<string, any> = {};
  for (const [seat, player] of Object.entries<any>(s.state.players)) players[seat] = { seat: Number(seat), ...structuredClone(player) };
  const m: MatchState = {
    status: 'active', current_seat: s.state.current_seat, turn_number: s.state.turn_number, version: s.state.version,
    winner_participant_id: null, finished_at: null,
    deck: [...s.state.deck], discard: [...s.state.discard], players, history: [],
  };

  return s.steps.map((step) => {
    let result: any;
    if (step.do === 'bot') {
      result = { played: botStep(m, participants) };
    } else {
      try {
        act(m, step.seat, step.action, structuredClone(step.payload));
        result = { ok: true };
      } catch (e) {
        if (!(e instanceof RuleError)) throw e;
        result = { error: { field: e.field, message: e.message } };
      }
    }
    return { result, snapshot: snapshot(m, Boolean(s.unordered)) };
  });
}

describe('paridade PHP x Worker (mesmos cenários, mesmo resultado)', () => {
  for (const scenario of doc.scenarios) {
    it(scenario.name, () => {
      expect(scenario.expected, 'rode PARITY_UPDATE=1 php artisan test --filter=EngineParityTest').toBeTruthy();
      const actual = run(scenario);
      expect(actual.length).toBe(scenario.expected.length);
      actual.forEach((step, i) => expect(step, `${scenario.name} · passo ${i + 1}`).toEqual(scenario.expected[i]));
    });
  }

  it('partida nova: Git Login para todos, baralho de 67 cartas e composição igual à do PHP', () => {
    const ps: Participant[] = [1, 2, 3, 4].map((i) => ({ id: i, seat: i, kind: 'user' }));
    for (let t = 0; t < 30; t++) {
      const m = createMatch(ps);
      expect(m.deck).toHaveLength(67);
      expect(m.deck).not.toContain('git_login');
      for (const p of Object.values(m.players)) {
        expect(p.hand).toHaveLength(4);
        expect(p.hand.filter((c) => c === 'git_login')).toHaveLength(1);
      }
      const all = [...m.deck, ...Object.values(m.players).flatMap((p) => p.hand)];
      const count = (c: string) => all.filter((x) => x === c).length;
      expect(
        ['git_login', 'git_clone', 'git_checkout', 'git_pull', 'git_add', 'git_commit', 'git_push', 'pull_request', 'clear_credentials', 'merge_conflict'].map(count),
      ).toEqual([4, 10, 10, 6, 18, 18, 8, 5, 2, 2]); // mesma constante do EngineParityTest.php
      expect([m.version, m.turn_number, m.status]).toEqual([1, 1, 'active']);
      expect(m.current_seat).toBeGreaterThanOrEqual(1);
      expect(m.current_seat).toBeLessThanOrEqual(4);
    }
  });

  it('criar a partida NÃO joga pelos bots (como no PHP): o primeiro passo só acontece por pedido', () => {
    const ps: Participant[] = [1, 2, 3, 4].map((i) => ({ id: i, seat: i, kind: i === 1 ? 'user' : 'bot' }));
    const m = createMatch(ps);
    expect([m.version, m.turn_number]).toEqual([1, 1]);
    const before = m.version;
    if (m.current_seat !== 1) {
      expect(botStep(m, ps)).toBe(true); // exatamente UM passo
      expect(m.version).toBe(before + 1);
    } else {
      expect(botStep(m, ps)).toBe(false); // vez do humano: nenhum bot joga
    }
  });

  it('partida só de bots termina com vencedor, percurso completo e 83 cartas conservadas (como no PHP)', () => {
    for (let t = 0; t < 12; t++) {
      const ps: Participant[] = [1, 2, 3, 4].map((i) => ({ id: i, seat: i, kind: 'bot' }));
      const m = createMatch(ps);
      for (let i = 0; i < 4000 && m.status === 'active'; i++) botStep(m, ps);
      expect(m.status).toBe('finished');
      const w = m.players[String(m.winner_participant_id)];
      expect(w.path).toHaveLength(18);
      expect(w.path.at(-1)).toBe('pull_request');
      const total = m.deck.length + m.discard.length + Object.values(m.players).reduce((n, p) => n + p.hand.length + p.path.filter((c) => !c.startsWith('block_')).length, 0);
      expect(total).toBe(83);
    }
  });
});
