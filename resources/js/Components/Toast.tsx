import type { ToastItem, ToastTone } from '@/lib/toast';

const TONES: Record<ToastTone, { bar: string; label: string }> = {
    success: { bar: 'bg-[#b4da33]', label: 'Pronto' },
    info: { bar: 'bg-[#464498]', label: 'Aviso' },
    warning: { bar: 'bg-[#f0b429]', label: 'Atenção' },
    error: { bar: 'bg-[#d01600]', label: 'Ops' },
};

function ToastCard({
    toast,
    onDismiss,
}: {
    toast: ToastItem;
    onDismiss: (uid: number) => void;
}) {
    const tone = TONES[toast.tone];

    return (
        <div
            // Erros interrompem a leitura de tela; o resto é anunciado com calma.
            role={toast.tone === 'error' ? 'alert' : 'status'}
            className="toast-in pointer-events-auto flex max-w-full border-[3px] border-[#1d1d1b] bg-white shadow-[5px_5px_0_#1d1d1b]"
        >
            <span aria-hidden="true" className={`w-2 shrink-0 ${tone.bar}`} />
            <div className="min-w-0 flex-1 p-3">
                <p className="font-mono text-[10px] font-black uppercase tracking-[.16em] text-[#464498]">
                    {tone.label}
                </p>
                <p className="mt-1 break-words text-sm leading-5 text-[#1d1d1b]">
                    {toast.message}
                </p>
            </div>
            <button
                type="button"
                aria-label="Fechar aviso"
                onClick={() => onDismiss(toast.uid)}
                className="self-start px-3 py-2 font-mono text-sm font-black text-[#1d1d1b] hover:text-[#d01600] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#464498]"
            >
                ×
            </button>
        </div>
    );
}

/**
 * Pilha de avisos. Fica acima da navbar (z-30) e dos modais (z-50), nunca causa
 * rolagem horizontal e deixa o resto da tela clicável.
 */
export default function ToastStack({
    toasts,
    onDismiss,
}: {
    toasts: readonly ToastItem[];
    onDismiss: (uid: number) => void;
}) {
    return (
        <div
            aria-label="Avisos"
            className="pointer-events-none fixed inset-x-3 bottom-4 z-[70] flex flex-col items-end gap-3 sm:left-auto sm:right-4 sm:w-96"
        >
            {toasts.map((toast) => (
                <ToastCard key={toast.uid} toast={toast} onDismiss={onDismiss} />
            ))}
        </div>
    );
}
