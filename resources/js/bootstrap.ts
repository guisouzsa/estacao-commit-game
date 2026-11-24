import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { createCloudflareEcho } from '@/lib/cloudflareRealtime';
import { createLazyEcho, type RealEcho } from '@/lib/lazyEcho';
import type { RoomEcho } from '@/lib/roomSync';
import { toast } from '@/lib/toast';

declare global {
    interface Window {
        Pusher: unknown;
        Echo: RoomEcho | null;
    }
}

// Variáveis VITE_* (definidas no build) têm prioridade. Sem elas, usa a chave pública
// entregue pelo servidor (meta tag) e o próprio endereço do site: o Apache já encaminha
// /app para o Reverb na mesma porta. Assim o tempo real funciona mesmo se o build do
// Render não recebeu os argumentos VITE_REVERB_*.
const metaKey =
    document
        .querySelector<HTMLMetaElement>('meta[name="reverb-key"]')
        ?.content?.trim() || undefined;
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || metaKey;
const reverbHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const secure = window.location.protocol === 'https:';
const reverbPort =
    import.meta.env.VITE_REVERB_PORT ||
    window.location.port ||
    (secure ? '443' : '80');
const reverbScheme =
    import.meta.env.VITE_REVERB_SCHEME || (secure ? 'https' : 'http');

// REALTIME_DRIVER=cloudflare: o servidor marca a página e o tempo real vem do Worker
// (Durable Objects). O restante do app usa window.Echo igual; nada mais muda.
const useCloudflare =
    document
        .querySelector<HTMLMetaElement>('meta[name="realtime-driver"]')
        ?.content === 'cloudflare';

if (useCloudflare) {
    let everConnected = false;
    let lost = false;
    window.Echo = createCloudflareEcho({
        fetchToken: (channel) =>
            window.axios
                .post('/realtime/token', { channel })
                .then((response) => response.data),
        createSocket: (url) => new WebSocket(url) as never,
        onConnectionChange: (connected) => {
            if (connected) {
                if (lost) {
                    toast.success('Conexão restabelecida.', { key: 'connection' });
                    lost = false;
                }
                everConnected = true;
            } else if (everConnected && !lost) {
                lost = true;
                toast.info(
                    'Sua conexão foi interrompida. Estamos tentando reconectar.',
                    { key: 'connection', durationMs: 0 },
                );
            }
        },
    }) as unknown as RoomEcho;
} else if (reverbKey && reverbHost) {
    // laravel-echo e pusher-js só são baixados, e o WebSocket só abre, quando uma página
    // assina um canal (sala, partida, Dashboard). Páginas sem tempo real (início, login,
    // perfil) não ocupam uma conexão do servidor nem pagam o custo do download.
    let everConnected = false;
    let lost = false;

    window.Echo = createLazyEcho(async () => {
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);
        window.Pusher = Pusher;

        const echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: reverbHost,
            wsPort: Number(reverbPort),
            wssPort: Number(reverbPort),
            forceTLS: reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });

        // Avisa quando a conexão em tempo real cai e quando volta. Um único aviso por
        // queda (a chave troca o conteúdo em vez de empilhar). A atualização do estado
        // depois da volta já é feita pelo sincronismo da página.
        try {
            echo.connector.pusher.connection.bind(
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

        return echo as unknown as RealEcho;
    });
} else {
    window.Echo = null;
    console.warn(
        'Tempo real desativado: a chave do Reverb não foi configurada no servidor (REVERB_APP_KEY). A página continua se atualizando por consulta periódica.',
    );
}
