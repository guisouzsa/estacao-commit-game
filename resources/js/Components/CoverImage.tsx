import type { CSSProperties } from 'react';

export type CoverFraming = {
    /** 0–100: qual ponto da imagem fica alinhado ao mesmo ponto da faixa (igual a object-position). */
    x: number;
    y: number;
    /** % do enquadramento "preencher": 100 = cobre a faixa toda (como as capas sempre foram exibidas);
     *  abaixo de 100 a imagem aparece inteira/maior, e a sobra ganha um fundo desfocado da própria imagem. */
    zoom: number;
};

export const DEFAULT_COVER_FRAMING: CoverFraming = { x: 50, y: 50, zoom: 100 };

/**
 * Estilo da imagem enquadrada. Com object-fit: cover + object-position x% y%, aplicar
 * scale(z) com transform-origin no MESMO ponto x% y% posiciona a imagem exatamente em
 * left = x · (L − largura·z) e top = y · (A − altura·z): é o object-position generalizado
 * para qualquer zoom. Só usa porcentagens, então o resultado é idêntico em qualquer tamanho
 * de faixa com a mesma proporção (prévia do editor = capa publicada).
 */
export function coverImageStyle({ x, y, zoom }: CoverFraming): CSSProperties {
    return {
        objectPosition: `${x}% ${y}%`,
        transform: zoom === 100 ? undefined : `scale(${zoom / 100})`,
        transformOrigin: `${x}% ${y}%`,
    };
}

/** Capa enquadrada; preenche o elemento pai (que precisa ser `relative` e `overflow-hidden`). */
export default function CoverImage({
    src,
    framing,
    alt = '',
}: {
    src: string;
    framing: CoverFraming;
    alt?: string;
}) {
    return (
        <>
            {framing.zoom < 100 && (
                // A mesma imagem (já em cache), desfocada, ocupa as sobras laterais/verticais.
                <img
                    alt=""
                    aria-hidden="true"
                    className="absolute inset-0 size-full scale-110 object-cover opacity-70 blur-xl brightness-75"
                    draggable={false}
                    src={src}
                />
            )}
            <img
                alt={alt}
                className="absolute inset-0 size-full select-none object-cover"
                draggable={false}
                src={src}
                style={coverImageStyle(framing)}
            />
        </>
    );
}
