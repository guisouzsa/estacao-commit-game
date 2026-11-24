import InputError from '@/Components/InputError';
import PasswordVisibilityToggle from '@/Components/PasswordVisibilityToggle';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function ConfirmPassword() {
    const [show, setShow] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirmar senha" />

            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">Área restrita</p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">Confirme sua senha.</h1>
            <p className="mt-3 mb-6 text-ink/55">
                Esta é uma área de segurança elevada. Confirme sua senha atual para continuar a operação.
            </p>

            <form noValidate onSubmit={submit}>
                <div>
                    <label className="auth-label" htmlFor="password">Senha</label>
                    <div className="relative">
                        <input
                            id="password"
                            type={show ? 'text' : 'password'}
                            name="password"
                            value={data.password}
                            className="auth-input pr-20"
                            autoComplete="current-password"
                            autoFocus
                            aria-invalid={Boolean(errors.password)}
                            required
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <PasswordVisibilityToggle visible={show} onToggle={() => setShow(!show)} />
                    </div>
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <button className="auth-submit !mt-7" disabled={processing}>
                    {processing ? 'Confirmando…' : 'Confirmar e continuar'}
                </button>
            </form>
        </GuestLayout>
    );
}
