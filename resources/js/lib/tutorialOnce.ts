// Tutorial automático ao entrar numa PARTIDA nova: abre uma única vez por partida, no
// navegador. A decisão é por id da partida, então re-renderizações, reconexões, atualizações
// do estado multiplayer e recarregamentos da mesma partida nunca o reabrem; uma partida nova
// (outro id) abre de novo.

type Store = Pick<Storage, 'getItem' | 'setItem'>;

const claimedInThisPage = new Set<string>();

/** true na PRIMEIRA vez que a partida é vista neste navegador; false depois (e já marca como vista). */
export function claimTutorial(gameId: string, storage?: Store | null): boolean {
    if (claimedInThisPage.has(gameId)) return false;
    claimedInThisPage.add(gameId);

    let store = storage;
    if (store === undefined) {
        try {
            store = window.sessionStorage;
        } catch {
            store = null;
        }
    }
    if (!store) return true; // sem armazenamento: vale só enquanto a página estiver aberta

    const key = `ec:tutorial-game:${gameId}`;
    try {
        if (store.getItem(key) === '1') return false;
        store.setItem(key, '1');
    } catch {
        /* armazenamento bloqueado: o Set da página já evita repetir */
    }
    return true;
}

/** Só para testes. */
export function resetTutorialClaims(): void {
    claimedInThisPage.clear();
}
