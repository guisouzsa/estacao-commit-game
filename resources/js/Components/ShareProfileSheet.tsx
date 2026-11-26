import Modal from '@/Components/Modal';
import type { PlayerProfile } from '@/Components/PlayerProfileCard';
import { titleLabels } from '@/Components/PlayerProfileCard';
import { avatarDataUri } from '@/lib/avatarArt';
import { useEffect, useMemo, useRef, useState } from 'react';

// Compartilhar o perfil (no estilo dos apps de música): um painel com a prévia de um STORY 9:16 gerado na hora,
// opções do que mostrar e do fundo, e as ações (copiar link, compartilhar, baixar, WhatsApp, X).
// A imagem é desenhada num <canvas> no próprio navegador: nada é enviado ao servidor.

const W = 1080;
const H = 1920;

type Backdrop = 'estrelas' | 'violeta' | 'lima' | 'rosa';

const BACKDROPS: { key: Backdrop; label: string; swatch: string }[] = [
    { key: 'estrelas', label: 'Estrelas', swatch: 'linear-gradient(160deg,#0a1428,#14122b 60%,#464498)' },
    { key: 'violeta', label: 'Violeta', swatch: '#464498' },
    { key: 'lima', label: 'Lima', swatch: '#b4da33' },
    { key: 'rosa', label: 'Rosa', swatch: 'linear-gradient(160deg,#f481c6,#464498)' },
];

type Show = { photo: boolean; title: boolean; stats: boolean; achievements: boolean; link: boolean };

const SHOW_LABELS: [keyof Show, string][] = [
    ['photo', 'Foto'],
    ['title', 'Título'],
    ['stats', 'Vitórias e partidas'],
    ['achievements', 'Conquistas'],
    ['link', 'Link do perfil'],
];

function loadImage(src: string): Promise<HTMLImageElement | null> {
    return new Promise((resolve) => {
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.onload = () => resolve(image);
        image.onerror = () => resolve(null);
        image.src = src;
    });
}

/** Estrelas determinísticas (mesma semente = mesmo céu). */
function drawStars(ctx: CanvasRenderingContext2D, count: number, color: string) {
    let s = 7;
    const rnd = () => (s = (s * 9301 + 49297) % 233280) / 233280;
    ctx.fillStyle = color;
    for (let i = 0; i < count; i++) {
        ctx.globalAlpha = 0.35 + rnd() * 0.65;
        ctx.beginPath();
        ctx.arc(rnd() * W, rnd() * H, 1.5 + rnd() * 3.5, 0, Math.PI * 2);
        ctx.fill();
    }
    ctx.globalAlpha = 1;
}

function drawBackdrop(ctx: CanvasRenderingContext2D, backdrop: Backdrop) {
    if (backdrop === 'estrelas') {
        const g = ctx.createLinearGradient(0, 0, W * 0.4, H);
        g.addColorStop(0, '#0a1428');
        g.addColorStop(0.6, '#14122b');
        g.addColorStop(1, '#464498');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        drawStars(ctx, 160, '#ffffff');
        return;
    }
    if (backdrop === 'rosa') {
        const g = ctx.createLinearGradient(0, 0, W * 0.5, H);
        g.addColorStop(0, '#f481c6');
        g.addColorStop(1, '#464498');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
        return;
    }
    ctx.fillStyle = backdrop === 'lima' ? '#b4da33' : '#464498';
    ctx.fillRect(0, 0, W, H);
    // Grade da identidade do tabuleiro.
    ctx.strokeStyle = backdrop === 'lima' ? '#1d1d1b22' : '#ffffff22';
    ctx.lineWidth = 2;
    for (let x = 0; x <= W; x += 90) {
        ctx.beginPath();
        ctx.moveTo(x, 0);
        ctx.lineTo(x, H);
        ctx.stroke();
    }
    for (let y = 0; y <= H; y += 90) {
        ctx.beginPath();
        ctx.moveTo(0, y);
        ctx.lineTo(W, y);
        ctx.stroke();
    }
}

function fitText(ctx: CanvasRenderingContext2D, text: string, max: number, size: number, weight = 900) {
    let s = size;
    do {
        ctx.font = `${weight} ${s}px 'Space Grotesk', Arial, sans-serif`;
        s -= 4;
    } while (ctx.measureText(text).width > max && s > 24);
}

