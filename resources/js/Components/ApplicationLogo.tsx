import { ImgHTMLAttributes } from 'react';

/** Compatibilidade para telas antigas: usa o símbolo oficial da marca atual. */
export default function ApplicationLogo(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return (
        <img
            {...props}
            src="/images/estacao-commit/mascots/foguete-gato.png"
            alt={props.alt ?? 'Estação Commit'}
        />
    );
}
