// Fila de avisos (toasts) do projeto inteiro.
//
// Fica FORA do React de propósito: sobrevive à navegação do Inertia (cada página
// remonta) e pode ser chamada de qualquer lugar (Echo, interceptadores). A
// interface a lê por useSyncExternalStore (ver Components/ToastHost.tsx).
// Sem imports: testável sem navegador.

export type ToastTone = 'success' | 'info' | 'warning' | 'error';

export type ToastInput = {
    message: string;
    tone?: ToastTone;
    /** Evita repetição: o mesmo id nunca é exibido duas vezes (ex.: aviso persistido). */
    id?: string;
    /** Mesma chave = SUBSTITUI o aviso que já está na tela (ex.: estado da conexão). */
    key?: string;
    /** 0 mantém na tela até ser fechado. Padrão: 6 s (erros: 9 s). */
    durationMs?: number;
};

export type ToastItem = {
    uid: number;
    message: string;
    tone: ToastTone;
    key?: string;
};

const MAX_VISIBLE = 4;
const DUPLICATE_WINDOW_MS = 3000;
const MAX_REMEMBERED_IDS = 200;

let nextUid = 1;
let items: ToastItem[] = [];
const timers = new Map<number, ReturnType<typeof setTimeout>>();
const seenIds = new Set<string>();
const recentMessages = new Map<string, number>();
const listeners = new Set<() => void>();

const emit = () => listeners.forEach((listener) => listener());

function clearTimer(uid: number) {
    const timer = timers.get(uid);
    if (timer !== undefined) clearTimeout(timer);
    timers.delete(uid);
}

export function dismissToast(uid: number): void {
    clearTimer(uid);
    const next = items.filter((item) => item.uid !== uid);
    if (next.length !== items.length) {
        items = next;
        emit();
    }
}

export function showToast(input: ToastInput): number | null {
    const tone = input.tone ?? 'info';
    const message = input.message.trim();
    if (!message) return null;

    // 1) Aviso com id: nunca se repete (eventos duplicados, recarregamentos parciais).
    if (input.id !== undefined) {
        if (seenIds.has(input.id)) return null;
        seenIds.add(input.id);
        if (seenIds.size > MAX_REMEMBERED_IDS) {
            seenIds.delete(seenIds.values().next().value as string);
        }
    } else if (input.key === undefined) {
        // 2) Sem id nem chave: a mesma mensagem em seguida é descartada.
        const signature = `${tone}|${message}`;
        const now = Date.now();
        const last = recentMessages.get(signature);
        if (last !== undefined && now - last < DUPLICATE_WINDOW_MS) return null;
        recentMessages.set(signature, now);
        if (recentMessages.size > MAX_REMEMBERED_IDS) {
            recentMessages.delete(recentMessages.keys().next().value as string);
        }
    }

    // 3) Mesma chave: troca o conteúdo no lugar, sem empilhar.
    if (input.key !== undefined) {
        const existing = items.find((item) => item.key === input.key);
        if (existing) dismissToast(existing.uid);
    }

    const uid = nextUid++;
    items = [...items, { uid, message, tone, key: input.key }].slice(-MAX_VISIBLE);
    // O que foi empurrado para fora do limite também perde o timer.
    for (const timerUid of [...timers.keys()]) {
        if (!items.some((item) => item.uid === timerUid)) clearTimer(timerUid);
    }

    const duration = input.durationMs ?? (tone === 'error' ? 9000 : 6000);
    if (duration > 0) {
        timers.set(uid, setTimeout(() => dismissToast(uid), duration));
    }

    emit();
    return uid;
}

export const toast = {
    show: showToast,
    success: (message: string, options: Omit<ToastInput, 'message' | 'tone'> = {}) =>
        showToast({ ...options, message, tone: 'success' }),
    info: (message: string, options: Omit<ToastInput, 'message' | 'tone'> = {}) =>
        showToast({ ...options, message, tone: 'info' }),
    warning: (message: string, options: Omit<ToastInput, 'message' | 'tone'> = {}) =>
        showToast({ ...options, message, tone: 'warning' }),
    error: (message: string, options: Omit<ToastInput, 'message' | 'tone'> = {}) =>
        showToast({ ...options, message, tone: 'error' }),
    dismiss: dismissToast,
};

/** Usado por useSyncExternalStore. */
export function subscribeToasts(listener: () => void): () => void {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

export function getToastSnapshot(): readonly ToastItem[] {
    return items;
}

/** Só para testes: volta ao estado inicial. */
export function resetToasts(): void {
    for (const uid of [...timers.keys()]) clearTimer(uid);
    items = [];
    seenIds.clear();
    recentMessages.clear();
    emit();
}

/** Traduz um erro HTTP em uma mensagem para a pessoa (nunca o texto técnico). */
export function friendlyHttpMessage(status: number | undefined): string {
    switch (status) {
        case 401:
        case 419:
            return 'Sua sessão expirou. Atualize a página e tente de novo.';
        case 403:
            return 'Você não tem permissão para realizar essa ação.';
        case 404:
            return 'Não encontramos o que você procurava. Pode ter sido removido.';
        case 409:
            return 'Essa ação não pode ser feita agora. Atualize e tente novamente.';
        case 410:
            return 'Isso não está mais disponível.';
        case 422:
            return 'Confira os dados informados e tente novamente.';
        case 429:
            return 'Muitas tentativas seguidas. Aguarde um instante e tente de novo.';
        case 503:
            return 'O serviço está temporariamente indisponível. Tente novamente em instantes.';
        default:
            return status !== undefined && status >= 500
                ? 'Algo deu errado do nosso lado. Tente novamente em instantes.'
                : 'Não foi possível concluir. Tente novamente.';
    }
}
