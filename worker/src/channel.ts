import { DurableObject } from 'cloudflare:workers';

export type Attachment = { sub: string };

const MAX_SOCKETS = 64; // uma sala tem 4 jogadores; folga para abas/reconexões.
const REPLAY_TTL_MS = 5 * 60_000;

/**
 * Um Durable Object por canal ("rooms.<id>", "App.Models.User.<id>"...).
 * Hub de WebSocket com Hibernation: sem timers nem processamento ocioso.
 * Identidade de cada socket sobrevive à hibernação via serializeAttachment.
 * O estado do jogo continua no Laravel/Neon; aqui circula só o AVISO
 * "algo mudou" (mesmo contrato do evento room.state.changed do Reverb).
 */
export class ChannelHub extends DurableObject {
  // Ids já vistos (jti de token e nonce de notificação). Em memória: ao hibernar,
  // a janela de 60 s de timestamp/expiração do token limita o replay residual.
  private seen = new Map<string, number>();

  async fetch(request: Request): Promise<Response> {
    const url = new URL(request.url);

    if (url.pathname === '/connect') {
      if (request.headers.get('Upgrade') !== 'websocket') return new Response('Expected websocket', { status: 426 });
      const sub = request.headers.get('X-Verified-Sub') ?? '';
      const jti = request.headers.get('X-Verified-Jti') ?? '';
      if (!sub || !jti || this.replayed(`jti:${jti}`)) return new Response('Token já utilizado', { status: 401 });

      // Uma conexão por ator: a nova substitui a antiga (aba duplicada / reconexão).
      for (const old of this.ctx.getWebSockets()) {
        if ((old.deserializeAttachment() as Attachment | null)?.sub === sub) old.close(4000, 'replaced');
      }
      if (this.ctx.getWebSockets().length >= MAX_SOCKETS) return new Response('Canal cheio', { status: 503 });

      const pair = new WebSocketPair();
      const [client, server] = [pair[0], pair[1]];
      this.ctx.acceptWebSocket(server);
      server.serializeAttachment({ sub } satisfies Attachment);
      server.send(JSON.stringify({ event: 'ready' }));
      return new Response(null, { status: 101, webSocket: client });
    }

    if (url.pathname === '/notify' && request.method === 'POST') {
      if (this.replayed(`nonce:${request.headers.get('X-Nonce') ?? ''}`)) return new Response('Replay', { status: 409 });
      const payload = await request.text();
      let delivered = 0;
      for (const ws of this.ctx.getWebSockets()) {
        try {
          ws.send(payload);
          delivered++;
        } catch {
          /* socket morto: limpo em webSocketClose */
        }
      }
      return Response.json({ delivered });
    }

    return new Response('Not found', { status: 404 });
  }

  /** true se a chave JÁ tinha sido vista (replay). */
  private replayed(id: string): boolean {
    const now = Date.now();
    for (const [k, t] of this.seen) if (now - t > REPLAY_TTL_MS) this.seen.delete(k);
    if (this.seen.has(id)) return true;
    this.seen.set(id, now);
    return false;
  }

  // O cliente só ouve; mensagens recebidas são ignoradas (exceto um ping barato).
  async webSocketMessage(ws: WebSocket, message: string | ArrayBuffer): Promise<void> {
    if (message === 'ping') ws.send('pong');
  }

  async webSocketClose(ws: WebSocket, code: number): Promise<void> {
    try {
      ws.close(code >= 1000 && code <= 4999 && ![1004, 1005, 1006, 1015].includes(code) ? code : 1000);
    } catch {
      /* já fechado */
    }
  }

  async webSocketError(ws: WebSocket): Promise<void> {
    try {
      ws.close(1011, 'error');
    } catch {
      /* já fechado */
    }
  }
}
