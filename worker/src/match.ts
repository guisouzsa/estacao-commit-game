import { DurableObject } from 'cloudflare:workers';
import { hmacHex, sha256Hex } from './auth';
import {
  ACTIONS, REQUIRED, RuleError, act, botStep, createMatch, replaceWithBot, runBots,
  type ActionPayload, type MatchState, type Participant,
} from './engine';

export interface MatchEnv {
  CHANNEL: DurableObjectNamespace;
  LARAVEL_CALLBACK_URL: string;
  REALTIME_CALLBACK_SECRET: string;
}

type Config = { room_id: string; turn_timeout_seconds: number; bot_pool: string[]; names: Record<string, string> };

const OUTBOX_RETRY_MS = 15_000;

/**
 * Um Durable Object por PARTIDA (id = id da sala). É a autoridade do jogo:
 * valida turno e regras, serializa ações simultâneas (um DO executa uma por vez),
 * roda os bots, controla a inatividade por Alarm e mantém o estado em SQLite.
 * O Neon recebe só o RESULTADO (callback assinado para o Laravel), via outbox com retentativa.
 * Nenhum timer: sem ação pendente não há alarme, então o objeto hiberna.
 */
export class MatchRoom extends DurableObject<MatchEnv> {
  constructor(ctx: DurableObjectState, env: MatchEnv) {
    super(ctx, env);
    const sql = ctx.storage.sql;
    sql.exec('CREATE TABLE IF NOT EXISTS kv (k TEXT PRIMARY KEY, v TEXT NOT NULL)');
    sql.exec('CREATE TABLE IF NOT EXISTS actions (uuid TEXT PRIMARY KEY)');
    sql.exec('CREATE TABLE IF NOT EXISTS outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, body TEXT NOT NULL)');
  }

  private get<T>(k: string): T | null {
    const row = this.ctx.storage.sql.exec('SELECT v FROM kv WHERE k = ?', k).toArray()[0];
    return row ? (JSON.parse(row.v as string) as T) : null;
  }
  private put(k: string, v: unknown) {
    this.ctx.storage.sql.exec('INSERT INTO kv (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', k, JSON.stringify(v));
  }

  async fetch(request: Request): Promise<Response> {
    const url = new URL(request.url);
    try {
      if (url.pathname === '/start' && request.method === 'POST') return await this.start(await request.json());
      if (url.pathname === '/view') return this.view(Number(url.searchParams.get('participant')));
      if (url.pathname === '/act' && request.method === 'POST') return await this.handleAct(await request.json());
      if (url.pathname === '/bots' && request.method === 'POST') return await this.handleBotStep(await request.json());
    } catch (e) {
      if (e instanceof RuleError) return Response.json({ errors: { [e.field]: e.message } }, { status: 422 });
      throw e;
    }
    return new Response('Not found', { status: 404 });
  }

  // ---------------------------------------------------------------- início
  private async start(body: { participants: Participant[]; names?: Record<string, string>; turn_timeout_seconds: number; bot_pool?: string[]; room_id: string }) {
    if (this.get('match')) return Response.json({ created: false, version: this.get<MatchState>('match')!.version });
    const participants = body.participants.map((p) => ({ id: p.id, seat: p.seat, kind: p.kind, bot_key: p.bot_key ?? null }));
    const match = createMatch(participants);
    const now = Date.now();
    // Como no PHP: criar a partida NÃO joga pelos bots; cada bot joga a sua vez por pedido (/bots).
    this.ctx.storage.transactionSync(() => {
      this.put('config', { room_id: body.room_id, turn_timeout_seconds: body.turn_timeout_seconds, bot_pool: body.bot_pool ?? [], names: body.names ?? {} } satisfies Config);
      this.put('participants', participants);
      this.put('match', match);
      this.put('last_move', now);
      if (match.status === 'finished') this.enqueueFinished(match);
    });
    await this.afterChange('match_started');
    return Response.json({ created: true, version: match.version });
  }

