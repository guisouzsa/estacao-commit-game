import type { ToastItem, ToastTone } from '@/lib/toast';
import type { ReactNode } from 'react';

const TONES: Record<
    ToastTone,
    { bar: string; label: string; icon: ReactNode }
> = {
    success: {
        bar: 'bg-[#b4da33]',
        label: 'Pronto',
        icon: <path d="M5 13l4 4L19 7" />,
    },
    info: {
        bar: 'bg-[#464498]',
        label: 'Aviso',
        icon: <path d="M12 8h.01M11 12h1v5h1" />,
    },
    warning: {
        bar: 'bg-[#f0b429]',
        label: 'Atenção',
        icon: <path d="M12 4l9 16H3L12 4Zm0 6v4m0 3h.01" />,
    },
    error: {
        bar: 'bg-[#d01600]',
        label: 'Ops',
        icon: <path d="M6 6l12 12M18 6L6 18" />,
    },
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
            className={`${toast.leaving ? 'toast-out' : 'toast-in'} pointer-events-auto flex max-w-full border-[3px] border-[#1d1d1b] bg-white shadow-[5px_5px_0_#1d1d1b]`}
        >
            <span
                aria-hidden="true"
                className={`grid w-9 shrink-0 place-items-center ${tone.bar} ${toast.tone === 'error' || toast.tone === 'info' ? 'text-white' : 'text-[#1d1d1b]'}`}
            >
                <svg
                    className="size-5"
                    fill="none"
                    stroke="currentColor"
                    strokeLinecap="square"
                    strokeWidth="2.6"
                    viewBox="0 0 24 24"
                >
                    {tone.icon}
                </svg>
            </span>
            <div className="min-w-0 flex-1 p-3">
                <p className="font-mono text-[10px] font-black uppercase tracking-[.16em] text-[#464498]">
                    {toast.title ?? tone.label}
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
            className="pointer-events-none fixed inset-x-3 top-[max(0.75rem,env(safe-area-inset-top))] z-[70] flex flex-col items-end gap-2 sm:inset-x-auto sm:bottom-4 sm:right-4 sm:top-auto sm:w-96 sm:gap-3"
        >
            {toasts.map((toast) => (
                <ToastCard
                    key={toast.uid}
                    toast={toast}
                    onDismiss={onDismiss}
                />
            ))}
        </div>
    );
}
