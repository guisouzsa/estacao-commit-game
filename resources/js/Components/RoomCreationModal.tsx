import Modal from '@/Components/Modal';
import PlayerAvatar from '@/Components/PlayerAvatar';
import type { PlayerProfile } from '@/Components/PlayerProfileCard';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';

export type BotPreview = PlayerProfile & {
    key: string;
    kind: 'bot';
    is_bot: true;
};

type Mode = 'friends' | 'bots';

export default function RoomCreationModal({
    show,
    host,
    botPreviews,
    onClose,
}: {
    show: boolean;
    host: PlayerProfile;
    botPreviews: BotPreview[];
    onClose: () => void;
}) {
    const [step, setStep] = useState<1 | 2>(1);
    const [mode, setMode] = useState<Mode | null>(null);
    const [bots, setBots] = useState(1);
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [requestId, setRequestId] = useState(() => crypto.randomUUID());
    const botsPicker = useRef<HTMLFieldSetElement>(null);

    useEffect(() => {
        if (!show) return;
        setStep(1);
        setMode(null);
        setBots(1);
        setError(null);
        setCreating(false);
        setRequestId(crypto.randomUUID());
    }, [show]);

    // No celular a escolha de bots aparece abaixo da dobra (atrás do rodapé): leva a pessoa até ela.
    useEffect(() => {
        if (mode !== 'bots') return;
        const reduce = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        const timer = window.setTimeout(
            () =>
                botsPicker.current?.scrollIntoView({
                    behavior: reduce ? 'auto' : 'smooth',
                    block: 'center',
                }),
            60, // espera o bloco entrar na tela
        );
        return () => window.clearTimeout(timer);
    }, [mode]);

    const selectedBots = mode === 'friends' ? 0 : bots;
    const humanVacancies = 3 - selectedBots;
    const seats = useMemo(
        () => [
            { type: 'host' as const, profile: host },
            ...Array.from({ length: humanVacancies }, () => ({
                type: 'friend' as const,
            })),
            ...botPreviews.slice(0, selectedBots).map((profile) => ({
                type: 'bot' as const,
                profile,
            })),
        ],
        [botPreviews, host, humanVacancies, selectedBots],
    );

    const close = () => {
        if (!creating) onClose();
    };

    const continueToPreview = () => {
        if (!mode) return;
        setError(null);
        setStep(2);
    };

    const create = () => {
        setCreating(true);
        setError(null);
        router.post(
            route('rooms.store'),
            { bots: selectedBots, request_id: requestId },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setError(
                        typeof errors.bots === 'string'
                            ? errors.bots
                            : 'Não foi possível criar a sala. Sua escolha foi preservada para tentar novamente.',
                    );
                },
                onFinish: () => setCreating(false),
            },
        );
    };

    return (
        <Modal show={show} maxWidth="2xl" closeable={!creating} onClose={close}>
            <div className="max-h-[calc(100dvh-3rem)] overflow-y-auto bg-[#f3f1ec] text-[#1d1d1b]">
                <header className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b-[3px] border-[#1d1d1b] bg-[#1d1d1b] px-5 py-4 text-white">
                    <div>
                        <p className="font-mono text-[10px] font-black uppercase tracking-[.18em] text-[#b4da33]">
                            Nova sala · etapa {step} de 2
                        </p>
                        <h2 className="mt-1 text-2xl font-black">
                            {step === 1
                                ? 'Como você quer jogar?'
                                : 'Confira sua mesa'}
                        </h2>
                    </div>
                    <button
                        type="button"
                        disabled={creating}
                        onClick={close}
                        aria-label="Cancelar criação da sala"
                        className="grid size-10 shrink-0 place-items-center border-2 border-white/40 font-mono text-xl hover:border-[#b4da33] disabled:opacity-50"
                    >
                        ×
                    </button>
                </header>

                <div className="p-4 sm:p-6">
                    {step === 1 ? (
                        <div className="motion-safe:animate-[fade-in_.2s_ease-out]">
                            <p className="max-w-xl text-sm leading-6 text-[#5b5953]">
                                Escolha o ritmo da partida. A mesa sempre terá
                                quatro lugares contando com você.
                            </p>
                            <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                <ModeCard
                                    selected={mode === 'friends'}
                                    title="Jogar com amigos"
                                    label="3 vagas para pessoas"
                                    icon="⌁"
                                    onClick={() => setMode('friends')}
                                >
                                    Crie a sala e convide seus amigos para
                                    completar a mesa.
                                </ModeCard>
                                <ModeCard
                                    selected={mode === 'bots'}
                                    title="Jogar com bots"
                                    label="Comece sem esperar"
                                    icon="&gt;_"
                                    onClick={() => setMode('bots')}
                                >
                                    Complete parte ou toda a mesa com jogadores
                                    controlados pelo jogo.
                                </ModeCard>
                            </div>

                            {mode === 'bots' && (
                                <fieldset
                                    ref={botsPicker}
                                    className="mt-5 scroll-mb-28 scroll-mt-4 border-[3px] border-[#1d1d1b] bg-white p-4 motion-safe:animate-[fade-in_.2s_ease-out]"
                                >
                                    <legend className="px-2 font-mono text-xs font-black uppercase tracking-[.12em] text-[#464498]">
                                        Quantos bots?
                                    </legend>
                                    <div
                                        className="grid grid-cols-3 gap-2"
                                        role="radiogroup"
                                        aria-label="Quantidade de bots"
                                    >
                                        {[1, 2, 3].map((count) => (
                                            <button
                                                key={count}
                                                type="button"
                                                role="radio"
                                                aria-checked={bots === count}
                                                onClick={() => setBots(count)}
                                                className={`min-h-14 border-[3px] font-mono text-lg font-black transition motion-reduce:transition-none ${
                                                    bots === count
                                                        ? 'translate-y-[-2px] border-[#1d1d1b] bg-[#b4da33] shadow-[3px_3px_0_#464498]'
                                                        : 'border-[#1d1d1b]/30 bg-[#f3f1ec] hover:border-[#1d1d1b]'
                                                }`}
                                            >
                                                {count}
                                            </button>
                                        ))}
                                    </div>
                                    <p
                                        className="mt-3 text-sm text-[#5b5953]"
                                        aria-live="polite"
                                    >
                                        {bots === 3
                                            ? 'A mesa ficará completa: você e três bots.'
                                            : `${3 - bots} ${3 - bots === 1 ? 'vaga continuará' : 'vagas continuarão'} disponível para amigos.`}
                                    </p>
                                </fieldset>
                            )}
                        </div>
                    ) : (
                        <div className="motion-safe:animate-[fade-in_.2s_ease-out]">
                            <p className="text-sm leading-6 text-[#5b5953]">
                                Esta será a composição inicial. As vagas abertas
                                poderão ser ocupadas pelo link de convite.
                            </p>
                            <ol
                                className="mt-5 grid gap-3 sm:grid-cols-2"
                                aria-label="Prévia dos quatro participantes"
                            >
                                {seats.map((seat, index) => (
                                    <SeatPreview
                                        key={`${seat.type}-${index}`}
                                        number={index + 1}
                                        seat={seat}
                                    />
                                ))}
                            </ol>
                            <div className="mt-5 border-l-[6px] border-[#b4da33] bg-white p-3 text-sm leading-6">
                                <strong>{4 - selectedBots} humanos</strong> ·{' '}
                                <strong>
                                    {selectedBots}{' '}
                                    {selectedBots === 1 ? 'bot' : 'bots'}
                                </strong>
                                <br />
                                Os bots participam das mesmas regras, mas não
                                possuem conta nem sessão de navegador.
                                {selectedBots > 0 && (
                                    <span className="mt-1 block text-xs text-[#5b5953]">
                                        Os avatares representam o catálogo; as
                                        identidades finais são sorteadas com
                                        segurança ao criar a sala.
                                    </span>
                                )}
                            </div>
                        </div>
                    )}

                    {error && (
                        <p
                            className="mt-4 border-2 border-[#d01600] bg-[#d01600]/10 p-3 text-sm font-semibold text-[#9c1000]"
                            role="alert"
                        >
                            {error}
                        </p>
                    )}
                </div>

                <footer className="sticky bottom-0 flex flex-col-reverse gap-2 border-t-[3px] border-[#1d1d1b] bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        type="button"
                        disabled={creating}
                        onClick={step === 1 ? close : () => setStep(1)}
                        className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-black uppercase disabled:opacity-50"
                    >
                        {step === 1 ? 'Cancelar' : '← Voltar'}
                    </button>
                    <button
                        type="button"
                        disabled={creating || (step === 1 && mode === null)}
                        onClick={step === 1 ? continueToPreview : create}
                        className="min-h-12 border-2 border-[#1d1d1b] bg-[#b4da33] px-5 font-mono text-xs font-black uppercase shadow-[4px_4px_0_#1d1d1b] disabled:translate-y-0 disabled:opacity-45 disabled:shadow-none"
                    >
                        {step === 1
                            ? 'Continuar'
                            : creating
                              ? 'Criando sala...'
                              : 'Criar esta sala'}
                    </button>
                </footer>
            </div>
        </Modal>
    );
}