  // ---------------------------------------------------------------- leitura
  private view(participantId: number): Response {
    const m = this.get<MatchState>('match');
    const participants = this.get<Participant[]>('participants');
    const cfg = this.get<Config>('config');
    if (!m || !participants || !cfg) return Response.json({ error: 'no_match' }, { status: 404 });
    const viewer = m.players[String(participantId)];
    if (!viewer) return Response.json({ error: 'not_participant' }, { status: 404 });

    const current = participants.find((p) => p.seat === m.current_seat);
    const timeout = cfg.turn_timeout_seconds;
    let left: number | null = null;
    if (timeout > 0 && m.status === 'active' && current && current.kind !== 'bot') {
      left = Math.max(0, timeout - Math.floor((Date.now() - (this.get<number>('last_move') ?? Date.now())) / 1000));
    }
    return Response.json({
      game: {
        status: m.status, current_seat: m.current_seat, turn_number: m.turn_number, version: m.version,
        winner_participant_id: m.winner_participant_id, finished_at: m.finished_at,
        deck_count: m.deck.length, discard_count: m.discard.length, turn_seconds_left: left,
        history: m.history.slice(-12).reverse(),
      },
      participants: participants.map((p) => {
        const g = m.players[String(p.id)];
        return { id: p.id, seat: p.seat, kind: p.kind, bot_key: p.bot_key ?? null, hand_count: g.hand.length, path: g.path, step: g.step, blocked: g.blocked, pending_discard: g.pending_discard };
      }),
      hand: viewer.hand,
      requiredCard: REQUIRED[viewer.step] ?? null,
    });
  }

  // ---------------------------------------------------------------- jogada
  private async handleAct(body: { participant_id: number; action: string; card?: string; target_id?: number | null; action_uuid: string; version: number }) {
    const m = this.get<MatchState>('match');
    const participants = this.get<Participant[]>('participants');
    if (!m || !participants) return Response.json({ error: 'no_match' }, { status: 404 });
    if (!ACTIONS.includes(body.action) || typeof body.action_uuid !== 'string' || body.action_uuid.length > 64) {
      return Response.json({ errors: { action: 'Ação de jogo desconhecida.' } }, { status: 422 });
    }
    // Repetição do mesmo pedido (duplo clique, reenvio): resultado já aplicado, nada a fazer.
    if (this.ctx.storage.sql.exec('SELECT 1 FROM actions WHERE uuid = ?', body.action_uuid).toArray().length) {
      return Response.json({ duplicate: true, version: m.version });
    }
    if (body.version !== m.version) return Response.json({ error: 'conflict', version: m.version }, { status: 409 });

    const wasActive = m.status === 'active';
    const payload: ActionPayload = { card: body.card, target_id: body.target_id };
    act(m, body.participant_id, body.action, payload);
    // Os bots NÃO jogam junto com a jogada humana: o cliente pede um passo por vez (/bots).

    this.ctx.storage.transactionSync(() => {
      this.put('match', m);
      this.put('last_move', Date.now());
      this.ctx.storage.sql.exec('INSERT INTO actions (uuid) VALUES (?)', body.action_uuid);
      if (wasActive && m.status === 'finished') this.enqueueFinished(m);
    });
    await this.afterChange('game_updated');
    return Response.json({ version: m.version, status: m.status });
  }

  // ---------------------------------------------------------------- passo de bot
  /** Um passo de bot, só se o cliente viu a versão atual (vários clientes pedindo juntos: só um passo acontece). */
  private async handleBotStep(body: { version: number }) {
    const m = this.get<MatchState>('match');
    const participants = this.get<Participant[]>('participants');
    if (!m || !participants) return Response.json({ error: 'no_match' }, { status: 404 });
    if (Number(body.version) !== m.version) return Response.json({ played: false, version: m.version });

    const wasActive = m.status === 'active';
    if (!botStep(m, participants)) return Response.json({ played: false, version: m.version });

    this.ctx.storage.transactionSync(() => {
      this.put('match', m);
      this.put('last_move', Date.now());
      if (wasActive && m.status === 'finished') this.enqueueFinished(m);
    });
    await this.afterChange('game_updated');
    return Response.json({ played: true, version: m.version, status: m.status });
  }

  // ---------------------------------------------------------------- alarme
  async alarm(): Promise<void> {
    try {
      await this.flushOutbox();
      await this.sweepInactivity();
    } catch (e) {
      console.error('alarm failed', e);
    }
    await this.schedule();
  }

