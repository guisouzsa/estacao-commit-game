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
    onPreviewChange,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
    onPreviewChange?: (updates: {
        username?: string;
        display_name?: string;
        bio?: string;
    }) => void;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            username: user.username,
            display_name: user.display_name,
            bio: user.bio ?? '',
            email: user.email,
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section
            className={`border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[6px_6px_0_#1d1d1b] sm:p-7 ${className}`}
        >
            <header className="border-b border-[#1d1d1b]/20 pb-4">
                <p className="font-mono text-xs uppercase tracking-[0.18em] text-[#464498]">
                    Identidade / 01
                </p>
                <h2 className="mt-2 text-2xl font-black text-[#1d1d1b]">
                    Quem está por trás do commit?
                </h2>
                <p className="mt-1 text-sm leading-6 text-[#5b5953]">
                    Seu nome público e sua biografia aparecem no perfil. O
                    e-mail continua privado.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <div>
                    <InputLabel htmlFor="username" value="Username público" />

                    <TextInput
                        id="username"
                        className="mt-2 block w-full border-[#1d1d1b]/30 bg-white focus:border-[#464498] focus:ring-[#464498]"
                        value={data.username}
                        onChange={(e) => {
                            const username = e.target.value
                                .toLowerCase()
                                .replace(/[^a-z0-9_-]/g, '');
                            setData('username', username);
                            onPreviewChange?.({ username });
                        }}
                        required
                        isFocused
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.username} />
                    <p className="mt-1 text-xs text-[#68665f]">
                        3–30 caracteres: letras, números, _ ou -.
                    </p>
                </div>

                <div>
                    <InputLabel
                        htmlFor="display_name"
                        value="Nome de exibição"
                    />
                    <TextInput
                        id="display_name"
                        className="mt-2 block w-full border-[#1d1d1b]/30 bg-white focus:border-[#464498] focus:ring-[#464498]"
                        value={data.display_name}
                        onChange={(e) => {
                            const display_name = e.target.value;
                            setData('display_name', display_name);
                            onPreviewChange?.({ display_name });
                        }}
                        required
                    />
                    <InputError
                        className="mt-2"
                        message={errors.display_name}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="bio" value="Biografia" />
                    <textarea
                        id="bio"
                        className="mt-2 block w-full border border-[#1d1d1b]/30 bg-white px-3 py-2 text-sm text-[#1d1d1b] shadow-sm focus:border-[#464498] focus:ring-[#464498]"
                        rows={4}
                        maxLength={500}
                        value={data.bio}
                        onChange={(e) => {
                            const bio = e.target.value;
                            setData('bio', bio);
                            onPreviewChange?.({ bio });
                        }}
                    />
                    <InputError className="mt-2" message={errors.bio} />
                    <p className="mt-1 text-right font-mono text-xs text-[#68665f]">
                        {data.bio.length}/500
                    </p>
                </div>

                <div>
                    <InputLabel htmlFor="email" value="E-mail da conta" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-2 block w-full border-[#1d1d1b]/30 bg-white focus:border-[#464498] focus:ring-[#464498]"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-[#5b5953]">
                            Seu e-mail ainda não foi verificado.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="ml-1 underline decoration-2 underline-offset-2 hover:text-[#464498] focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-[#464498]"
                            >
                                Reenviar e-mail de verificação.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div
                                role="status"
                                className="mt-2 text-sm font-medium text-[#464498]"
                            >
                                Um novo link de verificação foi enviado.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton
                        disabled={processing}
                        className="border-2 border-[#1d1d1b] bg-[#b4da33] text-[#1d1d1b] hover:bg-[#c5e84b] focus:ring-[#464498]"
                    >
                        {processing ? 'Salvando...' : 'Salvar informações'}
                    </PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p
                            role="status"
                            className="font-mono text-xs font-bold text-[#464498]"
                        >
                            Informações salvas.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
