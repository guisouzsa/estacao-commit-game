import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({
    code,
    participants,
}: {
    code: string;
    participants: number;
}) {
    const [joining, setJoining] = useState(false);
    return (
        <AuthenticatedLayout>
            <Head title="Convite para jogar" />
            <main className="grid min-h-[calc(100dvh-4rem)] place-items-center bg-[#e8e5dc] px-4 py-8 text-[#1d1d1b]">
                <section className="w-full max-w-lg border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-6 shadow-[8px_8px_0_#464498] sm:p-8">
                    <p className="font-mono text-xs font-black uppercase tracking-[.18em] text-[#464498]">
                        Convite privado
                    </p>
                    <h1 className="mt-2 text-3xl font-black">
                        Entre para a missão
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-[#5b5953]">
                        Você recebeu acesso a uma sala do Estação Commit. Sua
                        identidade atual será usada na mesa.
                    </p>
                    <div className="mt-5 flex items-center justify-between border-2 border-[#1d1d1b] bg-white p-3">
                        <div>
                            <span className="block font-mono text-[9px] uppercase text-[#68665f]">
                                Código
                            </span>
                            <strong className="font-mono text-2xl tracking-[.2em]">
                                {code}
                            </strong>
                        </div>
                        <strong className="border-2 border-[#1d1d1b] bg-[#b4da33] px-3 py-2 font-mono">
                            {participants}/4
                        </strong>
                    </div>
                    <button
                        disabled={joining || participants >= 4}
                        onClick={() => {
                            setJoining(true);
                            router.post(
                                route('rooms.join', code),
                                {},
                                { onFinish: () => setJoining(false) },
                            );
                        }}
                        className="mt-5 min-h-12 w-full border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-xs font-black uppercase shadow-[4px_4px_0_#1d1d1b] disabled:opacity-50"
                    >
                        {participants >= 4
                            ? 'Sala completa'
                            : joining
                              ? 'Entrando…'
                              : 'Entrar na sala'}
                    </button>
                    <Link
                        href={route('dashboard')}
                        className="mt-5 block text-center font-mono text-[10px] font-bold uppercase text-[#464498] underline decoration-2 underline-offset-4"
                    >
                        Voltar para a Dashboard
                    </Link>
                </section>
            </main>
        </AuthenticatedLayout>
    );
}
