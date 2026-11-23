import { createServer, type Server } from 'node:http';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { hmacHex, sha256Hex } from '../src/auth';

const BASE = 'http://127.0.0.1:8799';
const NOTIFY_SECRET = 'test-notify-secret';
const CALLBACK_SECRET = 'test-callback-secret';
const uuid = () => crypto.randomUUID();
const now = () => Math.floor(Date.now() / 1000);
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

// "Laravel" falso: recebe e valida os callbacks assinados do Worker.
type Callback = { body: any; validSignature: boolean };
const callbacks: Callback[] = [];
let callbackStatus = 200;
let server: Server;
beforeAll(async () => {
  server = createServer(async (req, res) => {
    let raw = '';
    for await (const c of req) raw += c;
    const ts = String(req.headers['x-timestamp']);
    const nonce = String(req.headers['x-nonce']);
    const expected = await hmacHex(CALLBACK_SECRET, `${ts}.${nonce}.${await sha256Hex(raw)}`);
    if (callbackStatus === 200) callbacks.push({ body: JSON.parse(raw), validSignature: expected === req.headers['x-signature'] });
    res.statusCode = callbackStatus;
    res.end('{}');
  }).listen(8801);
});
afterAll(() => server.close());

async function call(room: string, op: 'start' | 'act' | 'view', body?: unknown, query = '', secret = NOTIFY_SECRET) {
  const raw = body === undefined ? '' : JSON.stringify(body);
  const ts = String(now());
  const nonce = uuid().replace(/-/g, '');
  const sig = await hmacHex(secret, `${ts}.${nonce}.${await sha256Hex(raw)}`);
  const res = await fetch(`${BASE}/match/${room}/${op}${query}`, {
    method: body === undefined ? 'GET' : 'POST',
    body: body === undefined ? undefined : raw,
    headers: { 'X-Timestamp': ts, 'X-Nonce': nonce, 'X-Signature': sig },
  });
  return { status: res.status, json: (await res.json().catch(() => null)) as any };
}

const people = (kinds: Array<'user' | 'bot'> = ['user', 'user', 'user', 'user']) =>
  kinds.map((kind, i) => ({ id: 10 + i, seat: i + 1, kind, bot_key: kind === 'bot' ? `bot${i}` : null }));
const startBody = (room: string, extra: Record<string, unknown> = {}, kinds?: Array<'user' | 'bot'>) => ({
  room_id: room, participants: people(kinds), names: { 10: 'ana', 11: 'bia', 12: 'caio', 13: 'duda' }, turn_timeout_seconds: 0, bot_pool: ['pool1', 'pool2'], ...extra,
});
const view = (room: string, id: number) => call(room, 'view', undefined, `?participant=${id}`);
const currentOf = async (room: string) => {
  const v = (await view(room, 10)).json;
  return { v, current: v.participants.find((p: any) => p.seat === v.game.current_seat) };
};
const act = (room: string, id: number, version: number, action: string, extra: Record<string, unknown> = {}) =>
  call(room, 'act', { participant_id: id, action, action_uuid: uuid(), version, ...extra });

