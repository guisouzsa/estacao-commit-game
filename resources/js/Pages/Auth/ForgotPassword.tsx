import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Recuperar senha" />

            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">Recuperação de acesso</p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">Recupere sua senha.</h1>
            <p className="mt-3 text-ink/55">
                Informe seu e-mail e enviaremos as instruções para definir uma nova chave de acesso.
            </p>

            {status && (
                <div className="mt-5 border-l-4 border-lime bg-white p-3 text-sm font-semibold shadow-[3px_3px_0_#b4da33]">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="mt-8">
                <div>
                    <label className="auth-label" htmlFor="email">E-mail</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="auth-input"
                        autoFocus
                        autoComplete="email"
                        aria-invalid={Boolean(errors.email)}
                        required
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <button className="auth-submit !mt-7" disabled={processing}>
                    {processing ? 'Enviando link…' : 'Enviar link de recuperação'}
                </button>
            </form>

            <p className="mt-8 text-center text-sm text-ink/55">
                Lembrou sua senha?{' '}
                <Link href={route('login')} className="font-bold text-purple-700 hover:underline">
                    Entrar na estação
                </Link>
            </p>
        </GuestLayout>
    );
}
