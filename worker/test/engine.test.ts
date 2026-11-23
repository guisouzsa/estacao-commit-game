import { describe, expect, it } from 'vitest';
import { REQUIRED, RuleError, act, createMatch, runBots, type MatchState, type Participant } from '../src/engine';

// RNG determinístico (mulberry32) para reproduzir partidas.
const seeded = (seed: number) => () => {
  seed |= 0;
  seed = (seed + 0x6d2b79f5) | 0;
  let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
  t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

const humans: Participant[] = [1, 2, 3, 4].map((i) => ({ id: 100 + i, seat: i, kind: 'user' }));
const bots: Participant[] = [1, 2, 3, 4].map((i) => ({ id: 200 + i, seat: i, kind: 'bot', bot_key: `b${i}` }));
const total = (m: MatchState) =>
  m.deck.length +
  m.discard.length +
  Object.values(m.players).reduce((n, p) => n + p.hand.length + p.path.filter((c) => !c.startsWith('block_')).length, 0);
const fail = (fn: () => void): RuleError => {
  try {
    fn();
  } catch (e) {
    return e as RuleError;
  }
  throw new Error('não falhou');
};
const cur = (m: MatchState, ps: Participant[]) => ps.find((p) => p.seat === m.current_seat)!;

describe('motor: distribuição', () => {
  it('78 cartas, 4 por jogador, sentada inicial válida', () => {
    const m = createMatch(humans, seeded(1));
    expect(m.deck.length).toBe(78 - 16);
    for (const p of Object.values(m.players)) expect(p.hand).toHaveLength(4);
    expect(m.current_seat).toBeGreaterThanOrEqual(1);
    expect(m.current_seat).toBeLessThanOrEqual(4);
    expect(m.version).toBe(1);
  });
  it('exige exatamente 4 participantes', () => {
    expect(fail(() => createMatch(humans.slice(0, 3))).field).toBe('room');
  });
});

describe('motor: regras e turnos', () => {
  it('só quem está na vez joga', () => {
    const m = createMatch(humans, seeded(2));
    const other = humans.find((p) => p.seat !== m.current_seat)!;
    const e = fail(() => act(m, other.id, 'exchange', { card: m.players[String(other.id)].hand[0] }));
    expect(e.field).toBe('turn');
    expect(m.version).toBe(1);
  });
  it('carta fora da mão e carta que não é a etapa são recusadas sem alterar o estado', () => {
    const m = createMatch(humans, seeded(3));
    const p = cur(m, humans);
    m.players[String(p.id)].hand = ['git_add', 'git_commit', 'git_push', 'git_clone'];
    const before = structuredClone(m);
    expect(fail(() => act(m, p.id, 'play_card', { card: 'nao_existe' })).field).toBe('card');
    expect(fail(() => act(m, p.id, 'play_card', { card: 'git_add' })).field).toBe('card');
    expect(m).toEqual(before);
  });
  it('jogar a etapa certa avança o percurso e passa a vez; trocar também passa a vez', () => {
    const m = createMatch(humans, seeded(4));
    const p = cur(m, humans);
    m.players[String(p.id)].hand[0] = 'git_login';
    act(m, p.id, 'play_card', { card: 'git_login' });
    expect(m.players[String(p.id)].step).toBe(1);
    expect(m.current_seat).toBe((p.seat % 4) + 1);
    expect(m.version).toBe(2);
    const q = cur(m, humans);
    act(m, q.id, 'exchange', { card: m.players[String(q.id)].hand[0] });
    expect(m.current_seat).toBe((q.seat % 4) + 1);
  });
  it('mão acima de 6 exige descarte antes de qualquer outra ação', () => {
    const m = createMatch(humans, seeded(5));
    const p = cur(m, humans);
    const pl = () => m.players[String(p.id)];
    pl().hand = ['git_login', 'git_add', 'git_add', 'git_commit', 'git_commit', 'git_push', 'git_push'];
    act(m, p.id, 'play_card', { card: 'git_login' });
    expect(pl().pending_discard).toBe(1);
    expect(m.current_seat).toBe(p.seat);
    expect(fail(() => act(m, p.id, 'exchange', { card: pl().hand[0] })).field).toBe('hand');
    act(m, p.id, 'discard_excess', { card: pl().hand[0] });
    expect(pl().pending_discard).toBe(0);
    expect(m.current_seat).toBe((p.seat % 4) + 1);
  });
  it('merge conflict: alvo perde a vez uma vez; não pode alvejar a si mesmo', () => {
    const m = createMatch(humans, seeded(6));
    const p = cur(m, humans);
    const target = humans.find((h) => h.seat === (p.seat % 4) + 1)!;
    m.players[String(p.id)].hand[0] = 'merge_conflict';
    expect(fail(() => act(m, p.id, 'play_card', { card: 'merge_conflict', target_id: p.id })).field).toBe('target');
    act(m, p.id, 'play_card', { card: 'merge_conflict', target_id: target.id });
    expect(m.current_seat).toBe((target.seat % 4) + 1);
    expect(m.players[String(target.id)].blocked).toBe(false);
    expect(m.history.some((h) => h.message.includes('perdeu a vez'))).toBe(true);
  });
  it('clear credentials só no Treinamento', () => {
    const m = createMatch(humans, seeded(7));
    const p = cur(m, humans);
    const target = humans.find((h) => h.id !== p.id)!;
    m.players[String(p.id)].hand[0] = 'clear_credentials';
    m.players[String(target.id)].step = 3;
    expect(fail(() => act(m, p.id, 'play_card', { card: 'clear_credentials', target_id: target.id })).field).toBe('target');
    m.players[String(target.id)].step = 1;
    m.players[String(target.id)].path = ['git_login'];
    act(m, p.id, 'play_card', { card: 'clear_credentials', target_id: target.id });
    expect(m.players[String(target.id)].step).toBe(0);
    expect(m.players[String(target.id)].path).toEqual([]);
  });
  it('git pull no Treinamento compra duas cartas', () => {
    const m = createMatch(humans, seeded(8));
    const p = cur(m, humans);
    m.players[String(p.id)].hand = ['git_pull', 'git_add'];
    act(m, p.id, 'play_card', { card: 'git_pull' });
    // sai git_pull, entram 2 cartas: 1 + 2 = 3
    expect(m.players[String(p.id)].hand).toHaveLength(3);
  });
  it('depois do fim, nenhuma ação é aceita', () => {
    const m = createMatch(humans, seeded(9));
    m.status = 'finished';
    expect(fail(() => act(m, humans[0].id, 'exchange', { card: 'x' })).field).toBe('game');
  });
});

describe('motor: partidas completas com bots (várias sementes)', () => {
  it.each([11, 22, 33, 44, 55])('semente %i termina com vencedor, conserva as cartas e respeita o percurso', (seed) => {
    const rng = seeded(seed);
    const ps = bots.map((b) => ({ ...b }));
    const m = createMatch(ps, rng);
    for (let i = 0; i < 20000 && m.status === 'active'; i++) runBots(m, ps, rng);
    expect(m.status).toBe('finished');
    const winner = m.players[String(m.winner_participant_id)];
    expect(winner.path.at(-1)).toBe('pull_request');
    expect(winner.path).toHaveLength(REQUIRED.length);
    expect(total(m)).toBe(78);
    for (const p of Object.values(m.players)) expect(p.hand.length).toBeLessThanOrEqual(6);
  });
  it('humano na vez: bots não jogam por ele e param de novo na vez dele', () => {
    const ps: Participant[] = [
      { id: 1, seat: 1, kind: 'user' },
      ...[2, 3, 4].map((s) => ({ id: s, seat: s, kind: 'bot' as const, bot_key: `b${s}` })),
    ];
    const m = createMatch(ps, seeded(70));
    runBots(m, ps, seeded(71));
    expect(m.current_seat).toBe(1);
    act(m, 1, 'exchange', { card: m.players['1'].hand[0] });
    runBots(m, ps, seeded(72));
    expect(m.current_seat).toBe(1);
  });
});
