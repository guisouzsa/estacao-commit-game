import { describe, expect, it } from 'vitest';
import WebSocket from 'ws';
import { hmacHex, sha256Hex, signConnectionToken } from '../src/auth';

const BASE = 'http://127.0.0.1:8799';
const TOKEN_SECRET = 'test-token-secret';
const NOTIFY_SECRET = 'test-notify-secret';
const ORIGIN = 'http://localhost:8000';
const uuid = () => crypto.randomUUID();
const now = () => Math.floor(Date.now() / 1000);
const room = () => `rooms.${uuid()}`;
const tick = (ms = 150) => new Promise((r) => setTimeout(r, ms));

const token = (ch: string, sub = 'user:1', over: Partial<{ exp: number; jti: string }> = {}) =>
  signConnectionToken({ ch, sub, exp: now() + 60, jti: uuid(), ...over }, TOKEN_SECRET);

/** Tenta abrir o WebSocket; devolve o cliente aberto ou o status HTTP da recusa. */
function connect(ch: string, tk: string, origin: string | null = ORIGIN) {
  return new Promise<{ ws?: WebSocket; inbox: string[]; status: number }>((resolve) => {
    const inbox: string[] = [];
    const ws = new WebSocket(`ws://127.0.0.1:8799/ws/${ch}?token=${tk}`, { headers: origin ? { Origin: origin } : {} });
    ws.on('message', (d) => inbox.push(String(d)));
    ws.on('open', () => resolve({ ws, inbox, status: 101 }));
    ws.on('unexpected-response', (_req, res) => resolve({ inbox, status: res.statusCode ?? 0 }));
    ws.on('error', () => {});
  });
}

async function open(ch: string, sub = 'user:1') {
  const c = await connect(ch, await token(ch, sub));
  expect(c.status).toBe(101);
  return c as { ws: WebSocket; inbox: string[]; status: number };
}

async function notify(ch: string, body: string, over: Partial<{ ts: number; nonce: string; secret: string }> = {}) {
  const ts = String(over.ts ?? now());
  const nonce = over.nonce ?? uuid().replace(/-/g, '');
  const sig = await hmacHex(over.secret ?? NOTIFY_SECRET, `${ts}.${nonce}.${await sha256Hex(body)}`);
  return fetch(`${BASE}/notify/${ch}`, { method: 'POST', body, headers: { 'X-Timestamp': ts, 'X-Nonce': nonce, 'X-Signature': sig } });
}

const events = (inbox: string[]) => inbox.map((m) => JSON.parse(m));

describe('autenticação da conexão', () => {
  it('health responde', async () => {
    expect((await fetch(`${BASE}/health`)).status).toBe(200);
  });
  it('rejeita origem não permitida e ausente', async () => {
    const ch = room();
    expect((await connect(ch, await token(ch), 'https://evil.example')).status).toBe(403);
    expect((await connect(ch, await token(ch), null)).status).toBe(403);
  });
  it('rejeita token ausente, adulterado, expirado e de outro canal', async () => {
    const ch = room();
    expect((await connect(ch, '')).status).toBe(401);
    const good = await token(ch);
    expect((await connect(ch, good.slice(0, -2) + 'xx')).status).toBe(401);
    expect((await connect(ch, await token(ch, 'user:1', { exp: now() - 5 }))).status).toBe(401);
    expect((await connect(ch, await token(room()))).status).toBe(401); // token da sala B na sala A
  });
  it('rejeita canal malformado', async () => {
    expect((await connect('rooms.nao-e-uuid', 'x')).status).toBe(400);
  });
  it('token é de uso único (replay)', async () => {
    const ch = room();
    const tk = await token(ch);
    const first = await connect(ch, tk);
    expect(first.status).toBe(101);
    expect((await connect(ch, tk)).status).toBe(401);
    first.ws!.close();
  });
});

describe('entrega de avisos', () => {
  it('entrega ao conectado (ready + aviso)', async () => {
    const ch = room();
    const c = await open(ch);
    const res = await notify(ch, JSON.stringify({ event: 'room.state.changed', change: 'game_updated' }));
    expect(res.status).toBe(200);
    expect(await res.json()).toEqual({ delivered: 1 });
    await tick();
    expect(events(c.inbox).map((e) => e.event)).toEqual(['ready', 'room.state.changed']);
    c.ws.close();
  });

  it('salas diferentes não compartilham eventos (12 salas simultâneas)', async () => {
    const rooms = Array.from({ length: 12 }, room);
    const clients = await Promise.all(rooms.map((r) => open(r)));
    await Promise.all(rooms.map((r, i) => notify(r, JSON.stringify({ event: 'room.state.changed', change: `c${i}` }))));
    await tick(400);
    clients.forEach((c, i) => {
      const msgs = events(c.inbox).filter((m) => m.event === 'room.state.changed');
      expect(msgs).toHaveLength(1);
      expect(msgs[0].change).toBe(`c${i}`);
      c.ws.close();
    });
  });

  it('broadcast alcança os 4 jogadores; reconexão do mesmo ator substitui a anterior', async () => {
    const ch = room();
    const players = await Promise.all([1, 2, 3, 4].map((i) => open(ch, `user:${i}`)));
    const res = await notify(ch, '{"event":"room.state.changed","change":"x"}');
    expect(((await res.json()) as { delivered: number }).delivered).toBe(4);
    await tick();
    players.forEach((p) => expect(p.inbox.length).toBe(2));

    const again = await open(ch, 'user:1'); // reconexão
    await tick();
    const res2 = await notify(ch, '{"event":"room.state.changed","change":"y"}');
    expect(((await res2.json()) as { delivered: number }).delivered).toBe(4); // 3 outros + a nova; a antiga caiu
    await tick();
    expect(again.inbox.some((m) => m.includes('"y"'))).toBe(true);
    players.forEach((p) => p.ws.close());
    again.ws.close();
  });

  it('sem ouvintes não falha', async () => {
    const res = await notify(room(), '{"event":"room.state.changed","change":"x"}');
    expect(((await res.json()) as { delivered: number }).delivered).toBe(0);
  });
});

describe('autenticação do aviso Laravel→Worker', () => {
  const body = '{"event":"room.state.changed","change":"x"}';
  it('rejeita assinatura errada, timestamp velho e nonce repetido', async () => {
    const ch = room();
    expect((await notify(ch, body, { secret: 'outro' })).status).toBe(401);
    expect((await notify(ch, body, { ts: now() - 600 })).status).toBe(401);
    const nonce = uuid().replace(/-/g, '');
    expect((await notify(ch, body, { nonce })).status).toBe(200);
    expect((await notify(ch, body, { nonce })).status).toBe(409);
  });
  it('rejeita requisição sem cabeçalhos e corpo grande', async () => {
    const ch = room();
    expect((await fetch(`${BASE}/notify/${ch}`, { method: 'POST', body })).status).toBe(401);
    expect((await notify(ch, 'x'.repeat(5000))).status).toBe(413);
  });
});
