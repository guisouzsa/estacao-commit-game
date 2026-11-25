import GoogleLogo from '@/Components/GoogleLogo';
import { router } from '@inertiajs/react';
import { useState } from 'react';

function GoogleMark() {
    return (
        <span
            aria-hidden="true"
            className="grid size-10 shrink-0 place-items-center border-2 border-[#1d1d1b] bg-white text-lg font-black shadow-[2px_2px_0_#f0b429]"
        >
            <GoogleLogo className="size-6" />
        </span>
    );
}

export default function GoogleAccountForm({
    configured,
    linked,
}: {
    configured: boolean;
    linked: boolean;
}) {
    const [processing, setProcessing] = useState(false);

    const linkGoogle = () => {
        setProcessing(true);
        router.post(
            route('google.link'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <section className="flex h-full flex-col border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#f0b429] sm:p-6">
            <header className="border-b border-[#1d1d1b]/20 pb-4">
                <p className="font-mono text-xs uppercase tracking-[0.18em] text-[#464498]">
                    Conta / acesso social
                </p>
                <div className="mt-3 flex items-center gap-3">
                    <GoogleMark />
                    <div>
                        <h2 className="text-2xl font-black text-[#1d1d1b]">
                            Conta Google
                        </h2>
                        {linked ? (
                            <span className="mt-1 inline-flex border border-[#1d1d1b] bg-[#b4da33] px-2 py-0.5 font-mono text-[10px] font-black uppercase">
                                Vinculada
                            </span>
                        ) : null}
                    </div>
                </div>
            </header>

            <div className="mt-5 flex flex-1 flex-col">
                <p className="text-sm leading-6 text-[#5b5953]">
                    {linked
                        ? 'Sua conta está protegida e você também pode entrar usando o Google.'
                        : 'Vincule o Google para ter outra forma de entrar sem perder seu perfil, histórico ou vitórias.'}
                </p>

                {linked ? (
                    <p className="mt-auto border-l-4 border-[#b4da33] bg-white p-3 text-sm font-bold">
                        Nenhuma ação necessária. Seu login por e-mail continua
                        funcionando normalmente.
                    </p>
                ) : configured ? (
                    <button
                        type="button"
                        disabled={processing}
                        onClick={linkGoogle}
                        className="mt-auto min-h-12 self-start border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#464498] transition hover:-translate-y-0.5 hover:bg-[#f0b429] disabled:cursor-wait disabled:opacity-60"
                    >
                        {processing
                            ? 'Abrindo Google…'
                            : 'Vincular conta Google'}
                    </button>
                ) : (
                    <p className="mt-auto border-l-4 border-[#f0b429] bg-white p-3 text-sm">
                        O vínculo com Google está temporariamente indisponível.
                    </p>
                )}
            </div>
        </section>
    );
}
