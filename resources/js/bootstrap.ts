import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { toast } from '@/lib/toast';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'> | null;
    }
}

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;
const reverbHost = import.meta.env.VITE_REVERB_HOST;

if (reverbKey && reverbHost) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    // Avisa quando a conexão em tempo real cai e quando volta. Um único aviso por
    // queda (a chave troca o conteúdo em vez de empilhar). A atualização do estado
    // depois da volta já é feita pelo sincronismo da página.
    try {
        let everConnected = false;
        let lost = false;
        window.Echo.connector.pusher.connection.bind(
            'state_change',
            ({ current }: { current: string }) => {
                if (current === 'connected') {
                    if (lost) {
                        toast.success('Conexão restabelecida.', { key: 'connection' });
                        lost = false;
                    }
                    everConnected = true;
                } else if (
                    everConnected &&
                    !lost &&
                    ['unavailable', 'failed', 'disconnected'].includes(current)
                ) {
                    lost = true;
                    toast.info(
                        'Sua conexão foi interrompida. Estamos tentando reconectar.',
                        { key: 'connection', durationMs: 0 },
                    );
                }
            },
        );
    } catch {
        // Sem aviso de conexão: o polling de segurança continua funcionando.
    }
} else {
    window.Echo = null;
    console.error(
        'Laravel Echo is disabled: VITE_REVERB_APP_KEY and VITE_REVERB_HOST are required.',
    );
}
