import Modal from '@/Components/Modal';
import { useEffect, useRef, useState } from 'react';

const STORAGE_KEY = 'ec:mission-tutorial:v1';
let seenInThisPage = false; // se o navegador não guardar nada, ao menos não repete na mesma visita

/** Já viu a missão neste navegador? (localStorage; nunca quebra se estiver bloqueado) */
export function hasSeenMissionTutorial(): boolean {
    if (seenInThisPage) return true;
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function rememberMissionTutorial() {
    seenInThisPage = true;
    try {
        window.localStorage.setItem(STORAGE_KEY, '1');
    } catch {
        /* sem armazenamento: vale só nesta visita */
    }
}

const ART = '/images/estacao-commit';

/** Cartas que EXISTEM no jogo, com o texto do documento de regras. Nada inventado. */
const COMMAND_CARDS = [
    {
        image: 'git-clone.png',
        name: 'git clone',
        text: 'Cria uma cópia do projeto na sua máquina.',
    },
    {
        image: 'git-add.png',
        name: 'git add .',
        text: 'Prepara as alterações para o commit.',
    },
    {
        image: 'git-commit.png',
        name: 'git commit',
        text: 'Registra as alterações com uma mensagem.',
    },
    {
        image: 'git-push.png',
        name: 'git push',
        text: 'Envia os commits para o repositório remoto.',
    },
    {
        image: 'git-pull.png',
        name: 'git pull',
        text: 'Atualiza o seu projeto com as mudanças remotas.',
    },
] as const;

type Step = { title: string; kicker: string };
const STEPS: Step[] = [
    { kicker: 'Sua missão', title: 'Bem-vindo à equipe, estagiário!' },
    { kicker: 'Como você vai trabalhar', title: 'Tudo começa com o código!' },
    {
        kicker: 'Trabalho em equipe',
        title: 'Ninguém lança um foguete sozinho!',
    },
    {
        kicker: 'Lançamento',
        title: 'O objetivo é chegar primeiro ao lançamento!',
    },
];

function Terminal({ children }: { children: string }) {
    return (
        <p className="mt-4 border-l-4 border-[#b4da33] bg-[#1d1d1b] px-3 py-2 font-mono text-xs text-[#b4da33]">
            <span className="text-white/50">$ </span>
            {children}
        </p>
    );
}

function StepBody({ step }: { step: number }) {
    if (step === 0) {
        return (
            <div className="grid items-center gap-4 sm:grid-cols-[1fr_auto]">
                <div className="space-y-3 text-sm leading-6">
                    <p>
                        Você acaba de entrar em uma equipe de desenvolvimento.
                        Sua missão é ajudar a empresa a construir e{' '}
                        <strong>lançar um foguete</strong>!
                    </p>
                    <p>
                        Mas existe um problema: o foguete ainda não está pronto.
                        O código precisa ser desenvolvido, organizado e
                        integrado corretamente.
                    </p>
                    <p className="font-bold">E é aí que você entra!</p>
                    <Terminal>git checkout -b missao-foguete</Terminal>
                </div>
                <img
                    alt=""
                    className="mx-auto h-36 w-auto object-contain sm:h-44"
                    src={`${ART}/mascots/foguete-gato.png`}
                />
            </div>
        );
    }

    if (step === 1) {
        return (
            <div>
                <p className="text-sm leading-6">
                    Durante o jogo você usa{' '}
                    <strong>cartas que representam comandos do Git</strong>.
                    Cada carta tem uma função e ajuda a sua missão a avançar.
                </p>
                <ul className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {COMMAND_CARDS.map((card) => (
                        <li
                            className="flex items-center gap-2 border-2 border-[#1d1d1b] bg-white p-1.5"
                            key={card.name}
                        >
                            <img
                                alt=""
                                className="h-16 w-12 shrink-0 rounded-sm border border-[#1d1d1b] object-cover"
                                loading="lazy"
                                src={`${ART}/corrected/${card.image}`}
                            />
                            <span className="min-w-0">
                                <strong className="block font-mono text-[11px]">
                                    {card.name}
                                </strong>
                                <span className="block text-[11px] leading-4 text-[#5b5953]">
                                    {card.text}
                                </span>
                            </span>
                        </li>
                    ))}
                    <li className="flex items-center gap-2 border-2 border-[#d01600] bg-white p-1.5">
                        <img
                            alt=""
                            className="h-16 w-12 shrink-0 rounded-sm border border-[#1d1d1b] object-cover"
                            loading="lazy"
                            src={`${ART}/corrected/merge-conflict.png`}
                        />
                        <span className="min-w-0">
                            <strong className="block font-mono text-[11px]">
                                Merge Conflict
                            </strong>
                            <span className="block text-[11px] leading-4 text-[#5b5953]">
                                Carta de ataque: o alvo perde a próxima vez.
                            </span>
                        </span>
                    </li>
                </ul>
                <p className="mt-3 text-xs text-[#5b5953]">
                    Também existem Git Login, <code>git checkout -b</code>, os
                    Blocos de Código, Limpar Credenciais e a Pull Request. Você
                    vai conhecê-los jogando.
                </p>
            </div>
        );
    }

    if (step === 2) {
        return (
            <div className="grid items-center gap-4 sm:grid-cols-[1fr_12rem]">
                <div className="space-y-3 text-sm leading-6">
                    <p>
                        Vocês são <strong>quatro estagiários</strong> na mesma
                        mesa, mas cada um tem as próprias cartas e o próprio
                        percurso. Joga <strong>um de cada vez, em ordem</strong>
                        .
                    </p>
                    <p>
                        Quando não tiver a carta que precisa, troque uma — isso
                        conta como a sua jogada. Fique de olho nos adversários:
                        há cartas que atrapalham quem está na frente.
                    </p>
                    <p className="font-bold">
                        Use as suas cartas com estratégia!
                    </p>
                </div>
                <img
                    alt="Mesa com o tabuleiro do Estação Commit"
                    className="mx-auto w-40 rounded-md border-[3px] border-[#1d1d1b] sm:w-48"
                    loading="lazy"
                    src={`${ART}/tabuleiro.png`}
                />
            </div>
        );
    }

    return (
        <div className="grid items-center gap-4 sm:grid-cols-[1fr_auto]">
            <div className="space-y-3 text-sm leading-6">
                <p>
                    Agora você já conhece a missão. Para vencer, cumpra o
                    caminho nesta ordem:
                </p>
                <ol className="list-decimal space-y-1 pl-5 text-[13px]">
                    <li>
                        Treinamento: Git Login, <code>git clone</code> e{' '}
                        <code>git checkout -b</code>.
                    </li>
                    <li>
                        Quatro ciclos: Bloco de Código, <code>git add .</code> e{' '}
                        <code>git commit</code>.
                    </li>
                    <li>
                        Envio: <code>git pull</code>, <code>git push</code> e a{' '}
                        <strong>Pull Request</strong>.
                    </li>
                </ol>
                <p>
                    <strong>
                        Quem jogar a primeira Pull Request válida vence
                    </strong>
                    , e a partida termina na hora.
                </p>
            </div>
            <img
                alt=""
                className="mx-auto h-36 w-auto object-contain sm:h-44"
                loading="lazy"
                src={`${ART}/mascots/gato-commit.png`}
            />
        </div>
    );
}

/**
 * Tutorial narrativo ("Você é um estagiário!"). Só explica cartas e regras que existem.
 * Aparece uma vez por navegador (ao entrar numa sala em espera) e pode ser reaberto pelo
 * botão "Como jogar". Pular é possível a qualquer momento.
 */
export default function MissionTutorial({
    show,
    onClose,
}: {
    show: boolean;
    onClose: () => void;
}) {
    const [step, setStep] = useState(0);
    const closeRef = useRef<HTMLButtonElement>(null);
    const last = step === STEPS.length - 1;

    useEffect(() => {
        if (show) setStep(0);
    }, [show]);

    const finish = () => {
        rememberMissionTutorial();
        onClose();
    };

    return (
        <Modal closeable maxWidth="2xl" onClose={finish} show={show}>
            <div className="max-h-[calc(100dvh-2rem)] overflow-y-auto bg-[#f3f1ec] text-[#1d1d1b]">
                <header className="flex items-start justify-between gap-3 border-b-[3px] border-[#1d1d1b] bg-[#1d1d1b] px-5 py-4 text-white">
                    <div className="min-w-0">
                        <p className="font-mono text-[10px] font-black uppercase tracking-[.18em] text-[#b4da33]">
                            {STEPS[step].kicker} · {step + 1} de {STEPS.length}
                        </p>
                        <h2 className="mt-1 text-xl font-black sm:text-2xl">
                            {STEPS[step].title}
                        </h2>
                    </div>
                    <button
                        aria-label="Fechar tutorial"
                        className="grid size-10 shrink-0 place-items-center border-2 border-white/40 font-mono text-xl hover:border-[#b4da33]"
                        onClick={finish}
                        ref={closeRef}
                        type="button"
                    >
                        ×
                    </button>
                </header>

                <div
                    className="p-5"
                    key={step}
                    style={{ animation: 'fade-in .2s ease-out' }}
                >
                    <StepBody step={step} />
                </div>

                <footer className="flex flex-wrap items-center gap-3 border-t-[3px] border-[#1d1d1b] bg-white px-5 py-3">
                    <ol
                        aria-label="Progresso do tutorial"
                        className="mr-auto flex items-center gap-1.5"
                    >
                        {STEPS.map((item, index) => (
                            <li key={item.title}>
                                <button
                                    aria-current={
                                        index === step ? 'step' : undefined
                                    }
                                    aria-label={`Etapa ${index + 1}: ${item.kicker}`}
                                    className={`h-2.5 border border-[#1d1d1b] transition-all ${index === step ? 'w-7 bg-[#b4da33]' : 'w-2.5 bg-white hover:bg-[#b4da33]/50'}`}
                                    onClick={() => setStep(index)}
                                    type="button"
                                />
                            </li>
                        ))}
                    </ol>
                    {!last && (
                        <button
                            className="min-h-11 px-2 font-mono text-[11px] font-bold uppercase text-[#464498] underline underline-offset-4"
                            onClick={finish}
                            type="button"
                        >
                            Pular tutorial
                        </button>
                    )}
                    {step > 0 && (
                        <button
                            className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-black uppercase"
                            onClick={() => setStep((current) => current - 1)}
                            type="button"
                        >
                            Voltar
                        </button>
                    )}
                    <button
                        className="min-h-11 border-2 border-[#1d1d1b] bg-[#b4da33] px-5 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b]"
                        onClick={
                            last
                                ? finish
                                : () => setStep((current) => current + 1)
                        }
                        type="button"
                    >
                        {last ? 'Começar a jogar!' : 'Próximo'}
                    </button>
                </footer>
            </div>
        </Modal>
    );
}
