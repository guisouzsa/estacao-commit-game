import ConfirmDialog from '@/Components/ConfirmDialog';
import FlightLayer, { type Flight } from '@/Components/game/FlightLayer';
import GameBoard from '@/Components/game/GameBoard';
import VictoryScreen from '@/Components/game/VictoryScreen';
import GameLog, { type LogActor } from '@/Components/game/GameLog';
import type { PanelPlayer } from '@/Components/game/PlayerPanel';
import PlayerPreview, { type PreviewPlayer } from '@/Components/game/PlayerPreview';
import TurnBanner from '@/Components/game/TurnBanner';
import TargetPicker from '@/Components/game/TargetPicker';
import GameCard, { cardLabel } from '@/Components/GameCard';
import MissionTutorial from '@/Components/MissionTutorial';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AREAS, sideOf, type Side } from '@/lib/gameGeometry';
import { startRoomSync } from '@/lib/roomSync';
import { claimTutorial } from '@/lib/tutorialOnce';
import { toast } from '@/lib/toast';
import { Head, Link, router } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

type Player = {
    key: string;
    kind: 'user' | 'guest' | 'bot';
    is_bot: boolean;
    username: string;
    avatar?: string | null;
    avatar_style?: string;
    avatar_seed?: string;
    profile_frame?: string;
    profile_background?: string | null;
    profile_title?: string | null;
    primary_badge?: string | null;
    matches_won?: number | null;
};
type Participant = {
    id: number;
    seat: number;
    is_host: boolean;
    is_viewer: boolean;
    player: Player;
    hand_count: number;
    path: string[];
    step: number;
    blocked: boolean;
    pending_discard: number;
};
type HistoryItem = {
    id: string;
    participant_id?: number;
    message: string;
    turn: number;
    notify?: boolean;
};
type Game = {
    id: string;
    status: string;
    current_seat: number;
    turn_number: number;
    version: number;
    winner_participant_id?: number | null;
    deck_count: number;
    discard_count: number;
    turn_seconds_left?: number | null;
    /** Quando a sala concluída será excluída (2 min depois da vitória). */
    cleanup_at?: string | null;
    /** Vitórias do vencedor no ranking geral (só contas). */
    winner_wins?: number | null;
    history: HistoryItem[];
};

const PARTIAL = ['game', 'participants', 'hand', 'requiredCard'];

/** Primeiro elemento VISÍVEL que combina com o seletor (a mesa só tem um de cada, mas é barato garantir). */
function rectOf(selector: string): DOMRect | null {
    for (const element of document.querySelectorAll(selector)) {
        const rect = element.getBoundingClientRect();
        if (rect.width > 0 && rect.height > 0) return rect;
    }
    return null;
}

/** Cada jogador sempre guarda o Bloco da vez (1 a 4) ALÉM das cartas comuns: começa com 5. */
const blocksPlayed = (participant: Participant) =>
    participant.path.filter((card) => card.startsWith('block_')).length;
const holdsBlock = (participant: Participant) => blocksPlayed(participant) < 4;

const toPanel = (participant: Participant): PanelPlayer => ({
    id: participant.id,
    username: participant.player.username,
    is_host: participant.is_host,
    is_bot: participant.player.is_bot,
    hand_count: participant.hand_count + (holdsBlock(participant) ? 1 : 0),
    step: participant.step,
    blocked: participant.blocked,
    profile: participant.player,
});

