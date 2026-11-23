import '../css/app.css';
import './bootstrap';

import ToastHost from '@/Components/ToastHost';
import { friendlyHttpMessage, toast } from '@/lib/toast';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);

        // Host de avisos: montado uma única vez, fora da árvore do Inertia,
        // para os avisos sobreviverem à navegação entre páginas.
        const host = document.createElement('div');
        document.body.appendChild(host);
        createRoot(host).render(<ToastHost />);

        // Aviso enviado pelo servidor junto de um redirecionamento. O id impede
        // que reapareça em recarregamentos parciais.
        const showFlash = (pageProps: unknown) => {
            const flash = (pageProps as { flash?: { toast?: { id: string; tone: 'success' | 'info' | 'warning' | 'error'; title?: string; message: string } | null } })?.flash?.toast;
            if (flash) {
                toast.show({ id: `flash:${flash.id}`, tone: flash.tone, title: flash.title, message: flash.message });
            }
        };
        showFlash(props.initialPage.props);
        router.on('navigate', (event) => showFlash(event.detail.page.props));

        // Falha de rede: uma mensagem humana, sem tela de erro técnica.
        router.on('exception', () => {
            toast.error(
                'Sua conexão foi interrompida. Verifique a internet e tente novamente.',
                { key: 'network' },
            );
        });

        // Resposta que não é do Inertia (ex.: erro 500 em HTML). Em produção vira
        // um aviso amigável; em desenvolvimento mantém a tela de depuração.
        router.on('invalid', (event) => {
            if (!import.meta.env.PROD) return;
            event.preventDefault();
            toast.error(friendlyHttpMessage(event.detail.response.status));
        });
    },
    progress: {
        color: '#4B5563',
    },
});
