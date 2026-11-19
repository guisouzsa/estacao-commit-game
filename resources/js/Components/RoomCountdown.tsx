import { useEffect, useState } from 'react';

export default function RoomCountdown({
    expiresAt,
    expired = false,
}: {
    expiresAt?: string | null;
    expired?: boolean;
}) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const interval = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(interval);
    }, []);

    if (expired) {
        return (
            <span className="font-mono text-xs font-black uppercase text-[#d01600]">
                Sala expirada
            </span>
        );
    }

    if (!expiresAt) return null;

    const remaining = Math.max(0, new Date(expiresAt).getTime() - now);
    if (remaining === 0) {
        return (
            <span
                aria-live="polite"
                className="font-mono text-xs font-bold uppercase text-[#d01600]"
            >
                Prazo atingido · verificando status...
            </span>
        );
    }

    const totalSeconds = Math.ceil(remaining / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return (
        <span
            aria-live="off"
            className="font-mono text-xs font-bold uppercase tabular-nums text-[#464498]"
        >
            Expira em {minutes}:{String(seconds).padStart(2, '0')}
        </span>
    );
}