async function drawStory(canvas: HTMLCanvasElement, profile: PlayerProfile, show: Show, backdrop: Backdrop, url: string) {
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    await document.fonts?.ready;

    drawBackdrop(ctx, backdrop);
    const dark = backdrop !== 'lima';

    // Marca no topo.
    ctx.textAlign = 'center';
    ctx.fillStyle = dark ? '#ffffff' : '#1d1d1b';
    ctx.font = "700 40px 'DM Mono', monospace";
    ctx.fillText('ESTAÇÃO', W / 2 - 110, 170);
    ctx.fillStyle = dark ? '#b4da33' : '#464498';
    ctx.fillText('COMMIT', W / 2 + 130, 170);

    // Card do perfil (papel, borda preta e sombra deslocada, como no jogo).
    const cardX = 110;
    const cardW = W - 220;
    const cardY = 300;
    const hasExtras = show.title || show.stats || show.achievements;
    const cardH = 640 + (show.photo ? 0 : -260) + (hasExtras ? 360 : 0);
    ctx.fillStyle = dark ? '#b4da33' : '#1d1d1b';
    ctx.fillRect(cardX + 24, cardY + 24, cardW, cardH);
    ctx.fillStyle = '#f3f1ec';
    ctx.fillRect(cardX, cardY, cardW, cardH);
    ctx.lineWidth = 10;
    ctx.strokeStyle = '#1d1d1b';
    ctx.strokeRect(cardX, cardY, cardW, cardH);
    ctx.fillStyle = '#1d1d1b';
    ctx.fillRect(cardX, cardY, cardW, 70);
    ctx.font = "700 26px 'DM Mono', monospace";
    ctx.textAlign = 'left';
    ctx.fillStyle = '#ffffff';
    ctx.fillText('PERFIL DO JOGADOR', cardX + 40, cardY + 46);

    let y = cardY + 130;
    if (show.photo) {
        const size = 300;
        const cx = W / 2;
        const cy = y + size / 2;
        const art = await loadImage(
            profile.avatar || avatarDataUri(profile.avatar_style ?? 'pixel', profile.avatar_seed || profile.username),
        );
        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, cy, size / 2, 0, Math.PI * 2);
        ctx.closePath();
        ctx.fillStyle = '#464498';
        ctx.fill();
        ctx.clip();
        if (art) {
            ctx.imageSmoothingEnabled = Boolean(profile.avatar);
            ctx.drawImage(art, cx - size / 2, cy - size / 2, size, size);
        }
        ctx.restore();
        ctx.lineWidth = 14;
        ctx.strokeStyle = '#1d1d1b';
        ctx.beginPath();
        ctx.arc(cx, cy, size / 2, 0, Math.PI * 2);
        ctx.stroke();
        y += size + 70;
    }

    ctx.textAlign = 'center';
    ctx.fillStyle = '#1d1d1b';
    fitText(ctx, `@${profile.username}`, cardW - 100, 96);
    ctx.fillText(`@${profile.username}`, W / 2, y + 40);
    y += 110;

    const title = profile.profile_title ? titleLabels[profile.profile_title] : null;
    if (show.title && title) {
        ctx.font = "700 34px 'DM Mono', monospace";
        const width = ctx.measureText(title.toUpperCase()).width + 60;
        ctx.fillStyle = '#b4da33';
        ctx.fillRect(W / 2 - width / 2, y - 10, width, 64);
        ctx.lineWidth = 5;
        ctx.strokeRect(W / 2 - width / 2, y - 10, width, 64);
        ctx.fillStyle = '#1d1d1b';
        ctx.fillText(title.toUpperCase(), W / 2, y + 34);
        y += 110;
    }

    const stats: [string, string][] = [];
    if (show.stats && profile.statistics) {
        stats.push(['VITÓRIAS', String(profile.statistics.wins ?? 0)]);
        stats.push(['PARTIDAS', String(profile.statistics.matches ?? 0)]);
    }
    if (show.achievements) {
        stats.push(['CONQUISTAS', String((profile.badges ?? []).filter((b) => b.unlocked).length)]);
    }
    if (stats.length) {
        const gap = 24;
        const boxW = (cardW - 100 - gap * (stats.length - 1)) / stats.length;
        stats.forEach(([label, value], i) => {
            const x = cardX + 50 + i * (boxW + gap);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(x, y, boxW, 170);
            ctx.lineWidth = 5;
            ctx.strokeStyle = '#1d1d1b';
            ctx.strokeRect(x, y, boxW, 170);
            ctx.fillStyle = '#1d1d1b';
            ctx.font = "900 72px 'Space Grotesk', Arial, sans-serif";
            ctx.fillText(value, x + boxW / 2, y + 95);
            ctx.font = "700 24px 'DM Mono', monospace";
            ctx.fillStyle = '#464498';
            ctx.fillText(label, x + boxW / 2, y + 140);
        });
    }

    // Rodapé: convite e link.
    ctx.fillStyle = dark ? '#ffffff' : '#1d1d1b';
    ctx.font = "900 64px 'Space Grotesk', Arial, sans-serif";
    ctx.fillText('Bora jogar comigo?', W / 2, H - 330);
    if (show.link) {
        const label = url.replace(/^https?:\/\//, '');
        ctx.font = "700 36px 'DM Mono', monospace";
        const width = Math.min(W - 120, ctx.measureText(label).width + 80);
        ctx.fillStyle = dark ? '#b4da33' : '#1d1d1b';
        ctx.fillRect(W / 2 - width / 2, H - 270, width, 84);
        ctx.fillStyle = dark ? '#1d1d1b' : '#b4da33';
        fitText(ctx, label, width - 50, 36, 700);
        ctx.font = ctx.font.replace("'Space Grotesk', Arial, sans-serif", "'DM Mono', monospace");
        ctx.fillText(label, W / 2, H - 214);
    }
}

