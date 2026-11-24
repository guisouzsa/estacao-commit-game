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
        text: 'Configura a autoria dos seus commits. Todo mundo começa com uma na mão.',
    },
    git_clone: {
        image: 'git-clone.webp',
        name: 'git clone',
        text: 'Cria uma cópia local do repositório remoto.',
    },
    git_checkout: {
        image: 'git-checkout.webp',
        name: 'git checkout -b',
        text: 'Cria a sua própria branch para desenvolver.',
    },
    git_add: {
        image: 'git-add.webp',
        name: 'git add .',
        text: 'Prepara as alterações para o commit.',
    },
    git_commit: {
        image: 'git-commit.webp',
        name: 'git commit',
        text: 'Registra as alterações com uma mensagem.',
    },
    git_pull: {
        image: 'git-pull.webp',
        name: 'git pull',
        text: 'Atualiza o seu repositório local antes do envio.',
    },
    git_push: {
        image: 'git-push.webp',
        name: 'git push',
        text: 'Envia os commits da sua branch ao repositório remoto.',
    },
    pull_request: {
        image: 'pull-request.webp',
        name: 'Pull Request',
        text: 'Propõe a integração das alterações. É a carta que decide a partida.',
    },
    clear_credentials: {
        image: 'limpar-credenciais.webp',
        name: 'Limpar Credenciais',
        text: 'Ataque: só entre jogadores no Treinamento. O alvo volta ao início do percurso.',
        attack: true,
    },
    merge_conflict: {
        image: 'merge-conflict.webp',
        name: 'Merge Conflict',
        text: 'Ataque: o alvo perde a próxima vez.',
        attack: true,
    },
    block_1: {
        image: 'bloco-1.webp',
        name: 'Bloco de Código 1',
        text: 'Abre o 1º ciclo de desenvolvimento.',
    },
    block_2: {
        image: 'bloco-2.webp',
        name: 'Bloco de Código 2',
        text: 'Abre o 2º ciclo de desenvolvimento.',
    },
    block_3: {
        image: 'bloco-3.webp',
        name: 'Bloco de Código 3',
        text: 'Abre o 3º ciclo de desenvolvimento.',
    },
    block_4: {
        image: 'bloco-4.webp',
        name: 'Bloco de Código 4',
        text: 'Abre o 4º e último ciclo de desenvolvimento.',
    },
};

type Step = { title: string; kicker: string };
const STEPS: Step[] = [
    { kicker: 'Etapa 1 · Missão', title: 'Prepare o foguete' },
    { kicker: 'Etapa 2 · Treinamento', title: 'Configure o ambiente' },
    { kicker: 'Etapa 3 · Desenvolvimento', title: 'Complete quatro ciclos' },
    { kicker: 'Etapa 4 · Envio', title: 'Envie o código' },
    { kicker: 'Etapa 5 · Vitória', title: 'Abra a Pull Request' },
];

