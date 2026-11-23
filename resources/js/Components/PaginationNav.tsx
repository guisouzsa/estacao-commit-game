import { Link } from '@inertiajs/react';

type Paginated = {
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const base =
    'inline-flex min-h-10 items-center border-2 px-4 font-mono text-xs font-black uppercase focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]';

/** Anterior / Próxima + "Página X de Y", para qualquer lista paginada do Laravel. */
export default function PaginationNav({ page }: { page: Paginated }) {
    if (page.last_page <= 1) return null;

    return (
        <nav
            aria-label="Paginação"
            className="mt-5 flex flex-wrap items-center justify-between gap-3"
        >
            {page.prev_page_url ? (
                <Link
                    className={`${base} border-[#1d1d1b] bg-white hover:bg-[#b4da33]`}
                    href={page.prev_page_url}
                    preserveScroll
                >
                    ← Anterior
                </Link>
            ) : (
                <span className={`${base} border-[#1d1d1b]/25 text-[#1d1d1b]/35`}>
                    ← Anterior
                </span>
            )}
            <span className="font-mono text-xs font-bold text-[#5b5953]">
                Página {page.current_page} de {page.last_page}
            </span>
            {page.next_page_url ? (
                <Link
                    className={`${base} border-[#1d1d1b] bg-white hover:bg-[#b4da33]`}
                    href={page.next_page_url}
                    preserveScroll
                >
                    Próxima →
                </Link>
            ) : (
                <span className={`${base} border-[#1d1d1b]/25 text-[#1d1d1b]/35`}>
                    Próxima →
                </span>
            )}
        </nav>
    );
}
