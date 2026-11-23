import { dismissToast, getToastSnapshot, subscribeToasts } from '@/lib/toast';
import { useSyncExternalStore } from 'react';
import ToastStack from './Toast';

/** Montado UMA vez no app (ver app.tsx): os avisos sobrevivem à navegação. */
export default function ToastHost() {
    const toasts = useSyncExternalStore(
        subscribeToasts,
        getToastSnapshot,
        getToastSnapshot,
    );

    return <ToastStack toasts={toasts} onDismiss={dismissToast} />;
}
