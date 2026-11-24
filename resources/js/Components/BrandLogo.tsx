import { HTMLAttributes } from 'react';

/** Marca única do projeto: a mesma usada na navegação da Dashboard. */
export default function BrandLogo({
    className = '',
    dark = false,
    compact = false,
    ...props
}: HTMLAttributes<HTMLSpanElement> & {
    dark?: boolean;
    compact?: boolean;
}) {
    return (
        <span
            {...props}
            className={`inline-flex shrink-0 items-center gap-2 ${dark ? 'text-[#1d1d1b]' : 'text-white'} ${className}`}
        >
            <img
                src="/images/estacao-commit/mascots/foguete-gato.webp"
                alt=""
                className={compact ? 'size-10 object-contain' : 'size-11 object-contain sm:size-12'}
            />
            <span className={`${compact ? 'text-[10px] sm:text-sm' : 'text-sm sm:text-base'} font-black leading-tight tracking-tight`}>
                ESTAÇÃO
                <br />
                <span className={dark ? 'text-[#464498]' : 'text-[#b4da33]'}>
                    COMMIT
                </span>
            </span>
        </span>
    );
}
