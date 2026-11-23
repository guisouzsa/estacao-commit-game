import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Notice = { title: string; message: string };

const DEFAULT_NOTICE: Notice = {
    title: 'Aguarde um instante…',
    message: 'Estamos preparando tudo para você.',
};

/** Mensagem certa para cada tipo de ação (decidida pela rota, nunca pelo texto da tela). */
function noticeFor(method: string, url: URL): { notice: Notice; delayMs: number } {
    if (method === 'post' && url.pathname === '/jogar') {
        return {
            notice: {
                title: 'Aguarde um instante…',
                message: 'Estamos preparando o ambiente para você jogar. É rapidinho!',
            },
            delayMs: 0,
        };
    }
    if (method === 'post' && url.pathname === '/salas') {
        return {
            notice: { title: 'Montando sua mesa…', message: 'Estamos criando a sala e gerando o convite.' },
            delayMs: 150,
        };
    }
    if (url.pathname.startsWith('/convites/') || url.pathname.startsWith('/sala')) {
        return {
            notice: { title: 'Entrando na sala…', message: 'Estamos preparando a mesa para você.' },
            delayMs: 350,
        };
    }
    return { notice: DEFAULT_NOTICE, delayMs: 450 };
}

/**
 * Tela de espera amigável, no lugar da tela preta/branca durante carregamentos.
 *
 * - Visitas do Inertia lentas: aparece só se passar de alguns centenas de ms (sem piscar).
 * - Navegação completa do navegador (ex.: entrar como visitante): aparece na hora, e some
 *   sozinha se a pessoa voltar pelo histórico (bfcache).
 */
export default function LoadingHost() {
    const [notice, setNotice] = useState<Notice | null>(null);
    const timer = useRef<number | undefined>(undefined);

    useEffect(() => {
        const clear = () => window.clearTimeout(timer.current);

        const offStart = router.on('start', (event) => {
            clear();
            const visit = event.detail.visit;
            // Recarregamentos parciais e requisições em segundo plano não mostram tela.
            if (visit.async || visit.only.length > 0 || visit.except.length > 0) return;
            let url: URL;
            try {
                url = new URL(String(visit.url), window.location.origin);
            } catch {
                return;
            }
            const { notice: next, delayMs } = noticeFor(String(visit.method), url);
            timer.current = window.setTimeout(() => setNotice(next), delayMs);
        });
        const offFinish = router.on('finish', (event) => {
            // Cancelada/interrompida ou concluída: some. (Navegação completa reacende abaixo.)
            if (event.detail.visit.completed || event.detail.visit.cancelled || event.detail.visit.interrupted) {
                clear();
                setNotice(null);
            }
        });
        const onUnload = () => setNotice((current) => current ?? DEFAULT_NOTICE);
        const onShow = (event: PageTransitionEvent) => {
            if (event.persisted) setNotice(null);
        };

        window.addEventListener('beforeunload', onUnload);
        window.addEventListener('pageshow', onShow);

        return () => {
            clear();
            offStart();
            offFinish();
            window.removeEventListener('beforeunload', onUnload);
            window.removeEventListener('pageshow', onShow);
        };
    }, []);

    if (!notice) return null;

    return (
        <div
            role="status"
            aria-live="polite"
            className="fixed inset-0 z-[80] grid place-items-center bg-[#1d1d1b]/70 p-4 backdrop-blur-sm"
        >
            <div className="toast-in w-full max-w-sm border-[3px] border-black bg-[#f3f1ec] p-7 text-center text-[#1d1d1b] shadow-[8px_8px_0_#464498]">
                <img
                    alt=""
                    className="mx-auto h-16 w-auto object-contain motion-safe:animate-pulse"
                    src="/images/estacao-commit/mascots/gato-login.png"
                />
                <p className="mt-4 font-mono text-[10px] font-bold uppercase tracking-[.2em] text-[#464498]">
                    Estação Commit
                </p>
                <h2 className="mt-1 text-2xl font-black">{notice.title}</h2>
                <p className="mt-2 text-sm leading-6 text-[#5b5953]">{notice.message}</p>
                <div className="mt-5 h-2 overflow-hidden border-2 border-[#1d1d1b] bg-white" aria-hidden="true">
                    <div className="loading-bar h-full w-1/3 bg-[#b4da33]" />
                </div>
            </div>
        </div>
    );
}
