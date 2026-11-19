import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';

export default function DeleteUserForm({
    className = '',
}: {
    className?: string;
}) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`flex h-full flex-col border-[3px] border-[#d01600] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#d01600] sm:p-6 ${className}`}>
            <header className="border-b border-[#d01600]/25 pb-4">
                <p className="font-mono text-xs uppercase tracking-[0.18em] text-[#d01600]">Conta / zona de risco</p>
                <h2 className="mt-2 text-2xl font-black text-[#1d1d1b]">
                    Excluir conta
                </h2>

                <p className="mt-1 text-sm leading-6 text-[#5b5953]">
                    Esta ação remove permanentemente sua conta e os dados associados. Ela não poderá ser desfeita.
                </p>
            </header>

            <DangerButton className="mt-auto self-start border-2 border-[#1d1d1b] shadow-[3px_3px_0_#1d1d1b]" onClick={confirmUserDeletion}>
                Excluir minha conta
            </DangerButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Tem certeza de que deseja excluir sua conta?
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Depois da exclusão, seus dados não poderão ser recuperados. Digite sua senha para confirmar.
                    </p>

                    <div className="mt-6">
                        <InputLabel
                            htmlFor="password"
                            value="Senha"
                            className="sr-only"
                        />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            className="mt-1 block w-3/4"
                            isFocused
                            placeholder="Digite sua senha"
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <SecondaryButton onClick={closeModal}>
                            Cancelar
                        </SecondaryButton>

                        <DangerButton className="ms-3" disabled={processing}>
                            Excluir conta
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
