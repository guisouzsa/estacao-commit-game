import { lazy, Suspense, useState } from 'react';

// O tutorial (cartas, imagens, Modal) só é baixado quando a pessoa clica: a landing, o login e
// o cadastro continuam leves.
const MissionTutorial = lazy(() => import('@/Components/MissionTutorial'));

/**
 * Botão verde "Como jogar", no estilo do botão de registro do site (borda e sombra dura),
 * para abrir o mesmo tutorial da partida sem precisar entrar numa sala.
 *
 * - `landing`: usa as classes de botão da página inicial (fundo escuro).
 * - `form`: telas claras (login, cadastro, conta temporária).
 */
export default function HowToPlayButton({
    variant = 'form',
    className = '',
}: {
    variant?: 'landing' | 'form';
    className?: string;
}) {
    const [open, setOpen] = useState(false);

    const style =
        variant === 'landing'
            ? 'btn-primary justify-center'
            : 'flex min-h-12 w-full items-center justify-center border-2 border-[#1d1d1b] bg-[#b4da33] px-4 text-center font-mono text-xs font-black uppercase shadow-[4px_4px_0_#464498] transition hover:-translate-y-0.5';

    return (
        <>
            <button
                className={`${style} ${className}`}
                onClick={() => setOpen(true)}
                type="button"
            >
                Aprenda a jogar
            </button>
            {open && (
                <Suspense fallback={null}>
                    <MissionTutorial onClose={() => setOpen(false)} show />
                </Suspense>
            )}
        </>
    );
}
