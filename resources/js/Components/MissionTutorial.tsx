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

/**
 * Cartas que EXISTEM no jogo e o que cada uma faz (regras de GameEngine / documento do jogo).
 * Nada inventado: quando uma regra tem restrição (ex.: Limpar Credenciais), ela aparece junto.
 */
type CardInfo = { image: string; name: string; text: string; attack?: boolean };
const CARDS: Record<string, CardInfo> = {
    git_login: {
        image: 'git-login.webp',
        name: 'Git Login',
        text: 'Primeira carta do percurso. Todo mundo começa com uma.',
    },
    git_clone: {
        image: 'git-clone.webp',
        name: 'git clone',
        text: 'Traz o projeto para o seu computador.',
    },
    git_checkout: {
        image: 'git-checkout.webp',
        name: 'git checkout -b',
        text: 'Cria o seu próprio caminho de trabalho.',
    },
    git_add: {
        image: 'git-add.webp',
        name: 'git add .',
        text: 'Separa as mudanças que serão salvas.',
    },
    git_commit: {
        image: 'git-commit.webp',
        name: 'git commit',
        text: 'Salva as mudanças feitas.',
    },
    git_pull: {
        image: 'git-pull.webp',
        name: 'git pull',
        text: 'Traz as novidades antes de enviar. No Treinamento, compra 2 cartas.',
    },
    git_push: {
        image: 'git-push.webp',
        name: 'git push',
        text: 'Envia o seu trabalho.',
    },
    pull_request: {
        image: 'pull-request.webp',
        name: 'Pull Request',
        text: 'Pede para juntar o seu trabalho. Quem jogar primeiro vence.',
    },
    clear_credentials: {
        image: 'limpar-credenciais.webp',
        name: 'Limpar Credenciais',
        text: 'Ataque: só contra quem também está no Treinamento. A pessoa volta ao início.',
        attack: true,
    },
    merge_conflict: {
        image: 'merge-conflict.webp',
        name: 'Merge Conflict',
        text: 'Ataque: a pessoa escolhida perde a próxima vez.',
        attack: true,
    },
    block_1: {
        image: 'bloco-1.webp',
        name: 'Bloco de Código 1',
        text: 'Peça do 1º ciclo.',
    },
    block_2: {
        image: 'bloco-2.webp',
        name: 'Bloco de Código 2',
        text: 'Peça do 2º ciclo.',
    },
    block_3: {
        image: 'bloco-3.webp',
        name: 'Bloco de Código 3',
        text: 'Peça do 3º ciclo.',
    },
    block_4: {
        image: 'bloco-4.webp',
        name: 'Bloco de Código 4',
        text: 'Peça do 4º e último ciclo.',
    },
};

type Step = { title: string; kicker: string };
const STEPS: Step[] = [
    { kicker: 'Como jogar', title: 'O objetivo' },
    { kicker: 'Etapa 1', title: 'Treinamento' },
    { kicker: 'Etapa 2', title: 'Desenvolvimento' },
    { kicker: 'Etapa 3', title: 'Envio' },
];

