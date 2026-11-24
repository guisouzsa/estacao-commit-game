import { createServer, type Server } from 'node:http';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { safePath } from '../src/gate';

const BASE = 'http://127.0.0.1:8799';
let up = false;
let server: Server;
beforeAll(() => {
  // "Render" falso: /up responde 200 quando acordado, 503 enquanto dorme.
  server = createServer((req, res) => {
    res.statusCode = req.url === '/up' && up ? 200 : 503;
    res.end('x');
  }).listen(8802);
});
afterAll(() => server.close());

describe('portão de espera', () => {
  it('mostra a tela personalizada na raiz e em /acordar, sem cache', async () => {
    for (const path of ['/', '/acordar']) {
      const res = await fetch(`${BASE}${path}`);
      expect(res.status).toBe(200);
      expect(res.headers.get('content-type')).toContain('text/html');
      expect(res.headers.get('cache-control')).toBe('no-store');
      const html = await res.text();
      expect(html).toContain('Acordando o jogo');
      expect(html).toContain('Estação');
      expect(html).toContain('"http://127.0.0.1:8802/"');
    }
  });

  it('reporta se o jogo dorme ou já respondeu', async () => {
    up = false;
    expect(await (await fetch(`${BASE}/acordar/status`)).json()).toEqual({ ready: false });
    up = true;
    expect(await (await fetch(`${BASE}/acordar/status`)).json()).toEqual({ ready: true });
    up = false;
  });

  it('só redireciona para caminhos do próprio site (sem redirecionamento aberto nem XSS)', async () => {
    const page = async (to: string) => (await fetch(`${BASE}/acordar?to=${encodeURIComponent(to)}`)).text();
    expect(await page('/jogar')).toContain('"http://127.0.0.1:8802/jogar"');
    for (const bad of ['//evil.example', 'https://evil.example', '\\\\evil', '/ok\r\nSet-Cookie: x=1']) {
      const html = await page(bad);
      expect(html).toContain('"http://127.0.0.1:8802/"');
      expect(html).not.toContain('evil');
    }
    const xss = await page('/</script><script>alert(1)</script>');
    expect(xss).not.toContain('</script><script>alert');
    expect(xss).toContain('\\u003c/script');
  });

  it('safePath', () => {
    expect(safePath(null)).toBe('/');
    expect(safePath('/salas/1')).toBe('/salas/1');
    expect(safePath('//x')).toBe('/');
    expect(safePath('x')).toBe('/');
  });
});
