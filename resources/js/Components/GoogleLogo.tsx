/**
 * Logo "G" do Google nas quatro cores oficiais (SVG leve, sem imagem externa). Use em tudo que é ligado ao Google:
 * botões de entrar/cadastrar, escolha de nome após o Google e o cartão "Conta Google" do perfil.
 * Decorativo (aria-hidden): o texto ao lado ("Continuar com Google") é quem descreve a ação.
 */
export default function GoogleLogo({ className = 'size-5' }: { className?: string }) {
    return (
        <svg aria-hidden="true" className={`shrink-0 ${className}`} viewBox="0 0 48 48">
            <path
                d="M43.6 20.5H42V20.4H24v7.2h11.3C33.7 32.1 29.3 35.2 24 35.2c-6.2 0-11.2-5-11.2-11.2S17.8 12.8 24 12.8c2.9 0 5.4 1.1 7.4 2.8l5.1-5.1C33.2 7.5 28.8 5.6 24 5.6 13.8 5.6 5.6 13.8 5.6 24S13.8 42.4 24 42.4 42.4 34.2 42.4 24c0-1.2-.1-2.4-.4-3.5Z"
                fill="#FFC107"
            />
            <path
                d="m7.7 15.4 5.9 4.3c1.6-3.9 5.4-6.9 10.4-6.9 2.9 0 5.4 1.1 7.4 2.8l5.1-5.1C33.2 7.5 28.8 5.6 24 5.6c-7.1 0-13.2 4-16.3 9.8Z"
                fill="#FF3D00"
            />
            <path
                d="M24 42.4c4.7 0 9-1.8 12.2-4.7l-5.6-4.8c-1.8 1.4-4.1 2.3-6.6 2.3-5.3 0-9.7-3.4-11.3-8.1l-5.9 4.5C9.9 38.2 16.4 42.4 24 42.4Z"
                fill="#4CAF50"
            />
            <path
                d="M43.6 20.5H42V20.4H24v7.2h11.3c-.8 2.2-2.2 4.1-4.1 5.4l5.6 4.8c-.4.4 6-4.4 6-13.8 0-1.2-.1-2.4-.4-3.5Z"
                fill="#1976D2"
            />
        </svg>
    );
}
