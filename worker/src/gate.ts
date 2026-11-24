// "Portão de espera": uma porta de entrada com a cara do jogo para quando o servidor
// (Render Free) está dormindo. A tela do próprio Render, enquanto ele acorda, não pode ser
// personalizada; esta aqui sim. Ela mostra o progresso, pergunta ao Worker se o jogo já
// respondeu e, quando responde, leva a pessoa ao site. Só faz uma consulta quando alguém
// está esperando nela (nada de "manter acordado" artificial).

/** Só caminhos do próprio site: nunca outro endereço (evita redirecionamento aberto). */
export function safePath(raw: string | null): string {
  if (!raw || !raw.startsWith('/') || raw.startsWith('//') || raw.includes('\\') || /[\r\n]/.test(raw)) return '/';
  return raw.slice(0, 300);
}

export async function checkOrigin(origin: string, fetcher: typeof fetch = fetch): Promise<{ ready: boolean }> {
  try {
    const res = await fetcher(`${origin}/up`, { signal: AbortSignal.timeout(9000), headers: { 'User-Agent': 'estacao-commit-gate' } });
    return { ready: res.ok };
  } catch {
    return { ready: false };
  }
}

export function gatePage(origin: string, to: string): string {
  const target = origin + to;
  return `<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Acordando o jogo — Estação Commit</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;background:#1d1d1b;color:#1d1d1b;font-family:system-ui,-apple-system,"Segoe UI",sans-serif}
main{width:100%;max-width:36rem;border:3px solid #000;background:#f3f1ec;padding:28px;box-shadow:8px 8px 0 #464498}
.brand{font-weight:900}.brand span{color:#464498}.code{margin:26px 0 0;font:700 12px ui-monospace,Consolas,monospace;letter-spacing:.2em;text-transform:uppercase;color:#464498}
h1{margin:8px 0 0;font-size:clamp(1.6rem,5vw,2.2rem);line-height:1.15}p{line-height:1.7;color:#5b5953}
ul{margin:16px 0 0;padding:0;list-style:none;font:600 13px ui-monospace,Consolas,monospace}li{padding:4px 0;color:#9a978f}li.done{color:#2d6b00}li.now{color:#1d1d1b}
li:before{content:"○ "}li.done:before{content:"✓ "}li.now:before{content:"▶ ";color:#464498}
.bar{margin-top:18px;height:10px;border:2px solid #1d1d1b;background:#fff;overflow:hidden}.bar i{display:block;height:100%;width:35%;background:#b4da33;animation:s 1.2s ease-in-out infinite}
@keyframes s{0%{transform:translateX(-100%)}100%{transform:translateX(290%)}}
.meta{margin-top:12px;font:700 12px ui-monospace,Consolas,monospace;color:#464498;min-height:1.4em}
a,button{display:inline-flex;min-height:48px;align-items:center;justify-content:center;margin:14px 8px 0 0;padding:0 20px;border:2px solid #1d1d1b;background:#b4da33;color:#1d1d1b;font:900 13px ui-monospace,Consolas,monospace;text-transform:uppercase;text-decoration:none;box-shadow:4px 4px 0 #1d1d1b;cursor:pointer}
.hidden{display:none}@media (prefers-reduced-motion:reduce){.bar i{animation:none;width:100%}}
</style></head><body><main>
<div class="brand">ESTAÇÃO <span>COMMIT</span></div>
<p class="code" id="code">Acordando o jogo</p>
<h1 id="title">Estamos preparando a sua mesa</h1>
<p id="text">O jogo descansa quando ninguém está jogando. Ele está acordando agora e a primeira visita pode levar até dois minutos. Você não precisa fazer nada: assim que ficar pronto, entramos automaticamente.</p>
<ul id="steps"><li>acordando o servidor</li><li>conectando ao banco de dados</li><li>preparando as salas</li><li>abrindo o jogo</li></ul>
<div class="bar" role="status" aria-label="Acordando o jogo"><i></i></div>
<p class="meta" id="meta" aria-live="polite"></p>
<button type="button" id="retry" class="hidden">Tentar de novo</button>
</main>
<script>
(function(){
var TARGET=${JSON.stringify(target).replace(/</g, '\\u003c')};
var steps=[].slice.call(document.querySelectorAll('#steps li'));
var started=Date.now(),delay=2500,stopped=false,meta=document.getElementById('meta');
function paint(sec){var idx=sec<10?0:sec<40?1:sec<80?2:3;steps.forEach(function(li,i){li.className=i<idx?'done':i===idx?'now':''})}
function tick(){var sec=Math.round((Date.now()-started)/1000);paint(sec);meta.textContent='Esperando há '+sec+'s'}
setInterval(function(){if(!stopped)tick()},1000);tick();
function poll(){
 if(stopped)return;
 fetch('/acordar/status',{cache:'no-store'}).then(function(r){return r.json()}).then(function(s){
  if(s.ready){stopped=true;steps.forEach(function(li){li.className='done'});document.getElementById('title').textContent='Tudo pronto!';meta.textContent='Entrando…';location.replace(TARGET);return}
  next()
 }).catch(next)
}
function next(){
 if(Date.now()-started>240000){stopped=true;document.getElementById('title').textContent='Está demorando mais do que o normal';document.getElementById('retry').className='';meta.textContent='Toque em "Tentar de novo". Sua sala e sua partida continuam salvas.';return}
 delay=Math.min(delay*1.3,8000);setTimeout(poll,delay)
}
document.getElementById('retry').onclick=function(){location.reload()};
poll();
})();
</script></body></html>`;
}
