import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type Status =
    | { state: 'idle' }
    | { state: 'joining' }
    | { state: 'failed'; message: string }
    | { state: 'connection' };

type FlashToast = { tone?: string; message?: string } | null | undefined;

const toastOf = (props: unknown): FlashToast =>
    (props as { flash?: { toast?: FlashToast } }).flash?.toast;

/**
 * Convite por link. Abrir o link já é a intenção de entrar: a página faz o POST sozinha
 * (com CSRF; GET nunca altera nada) e mostra "Entrando na sala...". O servidor valida tudo
 * de novo (convite, sala, vagas) dentro de uma transação com trava.
 *
 * Se a entrada falhar, a mensagem aparece aqui e NÃO há nova tentativa automática
 * (evita laço de pedidos); a pessoa pode tentar de novo com o botão.
 */
export default function Show({
    code,
    participants,
    autoJoin = false,
}: {
    code: string;
    participants: number;
    autoJoin?: boolean;
}) {
    const { props } = usePage();
    const arrivedWithError = (() => {
        const toast = toastOf(props);
        return toast?.tone === 'error' || toast?.tone === 'warning'
            ? (toast.message ?? 'Não foi possível entrar nessa sala.')
            : null;
    })();
    const [status, setStatus] = useState<Status>(
        arrivedWithError
            ? { state: 'failed', message: arrivedWithError }
            : autoJoin && participants < 4
              ? { state: 'joining' }
              : { state: 'idle' },
    );
    const started = useRef(false);
    const full = participants >= 4;
    const joining = status.state === 'joining';

    const join = useCallback(() => {
        let settled = false;
        setStatus({ state: 'joining' });
        // Baixa o código da tela da sala enquanto o servidor confirma a entrada (em paralelo, não em sequência).
        void import('../Rooms/Show').catch(() => undefined);
        router.post(
            route('rooms.join', code),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    settled = true;
                    // Falha de negócio volta como aviso (sala cheia, expirada...): mostra aqui mesmo.
                    const toast = toastOf(page.props);
                    if (toast?.tone === 'error' || toast?.tone === 'warning') {
                        setStatus({
                            state: 'failed',
                            message:
                                toast.message ??
                                'Não foi possível entrar nessa sala.',
                        });
                    }
                },
                onError: (errors) => {
                    settled = true;
                    setStatus({
                        state: 'failed',
                        message: String(
                            Object.values(errors)[0] ??
                                'Não foi possível entrar nessa sala.',
                        ),
                    });
                },
                onFinish: () => {
                    setStatus((current) =>
                        current.state === 'joining'
                            ? settled
                                ? { state: 'idle' }
                                : { state: 'connection' }
                            : current,
                    );
                },
            },
        );
    }, [code]);

    // Uma única tentativa automática por abertura da página (o ref também segura o
    // efeito duplo do React em desenvolvimento).
    useEffect(() => {
        if (started.current || !autoJoin || full || arrivedWithError) return;
        started.current = true;
        join();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const note =
        status.state === 'joining'
            ? { tone: 'info', text: 'Entrando na sala…' }
            : status.state === 'failed'
              ? { tone: 'error', text: status.message }
              : status.state === 'connection'
                ? {
                      tone: 'error',
                      text: 'Não conseguimos falar com o servidor. Verifique a internet e tente de novo.',
                  }
                : null;

    return (
        <AuthenticatedLayout>
            <Head title="Convite para jogar" />
            <main className="grid min-h-[calc(100dvh-4rem)] place-items-center bg-[#e8e5dc] px-4 py-8 text-[#1d1d1b]">
                <section
                    aria-busy={joining}
                    className="w-full max-w-lg border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-6 shadow-[8px_8px_0_#464498] sm:p-8"
                >
                    <p className="font-mono text-xs font-black uppercase tracking-[.18em] text-[#464498]">
                        Convite privado
                    </p>
                    <h1 className="mt-2 text-3xl font-black">
                        {joining ? 'Entrando na sala…' : 'Entre para a missão'}
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-[#5b5953]">
                        Você recebeu acesso a uma sala do Estação Commit. Sua
                        identidade atual será usada na mesa.
                    </p>
                    <div className="mt-5 flex items-center justify-between border-2 border-[#1d1d1b] bg-white p-3">
                        <div>
                            <span className="block font-mono text-[9px] uppercase text-[#68665f]">
                                Código
                            </span>
                            <strong className="font-mono text-2xl tracking-[.2em]">
                                {code}
                            </strong>
                        </div>
                        <strong className="border-2 border-[#1d1d1b] bg-[#b4da33] px-3 py-2 font-mono">
                            {participants}/4
                        </strong>
                    </div>
                    {note && (
                        <p
                            aria-live="polite"
                            className={`mt-4 flex items-center gap-2 border-2 px-3 py-2 text-sm font-bold ${
                                note.tone === 'error'
                                    ? 'border-[#b4232a] bg-[#fbe9e7] text-[#8a1c22]'
                                    : 'border-[#464498] bg-[#ecebf7] text-[#2f2d6b]'
                            }`}
                            role={note.tone === 'error' ? 'alert' : 'status'}
                        >
                            {note.tone === 'info' && (
                                <span
                                    aria-hidden="true"
                                    className="size-3 animate-spin rounded-full border-2 border-current border-t-transparent"
                                />
                            )}
                            {note.text}
                        </p>
                    )}
                    <button
                        disabled={joining || full}
                        onClick={join}
                        className="mt-5 min-h-12 w-full border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-xs font-black uppercase shadow-[4px_4px_0_#1d1d1b] disabled:opacity-50"
                    >
                        {full
                            ? 'Sala completa'
                            : joining
                              ? 'Entrando…'
                              : status.state === 'idle'
                                ? 'Entrar na sala'
                                : 'Tentar de novo'}
                    </button>
                    <Link
                        href={route('dashboard')}
                        className="mt-5 block text-center font-mono text-[10px] font-bold uppercase text-[#464498] underline decoration-2 underline-offset-4"
                    >
                        Voltar para a Dashboard
                    </Link>
                </section>
            </main>
        </AuthenticatedLayout>
    );
}
