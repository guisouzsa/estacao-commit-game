import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

export default function UpdatePasswordForm({
    className = '',
}: {
    className?: string;
}) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={`flex h-full flex-col border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#464498] sm:p-6 ${className}`}>
            <header className="border-b border-[#1d1d1b]/20 pb-4">
                <p className="font-mono text-xs uppercase tracking-[0.18em] text-[#464498]">Conta / segurança</p>
                <h2 className="mt-2 text-2xl font-black text-[#1d1d1b]">
                    Alterar senha
                </h2>

                <p className="mt-1 text-sm leading-6 text-[#5b5953]">
                    Use uma senha longa e exclusiva para manter sua conta protegida.
                </p>
            </header>

            <form onSubmit={updatePassword} className="mt-5 flex flex-1 flex-col space-y-4">
                <div>
                    <InputLabel
                        htmlFor="current_password"
                        value="Senha atual"
                    />

                    <TextInput
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                        type="password"
                        className="mt-2 block w-full border-[#1d1d1b]/30 bg-white focus:border-[#464498] focus:ring-[#464498]"
                        autoComplete="current-password"
                    />

                    <InputError
                        message={errors.current_password}
                        className="mt-2"
                    />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Nova senha" />

                    <TextInput
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        className="mt-2 block w-full border-[#1d1d1b]/30 bg-white focus:border-[#464498] focus:ring-[#464498]"
                        autoComplete="new-password"
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Confirmar nova senha"
                    />

                    <TextInput
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        type="password"
                        className="mt-2 block w-full border-[#1d1d1b]/30 bg-white focus:border-[#464498] focus:ring-[#464498]"
                        autoComplete="new-password"
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <div className="mt-auto flex items-center gap-4 pt-1">
                    <PrimaryButton className="border-2 border-[#1d1d1b] bg-[#b4da33] text-[#1d1d1b] hover:bg-[#c5e84b] focus:ring-[#464498]" disabled={processing}>{processing ? 'Salvando...' : 'Salvar senha'}</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="font-mono text-xs font-bold text-[#464498]">
                            Senha atualizada.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
