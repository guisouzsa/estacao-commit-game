// Autenticação do Worker. O Laravel é a referência de identidade: ele assina
// (HMAC-SHA256) tokens curtos de conexão e as chamadas servidor→servidor. O Worker
// nunca confia em dados de usuário vindos do navegador sem essa assinatura.

const enc = new TextEncoder();

const b64url = (bytes: ArrayBuffer | Uint8Array): string => {
  const u = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
  let s = '';
  for (const b of u) s += String.fromCharCode(b);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};

const fromB64url = (s: string): Uint8Array<ArrayBuffer> => {
  const p = s.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - (s.length % 4)) % 4);
  return Uint8Array.from(atob(p), (c) => c.charCodeAt(0));
};

const key = (secret: string, usage: ('sign' | 'verify')[]) =>
  crypto.subtle.importKey('raw', enc.encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, usage);

const hex = (buf: ArrayBuffer) => [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');

export async function hmacHex(secret: string, data: string): Promise<string> {
  return hex(await crypto.subtle.sign('HMAC', await key(secret, ['sign']), enc.encode(data)));
}

export async function sha256Hex(data: string): Promise<string> {
  return hex(await crypto.subtle.digest('SHA-256', enc.encode(data)));
}

export type ConnectionClaims = {
  /** Canal autorizado, ex.: "rooms.<uuid>" ou "App.Models.User.<id>". */
  ch: string;
  /** Identidade do ator no Laravel: "user:<id>" ou "guest:<id>". */
  sub: string;
  /** Expiração (epoch, segundos). */
  exp: number;
  /** Id único do token (uso único). */
  jti: string;
};

/** Token: base64url(payload JSON) + "." + base64url(HMAC). Emitido pelo Laravel. */
export async function verifyConnectionToken(
  token: string,
  secret: string,
  expectedChannel: string,
  nowSeconds = Math.floor(Date.now() / 1000),
): Promise<ConnectionClaims | null> {
  const [body, sig, extra] = token.split('.');
  if (!body || !sig || extra !== undefined) return null;
  let ok = false;
  try {
    ok = await crypto.subtle.verify('HMAC', await key(secret, ['verify']), fromB64url(sig), enc.encode(body));
  } catch {
    return null;
  }
  if (!ok) return null;
  let claims: ConnectionClaims;
  try {
    claims = JSON.parse(new TextDecoder().decode(fromB64url(body)));
  } catch {
    return null;
  }
  if (typeof claims.exp !== 'number' || claims.exp < nowSeconds) return null;
  if (claims.ch !== expectedChannel || typeof claims.sub !== 'string' || typeof claims.jti !== 'string') return null;
  return claims;
}

export async function signConnectionToken(claims: ConnectionClaims, secret: string): Promise<string> {
  const body = b64url(enc.encode(JSON.stringify(claims)));
  const sig = await crypto.subtle.sign('HMAC', await key(secret, ['sign']), enc.encode(body));
  return `${body}.${b64url(sig)}`;
}

/**
 * Chamadas servidor→servidor (Laravel → Worker). Cabeçalhos:
 *   X-Timestamp (epoch s, janela de 60 s), X-Nonce, X-Signature = hex(HMAC(secret, ts.nonce.sha256(body)))
 * O nonce é registrado no Durable Object do canal e rejeitado se repetido (anti-replay).
 */
export async function verifyNotifySignature(
  req: { timestamp: string | null; nonce: string | null; signature: string | null },
  body: string,
  secret: string,
  nowSeconds = Math.floor(Date.now() / 1000),
): Promise<boolean> {
  if (!req.timestamp || !req.nonce || !req.signature) return false;
  const ts = Number(req.timestamp);
  if (!Number.isFinite(ts) || Math.abs(nowSeconds - ts) > 60) return false;
  if (!/^[A-Za-z0-9_-]{8,64}$/.test(req.nonce)) return false;
  const expected = await hmacHex(secret, `${req.timestamp}.${req.nonce}.${await sha256Hex(body)}`);
  if (expected.length !== req.signature.length) return false;
  let diff = 0;
  for (let i = 0; i < expected.length; i++) diff |= expected.charCodeAt(i) ^ req.signature.charCodeAt(i);
  return diff === 0;
}
