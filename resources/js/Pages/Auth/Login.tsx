import Checkbox from '@/Components/Checkbox';
import GoogleLogo from '@/Components/GoogleLogo';
import InputError from '@/Components/InputError';
import PasswordVisibilityToggle from '@/Components/PasswordVisibilityToggle';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Login({
    status,
    canResetPassword,
    googleConfigured = false,
}: {
    status?: string;
    canResetPassword: boolean;
    googleConfigured?: boolean;
}) {
    const [show, setShow] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Entrar" />

            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">
                Acesso à estação
            </p>
            <h1 className="mt-2 text-4xl font-black tracking-tight">
                Bem-vindo de volta.
            </h1>
            <p className="text-ink/55 mt-2">
                Continue sua missão de onde parou.
            </p>

            {status && (
                <div className="border-lime mt-4 border-l-4 bg-white p-3 text-sm font-semibold shadow-[3px_3px_0_#b4da33]">
                    {status}
                </div>
            )}

            {googleConfigured && (
                <>
                    <a
                        href={route('google.redirect')}
                        className="border-ink mt-6 flex w-full items-center justify-center gap-3 border-2 bg-white px-4 py-3 font-bold shadow-[4px_4px_0_#464498] transition hover:-translate-y-0.5"
                    >
                        <GoogleLogo className="size-5" />{' '}
                        <span>Continuar com Google</span>
                    </a>
                    <Divider text="ou com e-mail" />
                </>
            )}

            <form
                noValidate
                onSubmit={submit}
                className={googleConfigured ? '' : 'mt-6'}
            >
                <div className="space-y-6">
                    <div>
                        <label className="auth-label" htmlFor="email">
                            E-mail
                        </label>
                        <input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="username"
                            autoFocus
                            className="auth-input"
                            aria-invalid={Boolean(errors.email)}
                            required
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <label className="auth-label" htmlFor="password">
                            Senha
                        </label>
                        <div className="relative">
                            <input
                                id="password"
                                type={show ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                autoComplete="current-password"
                                className="auth-input pr-20"
                                aria-invalid={Boolean(errors.password)}
                                required
                            />
                            <PasswordVisibilityToggle
                                visible={show}
                                onToggle={() => setShow(!show)}
                            />
                        </div>
                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>
                </div>

                <div className="my-4 flex items-center justify-between gap-3 text-sm">
                    <label className="flex items-center gap-2">
                        <Checkbox
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        Lembrar acesso
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="font-bold text-purple-700 hover:underline"
                        >
                            Esqueci minha senha
                        </Link>
                    )}
                </div>

                <button className="auth-submit" disabled={processing}>
                    {processing ? 'Entrando…' : 'Entrar na plataforma'}
                </button>
            </form>

            <p className="text-ink/55 mt-5 text-center text-sm">
                Ainda não tem conta?{' '}
                <Link
                    href={route('register')}
                    className="font-bold text-purple-700 hover:underline"
                >
                    Criar conta
                </Link>
            </p>
            <p className="text-ink/45 mt-2 text-center text-xs">
                Não recebeu o código?{' '}
                <Link
                    href={route('verification.code')}
                    className="font-bold text-purple-700 hover:underline"
                >
                    Continuar a verificação
                </Link>
            </p>
        </GuestLayout>
    );
}

function Divider({ text }: { text: string }) {
    return (
        <div className="text-ink/35 my-4 flex items-center gap-3 text-xs uppercase tracking-wider">
            <span className="bg-ink/10 h-px flex-1" />
            {text}
            <span className="bg-ink/10 h-px flex-1" />
        </div>
    );
}
