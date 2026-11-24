import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useState } from 'react';

export default function VerifyEmailCode({
    maskedEmail,
    cooldown,
    status,
}: {
    maskedEmail: string;
    cooldown: number;
    status?: string;
}) {
    const { data, setData, post, processing, errors } = useForm({ code: '' });
    const resend = useForm({});
    const [wait, setWait] = useState(cooldown);

    useEffect(() => setWait(cooldown), [cooldown]);
    useEffect(() => {
        if (wait <= 0) return;
        const id = setTimeout(() => setWait((w) => w - 1), 1000);
        return () => clearTimeout(id);
    }, [wait]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('verification.verify'));
    };

    return (
        <GuestLayout>
            <Head title="Confirmar e-mail" />

            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">Verificação de e-mail</p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">Digite o código.</h1>
            <p className="mt-3 text-ink/55">
                Enviamos um código de 6 números para <strong>{maskedEmail}</strong>. Ele vale por poucos minutos.
            </p>

            {status && (
                <div className="mt-5 border-l-4 border-lime bg-white p-3 text-sm font-semibold shadow-[3px_3px_0_#b4da33]">
                    {status}
                </div>
            )}

            <form noValidate onSubmit={submit} className="mt-8">
                <label className="auth-label" htmlFor="code">Código</label>
                <input
                    id="code"
                    name="code"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={6}
                    value={data.code}
                    className="auth-input text-center font-mono text-2xl tracking-[.5em]"
                    autoFocus
                    aria-invalid={Boolean(errors.code)}
                    onChange={(event) => setData('code', event.target.value.replace(/\D/g, '').slice(0, 6))}
                />
                <InputError message={errors.code} className="mt-2" />

                <button className="auth-submit !mt-7" disabled={processing || data.code.length !== 6}>
                    {processing ? 'Conferindo…' : 'Confirmar e-mail'}
                </button>
            </form>

            <div className="mt-6 flex items-center justify-between text-sm">
                <button
                    type="button"
                    className="font-bold text-purple-700 hover:underline disabled:opacity-40 disabled:no-underline"
                    disabled={wait > 0 || resend.processing}
                    onClick={() => resend.post(route('verification.resend'))}
                >
                    {wait > 0 ? `Reenviar código em ${wait}s` : 'Reenviar código'}
                </button>
                <Link href={route('register')} className="text-ink/55 hover:underline">
                    Usar outro e-mail
                </Link>
            </div>
        </GuestLayout>
    );
}
