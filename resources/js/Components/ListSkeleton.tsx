/**
 * Esqueleto de lista (notificações, seguidores, seguindo). Tem a MESMA moldura e a mesma altura das
 * linhas reais, então a lista final entra no lugar sem empurrar a página (sem mudança brusca de layout).
 * O indicador fica só nesta área: o título, as abas e o resto da página continuam utilizáveis.
 */
export default function ListSkeleton({ rows = 6, label = 'Carregando lista' }: { rows?: number; label?: string }) {
    return (
        <ul
            aria-busy="true"
            aria-label={label}
            className="mt-4 border-[3px] border-[#1d1d1b] bg-white shadow-[4px_4px_0_#1d1d1b]"
            role="status"
        >
            {Array.from({ length: rows }, (_, index) => (
                <li
                    className="flex min-h-[4.25rem] items-center max-sm:min-h-[5.1rem] gap-3 border-b-2 border-[#1d1d1b]/10 p-3 last:border-b-0"
                    key={index}
                >
                    <span className="size-11 shrink-0 animate-pulse rounded-full bg-[#1d1d1b]/10 motion-reduce:animate-none" />
                    <span className="min-w-0 flex-1 space-y-2">
                        <span className="block h-3.5 w-2/5 animate-pulse rounded-sm bg-[#1d1d1b]/10 motion-reduce:animate-none" />
                        <span className="block h-3 w-3/5 animate-pulse rounded-sm bg-[#1d1d1b]/5 motion-reduce:animate-none" />
                    </span>
                </li>
            ))}
        </ul>
    );
}
