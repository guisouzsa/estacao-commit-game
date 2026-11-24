import { needsBlockingLoader } from '@/lib/loadingPolicy';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Notice = { title: string; message: string };

/** A tela de espera só aparece se a página demorar mais que isto. Antes disso, nada pisca. */
const SLOW_MS = 2000;
/** Depois disto a tela de espera oferece "Tentar de novo" e "Fechar". */
const STUCK_MS = 8000;
/** Navegação completa do navegador: só mostra a tela se a rede estiver realmente lenta. */
const SLOW_FULL_NAVIGATION_MS = 5000;

const DEFAULT_NOTICE: Notice = {
    title: 'Aguarde um instante…',
    message: 'Estamos preparando tudo para você.',
};

/** Mensagem certa para cada tipo de ação (decidida pela rota, nunca pelo texto da tela). */
function noticeFor(
    method: string,
    url: URL,
): { notice: Notice; delayMs: number } {
    if (method === 'post' && url.pathname === '/jogar') {
        return {
            notice: {
                title: 'Aguarde um instante…',
                message:
                    'Estamos preparando o ambiente para você jogar. É rapidinho!',
            },
            delayMs: SLOW_MS,
        };
    }
    if (method === 'post' && url.pathname === '/salas') {
        return {
            notice: {
                title: 'Montando sua mesa…',
                message: 'Estamos criando a sala e gerando o convite.',
            },
            delayMs: SLOW_MS,
        };
    }
    if (
        url.pathname.startsWith('/convites/') ||
        url.pathname.startsWith('/sala')
    ) {
        return {
            notice: {
                title: 'Entrando na sala…',
                message: 'Estamos preparando a mesa para você.',
            },
            delayMs: SLOW_MS,
        };
    }
    return { notice: DEFAULT_NOTICE, delayMs: SLOW_MS };
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
    const [stuck, setStuck] = useState(false);
    const timer = useRef<number | undefined>(undefined);

    // Nunca prende a pessoa: se a tela de espera passar de 8 s, aparece uma saída.
    useEffect(() => {
        setStuck(false);
        if (!notice) return;
        const escape = window.setTimeout(() => setStuck(true), STUCK_MS);
        return () => window.clearTimeout(escape);
    }, [notice]);

    useEffect(() => {
        const clear = () => window.clearTimeout(timer.current);

        const offStart = router.on('start', (event) => {
            clear();
            const visit = event.detail.visit;
            // Recarregamentos parciais e requisições em segundo plano não mostram tela.
            if (visit.async || visit.only.length > 0 || visit.except.length > 0)
                return;
            let url: URL;
            try {
                url = new URL(String(visit.url), window.location.origin);
            } catch {
                return;
            }
            const method = String(visit.method);
            // Modal só onde a pessoa não pode fazer mais nada até o servidor responder (ver
            // lib/loadingPolicy.ts). O resto usa a barra de progresso do topo e o estado do botão.
            if (!needsBlockingLoader(method, url.pathname)) return;
            const { notice: next, delayMs } = noticeFor(method, url);
            timer.current = window.setTimeout(() => setNotice(next), delayMs);
        });
        // Qualquer fim de visita encerra a espera: sucesso, erro de validação, cancelamento,
        // interrupção ou falha de rede. (Navegação completa reacende abaixo, se demorar.)
        const offFinish = router.on('finish', () => {
            clear();
            setNotice(null);
        });
        const offException = router.on('exception', () => {
            clear();
            setNotice(null);
        });
        // Navegação completa: se a próxima página demorar mais de 2 s, avisa; se for rápida, a
        // página antiga é descartada antes e o aviso nem chega a existir.
        const onUnload = () => {
            clear();
            timer.current = window.setTimeout(
                () => setNotice((current) => current ?? DEFAULT_NOTICE),
                SLOW_FULL_NAVIGATION_MS,
            );
        };
        const onShow = (event: PageTransitionEvent) => {
            if (event.persisted) {
                clear();
                setNotice(null);
            }
        };

        window.addEventListener('beforeunload', onUnload);
        window.addEventListener('pageshow', onShow);

        return () => {
            clear();
            offStart();
            offFinish();
            offException();
            window.removeEventListener('beforeunload', onUnload);
            window.removeEventListener('pageshow', onShow);
        };
    }, []);

    if (!notice) return null;

    return (
        <div
            role="status"
            aria-live="polite"
            className="fixed inset-0 z-[80] grid place-items-center bg-[#1d1d1b]/80 p-4"
        >
            <div className="toast-in w-full max-w-sm border-[3px] border-black bg-[#f3f1ec] p-7 text-center text-[#1d1d1b] shadow-[8px_8px_0_#464498]">
                <img
                    alt=""
                    className="mx-auto h-16 w-auto object-contain"
                    src="/images/estacao-commit/mascots/gato-login.png"
                />
                <p className="mt-4 font-mono text-[10px] font-bold uppercase tracking-[.2em] text-[#464498]">
                    Estação Commit
                </p>
                <h2 className="mt-1 text-2xl font-black">{notice.title}</h2>
                <p className="mt-2 text-sm leading-6 text-[#5b5953]">
                    {notice.message}
                </p>
                <div
                    className="mt-5 h-2 overflow-hidden border-2 border-[#1d1d1b] bg-white"
                    aria-hidden="true"
                >
                    <div className="loading-bar h-full w-1/3 bg-[#b4da33]" />
                </div>
                {stuck && (
                    <div className="mt-4">
                        <p className="text-sm font-bold">
                            Está demorando mais que o normal.
                        </p>
                        <div className="mt-3 flex justify-center gap-2">
                            <button
                                className="min-h-11 border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-[11px] font-black uppercase"
                                onClick={() => window.location.reload()}
                                type="button"
                            >
                                Tentar de novo
                            </button>
                            <button
                                className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-[11px] font-black uppercase"
                                onClick={() => setNotice(null)}
                                type="button"
                            >
                                Fechar
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
