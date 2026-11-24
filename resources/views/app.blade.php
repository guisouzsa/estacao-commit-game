<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        {{-- Chave PÚBLICA do Reverb em tempo de execução: o front não depende de variáveis VITE_* no build. --}}
        <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key') }}">
        @if (\App\Services\CloudflareRealtime::enabled())
        <meta name="realtime-driver" content="cloudflare">
        @endif

        {{-- Na página da partida, o tabuleiro (a maior imagem) começa a baixar já no HTML. --}}
        @if (request()->routeIs('games.show'))
        <link rel="preload" as="image" href="/images/estacao-commit/tabuleiro.webp" type="image/webp">
        @endif

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Fontes: o CSS delas NÃO bloqueia mais a renderização (antes um @import no CSS principal
             encadeava mais um download antes de qualquer pintura). Com display=swap o texto aparece
             na fonte do sistema e troca quando a fonte chega. --}}
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Space+Grotesk:wght@500;600;700&display=swap" media="print" onload="this.media='all'">
        <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Space+Grotesk:wght@500;600;700&display=swap"></noscript>
        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        {{-- Tela de abertura: aparece enquanto o JavaScript baixa (evita a tela preta). Sai quando o app monta. --}}
        <div id="boot-splash" role="status" aria-live="polite" style="position:fixed;inset:0;z-index:90;display:grid;opacity:0;animation:boot-in .2s ease-out 2s forwards;place-items:center;padding:16px;background:#1d1d1b;font-family:system-ui,-apple-system,'Segoe UI',sans-serif">
            <div style="width:100%;max-width:24rem;border:3px solid #000;background:#f3f1ec;color:#1d1d1b;padding:28px;text-align:center;box-shadow:8px 8px 0 #464498">
                <div style="font:700 10px ui-monospace,Consolas,monospace;letter-spacing:.2em;text-transform:uppercase;color:#464498">Estação Commit</div>
                <h1 style="margin:6px 0 0;font-size:1.5rem;font-weight:900">Aguarde um instante…</h1>
                <p style="margin:10px 0 0;line-height:1.6;color:#5b5953;font-size:.9rem">Estamos preparando o ambiente para você jogar.</p>
                <div style="margin-top:18px;height:8px;border:2px solid #1d1d1b;background:#fff;overflow:hidden"><div style="height:100%;width:35%;background:#b4da33;animation:boot-bar 1.1s ease-in-out infinite"></div></div>
                <p id="boot-hint" aria-live="polite" style="margin:14px 0 0;font:600 12px ui-monospace,Consolas,monospace;line-height:1.6;color:#464498;min-height:1.6em"></p>
            </div>
        </div>
        {{-- A tela de abertura explica a demora em vez de parecer travada. As frases são só
             texto: nada aqui faz requisição. Some junto com a tela quando o app monta. --}}
        <script>
            (function () {
                var hint = document.getElementById('boot-hint');
                if (!hint) return;
                var steps = [
                    [6000, '$ carregando o jogo…'],
                    [15000, '$ ainda carregando — conexões lentas levam um pouco mais.'],
                    [35000, '$ tudo certo, só está demorando. Não precisa recarregar.'],
                    [75000, '$ ainda nada? Toque em recarregar; sua sala e sua partida continuam salvas.']
                ];
                steps.forEach(function (s) {
                    setTimeout(function () {
                        if (document.getElementById('boot-splash')) hint.textContent = s[1];
                    }, s[0]);
                });
            })();
        </script>
        <style>@keyframes boot-in{to{opacity:1}}@keyframes boot-bar{0%{transform:translateX(-100%)}100%{transform:translateX(290%)}}@media (prefers-reduced-motion:reduce){#boot-splash div div div{animation:none!important;width:100%!important}}</style>
        @inertia
    </body>
</html>