export default function ShareProfileSheet({
    profile,
    show: open,
    onClose,
}: {
    profile: PlayerProfile;
    show: boolean;
    onClose: () => void;
}) {
    const url = `${window.location.origin}/u/${profile.username}`;
    const [show, setShow] = useState<Show>({
        photo: true,
        title: Boolean(profile.profile_title),
        stats: true,
        achievements: false,
        link: true,
    });
    const [backdrop, setBackdrop] = useState<Backdrop>('estrelas');
    const [preview, setPreview] = useState<string>('');
    const [status, setStatus] = useState('');
    const canvas = useRef<HTMLCanvasElement | null>(null);

    useEffect(() => {
        if (!open) return;
        let cancelled = false;
        const element = (canvas.current ??= document.createElement('canvas'));
        element.width = W;
        element.height = H;
        drawStory(element, profile, show, backdrop, url).then(() => {
            if (!cancelled) setPreview(element.toDataURL('image/png'));
        });

        return () => {
            cancelled = true;
        };
    }, [open, profile, show, backdrop, url]);

    const storyFile = async (): Promise<File | null> => {
        const blob = await new Promise<Blob | null>((resolve) => canvas.current?.toBlob(resolve, 'image/png') ?? resolve(null));
        return blob ? new File([blob], `estacao-commit-${profile.username}.png`, { type: 'image/png' }) : null;
    };

    const text = `Bora jogar Estação Commit comigo? @${profile.username}`;
    const nativeFiles = useMemo(
        () => typeof navigator !== 'undefined' && typeof navigator.canShare === 'function',
        [],
    );

    const actions: { label: string; icon: string; run: () => Promise<void> | void }[] = [
        {
            label: 'Copiar link',
            icon: '🔗',
            run: async () => {
                await navigator.clipboard.writeText(url);
                setStatus('Link copiado.');
            },
        },
        {
            label: 'Compartilhar story',
            icon: '📲',
            run: async () => {
                const file = await storyFile();
                if (file && nativeFiles && navigator.canShare({ files: [file] })) {
                    await navigator.share({ files: [file], title: `@${profile.username} na Estação Commit`, text });
                    setStatus('Pronto para postar.');
                } else if (typeof navigator.share === 'function') {
                    await navigator.share({ title: `@${profile.username} na Estação Commit`, text, url });
                } else {
                    setStatus('Seu navegador não compartilha imagens: use "Baixar imagem" e poste no story.');
                }
            },
        },
        {
            label: 'Baixar imagem',
            icon: '⬇️',
            run: async () => {
                const file = await storyFile();
                if (!file) return;
                const link = document.createElement('a');
                link.href = URL.createObjectURL(file);
                link.download = file.name;
                link.click();
                setTimeout(() => URL.revokeObjectURL(link.href), 2000);
                setStatus('Imagem salva no formato de story (1080 × 1920).');
            },
        },
        {
            label: 'WhatsApp',
            icon: '💬',
            run: () => {
                window.open(`https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`, '_blank', 'noopener');
            },
        },
        {
            label: 'X',
            icon: '𝕏',
            run: () => {
                window.open(
                    `https://x.com/intent/post?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`,
                    '_blank',
                    'noopener',
                );
            },
        },
    ];

    return (
        <Modal maxWidth="2xl" onClose={onClose} show={open}>
            <div className="max-h-[calc(100dvh-3rem)] overflow-y-auto bg-[#f3f1ec] text-[#1d1d1b]">
                <header className="flex items-center justify-between gap-3 border-b-[3px] border-[#1d1d1b] bg-[#1d1d1b] px-4 py-3 text-white">
                    <h2 className="font-mono text-xs font-black uppercase tracking-[.16em]">
                        Compartilhar perfil
                    </h2>
                    <button
                        aria-label="Fechar"
                        className="grid size-9 place-items-center border-2 border-white/40 font-mono text-lg hover:border-[#b4da33]"
                        onClick={onClose}
                        type="button"
                    >
                        ×
                    </button>
                </header>

                <div className="grid gap-5 p-4 sm:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] sm:p-5">
                    {/* Prévia do story: mesma proporção da imagem que será postada. */}
                    <div className="mx-auto w-full max-w-[12rem] sm:max-w-none">
                        <div className="aspect-[9/16] w-full overflow-hidden border-[3px] border-[#1d1d1b] bg-[#14122b] shadow-[5px_5px_0_#464498]">
                            {preview && (
                                <img
                                    alt="Prévia do story do perfil"
                                    className="size-full object-cover"
                                    src={preview}
                                />
                            )}
                        </div>
                    </div>

                    <div className="space-y-4">
                        <fieldset>
                            <legend className="mb-2 font-mono text-[10px] font-black uppercase tracking-[.14em] text-[#464498]">
                                Mostrar no story
                            </legend>
                            <div className="flex flex-wrap gap-1.5">
                                {SHOW_LABELS.map(([key, label]) => (
                                    <button
                                        aria-pressed={show[key]}
                                        className={`min-h-9 border-2 px-2.5 text-xs font-bold ${
                                            show[key]
                                                ? 'border-[#1d1d1b] bg-[#b4da33]'
                                                : 'border-[#1d1d1b]/25 bg-white text-[#5b5953]'
                                        }`}
                                        key={key}
                                        onClick={() => setShow((current) => ({ ...current, [key]: !current[key] }))}
                                        type="button"
                                    >
                                        {show[key] ? '✓ ' : ''}
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend className="mb-2 font-mono text-[10px] font-black uppercase tracking-[.14em] text-[#464498]">
                                Fundo
                            </legend>
                            <div className="flex gap-2">
                                {BACKDROPS.map((option) => (
                                    <button
                                        aria-label={`Fundo ${option.label}`}
                                        aria-pressed={backdrop === option.key}
                                        className={`size-11 border-2 ${
                                            backdrop === option.key
                                                ? 'border-[#1d1d1b] shadow-[3px_3px_0_#1d1d1b]'
                                                : 'border-[#1d1d1b]/25'
                                        }`}
                                        key={option.key}
                                        onClick={() => setBackdrop(option.key)}
                                        style={{ background: option.swatch }}
                                        title={option.label}
                                        type="button"
                                    />
                                ))}
                            </div>
                        </fieldset>

                        <div className="grid grid-cols-5 gap-1.5 border-t-2 border-[#1d1d1b]/15 pt-4">
                            {actions.map((action) => (
                                <button
                                    className="flex flex-col items-center gap-1 text-center"
                                    key={action.label}
                                    onClick={async () => {
                                        try {
                                            await action.run();
                                        } catch (error) {
                                            if ((error as Error)?.name !== 'AbortError') {
                                                setStatus('Não deu certo agora. Tente outra opção.');
                                            }
                                        }
                                    }}
                                    type="button"
                                >
                                    <span
                                        aria-hidden="true"
                                        className="grid size-11 place-items-center rounded-full border-2 border-[#1d1d1b] bg-white text-lg shadow-[2px_2px_0_#1d1d1b] hover:bg-[#b4da33]"
                                    >
                                        {action.icon}
                                    </span>
                                    <span className="text-[10px] font-bold leading-tight sm:text-[11px]">
                                        {action.label}
                                    </span>
                                </button>
                            ))}
                        </div>
                        {status && (
                            <p
                                className="text-sm font-bold text-[#464498]"
                                role="status"
                            >
                                {status}
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </Modal>
    );
}
