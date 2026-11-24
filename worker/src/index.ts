import { verifyConnectionToken, verifyNotifySignature } from './auth';
import { ChannelHub } from './channel';
import { checkOrigin, gatePage, safePath } from './gate';
import { MatchRoom } from './match';

export { ChannelHub, MatchRoom };

export interface Env {
  CHANNEL: DurableObjectNamespace<ChannelHub>;
  MATCH: DurableObjectNamespace<MatchRoom>;
  LARAVEL_CALLBACK_URL: string;
  REALTIME_CALLBACK_SECRET: string;
  REALTIME_TOKEN_SECRET: string;
  REALTIME_NOTIFY_SECRET: string;
  ALLOWED_ORIGINS: string;
  APP_ORIGIN: string;
}

const CHANNEL_RE = /^(rooms\.[0-9a-fA-F-]{36}|App\.Models\.(User|GuestProfile)\.[0-9a-zA-Z-]{1,40})$/;
const MAX_NOTIFY_BYTES = 2048;

const stub = (env: Env, channel: string) => env.CHANNEL.get(env.CHANNEL.idFromName(channel));

export default {
  async fetch(request: Request, env: Env): Promise<Response> {
    const url = new URL(request.url);

    if (url.pathname === '/health') return Response.json({ ok: true });

    // Portão de espera (jogo dormindo no Render): tela própria + consulta de prontidão.
    if (request.method === 'GET' && (url.pathname === '/' || url.pathname === '/acordar')) {
      return new Response(gatePage(env.APP_ORIGIN, safePath(url.searchParams.get('to'))), {
        headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store', 'X-Frame-Options': 'DENY' },
      });
    }
    if (request.method === 'GET' && url.pathname === '/acordar/status') {
      return Response.json(await checkOrigin(env.APP_ORIGIN), { headers: { 'Cache-Control': 'no-store' } });
    }

    // Navegador → WebSocket: /ws/<canal>?token=<emitido pelo Laravel>
    if (url.pathname.startsWith('/ws/')) {
      const channel = decodeURIComponent(url.pathname.slice(4));
      if (!CHANNEL_RE.test(channel)) return new Response('Canal inválido', { status: 400 });

      const origin = request.headers.get('Origin');
      const allowed = env.ALLOWED_ORIGINS.split(',').map((o) => o.trim());
      if (!origin || !allowed.includes(origin)) return new Response('Origem não permitida', { status: 403 });

      const claims = await verifyConnectionToken(url.searchParams.get('token') ?? '', env.REALTIME_TOKEN_SECRET, channel);
      if (!claims) return new Response('Token inválido ou expirado', { status: 401 });

      const forward = new Request(new URL('/connect', request.url), request);
      forward.headers.set('X-Verified-Sub', claims.sub);
      forward.headers.set('X-Verified-Jti', claims.jti);
      return stub(env, channel).fetch(forward);
    }

    // Laravel → aviso de mudança: POST /notify/<canal> (assinado)
    if (url.pathname.startsWith('/notify/') && request.method === 'POST') {
      const channel = decodeURIComponent(url.pathname.slice(8));
      if (!CHANNEL_RE.test(channel)) return new Response('Canal inválido', { status: 400 });
      const body = await request.text();
      if (body.length > MAX_NOTIFY_BYTES) return new Response('Corpo grande demais', { status: 413 });
      const nonce = request.headers.get('X-Nonce');
      const ok = await verifyNotifySignature(
        { timestamp: request.headers.get('X-Timestamp'), nonce, signature: request.headers.get('X-Signature') },
        body,
        env.REALTIME_NOTIFY_SECRET,
      );
      if (!ok) return new Response('Assinatura inválida', { status: 401 });
      return stub(env, channel).fetch(
        new Request(new URL('/notify', request.url), { method: 'POST', body, headers: { 'X-Nonce': nonce! } }),
      );
    }

    // Laravel -> partida: /match/<sala>/(start|act|view) (assinado; mesmo esquema do /notify)
    const m = url.pathname.match(/^\/match\/([0-9a-fA-F-]{36})\/(start|act|view|bots)$/);
    if (m) {
      const [, roomId, op] = m;
      const body = request.method === 'POST' ? await request.text() : '';
      if (body.length > 16_384) return new Response('Corpo grande demais', { status: 413 });
      const ok = await verifyNotifySignature(
        { timestamp: request.headers.get('X-Timestamp'), nonce: request.headers.get('X-Nonce'), signature: request.headers.get('X-Signature') },
        body,
        env.REALTIME_NOTIFY_SECRET,
      );
      if (!ok) return new Response('Assinatura inválida', { status: 401 });
      const target = new URL(`/${op}${url.search}`, request.url);
      return env.MATCH.get(env.MATCH.idFromName(roomId.toLowerCase())).fetch(
        new Request(target, { method: request.method, body: request.method === 'POST' ? body : undefined }),
      );
    }

    return new Response('Not found', { status: 404 });
  },
} satisfies ExportedHandler<Env>;