function ModeCard({
    title,
    label,
    icon,
    selected,
    onClick,
    children,
}: {
    title: string;
    label: string;
    icon: string;
    selected: boolean;
    onClick: () => void;
    children: string;
}) {
    return (
        <button
            type="button"
            aria-pressed={selected}
            onClick={onClick}
            className={`min-h-48 border-[3px] p-4 text-left transition motion-reduce:transition-none ${
                selected
                    ? 'border-[#1d1d1b] bg-[#b4da33] shadow-[5px_5px_0_#464498]'
                    : 'border-[#1d1d1b] bg-white shadow-[3px_3px_0_#1d1d1b] hover:-translate-y-1'
            }`}
        >
            <span
                className="font-mono text-xl font-black text-[#464498]"
                aria-hidden="true"
            >
                {icon}
            </span>
            <strong className="mt-4 block text-xl">{title}</strong>
            <span className="mt-1 block text-sm leading-5 text-[#5b5953]">
                {children}
            </span>
            <span className="mt-4 inline-block border-2 border-[#1d1d1b] bg-[#f3f1ec] px-2 py-1 font-mono text-[9px] font-black uppercase">
                {label}
            </span>
        </button>
    );
}

type PreviewSeat =
    | { type: 'host'; profile: PlayerProfile }
    | { type: 'friend' }
    | { type: 'bot'; profile: BotPreview };

