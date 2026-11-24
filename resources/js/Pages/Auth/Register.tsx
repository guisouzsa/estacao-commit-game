import InputError from '@/Components/InputError';
import HowToPlayButton from '@/Components/HowToPlayButton';
import PasswordVisibilityToggle from '@/Components/PasswordVisibilityToggle';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Register({
    status,
    googleConfigured = false,
}: {
    status?: string;
    googleConfigured?: boolean;
}) {
    const [show, setShow] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Criar conta" />

            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">Novo agente</p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">Prepare sua missão.</h1>
            <p className="mt-3 text-ink/55">Seu username será sua identificação pública na estação.</p>

            {status && (
                <div className="mt-5 border-l-4 border-lime bg-white p-3 text-sm font-semibold shadow-[3px_3px_0_#b4da33]">
                    {status}
                </div>
            )}

            {googleConfigured && (
                <>
                    <a
                        href={route('google.redirect')}
                        className="mt-7 flex w-full items-center justify-center gap-3 border-2 border-ink bg-white px-4 py-3 font-bold shadow-[4px_4px_0_#464498] transition hover:-translate-y-0.5"
                    >
                        G <span>Continuar com Google</span>
                    </a>
                    <Divider text="ou cadastre-se com e-mail" />
                </>
            )}

            <form noValidate onSubmit={submit} className={googleConfigured ? '' : 'mt-8'}>
                <div className="space-y-6">
                    <div>
                        <label className="auth-label" htmlFor="username">Username único</label>
                        <input
                            id="username"
                            className="auth-input"
                            value={data.username}
                            onChange={(e) =>
                                setData('username', e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''))
                            }
                            pattern="[A-Za-z0-9_-]{3,30}"
                            minLength={3}
                            maxLength={30}
                            autoComplete="username"
                            autoFocus
                            aria-invalid={Boolean(errors.username)}
                            required
                        />
                        <InputError message={errors.username} className="mt-2" />
                    </div>

                    <div>
                        <label className="auth-label" htmlFor="email">E-mail</label>
                        <input
                            id="email"
                            className="auth-input"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoComplete="email"
                            aria-invalid={Boolean(errors.email)}
                            required
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <label className="auth-label" htmlFor="password">Senha</label>
                        <div className="relative">
                            <input
                                id="password"
                                className="auth-input pr-20"
                                type={show ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="new-password"
                                aria-invalid={Boolean(errors.password)}
                                required
                            />
                            <PasswordVisibilityToggle visible={show} onToggle={() => setShow(!show)} />
                        </div>
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <label className="auth-label" htmlFor="password_confirmation">Confirmar senha</label>
                        <input
                            id="password_confirmation"
                            className="auth-input"
                            type={show ? 'text' : 'password'}
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                            aria-invalid={Boolean(errors.password_confirmation)}
                            required
                        />
                        <InputError message={errors.password_confirmation} className="mt-2" />
                    </div>
                </div>

                <button className="auth-submit !mt-7" disabled={processing}>
                    {processing ? 'Criando conta…' : 'Criar minha conta'}
                </button>
            </form>

            <p className="mt-8 text-center text-sm text-ink/55">
                Já tem conta?{' '}
                <Link href={route('login')} className="font-bold text-purple-700 hover:underline">
                    Entrar na estação
                </Link>
            </p>
            <div className="mt-5">
                <HowToPlayButton />
            </div>
        </GuestLayout>
    );
}

function Divider({ text }: { text: string }) {
    return (
        <div className="my-5 flex items-center gap-3 text-xs uppercase tracking-wider text-ink/35">
            <span className="h-px flex-1 bg-ink/10" />
            {text}
            <span className="h-px flex-1 bg-ink/10" />
        </div>
    );
}