/** Lista de cartas com a arte real e a função de cada uma. */
function CardList({
    cards,
    numbered = false,
}: {
    cards: string[];
    numbered?: boolean;
}) {
    return (
        <ul className="mt-2 grid gap-2 sm:grid-cols-2">
            {cards.map((key, index) => {
                const card = CARDS[key];
                return (
                    <li
                        className={`flex items-center gap-2 border-2 bg-white p-1.5 ${card.attack ? 'border-[#d01600]' : 'border-[#1d1d1b]'}`}
                        key={key}
                    >
                        <img
                            alt=""
                            className="h-14 w-11 shrink-0 rounded-sm border border-[#1d1d1b] object-cover"
                            decoding="async"
                            src={`${ART}/corrected/${card.image}`}
                        />
                        <span className="min-w-0">
                            <strong className="block font-mono text-[11px]">
                                {numbered ? `${index + 1}. ` : ''}
                                {card.name}
                            </strong>
                            <span className="block text-[10px] leading-4 text-[#5b5953]">
                                {card.text}
                            </span>
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

function Note({ children }: { children: React.ReactNode }) {
    return (
        <p className="mt-2 border-l-4 border-[#464498] bg-white px-3 py-1.5 text-[11px] leading-4 text-[#3d3b37]">
            {children}
        </p>
    );
}

function StepBody({ step }: { step: number }) {
    if (step === 0) {
        return (
            <div className="grid items-center gap-3 sm:grid-cols-[1fr_auto]">
                <div className="space-y-2 text-sm leading-5">
                    <p>
                        <strong>Objetivo:</strong> chegar ao fim do percurso
                        antes dos outros jogadores. Quem jogar a{' '}
                        <strong>Pull Request</strong> primeiro vence.
                    </p>
                    <p>
                        O percurso tem <strong>3 etapas</strong>: Treinamento,
                        Desenvolvimento e Envio.{' '}
                        <strong>
                            Em cada etapa você só joga as cartas dela.
                        </strong>
                    </p>
                    <p>
                        Na sua vez, jogue a carta da próxima etapa ou troque uma
                        carta da mão (trocar também gasta a sua vez).
                    </p>
                </div>
                <img
                    alt=""
                    className="mx-auto h-28 w-auto object-contain sm:h-36"
                    decoding="async"
                    src={`${ART}/mascots/foguete-gato.webp`}
                />
            </div>
        );
    }

    if (step === 1) {
        return (
            <div className="text-sm leading-5">
                <p>
                    Na etapa de <strong>Treinamento</strong> você só joga estas
                    3 cartas, <strong>nesta ordem</strong>:
                </p>
                <CardList
                    cards={['git_login', 'git_clone', 'git_checkout']}
                    numbered
                />
                <p className="mt-2 font-mono text-[10px] font-black uppercase text-[#d01600]">
                    Carta extra desta etapa
                </p>
                <CardList cards={['clear_credentials']} />
            </div>
        );
    }

    if (step === 2) {
        return (
            <div className="text-sm leading-5">
                <p>
                    Na etapa de <strong>Desenvolvimento</strong> são{' '}
                    <strong>4 ciclos</strong>. Em cada ciclo você só joga estas
                    3 cartas, nesta ordem:
                </p>
                <CardList
                    cards={['block_1', 'git_add', 'git_commit']}
                    numbered
                />
                <p className="mt-2 font-mono text-[10px] font-black uppercase text-[#d01600]">
                    Carta extra desta etapa
                </p>
                <CardList cards={['merge_conflict']} />
                <Note>
                    O bloco fica separado da mão e muda a cada ciclo (bloco 1,
                    2, 3 e 4). A mão guarda no máximo 6 cartas.
                </Note>
            </div>
        );
    }

    return (
        <div className="text-sm leading-5">
            <p>
                Na etapa de <strong>Envio</strong> você só joga estas 3 cartas,{' '}
                <strong>nesta ordem</strong>:
            </p>
            <CardList
                cards={['git_pull', 'git_push', 'pull_request']}
                numbered
            />
            <Note>
                <strong>Quem jogar a Pull Request primeiro vence</strong> e a
                partida termina na hora. Ao todo são 18 passos: 3 de
                Treinamento, 12 de Desenvolvimento e 3 de Envio.
            </Note>
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
    turnSecondsLeft = null,
}: {
    show: boolean;
    onClose: () => void;
    /** Segundos da SUA vez enquanto o tutorial está aberto (null = não é a sua vez). */
    turnSecondsLeft?: number | null;
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
            <div className="flex max-h-[calc(100dvh-1rem)] flex-col overflow-hidden bg-[#f3f1ec] text-[#1d1d1b] sm:max-h-[calc(100dvh-2rem)]">
                <header className="flex shrink-0 items-start justify-between gap-3 border-b-[3px] border-[#1d1d1b] bg-[#1d1d1b] px-4 py-3 text-white">
                    <div className="min-w-0">
                        <p className="font-mono text-[10px] font-black uppercase tracking-[.18em] text-[#b4da33]">
                            {STEPS[step].kicker} · {step + 1}/{STEPS.length}
                        </p>
                        <h2 className="mt-0.5 text-lg font-black sm:text-xl">
                            {STEPS[step].title}
                        </h2>
                    </div>
                    {turnSecondsLeft !== null && (
                        // O relógio da vez não para por causa do tutorial: avisa e deixa jogar na hora.
                        <button
                            className="ml-auto flex min-h-10 shrink-0 animate-pulse items-center gap-2 border-2 border-[#1d1d1b] bg-[#b4da33] px-3 font-mono text-[11px] font-black uppercase text-[#1d1d1b] motion-reduce:animate-none"
                            onClick={finish}
                            role="alert"
                            type="button"
                        >
                            É a sua vez · {turnSecondsLeft}s · Jogar agora
                        </button>
                    )}
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
                    className="min-h-0 flex-1 overflow-y-auto p-4"
                    key={step}
                    style={{ animation: 'fade-in .2s ease-out' }}
                >
                    <StepBody step={step} />
                </div>

                <footer className="shrink-0 border-t-[3px] border-[#1d1d1b] bg-white px-4 py-2.5">
                    <div className="flex items-center justify-between gap-3">
                        <ol
                            aria-label="Progresso do tutorial"
                            className="flex items-center gap-1"
                        >
                            {STEPS.map((item, index) => (
                                <li key={item.title}>
                                    <button
                                        aria-current={
                                            index === step ? 'step' : undefined
                                        }
                                        aria-label={`Etapa ${index + 1}: ${item.kicker}`}
                                        className={`grid size-6 place-items-center border border-[#1d1d1b] font-mono text-[9px] font-black transition ${index === step ? 'bg-[#b4da33]' : 'bg-white hover:bg-[#b4da33]/50'}`}
                                        onClick={() => setStep(index)}
                                        type="button"
                                    >
                                        {index + 1}
                                    </button>
                                </li>
                            ))}
                        </ol>
                        {/* Sempre visível: pular nunca fica escondido, em nenhuma etapa. */}
                        <button
                            className="min-h-9 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-[10px] font-black uppercase hover:bg-[#f3f1ec] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]"
                            onClick={finish}
                            type="button"
                        >
                            Pular tutorial
                        </button>
                    </div>
                    {/* Voltar e Próximo lado a lado, na base, com o mesmo tamanho. */}
                    <div className="mt-2 grid grid-cols-2 gap-2">
                        <button
                            className="min-h-10 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-[11px] font-black uppercase transition enabled:hover:bg-[#f3f1ec] disabled:cursor-not-allowed disabled:opacity-40"
                            disabled={step === 0}
                            onClick={() =>
                                setStep((current) => Math.max(0, current - 1))
                            }
                            type="button"
                        >
                            Voltar
                        </button>
                        <button
                            className="min-h-10 border-2 border-[#1d1d1b] bg-[#b4da33] px-3 font-mono text-[11px] font-black uppercase shadow-[2px_2px_0_#1d1d1b] transition hover:-translate-y-px"
                            onClick={
                                last
                                    ? finish
                                    : () => setStep((current) => current + 1)
                            }
                            type="button"
                        >
                            {last ? 'Começar a jogar!' : 'Próximo'}
                        </button>
                    </div>
                </footer>
            </div>
        </Modal>
    );
}
