import Modal from '@/Components/Modal';
import { KeyboardEvent, MouseEvent, useState } from 'react';

/**
 * Selo de verificado: pequeno, ao lado do nome (selo recortado azul com check branco).
 * Ao clicar, abre um aviso explicando o critério de concessão. Use sempre logo depois do @usuário.
 * Funciona dentro de links e cartões: o clique não navega.
 */
export default function VerifiedBadge({
    className = '',
    size = 16,
}: {
    className?: string;
    size?: number;
}) {
    const [open, setOpen] = useState(false);

    const show = (event: MouseEvent | KeyboardEvent) => {
        event.preventDefault();
        event.stopPropagation();
        setOpen(true);
    };

    return (
        <>
            <span
                aria-label="Perfil verificado. Toque para saber mais"
                className={`inline-flex shrink-0 cursor-pointer align-[-0.15em] ${className}`}
                onClick={show}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        show(event);
                    }
                }}
                role="button"
                tabIndex={0}
                title="Perfil verificado"
            >
                <svg
                    aria-hidden="true"
                    height={size}
                    viewBox="0 0 24 24"
                    width={size}
                >
                    <defs>
                        <linearGradient
                            id="vb-grad"
                            x1="0"
                            x2="1"
                            y1="0"
                            y2="1"
                        >
                            <stop offset="0" stopColor="#3aa6ff" />
                            <stop offset="1" stopColor="#1668d9" />
                        </linearGradient>
                    </defs>
                    <path
                        d="M12 1.6l2.6 1.9 3.2-.2 1.1 3 2.7 1.8-.9 3.1.9 3.1-2.7 1.8-1.1 3-3.2-.2L12 22.4l-2.6-1.9-3.2.2-1.1-3-2.7-1.8.9-3.1-.9-3.1 2.7-1.8 1.1-3 3.2.2L12 1.6z"
                        fill="url(#vb-grad)"
                    />
                    <path
                        d="m7.9 12.3 2.8 2.8 5.5-5.9"
                        fill="none"
                        stroke="#fff"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth="2.4"
                    />
                </svg>
            </span>

            <span
                className="contents"
                onClick={(event) => event.stopPropagation()}
            >
                <Modal maxWidth="sm" onClose={() => setOpen(false)} show={open}>
                    <div
                        className="p-6 text-[#1d1d1b]"
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="flex items-center gap-3">
                            <svg
                                aria-hidden="true"
                                height="34"
                                viewBox="0 0 24 24"
                                width="34"
                            >
                                <path
                                    d="M12 1.6l2.6 1.9 3.2-.2 1.1 3 2.7 1.8-.9 3.1.9 3.1-2.7 1.8-1.1 3-3.2-.2L12 22.4l-2.6-1.9-3.2.2-1.1-3-2.7-1.8.9-3.1-.9-3.1 2.7-1.8 1.1-3 3.2.2L12 1.6z"
                                    fill="#1668d9"
                                />
                                <path
                                    d="m7.9 12.3 2.8 2.8 5.5-5.9"
                                    fill="none"
                                    stroke="#fff"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2.4"
                                />
                            </svg>
                            <h3 className="text-lg font-black">
                                Perfil verificado
                            </h3>
                        </div>
                        <p className="mt-3 text-sm leading-6 text-[#393833]">
                            O selo de verificado identifica pessoas que
                            participam da concepção, da manutenção e da evolução
                            do Estação Commit: idealizadores, mantenedores,
                            colaboradores e demais responsáveis por manter o
                            jogo atualizado.
                        </p>
                        <p className="mt-2 text-sm leading-6 text-[#393833]">
                            A concessão é manual e exclusiva da administração. O
                            selo não pode ser solicitado nem atribuído pelo
                            próprio jogador.
                        </p>
                        <button
                            className="mt-5 min-h-10 border-2 border-[#1d1d1b] bg-[#b4da33] px-5 font-mono text-xs font-black uppercase"
                            onClick={() => setOpen(false)}
                            type="button"
                        >
                            Entendi
                        </button>
                    </div>
                </Modal>
            </span>
        </>
    );
}
