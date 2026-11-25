import BrandLogo from '@/Components/BrandLogo';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

function AuthBrand({ dark = false }: { dark?: boolean }) {
    return (
        <Link href="/" aria-label="Estação Commit — início">
            <BrandLogo dark={dark} />
        </Link>
    );
}

export default function Guest({
    children,
    wide = false,
}: PropsWithChildren<{ wide?: boolean }>) {
    const fallback = usePage<PageProps>().props.flash?.auth_fallback;

    return (
        <main className="bg-ink min-h-[100dvh] p-2 text-white sm:p-4 lg:h-[100dvh] lg:overflow-hidden">
            <div className="relative mx-auto grid min-h-[calc(100dvh-1rem)] w-full max-w-[1500px] border-[3px] border-black bg-[#151515] shadow-[6px_6px_0_#464498] sm:min-h-[calc(100dvh-2rem)] lg:h-[calc(100dvh-2rem)] lg:min-h-0 lg:grid-cols-[.9fr_1.1fr]">
                <aside className="bg-purple relative hidden overflow-hidden border-r-[3px] border-black p-[clamp(1.5rem,4vh,3rem)] lg:flex lg:flex-col lg:justify-between">
                    <div className="relative z-20">
                        <AuthBrand />
                        <h2 className="mt-[clamp(1.5rem,5vh,3.5rem)] max-w-lg text-[clamp(2.5rem,4.2vw,4.5rem)] font-black leading-[.96] tracking-[-.05em]">
                            Toda missão começa com uma branch.
                        </h2>
                        <p className="mt-5 max-w-sm leading-7 text-white/70">
                            Entre na estação, prepare sua contribuição e avance
                            do primeiro comando à Pull Request.
                        </p>
                    </div>
                    <div className="relative z-10 mt-4 flex min-h-0 flex-1 items-center justify-center py-3">
                        <img
                            src="/images/estacao-commit/mascots/foguete-gato.webp"
                            alt="Foguete com personagem do jogo"
                            className="h-full w-full object-contain drop-shadow-[10px_13px_0_rgba(29,29,27,.45)]"
                        />
                    </div>
                    <p className="border-lime relative z-20 mt-8 border-l-4 pl-3 font-mono text-xs text-white/60">
                        git commit -m "começar missão"
                    </p>
                </aside>
                <section className="bg-paper text-ink flex min-h-0 items-start justify-center overflow-y-auto p-5 sm:p-7 lg:p-[clamp(1rem,3vh,2.5rem)]">
                    <div
                        className={`auth-content my-auto w-full py-2 ${wide ? 'max-w-3xl' : 'max-w-[390px]'}`}
                    >
                        <div className="border-ink/15 mb-10 border-b-2 pb-6 lg:hidden">
                            <AuthBrand dark />
                        </div>
                        {fallback && (
                            <div
                                className="mb-6 border-2 border-[#1d1d1b] bg-[#b4da33]/30 p-4 text-sm leading-6"
                                role="alert"
                            >
                                <p className="font-black">
                                    Não conseguimos concluir agora.
                                </p>
                                <p className="mt-1 text-[#393833]">
                                    Foi um problema do nosso lado, não dos seus
                                    dados. Tente de novo em instantes ou jogue já
                                    com um perfil temporário, sem e-mail e sem
                                    senha.
                                </p>
                                <Link
                                    className="mt-3 inline-flex min-h-11 items-center border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b]"
                                    href={route('guest.create')}
                                >
                                    Jogar com perfil temporário
                                </Link>
                            </div>
                        )}
                        {children}
                    </div>
                </section>
            </div>
        </main>
    );
}
