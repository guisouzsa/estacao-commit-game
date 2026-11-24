import InputError from '@/Components/InputError';
import PasswordVisibilityToggle from '@/Components/PasswordVisibilityToggle';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const [show, setShow] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Redefinir senha" />

            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">Chave de acesso</p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">Crie uma nova senha.</h1>
            <p className="mt-3 mb-6 text-ink/55">
                Defina sua nova credencial para retomar suas missões na estação.
            </p>

            <form noValidate onSubmit={submit} className="space-y-6">
                <div>
                    <label className="auth-label" htmlFor="email">E-mail</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="auth-input"
                        autoComplete="username"
                        aria-invalid={Boolean(errors.email)}
                        required
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <label className="auth-label" htmlFor="password">Nova senha</label>
                    <div className="relative">
                        <input
                            id="password"
                            type={show ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            className="auth-input pr-20"
                            autoComplete="new-password"
                            autoFocus
                            aria-invalid={Boolean(errors.password)}
                            required
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <PasswordVisibilityToggle visible={show} onToggle={() => setShow(!show)} />
                    </div>
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <label className="auth-label" htmlFor="password_confirmation">Confirmar nova senha</label>
                    <input
                        id="password_confirmation"
                        type={show ? 'text' : 'password'}
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="auth-input"
                        autoComplete="new-password"
                        aria-invalid={Boolean(errors.password_confirmation)}
                        required
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <button className="auth-submit !mt-7" disabled={processing}>
                    {processing ? 'Redefinindo…' : 'Redefinir senha'}
                </button>
            </form>
        </GuestLayout>
    );
}