  /** Humano parado na vez por tempo demais: um bot assume (mesma regra do GameInactivityService). */
  private async sweepInactivity() {
    const m = this.get<MatchState>('match');
    const participants = this.get<Participant[]>('participants');
    const cfg = this.get<Config>('config');
    if (!m || !participants || !cfg || m.status !== 'active' || cfg.turn_timeout_seconds <= 0) return;
    if (Date.now() < (this.get<number>('last_move') ?? 0) + cfg.turn_timeout_seconds * 1000) return;

    const idle = participants.find((p) => p.seat === m.current_seat);
    if (!idle || idle.kind === 'bot') return;
    const used = new Set(participants.map((p) => p.bot_key).filter(Boolean));
    const botKey = cfg.bot_pool.find((k) => !used.has(k));
    if (!botKey) return;

    const replaced = replaceWithBot(m, participants, botKey, cfg.names[String(idle.id)] ?? null)!;
    runBots(m, participants);
    this.ctx.storage.transactionSync(() => {
      this.put('participants', participants);
      this.put('match', m);
      this.put('last_move', Date.now());
      this.enqueue({ type: 'participant_replaced', participant_id: replaced.id, bot_key: botKey });
      if (m.status === 'finished') this.enqueueFinished(m);
    });
    await this.afterChange('game_updated');
  }

  /** Próximo alarme: retentativa do outbox OU o prazo da vez de um humano. Sem nada pendente: nenhum alarme. */
  private async schedule() {
    const next: number[] = [];
    if (this.ctx.storage.sql.exec('SELECT 1 FROM outbox LIMIT 1').toArray().length) next.push(Date.now() + OUTBOX_RETRY_MS);
    const m = this.get<MatchState>('match');
    const participants = this.get<Participant[]>('participants');
    const cfg = this.get<Config>('config');
    if (m && participants && cfg && m.status === 'active' && cfg.turn_timeout_seconds > 0) {
      const current = participants.find((p) => p.seat === m.current_seat);
      if (current && current.kind !== 'bot') next.push((this.get<number>('last_move') ?? Date.now()) + cfg.turn_timeout_seconds * 1000 + 250);
    }
    if (next.length) await this.ctx.storage.setAlarm(Math.min(...next));
    else await this.ctx.storage.deleteAlarm();
  }

  // ---------------------------------------------------------------- saídas
  private async afterChange(change: string) {
    await this.flushOutbox();
    await this.schedule();
    const cfg = this.get<Config>('config')!;
    try {
      const hub = this.env.CHANNEL.get(this.env.CHANNEL.idFromName(`rooms.${cfg.room_id}`));
      await hub.fetch('https://hub/notify', {
        method: 'POST',
        headers: { 'X-Nonce': crypto.randomUUID() },
        body: JSON.stringify({ event: 'room.state.changed', data: { roomId: cfg.room_id, change } }),
      });
    } catch {
      /* o polling de segurança do front cobre */
    }
  }

  private enqueue(event: Record<string, unknown>) {
    this.ctx.storage.sql.exec('INSERT INTO outbox (body) VALUES (?)', JSON.stringify({ ...event, room_id: this.get<Config>('config')!.room_id }));
  }
  private enqueueFinished(m: MatchState) {
    this.enqueue({ type: 'match_finished', winner_participant_id: m.winner_participant_id, finished_at: m.finished_at });
  }

  /** Entrega ao Laravel em ordem; se falhar, o item continua no outbox e o alarme tenta de novo. */
  private async flushOutbox() {
    const rows = this.ctx.storage.sql.exec('SELECT id, body FROM outbox ORDER BY id').toArray();
    for (const row of rows) {
      const body = row.body as string;
      const ts = String(Math.floor(Date.now() / 1000));
      const nonce = crypto.randomUUID().replace(/-/g, '');
      const sig = await hmacHex(this.env.REALTIME_CALLBACK_SECRET, `${ts}.${nonce}.${await sha256Hex(body)}`);
      try {
        const res = await fetch(`${this.env.LARAVEL_CALLBACK_URL}/realtime/callback`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Timestamp': ts, 'X-Nonce': nonce, 'X-Signature': sig },
          body,
        });
        if (!res.ok) return;
      } catch {
        return;
      }
      this.ctx.storage.sql.exec('DELETE FROM outbox WHERE id = ?', row.id);
    }
  }
}
