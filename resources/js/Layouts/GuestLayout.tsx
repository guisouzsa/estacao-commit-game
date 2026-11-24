import BrandLogo from '@/Components/BrandLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

function AuthBrand({ dark = false }: { dark?: boolean }) {
    return <Link href="/" aria-label="Estação Commit — início">
        <BrandLogo dark={dark} />
    </Link>;
}

export default function Guest({ children }: PropsWithChildren) {
    return <main className="min-h-[100dvh] bg-ink p-2 text-white sm:p-4 lg:h-[100dvh] lg:overflow-hidden">
        <div className="relative mx-auto grid min-h-[calc(100dvh-1rem)] w-full max-w-[1500px] border-[3px] border-black bg-[#151515] shadow-[6px_6px_0_#464498] sm:min-h-[calc(100dvh-2rem)] lg:h-[calc(100dvh-2rem)] lg:min-h-0 lg:grid-cols-[.9fr_1.1fr]">
            <aside className="relative hidden overflow-hidden border-r-[3px] border-black bg-purple p-[clamp(1.5rem,4vh,3rem)] lg:flex lg:flex-col lg:justify-between">
                <div className="relative z-20">
                    <AuthBrand />
                    <h2 className="mt-[clamp(1.5rem,5vh,3.5rem)] max-w-lg text-[clamp(2.5rem,4.2vw,4.5rem)] font-black leading-[.96] tracking-[-.05em]">Toda missão começa com uma branch.</h2>
                    <p className="mt-5 max-w-sm leading-7 text-white/70">Entre na estação, prepare sua contribuição e avance do primeiro comando à Pull Request.</p>
                </div>
                <div className="relative z-10 mt-4 flex min-h-0 flex-1 items-center justify-center py-3">
                    <img src="/images/estacao-commit/mascots/foguete-gato.webp" alt="Foguete com personagem do jogo" className="h-full w-full object-contain drop-shadow-[10px_13px_0_rgba(29,29,27,.45)]" />
                </div>
                <p className="relative z-20 mt-8 border-l-4 border-lime pl-3 font-mono text-xs text-white/60">git commit -m "começar missão"</p>
            </aside>
            <section className="flex min-h-0 items-center justify-center overflow-y-auto bg-paper p-5 text-ink sm:p-7 lg:overflow-hidden lg:p-[clamp(1rem,3vh,2.5rem)]">
                <div className="auth-content w-full max-w-[390px] py-2">
                    <div className="mb-10 border-b-2 border-ink/15 pb-6 lg:hidden"><AuthBrand dark /></div>
                    {children}
                </div>
            </section>
        </div>
    </main>;
}
