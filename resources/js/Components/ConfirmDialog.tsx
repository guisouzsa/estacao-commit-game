import Modal from '@/Components/Modal';
import type { ReactNode } from 'react';

/**
 * Confirmação padrão de ações destrutivas ou irreversíveis. Toda página usa
 * este mesmo modal (nada de window.confirm). Explica a consequência.
 */
export default function ConfirmDialog({
    show,
    title,
    children,
    confirmLabel,
    busyLabel,
    cancelLabel = 'Cancelar',
    eyebrow = 'Ação destrutiva',
    tone = 'danger',
    busy = false,
    onConfirm,
    onClose,
}: {
    show: boolean;
    title: string;
    children: ReactNode;
    confirmLabel: string;
    busyLabel?: string;
    cancelLabel?: string;
    eyebrow?: string;
    tone?: 'danger' | 'default';
    busy?: boolean;
    onConfirm: () => void;
    onClose: () => void;
}) {
    const danger = tone === 'danger';

    return (
        <Modal show={show} maxWidth="md" onClose={() => !busy && onClose()}>
            <div className="p-6">
                <p
                    className={`font-mono text-xs font-black uppercase tracking-[.16em] ${danger ? 'text-[#d01600]' : 'text-[#464498]'}`}
                >
                    {eyebrow}
                </p>
                <h2 className="mt-2 text-2xl font-black">{title}</h2>
                <div className="mt-3 text-sm leading-6 text-[#5b5953]">
                    {children}
                </div>
                <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button
                        disabled={busy}
                        onClick={onClose}
                        className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-bold uppercase disabled:opacity-60"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        disabled={busy}
                        onClick={onConfirm}
                        className={`min-h-11 border-2 border-[#1d1d1b] px-4 font-mono text-xs font-black uppercase disabled:opacity-60 ${danger ? 'bg-[#d01600] text-white' : 'bg-[#b4da33] text-[#1d1d1b]'}`}
                    >
                        {busy ? (busyLabel ?? 'Aguarde...') : confirmLabel}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