function SeatPreview({ number, seat }: { number: number; seat: PreviewSeat }) {
    const emptyProfile: PlayerProfile = {
        username: 'Vaga livre',
        avatar_style: 'terminal',
        avatar_seed: `vaga-${number}`,
        profile_frame: 'standard',
    };
    const profile = seat.type === 'friend' ? emptyProfile : seat.profile;

    return (
        <li className="flex min-h-24 items-center gap-4 border-[3px] border-[#1d1d1b] bg-white p-3 shadow-[3px_3px_0_#1d1d1b] transition motion-reduce:transition-none">
            <span className="grid size-7 shrink-0 place-items-center border-2 border-[#1d1d1b] bg-[#e8e5dc] font-mono text-xs font-black">
                {number}
            </span>
            <PlayerAvatar profile={profile} size="sm" />
            <div className="min-w-0">
                <strong className="block truncate">
                    {seat.type === 'host'
                        ? `@${profile.username}`
                        : seat.type === 'bot'
                          ? profile.username
                          : 'Vaga para amigo'}
                </strong>
                <span className="mt-1 inline-block font-mono text-[9px] font-black uppercase text-[#464498]">
                    {seat.type === 'host'
                        ? 'Você · anfitrião'
                        : seat.type === 'bot'
                          ? 'Bot do jogo'
                          : 'Jogador real'}
                </span>
            </div>
        </li>
    );
}
