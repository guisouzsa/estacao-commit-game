import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificar e-mail" />

            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">Confirmação de acesso</p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">Verifique seu e-mail.</h1>
            <p className="mt-3 text-ink/55">
                Enviamos um link para confirmar seu endereço. Abra-o para liberar o acesso à estação.
            </p>

            {status === 'verification-link-sent' && (
                <div className="mt-5 border-l-4 border-lime bg-white p-3 text-sm font-semibold shadow-[3px_3px_0_#b4da33]">
                    Um novo link de verificação foi enviado para o seu e-mail.
                </div>
            )}

            <form onSubmit={submit} className="mt-8 space-y-4">
                <button className="auth-submit" disabled={processing}>
                    {processing ? 'Reenviando…' : 'Reenviar e-mail de verificação'}
                </button>

                <div className="text-center pt-2">
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="text-sm font-bold text-purple-700 hover:underline"
                    >
                        Sair da conta
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
