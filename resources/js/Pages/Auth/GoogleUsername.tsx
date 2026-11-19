import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
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
        <GuestLayout>
            <Head title="Escolha seu username" />

            <div className="mb-6">
                <h1 className="text-xl font-bold text-gray-800">
                    Escolha seu username
                </h1>
                <p className="mt-2 text-sm text-gray-600">
                    {displayName
                        ? `Olá, ${displayName}. Defina seu nome público para concluir o cadastro.`
                        : 'Defina seu nome público para concluir o cadastro.'}
                </p>
                <p className="mt-1 text-sm text-gray-500">{email}</p>
            </div>

            <form onSubmit={submit}>
                <InputLabel htmlFor="username" value="Username público" />
                <TextInput
                    id="username"
                    name="username"
                    value={data.username}
                    className="mt-1 block w-full"
                    autoComplete="username"
                    minLength={3}
                    maxLength={30}
                    pattern="[A-Za-z0-9_-]{3,30}"
                    title="Use de 3 a 30 letras, números, _ ou -"
                    autoFocus
                    required
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

                <p className="mt-3 text-xs leading-5 text-gray-500">
                    O username é exclusivo e pode ser diferente do seu nome de
                    exibição.
                </p>

                <PrimaryButton className="mt-6 w-full" disabled={processing}>
                    Concluir cadastro
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
