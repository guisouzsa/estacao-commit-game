import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            username: user.username,
            email: user.email,
        });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section
            className={`flex flex-col border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#1d1d1b] sm:p-6 ${className}`}
        >
            <header className="border-b border-[#1d1d1b]/20 pb-4">
                <p className="font-mono text-xs uppercase tracking-[0.18em] text-[#464498]">
                    Conta / e-mail
                </p>
                <h2 className="mt-2 text-2xl font-black text-[#1d1d1b]">
                    E-mail privado
                </h2>
                <p className="mt-1 text-sm leading-6 text-[#5b5953]">
                    Usado para acesso e recuperação da conta. Não aparece no
                    perfil público.
                </p>
            </header>

            <form className="mt-5 flex flex-1 flex-col gap-4" onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="E-mail da conta" />
                    <TextInput
                        autoComplete="email"
                        className="mt-2 block w-full border-[#1d1d1b]/30 bg-white focus:border-[#464498] focus:ring-[#464498]"
                        id="email"
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        required
                        type="email"
                        value={data.email}
                    />
                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-[#5b5953]">
                            Seu e-mail ainda não foi verificado.
                            <Link
                                as="button"
                                className="ml-1 underline decoration-2 underline-offset-2 hover:text-[#464498] focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-[#464498]"
                                href={route('verification.send')}
                                method="post"
                            >
                                Reenviar e-mail de verificação.
                            </Link>
                        </p>
                        {status === 'verification-link-sent' && (
                            <div
                                className="mt-2 text-sm font-medium text-[#464498]"
                                role="status"
                            >
                                Um novo link de verificação foi enviado.
                            </div>
                        )}
                    </div>
                )}

                <div className="mt-auto flex flex-wrap items-center gap-3 pt-2">
                    <PrimaryButton
                        className="border-2 border-[#1d1d1b] bg-[#b4da33] text-[#1d1d1b] hover:bg-[#c5e84b] focus:ring-[#464498]"
                        disabled={processing}
                    >
                        {processing ? 'Salvando...' : 'Salvar e-mail'}
                    </PrimaryButton>
                    <Transition
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                        show={recentlySuccessful}
                    >
                        <p
                            className="font-mono text-xs font-bold text-[#464498]"
                            role="status"
                        >
                            E-mail atualizado.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