export default function Show({
    room,
    game,
    participants,
    hand,
    requiredCard,
    profileLabels = { titles: {}, badges: {} },
}: {
    room: { id: string };
    game: Game;
    participants: Participant[];
    hand: string[];
    requiredCard?: string | null;
    profileLabels?: { titles: Record<string, string>; badges: Record<string, string> };
}) {
    const [selected, setSelected] = useState<number | null>(null);
    const [target, setTarget] = useState('');
    const [busy, setBusy] = useState(false);
    // Jogada otimista: a carta sai da mão na hora; o servidor continua sendo a autoridade.
    // A remoção só vale enquanto a versão da partida é a mesma da jogada (ao chegar o estado
    // novo, ele manda). Se o servidor recusar, a carta volta com a animação de entrada.
    const [optimistic, setOptimistic] = useState<{
        index: number;
        version: number;
    } | null>(null);
    const busyRef = useRef(false);
    const staleDuringAction = useRef(false);
    const [leaveOpen, setLeaveOpen] = useState(false);
    // Prévia de perfil: só guarda QUEM e ONDE (a posição vem do indicador clicado); os dados são os da partida.
    const [preview, setPreview] = useState<{ id: number; anchor: DOMRect } | null>(null);
    const closePreview = useCallback(() => setPreview(null), []);
    const selectPlayer = useCallback((id: number, element: HTMLElement) => {
        setPreview((current) => (current?.id === id ? null : { id, anchor: element.getBoundingClientRect() }));
    }, []);
    const [leaving, setLeaving] = useState(false);
    const [tutorial, setTutorial] = useState(false);
    // "Como jogar" abre sozinho ao ENTRAR numa partida nova. A decisão é por id da partida
    // (lib/tutorialOnce): re-renderizações, reconexões e atualizações do estado multiplayer
    // nunca o reabrem; só o botão "Como jogar" abre de novo.
    useEffect(() => {
        // Só nas primeiras rodadas: quem entra (ou volta de outro aparelho) numa partida já em andamento
        // não é interrompido por um tutorial; o botão "Como jogar" continua disponível.
        if (game.status === 'active' && game.turn_number <= 6 && claimTutorial(game.id)) setTutorial(true);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [game.id]);
    // Indica (sombra + seta) quando a mão tem mais cartas fora da tela; só ela rola, a mesa não.
    const handRef = useRef<HTMLDivElement>(null);
    const [handHint, setHandHint] = useState({ left: false, right: false });
    const measureHand = useCallback(() => {
        const element = handRef.current;
        if (!element) return;
        const left = element.scrollLeft > 4;
        const right =
            element.scrollLeft + element.clientWidth < element.scrollWidth - 4;
        setHandHint((current) =>
            current.left === left && current.right === right
                ? current
                : { left, right },
        );
    }, []);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [online, setOnline] = useState(() => navigator.onLine);
    const reduced = useReducedMotion();
    const statusRef = useRef(game.status);
    statusRef.current = game.status;

    const viewer = participants.find((participant) => participant.is_viewer)!;
    const opponents = participants.filter(
        (participant) => !participant.is_viewer,
    );
    const isTurn =
        game.status === 'active' && game.current_seat === viewer.seat;
    const currentPlayer = participants.find(
        (participant) => participant.seat === game.current_seat,
    );
    const hiddenIndex =
        optimistic && optimistic.version === game.version
            ? optimistic.index
            : null;

    const handKeys = useMemo(() => {
        const seen: Record<string, number> = {};
        return hand.map((card, index) => {
            seen[card] = (seen[card] ?? 0) + 1;
            return { card, index, key: `${card}#${seen[card]}` };
        });
    }, [hand]);
    const selectedCard = selected === null ? null : hand[selected];
    const needsTarget =
        selectedCard === 'clear_credentials' ||
        selectedCard === 'merge_conflict';
    const canPlaySelected = Boolean(
        selectedCard &&
        (selectedCard === requiredCard ||
            (selectedCard === 'git_pull' && viewer.step < 3) ||
            needsTarget),
    );
    const blockRequired = requiredCard?.startsWith('block_') ?? false;
    // Sem carta jogável? Trocar uma carta é a jogada da vez (e passa a vez ao próximo).
    const canPlayNow =
        blockRequired ||
        hand.some(
            (card) =>
                card === requiredCard ||
                (card === 'git_pull' && viewer.step < 3) ||
                ['clear_credentials', 'merge_conflict'].includes(card),
        );
    const turnHint = canPlayNow
        ? 'Você tem cartas para jogar!'
        : 'Sem jogada? Troque uma carta — conta como sua vez';
    const isPlayable = (card: string) =>
        isTurn &&
        (card === requiredCard ||
            (card === 'git_pull' && viewer.step < 3) ||
            ['clear_credentials', 'merge_conflict'].includes(card));

    // ---------- Sincronização (inalterada: o servidor é a fonte da verdade) ----------
    useEffect(
        () =>
            startRoomSync({
                echo: window.Echo,
                roomId: room.id,
                reload: (done) => {
                    // Minha própria jogada já traz o estado novo na resposta: um reload agora
                    // seria redundante. Se algo chegou nesse intervalo, recarrego UMA vez depois.
                    if (busyRef.current) {
                        staleDuringAction.current = true;
                        done();
                        return;
                    }
                    router.reload({ only: PARTIAL, onFinish: done });
                },
                shouldPoll: () => statusRef.current === 'active',
                onSubscriptionError: () =>
                    toast.warning(
                        'Conexão em tempo real instável. Recuperando o estado da partida…',
                        {
                            id: `realtime:${Date.now()}`,
                        },
                    ),
                onRoomDeleted: () => router.visit(route('dashboard')),
                pollIntervalMs: 15000,
                document,
                window,
            }),
        [room.id],
    );

    useEffect(() => {
        const connected = () => setOnline(true);
        const disconnected = () => {
            setOnline(false);
            toast.warning('Você perdeu a conexão. Tentando reconectar…', {
                id: `offline:${Date.now()}`,
            });
        };
        window.addEventListener('online', connected);
        window.addEventListener('offline', disconnected);
        return () => {
            window.removeEventListener('online', connected);
            window.removeEventListener('offline', disconnected);
        };
    }, []);

    useEffect(() => {
        setSelected(null);
        setTarget('');
    }, [game.version]);

    // Avisos de eventos especiais (ex.: bot assumiu). O id evita repetir com Reverb + polling.
    useEffect(() => {
        game.history
            .filter((item) => item.notify)
            .forEach((item) =>
                toast.info(item.message, {
                    id: `game-event:${item.id}`,
                    title: 'Mudança na mesa',
                }),
            );
    }, [game.history]);

    // ---------- Bloco novo: sai do baralho e vai para a mão ----------
    // Ao jogar um Bloco de Código, o seguinte é entregue na hora (regra). A carta nova VOA do baralho
    // até o lugar do Bloco na mão; até chegar, o Bloco da mão fica invisível (sem "surgir do nada").
    const viewerBlocks = blocksPlayed(viewer);
    const previousBlocks = useRef<number | null>(null);
    const [blockIncoming, setBlockIncoming] = useState(false);
    // Bloco que SAIU da mão (voando para o tabuleiro, ainda sem confirmação do servidor): a casa
    // fica vazia em vez de mostrar a mesma carta duas vezes (na mão e no ar).
    const [blockOutgoing, setBlockOutgoing] = useState(false);
    // Anel curto no bloco que ACABOU DE ENTRAR, para a troca ser fácil de ler: o que sai voa para o
    // tabuleiro, o que entra pousa na mão com destaque.
    const [blockArrived, setBlockArrived] = useState(false);
    useEffect(() => {
        const before = previousBlocks.current;
        previousBlocks.current = viewerBlocks;
        if (
            before === null ||
            viewerBlocks <= before ||
            viewerBlocks >= 4 ||
            reduced
        )
            return;
        setBlockOutgoing(false); // o servidor confirmou: o bloco antigo já está no tabuleiro
        setBlockIncoming(true);
        const safety = window.setTimeout(() => setBlockIncoming(false), 1800);
        const frame = requestAnimationFrame(() =>
            requestAnimationFrame(() => {
                const from = rectOf('[data-pile="deck"]');
                const to = rectOf('[data-hand-key="block"]');
                if (!from || !to) return setBlockIncoming(false);
                launch({
                    card: `block_${viewerBlocks + 1}`,
                    from,
                    to,
                    rotate: 0,
                    hold: false,
                    glow: true,
                    duration: 0.5,
                    // A carta real aparece (fade) por baixo da que está pousando; a de voo sai
                    // logo depois. Sem piscar, sem buraco, sem carta duplicada.
                    onArrive: () => {
                        setBlockIncoming(false);
                        setBlockArrived(true);
                        window.setTimeout(() => setBlockArrived(false), 950);
                    },
                    onLand: () => setBlockIncoming(false),
                });
            }),
        );
        return () => {
            cancelAnimationFrame(frame);
            window.clearTimeout(safety);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [viewerBlocks]);

    // ---------- Vez dos bots: UMA jogada por vez, no ritmo da mesa ----------
    // O servidor só joga pelo bot quando alguém pede um passo (com a versão que viu). Aqui, se a vez
    // é de um bot, esperamos um instante (para dar para ver a jogada) e pedimos UM passo. Quando o
    // estado novo chega, se for a vez de outro bot, repetimos. Nenhum bot joga junto com outro.
    const botTurn =
        game.status === 'active' && currentPlayer?.player.is_bot === true;
    useEffect(() => {
        if (!botTurn) return;
        const timer = window.setTimeout(
            () => {
                if (busyRef.current) return;
                router.post(
                    route('games.bots', room.id),
                    { version: game.version },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        only: PARTIAL,
                    },
                );
            },
            reduced ? 600 : 1300,
        );
        return () => window.clearTimeout(timer);
    }, [botTurn, game.version, room.id, reduced]);

    // ---------- Cartas em voo ----------
    const [flights, setFlights] = useState<Flight[]>([]);
    const [freshSlot, setFreshSlot] = useState<string | null>(null);
    const ownFlights = useRef<string[]>([]);

    const finishFlight = useCallback((id: string) => {
        setFlights((current) => {
            const done = current.find((flight) => flight.id === id);
            if (done?.slotKey) setFreshSlot(done.slotKey);
            done?.onLand?.();
            return current.filter((flight) => flight.id !== id);
        });
    }, []);

    const launch = useCallback(
        (flight: Omit<Flight, 'id'>) => {
            if (reduced) {
                if (flight.slotKey) setFreshSlot(flight.slotKey);
                return null;
            }
            const id = crypto.randomUUID();
            setFlights((current) => [...current.slice(-5), { ...flight, id }]);
            return id;
        },
        [reduced],
    );

    // Casas do tabuleiro que ainda estão "recebendo" uma carta em voo ficam vazias até ela chegar.
    const pendingSlots = useMemo(
        () =>
            new Set(
                flights
                    .map((flight) => flight.slotKey)
                    .filter(Boolean) as string[],
            ),
        [flights],
    );

    // Jogadas dos OUTROS (bots inclusive): a carta sai do painel do jogador até o espaço dele.
    // Compara com o estado anterior, então eventos repetidos ou um reload não repetem animações.
    const previousSteps = useRef<Record<number, number> | null>(null);
    useEffect(() => {
        const current = Object.fromEntries(
            participants.map((participant) => [
                participant.id,
                participant.path.length,
            ]),
        );
        const previous = previousSteps.current;
        previousSteps.current = current;
        if (!previous) return; // primeira renderização: nada de reprise

        for (const participant of participants) {
            const before = previous[participant.id] ?? current[participant.id];
            const grew = current[participant.id] - before;
            if (grew <= 0) continue;

            const lastIndex = current[participant.id] - 1;
            if (participant.is_viewer) {
                // Minha jogada confirmada: o encaixe é tratado no voo da própria carta (ou aqui, sem voo).
                if (ownFlights.current.length === 0)
                    setFreshSlot(`${participant.id}:${lastIndex}`);
                continue;
            }
            // Muitas cartas de uma vez = voltei de uma queda de conexão: só mostra o estado atual.
            if (grew > 2) {
                setFreshSlot(`${participant.id}:${lastIndex}`);
                continue;
            }
            const side = sideOf(participant.seat, viewer.seat);
            for (let index = before; index <= lastIndex; index++) {
                const from = rectOf(`[data-panel="${participant.id}"]`);
                const to = rectOf(`[data-slot="${participant.id}:${index}"]`);
                if (!from || !to) {
                    setFreshSlot(`${participant.id}:${index}`);
                    continue;
                }
                launch({
                    card: participant.path[index],
                    from,
                    to,
                    rotate: AREAS[side].rotate,
                    hold: false,
                    small: true,
                    slotKey: `${participant.id}:${index}`,
                });
            }
        }
    }, [participants, viewer.seat, launch]);

    // ---------- Jogar ----------
    const send = (action: string, card?: string, targetId?: string) => {
        if (busyRef.current) return; // nunca duas jogadas ao mesmo tempo
        busyRef.current = true;
        setBusy(true);

        // A carta começa a voar AGORA (antes da resposta): sai da mão e vai ao percurso ou ao descarte.
        const handKey =
            action === 'play_block'
                ? 'block'
                : selected !== null
                  ? handKeys[selected]?.key
                  : undefined;
        const advances =
            action === 'play_block' ||
            (action === 'play_card' && card === requiredCard);
        const flightCard =
            action === 'play_block' ? (requiredCard ?? '') : (card ?? '');
        const from = handKey ? rectOf(`[data-hand-key="${handKey}"]`) : null;
        const slotKey = `${viewer.id}:${viewer.step}`;
        const to = advances
            ? rectOf(`[data-slot="${slotKey}"]`)
            : rectOf('[data-pile="discard"]');
        if (from && to && flightCard) {
            const id = launch({
                card: flightCard,
                from,
                to,
                rotate: advances ? AREAS[0].rotate : 0,
                hold: true,
                slotKey: advances ? slotKey : undefined,
            });
            if (id) {
                ownFlights.current.push(id);
                if (action === 'play_block') setBlockOutgoing(true);
            }
        }
        if (selected !== null && action !== 'play_block') {
            setOptimistic({ index: selected, version: game.version });
        }

        router.post(
            route('games.action', room.id),
            {
                action_uuid: crypto.randomUUID(),
                action,
                card,
                target_id: targetId || null,
                version: game.version,
            },
            {
                preserveScroll: true,
                preserveState: true,
                // Só o que muda: a mesa não é remontada nem a página recarregada.
                only: PARTIAL,
                onError: (errors) => {
                    setOptimistic(null); // a carta volta para a mão
                    toast.warning(
                        String(
                            Object.values(errors)[0] ??
                                'Não foi possível realizar essa jogada.',
                        ),
                        {
                            id: `move:${Date.now()}`,
                        },
                    );
                },
                onFinish: () => {
                    busyRef.current = false;
                    setBusy(false);
                    setOptimistic(null);
                    setBlockOutgoing(false); // recusada: o bloco volta para a mão
                    // A carta em espera pousa (jogada aceita) ou some (recusada: voltou para a mão).
                    const ids = ownFlights.current;
                    ownFlights.current = [];
                    ids.forEach((id) => finishFlight(id));
                    if (staleDuringAction.current) {
                        staleDuringAction.current = false;
                        router.reload({ only: PARTIAL });
                    }
                },
            },
        );
    };

    useEffect(() => {
        measureHand();
        window.addEventListener('resize', measureHand);
        return () => window.removeEventListener('resize', measureHand);
    }, [measureHand, hand.length]);

    // ---------- Turno e tempo ----------
    const [clock, setClock] = useState(() => ({
        base: game.turn_seconds_left ?? null,
        at: Date.now(),
    }));
    const [, setTick] = useState(0);
    useEffect(() => {
        setClock({ base: game.turn_seconds_left ?? null, at: Date.now() });
    }, [game.version, game.turn_seconds_left]);
    useEffect(() => {
        if (clock.base === null || game.status !== 'active') return;
        const timer = window.setInterval(
            () => setTick((value) => value + 1),
            1000,
        ); // só o contador visual
        return () => window.clearInterval(timer);
    }, [clock.base, game.status]);
    const secondsLeft =
        clock.base === null
            ? null
            : Math.max(
                  0,
                  clock.base - Math.floor((Date.now() - clock.at) / 1000),
              );

    // ---------- Histórico compacto ----------
    const seenHistory = useRef<Set<string>>(
        new Set(game.history.map((item) => item.id)),
    );
    const unseen = game.history.filter(
        (item) => !seenHistory.current.has(item.id),
    ).length;
    useEffect(() => {
        if (historyOpen)
            game.history.forEach((item) => seenHistory.current.add(item.id));
    }, [historyOpen, game.history]);

    const logActors = useMemo(
        () =>
            Object.fromEntries(
                participants.map((participant): [number, LogActor] => [
                    participant.id,
                    {
                        username: participant.player.username,
                        is_viewer: participant.is_viewer,
                        profile: participant.player,
                    },
                ]),
            ),
        [participants],
    );

    // ---------- Mesa ----------
    const bySide = (side: Side) =>
        participants.find(
            (participant) => sideOf(participant.seat, viewer.seat) === side,
        );
    const left = bySide(1);
    const top = bySide(2);
    const right = bySide(3);
    const boardPlayers = participants.map((participant) => ({
        id: participant.id,
        side: sideOf(participant.seat, viewer.seat),
        username: participant.player.username,
        path: participant.path.filter(
            (_, index) => !pendingSlots.has(`${participant.id}:${index}`),
        ),
        active:
            game.status === 'active' && game.current_seat === participant.seat,
    }));
    // `filter` acima remove cartas em voo, mas quebraria os índices: usa-se a lista completa com buracos.
    const boardPlayersFixed = boardPlayers.map((player, i) => ({
        ...player,
        path: participants[i].path.map((card, index) =>
            pendingSlots.has(`${player.id}:${index}`) ? undefined : card,
        ) as string[],
    }));

    const winner = participants.find(
        (participant) => participant.id === game.winner_participant_id,
    );
    const standings = useMemo(
        () =>
            [...participants].sort(
                (a, b) =>
                    (b.id === game.winner_participant_id ? 1 : 0) -
                        (a.id === game.winner_participant_id ? 1 : 0) ||
                    b.step - a.step,
            ),
        [participants, game.winner_participant_id],
    );

    // Indicadores circulares: um por jogador, junto à área dele no tabuleiro.
    const indicators = participants.map((participant) => ({
        player: toPanel(participant),
        side: sideOf(participant.seat, viewer.seat),
        active: game.status === 'active' && game.current_seat === participant.seat,
        you: participant.is_viewer,
    }));
    const previewTarget = preview ? participants.find((p) => p.id === preview.id) : undefined;
    const bannerHint =
        game.status !== 'active'
            ? ''
            : viewer.pending_discard > 0 && isTurn
              ? 'Descarte o excesso da mão'
              : isTurn
                ? turnHint
                : 'Aguarde a jogada';

    return (
        <AuthenticatedLayout immersive>
            <Head title="Partida em andamento" />
            <main className="game-stage relative flex-1 overflow-x-hidden text-white max-lg:flex max-lg:min-h-0 max-lg:flex-col max-lg:overflow-hidden">
                <header className="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 bg-black/40 px-3 py-1.5 backdrop-blur-sm max-lg:shrink-0 sm:px-5 lg:pointer-events-none lg:absolute lg:inset-x-0 lg:top-0 lg:z-30 lg:border-0 lg:bg-transparent lg:backdrop-blur-none [&>*]:lg:pointer-events-auto">
                    <div className="flex items-center gap-3">
                        <strong className="font-mono text-xs uppercase text-[#b4da33] max-sm:hidden">
                            Estação Commit
                        </strong>
                        <span className="text-white/30 max-sm:hidden">/</span>
                        <span className="font-mono text-[10px] uppercase text-white/65">
                            Turno {game.turn_number}
                        </span>
                    </div>
                    <div className="relative flex items-center gap-2 text-[10px] uppercase">
                        <span
                            aria-hidden="true"
                            className={`size-2 rounded-full ${online ? 'bg-[#b4da33]' : 'bg-[#d01600]'}`}
                        />
                        <span className="hidden font-mono text-white/60 sm:inline">
                            {online ? 'Sincronizado' : 'Reconectando…'}
                        </span>
                        <button
                            aria-expanded={historyOpen}
                            className="relative min-h-11 border border-white/25 px-3 font-mono font-bold hover:border-[#b4da33] sm:min-h-9 lg:hidden"
                            onClick={() => setHistoryOpen((value) => !value)}
                            type="button"
                        >
                            Registro
                            {!historyOpen && unseen > 0 && (
                                <span className="absolute -right-1 -top-1 grid size-4 place-items-center rounded-full bg-[#f481c6] text-[9px] font-black text-[#1d1d1b]">
                                    {unseen > 9 ? '9+' : unseen}
                                </span>
                            )}
                        </button>
                        <button
                            aria-label="Como jogar"
                            className="min-h-11 border border-white/25 px-3 font-mono font-bold hover:border-[#b4da33] sm:min-h-9"
                            onClick={() => setTutorial(true)}
                            title="Como jogar"
                            type="button"
                        >
                            ?
                        </button>
                        <button
                            className="min-h-11 border border-white/25 px-3 font-mono font-bold hover:border-[#f481c6] sm:min-h-9"
                            onClick={() => setLeaveOpen(true)}
                            type="button"
                        >
                            Sair
                        </button>

                        <AnimatePresence>
                            {historyOpen && (
                                <motion.aside
                                    animate={{ opacity: 1, y: 0 }}
                                    aria-label="Registro da partida"
                                    className="absolute right-0 top-full z-40 mt-2 w-72 max-w-[85vw] rounded-lg border-2 border-white/20 bg-[#10100f]/95 p-3 normal-case shadow-xl lg:hidden"
                                    exit={{ opacity: 0, y: -6 }}
                                    initial={
                                        reduced ? false : { opacity: 0, y: -6 }
                                    }
                                >
                                    <h2 className="mb-2 font-mono text-[10px] font-black uppercase tracking-[.14em] text-[#f481c6]">
                                        Registro da estação
                                    </h2>
                                    <GameLog
                                        actors={logActors}
                                        items={game.history}
                                    />
                                </motion.aside>
                            )}
                        </AnimatePresence>
                    </div>
                </header>

                <aside
                    aria-label="Últimas jogadas"
                    className="absolute left-3 top-12 z-20 hidden w-60 rounded-lg border-2 border-white/15 bg-[#10100f]/80 p-2.5 lg:block xl:w-64"
                >
                    <h2 className="mb-1.5 font-mono text-[10px] font-black uppercase tracking-[.14em] text-[#f481c6]">
                        Últimas jogadas
                    </h2>
                    <GameLog actors={logActors} items={game.history} />
                </aside>

                <section className="mx-auto w-full max-w-[1280px] px-2 py-2 max-lg:flex max-lg:min-h-0 max-lg:flex-1 max-lg:flex-col max-lg:gap-1.5 max-lg:py-1 sm:px-4">
                    {/* Prioridade visual: 1) tabuleiro e cartas, 2) quem joga agora, 3) indicadores. */}
                    <div className="relative z-20 shrink-0 lg:pt-10">
                        <TurnBanner
                            current={
                                currentPlayer
                                    ? { username: currentPlayer.player.username, profile: currentPlayer.player }
                                    : winner
                                      ? { username: winner.player.username, profile: winner.player }
                                      : null
                            }
                            finished={game.status !== 'active'}
                            hint={bannerHint}
                            isTurn={isTurn}
                            secondsLeft={game.status === 'active' ? secondsLeft : null}
                            turnNumber={game.turn_number}
                        />
                    </div>

                    <div className="mx-auto w-full max-lg:min-h-0 max-lg:flex-1 max-lg:[container-type:size] lg:mt-1 lg:w-[max(18rem,min(calc(100vw_-_21rem),calc(100dvh_-_19rem),58rem))]">
                        {/* Celular: o tabuleiro ocupa o MAIOR quadrado que cabe no espaço livre (sem rolagem). */}
                        <div className="max-lg:grid max-lg:size-full max-lg:place-items-center">
                            <div className="w-full max-lg:w-[min(100cqw,100cqh)]">
                                <GameBoard
                                    deckCount={game.deck_count}
                                    discardCount={game.discard_count}
                                    freshSlot={freshSlot}
                                    indicators={indicators}
                                    onSelectPlayer={selectPlayer}
                                    players={boardPlayersFixed}
                                />
                            </div>
                        </div>
                    </div>

                    <div className="relative z-20 mt-1 rounded-xl border-2 border-white/15 bg-[#161615]/80 p-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-[0_-10px_30px_#0006] max-lg:mt-0 max-lg:shrink-0 sm:p-3 lg:mt-1 lg:grid lg:grid-cols-[minmax(0,1fr)_11rem] lg:items-end lg:gap-3">
                        <div className="relative min-w-0">
                            <div
                                className="flex min-h-[9.5rem] items-end gap-0 overflow-x-auto px-1 pb-2 pt-7 [scrollbar-width:none] sm:gap-2 sm:[scrollbar-width:thin] max-sm:[&>*+*]:-ml-5 [&>:first-child]:ml-auto [&>:last-child]:mr-auto"
                                onScroll={measureHand}
                                ref={handRef}
                            >
                                {holdsBlock(viewer) && (
                                    <motion.div
                                        animate={{
                                            opacity:
                                                blockIncoming || blockOutgoing
                                                    ? 0
                                                    : 1,
                                        }}
                                        className="relative"
                                        transition={{
                                            // some na hora (a carta em voo assume); volta suave.
                                            duration:
                                                blockIncoming || blockOutgoing
                                                    ? 0
                                                    : 0.16,
                                        }}
                                    >
                                        {blockArrived && (
                                            <span
                                                aria-hidden="true"
                                                className="block-arrive-ring pointer-events-none absolute inset-0 rounded-xl"
                                            />
                                        )}
                                        <GameCard
                                            card={`block_${blocksPlayed(viewer) + 1}`}
                                            dataKey="block"
                                            dimmed={!isTurn}
                                            onClick={
                                                isTurn && !busy && blockRequired
                                                    ? () => send('play_block')
                                                    : undefined
                                            }
                                            playable={isTurn && blockRequired}
                                        />
                                    </motion.div>
                                )}
                                <AnimatePresence
                                    initial={false}
                                    mode="popLayout"
                                >
                                    {handKeys
                                        .filter(
                                            ({ index }) =>
                                                index !== hiddenIndex,
                                        )
                                        .map(({ card, index, key }) => (
                                            <GameCard
                                                card={card}
                                                dataKey={key}
                                                dimmed={!isTurn}
                                                key={key}
                                                onClick={
                                                    isTurn && !busy
                                                        ? () =>
                                                              setSelected(
                                                                  selected ===
                                                                      index
                                                                      ? null
                                                                      : index,
                                                              )
                                                        : undefined
                                                }
                                                playable={isPlayable(card)}
                                                selected={selected === index}
                                            />
                                        ))}
                                </AnimatePresence>
                            </div>
                            {handHint.left && (
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-[#161615] to-transparent"
                                />
                            )}
                            {handHint.right && (
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-y-0 right-0 flex w-10 items-center justify-end bg-gradient-to-l from-[#161615] to-transparent pr-1 text-lg font-black text-white/70"
                                >
                                    ›
                                </span>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-2 border-t border-white/10 pt-2 lg:flex lg:flex-col lg:items-stretch lg:border-0 lg:pt-0">
                            <span className="col-span-2 truncate font-mono text-[10px] uppercase text-white/50 lg:order-first">
                                {selectedCard
                                    ? cardLabel(selectedCard)
                                    : `Baralho ${game.deck_count} · Descarte ${game.discard_count}`}
                            </span>
                            {needsTarget && selectedCard && (
                                <TargetPicker
                                    card={selectedCard}
                                    onChange={setTarget}
                                    options={opponents.map((p) => ({
                                        id: p.id,
                                        username: p.player.username,
                                        is_bot: p.player.is_bot,
                                        hand_count:
                                            p.hand_count +
                                            (holdsBlock(p) ? 1 : 0),
                                        step: p.step,
                                        blocked: p.blocked,
                                        profile: p.player,
                                    }))}
                                    value={target}
                                    viewerStep={viewer.step}
                                />
                            )}
                            {viewer.pending_discard > 0 ? (
                                <button
                                    className="col-span-2 min-h-12 border-2 border-white bg-[#d01600] px-3 font-mono text-xs font-black uppercase disabled:opacity-40"
                                    disabled={busy || !selectedCard || !isTurn}
                                    onClick={() =>
                                        selectedCard &&
                                        send('discard_excess', selectedCard)
                                    }
                                    type="button"
                                >
                                    {selectedCard
                                        ? 'Descartar excesso'
                                        : 'Escolha uma carta para descartar'}
                                </button>
                            ) : (
                                <>
                                    <button
                                        className="min-h-12 border-2 border-white/60 bg-[#232323] px-3 font-mono text-xs font-black uppercase text-white disabled:opacity-35"
                                        disabled={
                                            busy || !isTurn || !selectedCard
                                        }
                                        onClick={() =>
                                            selectedCard &&
                                            send('exchange', selectedCard)
                                        }
                                        title="Trocar uma carta conta como a sua jogada"
                                        type="button"
                                    >
                                        Trocar carta
                                    </button>
                                    <button
                                        className="min-h-12 border-2 border-white bg-[#b4da33] px-3 font-mono text-xs font-black uppercase text-[#1d1d1b] shadow-[3px_3px_0_#f481c6] disabled:opacity-35 disabled:shadow-none"
                                        disabled={
                                            busy ||
                                            !isTurn ||
                                            !selectedCard ||
                                            !canPlaySelected ||
                                            (needsTarget && !target)
                                        }
                                        onClick={() =>
                                            selectedCard &&
                                            send(
                                                'play_card',
                                                selectedCard,
                                                target,
                                            )
                                        }
                                        type="button"
                                    >
                                        Jogar carta
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </section>
            </main>

            <MissionTutorial
                onClose={() => setTutorial(false)}
                show={tutorial}
            />
            <FlightLayer flights={flights} onDone={finishFlight} />

            {preview && previewTarget ? (
                <PlayerPreview
                    anchor={preview.anchor}
                    labels={profileLabels}
                    onClose={closePreview}
                    target={previewTarget as unknown as PreviewPlayer}
                />
            ) : null}

            {game.status === 'finished' && (
                <VictoryScreen
                    cleanupAt={game.cleanup_at}
                    dashboardHref={route('dashboard')}
                    gameId={game.id}
                    newRoomHref={`${route('dashboard')}#criar-sala`}
                    reduced={Boolean(reduced)}
                    standings={standings}
                    wins={game.winner_wins}
                    winner={winner}
                />
            )}
            <ConfirmDialog
                busy={leaving}
                busyLabel="Saindo..."
                confirmLabel="Abandonar partida"
                onClose={() => setLeaveOpen(false)}
                onConfirm={() => {
                    setLeaving(true);
                    router.delete(route('rooms.leave', room.id), {
                        onFinish: () => setLeaving(false),
                    });
                }}
                show={leaveOpen}
                title="Abandonar a partida?"
            >
                <p>
                    Você sai da mesa e um bot assume o seu lugar, com as suas
                    cartas e o seu percurso. Se não restar nenhuma pessoa, a
                    sala é encerrada.
                </p>
                <button
                    className="mt-3 min-h-11 w-full border-2 border-[#1d1d1b] bg-white font-mono text-xs font-black uppercase"
                    onClick={() => router.visit(route('dashboard'))}
                    type="button"
                >
                    Só voltar à Dashboard (continuo na mesa)
                </button>
            </ConfirmDialog>
        </AuthenticatedLayout>
    );
}
