import GoogleLogo from '@/Components/GoogleLogo';
import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function GoogleUsername({
    displayName,
    email,
}: {
    displayName: string;
    email: string;
}) {
    const { data, setData, post, processing, errors } = useForm({
        username: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('google.username.store'));
    };

    return (
        <GuestLayout back={{ href: route('login'), label: 'Cancelar e voltar' }}>
            <Head title="Escolha seu username" />

            <p className="flex items-center gap-2 font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700"><GoogleLogo className="size-4" /> Cadastro com Google</p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">Defina seu nickname.</h1>
            <p className="mt-3 text-ink/55">
                {displayName
                    ? `Olá, ${displayName}! Escolha seu username público para finalizar o cadastro com Google.`
                    : 'Escolha seu username público para finalizar o cadastro com Google.'}
            </p>
            <p className="mt-1 font-mono text-xs text-purple-700">{email}</p>

            <form noValidate onSubmit={submit} className="mt-8 space-y-6">
                <div>
                    <label className="auth-label" htmlFor="username">Username único</label>
                    <input
                        id="username"
                        name="username"
                        value={data.username}
                        className="auth-input"
                        autoComplete="username"
                        minLength={3}
                        maxLength={30}
                        pattern="[A-Za-z0-9_-]{3,30}"
                        title="De 3 a 30 caracteres: comece com uma letra e use letras sem acento, números, _ ou -"
                        autoFocus
                        required
                        aria-invalid={Boolean(errors.username)}
                        onChange={(event) =>
                            setData(
                                'username',
                                event.target.value
                                    .toLowerCase()
                                    .replace(/[^a-z0-9_-]/g, ''),
                            )
                        }
                    />
                    <InputError message={errors.username} className="mt-2" />
                    <p className="mt-2 text-xs leading-5 text-ink/40">
                        Seu nickname será sua identificação nas partidas, salas e rankings da estação.
                    </p>
                </div>

                <button className="auth-submit !mt-7" disabled={processing}>
                    {processing ? 'Concluindo cadastro…' : 'Concluir cadastro e entrar'}
                </button>
            </form>
        </GuestLayout>
    );
}
