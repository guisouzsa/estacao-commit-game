import type { ToastPayload, ToastTone } from '@/types';
import { useCallback, useEffect, useRef, useState } from 'react';

const TONES: Record<ToastTone, { bar: string; label: string }> = {
    success: { bar: 'bg-[#b4da33]', label: 'Pronto' },
    warning: { bar: 'bg-[#f0b429]', label: 'Atenção' },
    info: { bar: 'bg-[#464498]', label: 'Aviso' },
};

/**
 * Fila de avisos. O mesmo `id` nunca é exibido duas vezes, mesmo que o servidor
 * reenvie a propriedade em um recarregamento parcial.
 */
export function useToasts() {
    const [toasts, setToasts] = useState<ToastPayload[]>([]);
    const seen = useRef(new Set<string>());

    const push = useCallback((toast: ToastPayload) => {
        if (seen.current.has(toast.id)) return;
        seen.current.add(toast.id);
        setToasts((current) => [...current, toast]);
    }, []);

    const dismiss = useCallback((id: string) => {
        setToasts((current) => current.filter((toast) => toast.id !== id));
    }, []);

    return { toasts, push, dismiss };
}

function ToastCard({
    toast,
    onDismiss,
    durationMs,
}: {
    toast: ToastPayload;
    onDismiss: (id: string) => void;
    durationMs: number;
}) {
    useEffect(() => {
        const timer = window.setTimeout(() => onDismiss(toast.id), durationMs);
        return () => window.clearTimeout(timer);
    }, [toast.id, onDismiss, durationMs]);

    const tone = TONES[toast.tone];

    return (
        <div className="pointer-events-auto flex border-[3px] border-[#1d1d1b] bg-white shadow-[5px_5px_0_#1d1d1b]">
            <span aria-hidden="true" className={`w-2 shrink-0 ${tone.bar}`} />
            <div className="flex-1 p-3">
                <p className="font-mono text-[10px] font-black uppercase tracking-[.16em] text-[#464498]">
                    {tone.label}
                </p>
                <p className="mt-1 text-sm leading-5">{toast.message}</p>
            </div>
            <button
                type="button"
                aria-label="Fechar aviso"
                onClick={() => onDismiss(toast.id)}
                className="self-start px-3 py-2 font-mono text-sm font-black hover:text-[#d01600] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#464498]"
            >
                ×
            </button>
        </div>
    );
}

export default function ToastStack({
    toasts,
    onDismiss,
    durationMs = 12000,
}: {
    toasts: ToastPayload[];
    onDismiss: (id: string) => void;
    durationMs?: number;
}) {
    return (
        <div
            aria-live="polite"
            role="status"
            className="pointer-events-none fixed inset-x-3 bottom-4 z-[60] flex flex-col items-end gap-3 sm:left-auto sm:right-4 sm:w-96"
        >
            {toasts.map((toast) => (
                <ToastCard
                    key={toast.id}
                    toast={toast}
                    onDismiss={onDismiss}
                    durationMs={durationMs}
                />
            ))}
        </div>
    );
}
