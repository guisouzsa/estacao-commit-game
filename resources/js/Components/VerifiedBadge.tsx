/**
 * Selo de verificado: pequeno, ao lado do nome. Selo recortado (como nas redes sociais)
 * em azul com um check branco. Use sempre logo depois do @usuário.
 */
export default function VerifiedBadge({
    className = '',
    size = 16,
}: {
    className?: string;
    size?: number;
}) {
    return (
        <svg
            aria-label="Perfil verificado"
            className={`inline-block shrink-0 align-[-0.15em] ${className}`}
            height={size}
            role="img"
            viewBox="0 0 24 24"
            width={size}
        >
            <title>Perfil verificado</title>
            <defs>
                <linearGradient id="vb-grad" x1="0" x2="1" y1="0" y2="1">
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
    );
}
