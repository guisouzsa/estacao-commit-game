import { hasInAppHistory } from '@/lib/inAppHistory';
import { Link } from '@inertiajs/react';
import type { MouseEvent } from 'react';

/**
 * Botão "Voltar" padrão das telas (sempre no topo à esquerda, mesmo estilo).
 * Se a pessoa veio de outra tela do próprio site, volta para ela (como o botão do navegador). Se abriu a
 * página direto (link compartilhado, nova aba), leva ao destino seguro `href`.
 */
export default function BackLink({
    href,
    label = 'Voltar',
    className = '',
}: {
    href: string;
    label?: string;
    className?: string;
}) {
    const goBack = (event: MouseEvent) => {
        if (hasInAppHistory()) {
            event.preventDefault();
            window.history.back();
        }
    };

    return (
        <Link
            className={`inline-flex min-h-10 items-center gap-1.5 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-black uppercase text-[#1d1d1b] shadow-[2px_2px_0_#b4da33] hover:bg-[#b4da33] ${className}`}
            href={href}
            onClick={goBack}
        >
            <span aria-hidden="true">←</span>
            {label}
        </Link>
    );
}