function Terminal({ children }: { children: string }) {
    return (
        <p className="mt-3 border-l-4 border-[#b4da33] bg-[#1d1d1b] px-3 py-2 font-mono text-xs text-[#b4da33]">
            <span className="text-white/50">$ </span>
            {children}
        </p>
    );
}

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
    // ---- Contexto ----
    if (step === 0) {
        return (
            <div className="grid items-center gap-3 sm:grid-cols-[1fr_auto]">
                <div className="space-y-2 text-sm leading-5">
                    <p>
                        Você é o novo <strong>estagiário</strong>. Sua missão é
                        preparar o código e lançar o foguete antes dos outros
                        jogadores.
                    </p>
                    <p>
                        Em cada turno, jogue a carta indicada no percurso ou
                        troque uma carta da mão. A troca também encerra o turno.
                    </p>
                    <p>
                        <strong>Objetivo:</strong> concluir Treinamento,
                        Desenvolvimento e Envio — 18 passos no total.
                    </p>
                    <Terminal>git checkout -b missao-foguete</Terminal>
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

    // ---- Primeira etapa: Treinamento ----
    if (step === 1) {
        return (
            <div className="text-sm leading-5">
                <p>
                    O <strong>Treinamento</strong> prepara o seu ambiente. Jogue
                    as três cartas <strong>nesta ordem</strong>:
                </p>
                <CardList
                    cards={['git_login', 'git_clone', 'git_checkout']}
                    numbered
                />
                <p className="mt-2 font-mono text-[10px] font-black uppercase text-[#d01600]">
                    Carta especial do Treinamento
                </p>
                <CardList cards={['clear_credentials']} />
                <Note>
                    Você começa com <strong>Git Login</strong>.{' '}
                    <strong>Limpar Credenciais</strong> é exclusiva desta fase:
                    atacante e alvo precisam estar no Treinamento. Aqui,{' '}
                    <code>git pull</code> compra duas cartas.
                </Note>
            </div>
        );
    }

    // ---- Segunda etapa: Desenvolvimento ----
    if (step === 2) {
        return (
            <div className="text-sm leading-5">
                <p>
                    O <strong>Desenvolvimento</strong> tem{' '}
                    <strong>quatro ciclos</strong>. Cada ciclo repete a mesma
                    sequência:
                </p>
                <CardList
                    cards={['block_1', 'git_add', 'git_commit']}
                    numbered
                />
                <p className="mt-2 font-mono text-[10px] font-black uppercase text-[#d01600]">
                    Carta de conflito
                </p>
                <CardList cards={['merge_conflict']} />
                <Note>
                    Repita <strong>bloco → add → commit</strong> quatro vezes. O
                    bloco fica separado da mão. O{' '}
                    <strong>Merge Conflict</strong> faz o alvo perder a próxima
                    vez e pode ser usado em qualquer fase. Limite da mão: seis
                    cartas.
                </Note>
            </div>
        );
    }

    // ---- Terceira etapa: Envio ----
    if (step === 3) {
        return (
            <div className="text-sm leading-5">
                <p>
                    Com os quatro ciclos prontos, é hora do{' '}
                    <strong>Envio</strong>. Jogue, em ordem:
                </p>
                <CardList
                    cards={['git_pull', 'git_push', 'pull_request']}
                    numbered
                />
                <Note>
                    Depois dos quatro ciclos, siga{' '}
                    <strong>pull → push → Pull Request</strong>. Não há atalhos.
                </Note>
            </div>
        );
    }

    // ---- Etapa final: Como vencer ----
    return (
        <div className="text-sm leading-5">
            <p>
                Para vencer, cumpra o percurso inteiro. São{' '}
                <strong>quatro Blocos de Código</strong>, um por ciclo, sempre
                na ordem:
            </p>
            <ol className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                {(['block_1', 'block_2', 'block_3', 'block_4'] as const).map(
                    (key, index) => (
                        <li
                            className="border-2 border-[#1d1d1b] bg-white p-1.5 text-center"
                            key={key}
                        >
                            <img
                                alt={CARDS[key].name}
                                className="mx-auto h-16 w-12 rounded-sm border border-[#1d1d1b] object-cover"
                                decoding="async"
                                src={`${ART}/corrected/${CARDS[key].image}`}
                            />
                            <strong className="mt-1 block font-mono text-[10px]">
                                Ciclo {index + 1}
                            </strong>
                            <span className="block text-[10px] leading-4 text-[#5b5953]">
                                bloco → add → commit
                            </span>
                        </li>
                    ),
                )}
            </ol>
            <div className="mt-3 grid items-center gap-3 sm:grid-cols-[auto_1fr]">
                <img
                    alt={CARDS.pull_request.name}
                    className="mx-auto h-20 w-14 rounded-sm border-2 border-[#1d1d1b] object-cover"
                    decoding="async"
                    src={`${ART}/corrected/${CARDS.pull_request.image}`}
                />
                <div className="space-y-2">
                    <p>
                        A <strong>Pull Request</strong> só vale após completar
                        todo o percurso, incluindo pull e push.
                    </p>
                    <p className="font-bold">
                        Quem jogar a primeira Pull Request válida vence, e a
                        partida termina na hora.
                    </p>
                </div>
            </div>
            <Note>
                Resumo do percurso: Treinamento (3) + quatro ciclos de bloco,
                add e commit (12) + Envio (3) = 18 etapas.
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
