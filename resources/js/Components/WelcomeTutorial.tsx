import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Modal from './Modal';
const steps = [
    [
        'Objetivo',
        'Chegue ao fim do percurso antes dos outros jogadores. Quem jogar a Pull Request primeiro vence. A partida tem quatro jogadores e cada um joga na sua vez.',
        '/images/estacao-commit/mascots/foguete-gato.webp',
    ],
    [
        'Etapa 1: Treinamento',
        'Nesta etapa você só joga 3 cartas, nesta ordem: Git Login, git clone e git checkout -b.',
        '/images/estacao-commit/corrected/git-login.webp',
    ],
    [
        'Etapa 2: Desenvolvimento',
        'São 4 ciclos. Em cada ciclo você só joga: o bloco, git add e git commit, nesta ordem.',
        '/images/estacao-commit/corrected/git-commit.webp',
    ],
    [
        'Etapa 3: Envio',
        'Aqui você só joga git pull, git push e, por último, a Pull Request. Quem jogar a Pull Request vence na hora.',
        '/images/estacao-commit/corrected/pull-request.webp',
    ],
    [
        'Trocas e ataques',
        'Na sua vez, jogue a carta da etapa ou troque uma carta (trocar gasta a vez). Limpar Credenciais manda alguém do Treinamento de volta ao início. Merge Conflict faz a pessoa perder a próxima vez.',
        '/images/estacao-commit/corrected/merge-conflict.webp',
    ],
];
export default function WelcomeTutorial() {
    const user = usePage().props.auth.user as {
        onboarding_completed_at?: string | null;
    };
    const [open, setOpen] = useState(!user.onboarding_completed_at);
    const [tutorial, setTutorial] = useState(false);
    const [step, setStep] = useState(0);
    const [busy, setBusy] = useState(false);
    const complete = () => {
        setBusy(true);
        router.post(
            route('onboarding.complete'),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setBusy(false),
            },
        );
    };
    if (!open) return null;
    const current = steps[step];
    return (
        <Modal show={open} onClose={() => {}} maxWidth="2xl">
            <div className="p-6">
                {!tutorial ? (
                    <>
                        <img
                            src="/images/estacao-commit/mascots/foguete-gato.webp"
                            alt="Foguete do Estação Commit"
                            className="mx-auto h-32 w-auto object-contain"
                        />
                        <p className="mt-4 text-center font-mono text-xs font-black uppercase tracking-[.18em] text-[#464498]">
                            Primeiro acesso
                        </p>
                        <h2 className="mt-2 text-center text-3xl font-black">
                            Bem-vindo ao Estação Commit!
                        </h2>
                        <p className="mx-auto mt-3 max-w-lg text-center text-sm leading-6 text-[#5b5953]">
                            Prepare sua contribuição, avance pelas etapas do Git
                            e seja o primeiro a abrir uma Pull Request válida!
                        </p>
                        <div className="mt-6 flex flex-wrap justify-center gap-3">
                            <button
                                disabled={busy}
                                onClick={complete}
                                className="min-h-11 border-2 border-[#1d1d1b] bg-[#b4da33] px-5 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b]"
                            >
                                Vamos começar
                            </button>
                            <button
                                onClick={() => setTutorial(true)}
                                className="min-h-11 border-2 border-[#1d1d1b] bg-white px-5 font-mono text-xs font-black uppercase"
                            >
                                Aprender a jogar
                            </button>
                        </div>
                    </>
                ) : (
                    <>
                        <div className="flex items-center justify-between gap-3">
                            <p className="font-mono text-xs font-black uppercase text-[#464498]">
                                Tutorial {step + 1}/{steps.length}
                            </p>
                            <button
                                onClick={() => setTutorial(false)}
                                className="text-sm font-bold underline"
                            >
                                Voltar ao início
                            </button>
                        </div>
                        <div className="mt-3 h-2 border border-[#1d1d1b] bg-white">
                            <div
                                className="h-full bg-[#b4da33]"
                                style={{
                                    width: `${((step + 1) / steps.length) * 100}%`,
                                }}
                            />
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-[180px_1fr] sm:items-center">
                            <img
                                src={current[2]}
                                alt=""
                                className="mx-auto max-h-52 w-full object-contain"
                            />
                            <div>
                                <h2 className="text-2xl font-black">
                                    {current[0]}
                                </h2>
                                <p className="mt-3 text-sm leading-7 text-[#4f4d48]">
                                    {current[1]}
                                </p>
                            </div>
                        </div>
                        <div className="mt-6 flex justify-between gap-3">
                            <button
                                disabled={step === 0}
                                onClick={() => setStep((s) => s - 1)}
                                className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-bold uppercase disabled:opacity-40"
                            >
                                Voltar
                            </button>
                            {step < steps.length - 1 ? (
                                <button
                                    onClick={() => setStep((s) => s + 1)}
                                    className="min-h-11 border-2 border-[#1d1d1b] bg-[#b4da33] px-5 font-mono text-xs font-black uppercase"
                                >
                                    Avançar
                                </button>
                            ) : (
                                <button
                                    disabled={busy}
                                    onClick={complete}
                                    className="min-h-11 border-2 border-[#1d1d1b] bg-[#b4da33] px-5 font-mono text-xs font-black uppercase"
                                >
                                    Concluir tutorial
                                </button>
                            )}
                        </div>
                    </>
                )}
            </div>
        </Modal>
    );
}
