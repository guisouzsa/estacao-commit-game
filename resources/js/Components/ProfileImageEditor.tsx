import CoverImage, { CoverFraming } from '@/Components/CoverImage';
import {
    type KeyboardEvent,
    type PointerEvent,
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';

type CropValues = {
    x: number;
    y: number;
    zoom: number;
};

const clamp = (value: number, min: number, max: number) =>
    Math.min(max, Math.max(min, value));

/** Passo de teclado em % inteiros, sem "travar" quando o passo calculado fica abaixo de 1%. */
const atLeastOne = (delta: number) =>
    delta === 0 ? 0 : Math.sign(delta) * Math.max(1, Math.abs(delta));

/** Limites do zoom da capa (o servidor aceita 10–300). */
const COVER_MAX_ZOOM = 300;
const COVER_MIN_ZOOM_FLOOR = 10;

/**
 * Enquadramento inicial de uma capa NOVA: em vez de sempre "preencher" (que numa faixa 5:1
 * corta a maior parte de uma foto comum), mostra pelo menos metade da altura da imagem,
 * sem deixar sobras laterais maiores que 20% de cada lado.
 */
function initialCoverZoom(fillHeightRatio: number, minZoom: number) {
    // fillHeightRatio = altura da imagem "preenchendo" / altura da faixa (>= 1 em fotos comuns)
    const showHalf = 200 / fillHeightRatio;
    return Math.round(clamp(Math.max(60, showHalf), minZoom, 100));
}

export default function ProfileImageEditor({
    file,
    src,
    kind,
    initial,
    frame,
    coverAspect = 5,
    freshCover = false,
    onCancel,
    onConfirm,
}: {
    file?: File;
    src?: string;
    kind: 'avatar' | 'cover';
    initial: CropValues;
    frame: string;
    /** Proporção (largura/altura) REAL da capa no card, medida na página: a prévia usa a mesma. */
    coverAspect?: number;
    /** Imagem recém-escolhida: calcula um enquadramento inicial menos aproximado. */
    freshCover?: boolean;
    onCancel: () => void;
    onConfirm: (crop: CropValues) => void;
}) {
    const [imageUrl, setImageUrl] = useState('');
    const [crop, setCrop] = useState(initial);
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(
        null,
    );
    const [dragging, setDragging] = useState(false);
    const viewport = useRef<HTMLDivElement>(null);
    const dragStart = useRef<{
        pointerX: number;
        pointerY: number;
        x: number;
        y: number;
    } | null>(null);
    const isCover = kind === 'cover';

    useEffect(() => {
        if (!file) {
            setImageUrl(src ?? '');

            return;
        }

        const url = URL.createObjectURL(file);
        setImageUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [file, src]);

    // Geometria da capa (independente de pixels: tudo relativo à altura da faixa = 1).
    // fillScale: quanto a imagem "preenchendo" é maior que a faixa em cada eixo.
    const imageAspect = natural ? natural.w / natural.h : coverAspect;
    const fillW = Math.max(1, imageAspect / coverAspect); // largura preenchida / largura da faixa
    const fillH = Math.max(1, coverAspect / imageAspect); // altura preenchida / altura da faixa
    // Zoom em que a imagem inteira cabe na faixa ("conter").
    const minCoverZoom = Math.max(
        COVER_MIN_ZOOM_FLOOR,
        Math.floor(100 / Math.max(fillW, fillH)),
    );
    const minZoom = isCover ? minCoverZoom : 100;
    const maxZoom = isCover ? COVER_MAX_ZOOM : 200;

    const defaultFraming = useCallback(
        (): CropValues => ({
            x: 50,
            y: 50,
            zoom: initialCoverZoom(fillH, minCoverZoom),
        }),
        [fillH, minCoverZoom],
    );

    // Capa nova: aplica o enquadramento inicial assim que o tamanho da imagem é conhecido.
    const framedFresh = useRef(false);
    useEffect(() => {
        if (!isCover || !natural || !freshCover || framedFresh.current) return;
        framedFresh.current = true;
        setCrop(defaultFraming());
    }, [isCover, natural, freshCover, defaultFraming]);

    // Zoom salvo fora dos limites desta imagem (ex.: valor antigo) é trazido para dentro.
    useEffect(() => {
        if (!isCover || !natural) return;
        setCrop((c) =>
            c.zoom < minZoom || c.zoom > maxZoom
                ? { ...c, zoom: clamp(c.zoom, minZoom, maxZoom) }
                : c,
        );
    }, [isCover, natural, minZoom, maxZoom]);

    const setZoom = (zoom: number) =>
        setCrop((c) => ({ ...c, zoom: Math.round(clamp(zoom, minZoom, maxZoom)) }));

    /**
     * Arrastar segue o cursor 1:1. A imagem fica em left = x·(L − largura exibida); então
     * mover o ponteiro dx pixels muda x em dx / (L − largura exibida). Se a imagem ocupa a
     * largura exata, não há o que mover naquele eixo.
     */
    const moveCover = (event: PointerEvent<HTMLDivElement>) => {
        const start = dragStart.current;
        const box = viewport.current?.getBoundingClientRect();
        if (!start || !box) return;
        const z = crop.zoom / 100;
        const slackX = box.width * (1 - fillW * z);
        const slackY = box.height * (1 - fillH * z);
        const dx = event.clientX - start.pointerX;
        const dy = event.clientY - start.pointerY;
        setCrop((c) => ({
            ...c,
            x:
                Math.abs(slackX) < 0.5
                    ? c.x
                    : clamp(start.x + (dx / slackX) * 100, 0, 100),
            y:
                Math.abs(slackY) < 0.5
                    ? c.y
                    : clamp(start.y + (dy / slackY) * 100, 0, 100),
        }));
    };

    // Avatar: comportamento original (arrasto proporcional ao tamanho do recorte).
    const moveAvatar = (event: PointerEvent<HTMLDivElement>) => {
        const start = dragStart.current;
        const rect = viewport.current?.getBoundingClientRect();
        if (!start || !rect) return;
        setCrop((current) => ({
            ...current,
            x: clamp(
                start.x - ((event.clientX - start.pointerX) / rect.width) * 100,
                0,
                100,
            ),
            y: clamp(
                start.y - ((event.clientY - start.pointerY) / rect.height) * 100,
                0,
                100,
            ),
        }));
    };

    // Setas movem 1% (Shift: 5%); + e − ajustam o zoom.
    const onPreviewKey = (event: KeyboardEvent<HTMLDivElement>) => {
        const step = event.shiftKey ? 5 : 1;
        const moves: Record<string, [number, number]> = {
            ArrowLeft: [-step, 0],
            ArrowRight: [step, 0],
            ArrowUp: [0, -step],
            ArrowDown: [0, step],
        };
        if (moves[event.key]) {
            event.preventDefault();
            const [mx, my] = moves[event.key];
            if (isCover) {
                // A imagem anda na direção da seta: `step`% da largura/altura da faixa.
                const z = crop.zoom / 100;
                const slackX = 1 - fillW * z;
                const slackY = 1 - fillH * z;
                setCrop((c) => ({
                    ...c,
                    x:
                        Math.abs(slackX) < 0.005
                            ? c.x
                            : Math.round(clamp(c.x + atLeastOne(mx / slackX), 0, 100)),
                    y:
                        Math.abs(slackY) < 0.005
                            ? c.y
                            : Math.round(clamp(c.y + atLeastOne(my / slackY), 0, 100)),
                }));
                return;
            }
            setCrop((c) => ({
                ...c,
                x: clamp(c.x + mx, 0, 100),
                y: clamp(c.y + my, 0, 100),
            }));
        } else if (isCover && (event.key === '+' || event.key === '=')) {
            event.preventDefault();
            setZoom(crop.zoom + step * 2);
        } else if (isCover && (event.key === '-' || event.key === '_')) {
            event.preventDefault();
            setZoom(crop.zoom - step * 2);
        }
    };

    // Roda do mouse sobre a prévia da capa: zoom suave (sem rolar a página).
    useEffect(() => {
        const el = viewport.current;
        if (!el || !isCover) return;
        const onWheel = (event: WheelEvent) => {
            event.preventDefault();
            setCrop((c) => ({
                ...c,
                zoom: Math.round(
                    clamp(c.zoom - event.deltaY * 0.05, minZoom, maxZoom),
                ),
            }));
        };
        el.addEventListener('wheel', onWheel, { passive: false });
        return () => el.removeEventListener('wheel', onWheel);
    }, [isCover, minZoom, maxZoom]);

    // Mapa da imagem inteira com o retângulo do que aparece na capa.
    const visible = (() => {
        const z = crop.zoom / 100;
        const w = fillW * z; // largura exibida / largura da faixa
        const h = fillH * z;
        const left = (crop.x / 100) * (1 - w); // em larguras da faixa
        const top = (crop.y / 100) * (1 - h);
        return {
            left: clamp(-left / w, 0, 1),
            top: clamp(-top / h, 0, 1),
            width: Math.min(1, 1 / w),
            height: Math.min(1, 1 / h),
        };
    })();
    const shownPercent = Math.round(visible.width * visible.height * 100);

    const framing: CoverFraming = crop;

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-[#1d1d1b]/80 p-4 backdrop-blur-sm"
            role="presentation"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) onCancel();
            }}
        >
            <section
                aria-labelledby="profile-image-editor-title"
                aria-modal="true"
                className={`w-full border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 text-[#1d1d1b] shadow-[8px_8px_0_#b4da33] sm:p-7 ${isCover ? 'max-w-3xl' : 'max-w-xl'}`}
                onKeyDown={(event) => {
                    if (event.key === 'Escape') onCancel();
                }}
                role="dialog"
                tabIndex={-1}
            >
                <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-[#464498]">
                    Estação Commit / editor de imagem
                </p>
                <h2
                    className="mt-2 text-2xl font-black"
                    id="profile-image-editor-title"
                >
                    {kind === 'avatar'
                        ? 'Enquadre seu avatar'
                        : 'Ajuste sua capa'}
                </h2>
                <p className="mt-2 text-sm text-[#5b5953]">
                    {isCover
                        ? 'Arraste a imagem para mover. Use o zoom (ou a roda do mouse) para mostrar mais ou menos da foto. A prévia tem a mesma proporção da capa do seu perfil.'
                        : 'Arraste a imagem para reposicionar. A prévia mostra o corte que aparecerá no perfil.'}
                </p>

                <div
                    aria-label={
                        isCover
                            ? 'Prévia da capa. Arraste para mover; setas movem, + e − ajustam o zoom.'
                            : 'Prévia do avatar. Arraste ou use as setas para mover.'
                    }
                    className={`relative mx-auto mt-6 touch-none select-none overflow-hidden border-[5px] border-[#1d1d1b] bg-[#464498] outline-none focus-visible:ring-4 focus-visible:ring-[#b4da33] ${
                        dragging ? 'cursor-grabbing' : 'cursor-grab'
                    } ${kind === 'avatar' ? `size-56 rounded-full ${frame}` : 'w-full'}`}
                    onKeyDown={onPreviewKey}
                    onPointerCancel={() => {
                        dragStart.current = null;
                        setDragging(false);
                    }}
                    onPointerDown={(event) => {
                        dragStart.current = {
                            pointerX: event.clientX,
                            pointerY: event.clientY,
                            x: crop.x,
                            y: crop.y,
                        };
                        setDragging(true);
                        event.currentTarget.setPointerCapture(event.pointerId);
                    }}
                    onPointerMove={isCover ? moveCover : moveAvatar}
                    onPointerUp={() => {
                        dragStart.current = null;
                        setDragging(false);
                        // O perfil guarda a posição em % inteiros: ao soltar, a prévia já mostra
                        // exatamente o que será publicado.
                        setCrop((c) => ({
                            ...c,
                            x: Math.round(c.x),
                            y: Math.round(c.y),
                        }));
                    }}
                    ref={viewport}
                    role="img"
                    style={
                        isCover
                            ? { aspectRatio: `${coverAspect}`, touchAction: 'none' }
                            : { touchAction: 'none' }
                    }
                    tabIndex={0}
                >
                    {imageUrl &&
                        (isCover ? (
                            <CoverImage framing={framing} src={imageUrl} />
                        ) : (
                            <img
                                alt="Prévia do recorte"
                                className="h-full w-full object-cover"
                                draggable={false}
                                src={imageUrl}
                                style={{
                                    objectPosition: `${crop.x}% ${crop.y}%`,
                                    transform: `scale(${crop.zoom / 100})`,
                                }}
                            />
                        ))}
                    {isCover && imageUrl && (
                        // Imagem invisível só para descobrir o tamanho original (proporção).
                        <img
                            alt=""
                            aria-hidden="true"
                            className="hidden"
                            onLoad={(event) =>
                                setNatural({
                                    w: event.currentTarget.naturalWidth || 1,
                                    h: event.currentTarget.naturalHeight || 1,
                                })
                            }
                            src={imageUrl}
                        />
                    )}
                </div>

                {isCover ? (
                    <div className="mt-5 grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                        <div>
                            <label className="block text-sm font-bold">
                                <span className="flex justify-between">
                                    <span>Zoom</span>
                                    <span className="font-mono text-xs">
                                        {crop.zoom}%
                                        {crop.zoom === 100
                                            ? ' · preenchendo'
                                            : crop.zoom <= minZoom
                                              ? ' · imagem inteira'
                                              : ''}
                                    </span>
                                </span>
                                <span className="mt-2 flex items-center gap-2">
                                    <button
                                        aria-label="Diminuir zoom"
                                        className="grid size-9 shrink-0 place-items-center border-2 border-[#1d1d1b] bg-white font-mono text-lg font-black disabled:opacity-40"
                                        disabled={crop.zoom <= minZoom}
                                        onClick={() => setZoom(crop.zoom - 5)}
                                        type="button"
                                    >
                                        −
                                    </button>
                                    <input
                                        aria-label="Zoom da capa"
                                        className="w-full accent-[#464498]"
                                        max={maxZoom}
                                        min={minZoom}
                                        onChange={(event) =>
                                            setZoom(Number(event.target.value))
                                        }
                                        step={1}
                                        type="range"
                                        value={crop.zoom}
                                    />
                                    <button
                                        aria-label="Aumentar zoom"
                                        className="grid size-9 shrink-0 place-items-center border-2 border-[#1d1d1b] bg-white font-mono text-lg font-black disabled:opacity-40"
                                        disabled={crop.zoom >= maxZoom}
                                        onClick={() => setZoom(crop.zoom + 5)}
                                        type="button"
                                    >
                                        +
                                    </button>
                                </span>
                            </label>
                            <div className="mt-4 flex flex-wrap gap-2">
                                {[
                                    {
                                        label: 'Centralizar',
                                        apply: () =>
                                            setCrop((c) => ({ ...c, x: 50, y: 50 })),
                                    },
                                    {
                                        label: 'Imagem inteira',
                                        apply: () =>
                                            setCrop({ x: 50, y: 50, zoom: minZoom }),
                                    },
                                    {
                                        label: 'Preencher a capa',
                                        apply: () =>
                                            setCrop((c) => ({ ...c, zoom: 100 })),
                                    },
                                    {
                                        label: 'Redefinir',
                                        apply: () => setCrop(defaultFraming()),
                                    },
                                ].map((action) => (
                                    <button
                                        className="min-h-10 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-[11px] font-bold uppercase hover:bg-[#b4da33]"
                                        key={action.label}
                                        onClick={action.apply}
                                        type="button"
                                    >
                                        {action.label}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Para não perder partes da foto sem perceber: a imagem inteira e o que fica visível. */}
                        <figure>
                            <div
                                className="relative overflow-hidden border-2 border-[#1d1d1b] bg-[#1d1d1b]"
                                style={{ aspectRatio: `${imageAspect}` }}
                            >
                                {imageUrl && (
                                    <img
                                        alt=""
                                        className="size-full object-fill opacity-50"
                                        draggable={false}
                                        src={imageUrl}
                                    />
                                )}
                                <div
                                    aria-hidden="true"
                                    className="absolute border-2 border-[#b4da33] shadow-[0_0_0_999px_#1d1d1b80]"
                                    style={{
                                        left: `${visible.left * 100}%`,
                                        top: `${visible.top * 100}%`,
                                        width: `${visible.width * 100}%`,
                                        height: `${visible.height * 100}%`,
                                    }}
                                />
                            </div>
                            <figcaption className="mt-1 font-mono text-[10px] font-bold uppercase text-[#5b5953]">
                                Aparece na capa: {shownPercent}% da foto
                            </figcaption>
                        </figure>
                    </div>
                ) : (
                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-bold sm:col-span-2">
                            <span className="flex justify-between">
                                <span>Zoom</span>
                                <span className="font-mono text-xs">
                                    {crop.zoom}%
                                </span>
                            </span>
                            <input
                                aria-label="Zoom do avatar"
                                className="mt-2 w-full accent-[#464498]"
                                max={200}
                                min={100}
                                onChange={(event) =>
                                    setCrop((value) => ({
                                        ...value,
                                        zoom: Number(event.target.value),
                                    }))
                                }
                                type="range"
                                value={crop.zoom}
                            />
                        </label>
                        <label className="text-sm font-bold">
                            <span className="flex justify-between">
                                <span>Horizontal</span>
                                <span className="font-mono text-xs">
                                    {Math.round(crop.x)}%
                                </span>
                            </span>
                            <input
                                aria-label="Posição horizontal da imagem"
                                className="mt-2 w-full accent-[#464498]"
                                max={100}
                                min={0}
                                onChange={(event) =>
                                    setCrop((value) => ({
                                        ...value,
                                        x: Number(event.target.value),
                                    }))
                                }
                                type="range"
                                value={crop.x}
                            />
                        </label>
                        <label className="text-sm font-bold">
                            <span className="flex justify-between">
                                <span>Vertical</span>
                                <span className="font-mono text-xs">
                                    {Math.round(crop.y)}%
                                </span>
                            </span>
                            <input
                                aria-label="Posição vertical da imagem"
                                className="mt-2 w-full accent-[#464498]"
                                max={100}
                                min={0}
                                onChange={(event) =>
                                    setCrop((value) => ({
                                        ...value,
                                        y: Number(event.target.value),
                                    }))
                                }
                                type="range"
                                value={crop.y}
                            />
                        </label>
                    </div>
                )}

                <div className="mt-7 flex flex-wrap justify-end gap-3 border-t-2 border-[#1d1d1b]/15 pt-5">
                    <button
                        className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 font-mono text-xs font-bold uppercase"
                        onClick={onCancel}
                        type="button"
                    >
                        Cancelar
                    </button>
                    <button
                        className="min-h-11 border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b]"
                        onClick={() => onConfirm(crop)}
                        type="button"
                    >
                        Confirmar recorte
                    </button>
                </div>
            </section>
        </div>
    );
}
