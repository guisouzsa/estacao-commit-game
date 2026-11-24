import { router } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';

type Status =
    | { state: 'idle' }
    | { state: 'searching' }
    | { state: 'cancelled' }
    | { state: 'unavailable'; message: string }
    | { state: 'connection' };

type CancelToken = { cancel: () => void };

/** Aceita o link inteiro ou só o código. */
const extractCode = (raw: string) =>
    raw.trim().split('/').filter(Boolean).pop() ?? '';

/**
 * Entrar numa sala por código ou link, com busca CANCELÁVEL.
 *
 * - Cancelar interrompe a requisição de verdade (cancelToken do Inertia/axios).
 * - Cada tentativa tem um número: respostas de tentativas canceladas ou antigas são ignoradas,
 *   então ninguém "entra" numa sala depois de cancelar nem vê mensagem velha.
 * - Se o pedido já chegou ao servidor e ele concluiu a entrada, a sala aparece em "Suas salas";
 *   o cliente só não é levado até ela.
 */
export default function JoinByCodeForm() {
    const [code, setCode] = useState('');
    const [status, setStatus] = useState<Status>({ state: 'idle' });
    const attempt = useRef(0);
    const token = useRef<CancelToken | null>(null);
    const input = useRef<HTMLInputElement>(null);
    const searching = status.state === 'searching';

    // Sair da tela durante a busca também cancela.
    useEffect(
        () => () => {
            attempt.current += 1;
            token.current?.cancel();
        },
        [],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const joinCode = extractCode(code);
        if (!joinCode || searching) return; // sem duplo clique

        const mine = ++attempt.current;
        const stale = () => mine !== attempt.current;
        let settled = false;
        setStatus({ state: 'searching' });

        router.post(
            route('rooms.join', joinCode),
            {},
            {
                preserveScroll: true,
                onCancelToken: (cancelToken) => {
                    token.current = cancelToken;
                },
                onSuccess: (page) => {
                    if (stale()) return;
                    settled = true;
                    // Falhas de negócio voltam como aviso de erro (sala não existe, cheia, expirada...).
                    const flash = (
                        page.props as {
                            flash?: {
                                toast?: {
                                    tone?: string;
                                    message?: string;
                                } | null;
                            };
                        }
                    ).flash?.toast;
                    if (flash?.tone === 'error' || flash?.tone === 'warning') {
                        setStatus({
                            state: 'unavailable',
                            message:
                                flash.message ??
                                'Não foi possível entrar nessa sala.',
                        });
                    }
                },
                onError: (errors) => {
                    if (stale()) return;
                    settled = true;
                    setStatus({
                        state: 'unavailable',
                        message: String(
                            Object.values(errors)[0] ??
                                'Esse código não pode ser usado.',
                        ),
                    });
                },
                onFinish: () => {
                    if (stale()) return;
                    token.current = null;
                    // Terminou sem sucesso, sem resposta de negócio e sem cancelar: a conexão falhou.
                    setStatus((current) =>
                        current.state === 'searching'
                            ? settled
                                ? { state: 'idle' }
                                : { state: 'connection' }
                            : current,
                    );
                },
            },
        );
    };

    const cancel = () => {
        attempt.current += 1; // invalida qualquer resposta que ainda chegue
        token.current?.cancel();
        token.current = null;
        setStatus({ state: 'cancelled' });
        setCode('');
        window.setTimeout(() => input.current?.focus(), 0);
    };

    const note =
        status.state === 'searching'
            ? { tone: 'info', text: 'Procurando sua sala…' }
            : status.state === 'cancelled'
              ? {
                    tone: 'info',
                    text: 'Busca cancelada. Você pode informar outro código.',
                }
              : status.state === 'unavailable'
                ? { tone: 'error', text: status.message }
                : status.state === 'connection'
                  ? {
                        tone: 'error',
                        text: 'Não conseguimos falar com o servidor. Verifique a internet e tente de novo.',
                    }
                  : null;

    return (
        <form className="mt-5" noValidate onSubmit={submit}>
            <label className="text-sm font-bold" htmlFor="invite">
                Link ou código do convite
            </label>
            <input
                aria-describedby="join-status"
                autoComplete="off"
                className="mt-2 min-h-12 w-full border-2 border-[#1d1d1b] bg-white px-3 disabled:opacity-60"
                disabled={searching}
                id="invite"
                onChange={(event) => {
                    setCode(event.target.value);
                    if (status.state !== 'idle') setStatus({ state: 'idle' });
                }}
                placeholder="Cole o convite aqui"
                ref={input}
                required
                value={code}
            />

            <div className="mt-3 flex flex-wrap items-center gap-2">
                <button
                    className="min-h-11 border-2 border-[#1d1d1b] bg-white px-5 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#b4da33] disabled:opacity-60"
                    disabled={searching || extractCode(code) === ''}
                    type="submit"
                >
                    {searching ? 'Conectando…' : 'Entrar com convite'}
                </button>
                {searching && (
                    <button
                        className="min-h-11 border-2 border-[#d01600] bg-white px-4 font-mono text-xs font-black uppercase text-[#d01600] hover:bg-[#d01600] hover:text-white"
                        onClick={cancel}
                        type="button"
                    >
                        Cancelar
                    </button>
                )}
            </div>

            <p
                aria-live="polite"
                className={`mt-3 min-h-5 text-sm font-semibold ${note?.tone === 'error' ? 'text-[#d01600]' : 'text-[#464498]'}`}
                id="join-status"
                role={note?.tone === 'error' ? 'alert' : 'status'}
            >
                {note?.text}
            </p>
        </form>
    );
}
