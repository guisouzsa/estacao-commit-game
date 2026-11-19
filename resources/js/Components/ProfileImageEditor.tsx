import { type PointerEvent, useEffect, useRef, useState } from 'react';

type CropValues = {
    x: number;
    y: number;
    zoom: number;
};

export default function ProfileImageEditor({
    file,
    src,
    kind,
    initial,
    frame,
    onCancel,
    onConfirm,
}: {
    file?: File;
    src?: string;
    kind: 'avatar' | 'cover';
    initial: CropValues;
    frame: string;
    onCancel: () => void;
    onConfirm: (crop: CropValues) => void;
}) {
    const [imageUrl, setImageUrl] = useState('');
    const [crop, setCrop] = useState(initial);
    const viewport = useRef<HTMLDivElement>(null);
    const dragStart = useRef<{
        pointerX: number;
        pointerY: number;
        x: number;
        y: number;
    } | null>(null);

    useEffect(() => {
        if (!file) {
            setImageUrl(src ?? '');

            return;
        }

        const url = URL.createObjectURL(file);
        setImageUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [file, src]);

    const moveImage = (event: PointerEvent<HTMLDivElement>) => {
        if (!dragStart.current || !viewport.current) return;

        const rect = viewport.current.getBoundingClientRect();
        setCrop((current) => ({
            ...current,
            x: Math.max(
                0,
                Math.min(
                    100,
                    dragStart.current!.x -
                        ((event.clientX - dragStart.current!.pointerX) /
                            rect.width) *
                            100,
                ),
            ),
            y: Math.max(
                0,
                Math.min(
                    100,
                    dragStart.current!.y -
                        ((event.clientY - dragStart.current!.pointerY) /
                            rect.height) *
                            100,
                ),
            ),
        }));
    };

    return (
        <div
            className="fixed inset-0 z-[100] flex items-center justify-center bg-[#1d1d1b]/80 p-4 backdrop-blur-sm"
            role="presentation"
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) onCancel();
            }}
        >
            <section
                aria-labelledby="profile-image-editor-title"
                aria-modal="true"
                className="w-full max-w-xl border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 text-[#1d1d1b] shadow-[8px_8px_0_#b4da33] sm:p-7"
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
                    Arraste a imagem para reposicionar. A prévia mostra o corte
                    que aparecerá no perfil.
                </p>

                <div
                    className={`mx-auto mt-6 cursor-grab touch-none select-none overflow-hidden border-[5px] border-[#1d1d1b] bg-[#464498] active:cursor-grabbing ${
                        kind === 'avatar'
                            ? `size-56 rounded-full ${frame}`
                            : 'aspect-[2.4/1] w-full'
                    }`}
                    onPointerDown={(event) => {
                        const rect =
                            event.currentTarget.getBoundingClientRect();
                        dragStart.current = {
                            pointerX: event.clientX,
                            pointerY: event.clientY,
                            x: crop.x,
                            y: crop.y,
                        };
                        event.currentTarget.setPointerCapture(event.pointerId);
                        if (!rect.width) dragStart.current = null;
                    }}
                    onPointerMove={moveImage}
                    onPointerUp={() => {
                        dragStart.current = null;
                    }}
                    onPointerCancel={() => {
                        dragStart.current = null;
                    }}
                    ref={viewport}
                    style={{ touchAction: 'none' }}
                >
                    {imageUrl && (
                        <img
                            alt="Prévia do recorte"
                            className="h-full w-full object-cover"
                            draggable={false}
                            src={imageUrl}
                            style={{
                                objectPosition: `${crop.x}% ${crop.y}%`,
                                transform:
                                    kind === 'avatar'
                                        ? `scale(${crop.zoom / 100})`
                                        : undefined,
                            }}
                        />
                    )}
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                    {kind === 'avatar' && (
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
                    )}
                    <label className="text-sm font-bold">
                        <span className="flex justify-between">
                            <span>Horizontal</span>
                            <span className="font-mono text-xs">{crop.x}%</span>
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
                            <span className="font-mono text-xs">{crop.y}%</span>
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