describe('partida no Durable Object', () => {
  it('rejeita chamadas sem assinatura válida', async () => {
    const room = uuid();
    expect((await call(room, 'start', startBody(room), '', 'segredo-errado')).status).toBe(401);
    expect((await fetch(`${BASE}/match/${room}/view?participant=10`)).status).toBe(401);
  });

  it('inicia, é idempotente e cada jogador só vê a própria mão', async () => {
    const room = uuid();
    const first = await call(room, 'start', startBody(room));
    expect(first.json.created).toBe(true);
    expect((await call(room, 'start', startBody(room))).json.created).toBe(false);

    const a = (await view(room, 10)).json;
    const b = (await view(room, 11)).json;
    expect(a.hand).toHaveLength(4);
    expect(a.hand).not.toBe(b.hand);
    expect(JSON.stringify(a.participants)).not.toContain('"hand":'); // só hand_count dos outros
    expect(a.participants.every((p: any) => typeof p.hand_count === 'number')).toBe(true);
    expect((await view(room, 999)).status).toBe(404); // não participante
    expect((await view(uuid(), 10)).status).toBe(404); // sala sem partida
  });

  it('valida turno, regras, conflito de versão e repetição', async () => {
    const room = uuid();
    await call(room, 'start', startBody(room));
    const { v, current } = await currentOf(room);
    const other = v.participants.find((p: any) => p.id !== current.id);

    const wrongTurn = await act(room, other.id, v.game.version, 'exchange', { card: 'git_add' });
    expect(wrongTurn.status).toBe(422);
    expect(wrongTurn.json.errors.turn).toBeTruthy();

    const mine = (await view(room, current.id)).json;
    expect((await act(room, current.id, mine.game.version, 'play_card', { card: 'inexistente' })).status).toBe(422);
    expect((await act(room, current.id, 999, 'exchange', { card: mine.hand[0] })).status).toBe(409);
    expect((await act(room, current.id, mine.game.version, 'hackear')).status).toBe(422);

    const uuidFixed = uuid();
    const body = { participant_id: current.id, action: 'exchange', card: mine.hand[0], action_uuid: uuidFixed, version: mine.game.version };
    const ok = await call(room, 'act', body);
    expect(ok.status).toBe(200);
    const again = await call(room, 'act', body); // mesmo uuid: não aplica duas vezes
    expect(again.json.duplicate).toBe(true);
    expect((await view(room, 10)).json.game.version).toBe(ok.json.version);
  });

  it('ações simultâneas da mesma versão: só uma vence, estado íntegro', async () => {
    const room = uuid();
    await call(room, 'start', startBody(room));
    const { v, current } = await currentOf(room);
    const mine = (await view(room, current.id)).json;
    const results = await Promise.all(
      Array.from({ length: 6 }, (_, i) => act(room, current.id, v.game.version, 'exchange', { card: mine.hand[i % mine.hand.length] })),
    );
    expect(results.filter((r) => r.status === 200)).toHaveLength(1);
    expect(results.filter((r) => r.status === 409 || r.status === 422)).toHaveLength(5);
    expect((await view(room, 10)).json.game.version).toBe(v.game.version + 1);
  });

  it('várias salas simultâneas não compartilham estado', async () => {
    const rooms = Array.from({ length: 8 }, uuid);
    await Promise.all(rooms.map((r) => call(r, 'start', startBody(r))));
    // joga uma troca só na sala 0
    const { v, current } = await currentOf(rooms[0]);
    const mine = (await view(rooms[0], current.id)).json;
    await act(rooms[0], current.id, v.game.version, 'exchange', { card: mine.hand[0] });
    for (const r of rooms.slice(1)) expect((await view(r, 10)).json.game.version).toBe(1);
    expect((await view(rooms[0], 10)).json.game.version).toBe(2);
  });

  it('partida completa (1 humano + 3 bots): termina, avisa o Laravel com retentativa e bloqueia novas ações', async () => {
    const room = uuid();
    callbacks.length = 0;
    callbackStatus = 500; // Laravel fora do ar no início: o resultado fica no outbox
    await call(room, 'start', startBody(room, {}, ['user', 'bot', 'bot', 'bot']));

    let finished = false;
    for (let i = 0; i < 2000 && !finished; i++) {
      const v = (await view(room, 10)).json;
      if (v.game.status === 'finished') break;
      const me = v.participants.find((p: any) => p.id === 10);
      if (v.game.current_seat !== me.seat) throw new Error('mesa parou fora da vez do humano');
      let action = 'exchange';
      let card: string | undefined = v.hand[0];
      if (me.pending_discard > 0) action = 'discard_excess';
      else if (v.requiredCard?.startsWith('block_')) { action = 'play_block'; card = undefined; }
      else if (v.requiredCard && v.hand.includes(v.requiredCard)) { action = 'play_card'; card = v.requiredCard; }
      const r = await act(room, 10, v.game.version, action, { card });
      expect(r.status).toBe(200);
      finished = r.json.status === 'finished';
    }
    const final = (await view(room, 10)).json;
    expect(final.game.status).toBe('finished');
    expect(final.game.winner_participant_id).not.toBeNull();
    expect((await act(room, 10, final.game.version, 'exchange', { card: final.hand[0] })).status).toBe(422);

    expect(callbacks.filter((c) => c.body.room_id === room)).toHaveLength(0); // Laravel ainda "fora"
    callbackStatus = 200; // volta: o alarme de retentativa entrega (≈15 s)
    for (let i = 0; i < 40 && !callbacks.some((c) => c.body.room_id === room); i++) await sleep(1000);
    const delivered = callbacks.filter((c) => c.body.room_id === room);
    expect(delivered).toHaveLength(1);
    expect(delivered[0].validSignature).toBe(true);
    expect(delivered[0].body).toMatchObject({ type: 'match_finished', winner_participant_id: final.game.winner_participant_id });
  }, 120_000);

  it('inatividade: após o prazo, um bot assume o lugar do humano parado (alarme) e o Laravel é avisado', async () => {
    const room = uuid();
    callbacks.length = 0;
    await call(room, 'start', startBody(room, { turn_timeout_seconds: 2, bot_pool: ['pool1', 'pool2'] }));
    const { current } = await currentOf(room);
    expect(current.kind).toBe('user');

    for (let i = 0; i < 20 && !callbacks.some((c) => c.body.room_id === room); i++) await sleep(500);
    const replaced = callbacks.find((c) => c.body.room_id === room && c.body.type === 'participant_replaced');
    expect(replaced).toBeTruthy();
    expect(replaced!.validSignature).toBe(true);
    expect(replaced!.body.participant_id).toBe(current.id);

    const v = (await view(room, 10)).json;
    expect(v.participants.find((p: any) => p.id === current.id).kind).toBe('bot');
    expect(v.game.history.some((h: any) => h.notify && h.message.includes('assumiu'))).toBe(true);
  }, 30_000);
});
