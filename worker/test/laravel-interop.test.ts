import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import WebSocket from 'ws';

// Interoperabilidade REAL: o PHP (CloudflareRealtime do Laravel) assina o token e o
// aviso, e o Worker em execução aceita ambos.
const root = resolve(__dirname, '../..');
const env = {
  ...process.env,
  REALTIME_DRIVER: 'cloudflare',
  REALTIME_WORKER_URL: 'http://127.0.0.1:8799',
  REALTIME_TOKEN_SECRET: 'test-token-secret',
  REALTIME_NOTIFY_SECRET: 'test-notify-secret',
};
const CF = String.raw`\App\Services\CloudflareRealtime`;
const HTTP = String.raw`\Illuminate\Support\Facades\Http`;
const php = (code: string) => execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: root, env }).toString().trim();

describe('Laravel ⇄ Worker', () => {
  it('token e aviso assinados pelo PHP são aceitos pelo Worker', async () => {
    const room = crypto.randomUUID();
    const ch = `rooms.${room}`;
    const token = php(`echo app(${CF}::class)->connectionToken('${ch}', 'user:42');`).split(/\r?\n/).pop()!.trim();

    const inbox: string[] = [];
    const ws = new WebSocket(`ws://127.0.0.1:8799/ws/${ch}?token=${token}`, { headers: { Origin: 'http://localhost:8000' } });
    await new Promise<void>((ok, fail) => { ws.on('open', () => ok()); ws.on('error', fail); ws.on('unexpected-response', (_r, res) => fail(new Error(`HTTP ${res.statusCode}`))); });
    ws.on('message', (d) => inbox.push(String(d)));

    php(`app(${CF}::class)->notify('${ch}', 'room.state.changed', ['roomId' => '${room}', 'change' => 'game_updated']); echo 'sent';`);
    await new Promise((r) => setTimeout(r, 300));
    expect(inbox.map((m) => JSON.parse(m))).toContainEqual({ event: 'room.state.changed', data: { roomId: room, change: 'game_updated' } });
    ws.close();
  });


  it('chamadas de partida assinadas pelo PHP (inclusive com acentos no corpo) são aceitas', () => {
    const room = crypto.randomUUID();
    const parts = "[['id'=>1,'seat'=>1,'kind'=>'user'],['id'=>2,'seat'=>2,'kind'=>'bot','bot_key'=>'k'],['id'=>3,'seat'=>3,'kind'=>'bot','bot_key'=>'k2'],['id'=>4,'seat'=>4,'kind'=>'bot','bot_key'=>'k3']]";
    const code = [
      `$rt = app(${CF}::class);`,
      `$body = json_encode(['room_id' => '${room}', 'participants' => ${parts}, 'names' => ['1' => 'João Robô'], 'turn_timeout_seconds' => 0, 'bot_pool' => []], JSON_UNESCAPED_SLASHES);`,
      `$s = ${HTTP}::withBody($body, 'application/json')->withHeaders($rt->signedHeaders($body))->post('http://127.0.0.1:8799/match/${room}/start')->status();`,
      `$v = ${HTTP}::withHeaders($rt->signedHeaders(''))->get('http://127.0.0.1:8799/match/${room}/view?participant=1')->status();`,
      `echo "R:$s:$v";`,
    ].join(' ');
    expect(php(code)).toMatch(/R:200:200$/);
  });
});
