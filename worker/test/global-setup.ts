import { spawn, type ChildProcess } from 'node:child_process';

export const PORT = 8799;
let child: ChildProcess | undefined;

export async function setup() {
  child = spawn(
    process.platform === 'win32' ? 'npx.cmd' : 'npx',
    ['wrangler', 'dev', '--port', String(PORT), '--var', 'REALTIME_TOKEN_SECRET:test-token-secret', '--var', 'REALTIME_NOTIFY_SECRET:test-notify-secret', '--var', 'ALLOWED_ORIGINS:http://localhost:8000', '--var', 'REALTIME_CALLBACK_SECRET:test-callback-secret', '--var', 'LARAVEL_CALLBACK_URL:http://127.0.0.1:8801', '--local'],
    { stdio: 'pipe', shell: process.platform === 'win32' },
  );
  let log = '';
  child.stdout?.on('data', (d) => (log += d));
  child.stderr?.on('data', (d) => (log += d));
  const deadline = Date.now() + 80000;
  while (Date.now() < deadline) {
    try {
      if ((await fetch(`http://127.0.0.1:${PORT}/health`)).ok) return;
    } catch {
      /* ainda subindo */
    }
    await new Promise((r) => setTimeout(r, 500));
  }
  throw new Error('wrangler dev não subiu:\n' + log);
}

export async function teardown() {
  if (!child?.pid) return;
  if (process.platform === 'win32') spawn('taskkill', ['/pid', String(child.pid), '/T', '/F']);
  else child.kill('SIGTERM');
}
