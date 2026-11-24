// Quando uma visita merece a tela de espera (modal) e quando basta o feedback discreto.
//
// Modal só onde a pessoa realmente não pode fazer mais nada até o servidor responder:
// entrar como visitante, criar sala, entrar numa sala (convite/código) e abrir sala/partida.
// Todo o resto (rankings, perfis, seguidores, notificações, listas, formulários) usa a barra
// de progresso fina do topo e o estado do próprio botão, sem bloquear a página inteira.

/** Rotas cujo carregamento bloqueia a interação. */
export function needsBlockingLoader(method: string, pathname: string): boolean {
    const m = method.toLowerCase();

    if (m === 'post') {
        return (
            pathname === '/jogar' ||
            pathname === '/salas' ||
            pathname.startsWith('/convites/') ||
            /^\/salas\/[^/]+\/partida$/.test(pathname) // iniciar a partida
        );
    }
    if (m === 'get') {
        return pathname.startsWith('/convites/') || pathname.startsWith('/salas/');
    }

    return false;
}
