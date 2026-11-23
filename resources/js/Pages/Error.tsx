import BrandLogo from '@/Components/BrandLogo';
import { Head, Link } from '@inertiajs/react';

type Kind =
    | 'invite'
    | 'room'
    | 'profile'
    | 'page'
    | 'bad_request'
    | 'unauthenticated'
    | 'forbidden'
    | 'method'
    | 'request_timeout'
    | 'gone'
    | 'conflict'
    | 'session'
    | 'invalid_data'
    | 'throttle'
    | 'server'
    | 'bad_gateway'
    | 'maintenance'
    | 'gateway_timeout'
    | 'http_version';

const COPY: Record<Kind, { title: string; body: string; retry?: boolean }> = {
    invite: {
        title: 'Esse convite não está disponível.',
        body: 'Ele pode ter expirado, sido revogado ou pertencer a uma sala que já terminou.',
    },
    room: {
        title: 'Ops! Essa sala não foi encontrada.',
        // Não afirma o motivo: a sala pode nunca ter existido ou ser de outra pessoa.
        body: 'Talvez ela tenha sido excluída, expirado ou o endereço esteja incorreto.',
    },
    profile: {
        title: 'Perfil não encontrado.',
        body: 'Esse jogador não existe ou mudou de nome. Confira o endereço e tente de novo.',
    },
    page: {
        title: 'Ops! Essa página não foi encontrada.',
        body: 'O endereço pode estar incorreto ou a página não existe mais.',
    },
    bad_request: {
        title: 'Não conseguimos entender esse pedido.',
        body: 'Confira o endereço ou volte uma página e tente novamente.',
    },
    unauthenticated: {
        title: 'Você precisa se identificar para continuar.',
        body: 'Crie um perfil temporário para jogar agora, sem e-mail e sem senha.',
    },
    forbidden: {
        title: 'Você não tem acesso a esta página.',
        body: 'Se acha que isso é um engano, volte ao início e tente de novo.',
    },
    method: {
        title: 'Essa ação não está disponível desse jeito.',
        body: 'Volte à página anterior e use os botões do próprio jogo para continuar.',
    },
    request_timeout: {
        title: 'O pedido demorou mais do que o esperado.',
        body: 'A conexão pode estar instável. Confira o estado atual antes de tentar novamente.',
        retry: true,
    },
    gone: {
        title: 'Isso não está mais disponível.',
        body: 'O conteúdo que você procura foi encerrado ou removido.',
    },
    conflict: {
        title: 'Isso não pode ser feito agora.',
        body: 'O estado mudou enquanto você estava aqui. Volte e confira como está.',
    },
    session: {
        title: 'Sua sessão expirou.',
        body: 'Por segurança, a página ficou parada por muito tempo. Atualize e tente de novo.',
        retry: true,
    },
    invalid_data: {
        title: 'Algumas informações precisam ser corrigidas.',
        body: 'Volte ao formulário, confira os campos destacados e tente novamente.',
    },
    throttle: {
        title: 'Calma, jogador!',
        body: 'Muitas tentativas seguidas. Aguarde um instante e tente de novo.',
        retry: true,
    },
    server: {
        title: 'Algo deu errado do nosso lado.',
        body: 'Já registramos o problema. Tente novamente em instantes.',
        retry: true,
    },
    bad_gateway: {
        title: 'Não conseguimos alcançar o servidor do jogo.',
        body: 'A comunicação foi interrompida no caminho. Tente novamente em instantes.',
        retry: true,
    },
    maintenance: {
        title: 'Estamos em manutenção.',
        body: 'O serviço está temporariamente indisponível. Volte em alguns minutos.',
        retry: true,
    },
    gateway_timeout: {
        title: 'O servidor demorou para responder.',
        body: 'Sua solicitação pode ainda estar sendo processada. Atualize para conferir o estado real antes de repetir a ação.',
        retry: true,
    },
    http_version: {
        title: 'Seu navegador não conseguiu conversar com o servidor.',
        body: 'Atualize o navegador ou tente novamente usando uma versão mais recente.',
    },
};

const primary =
    'inline-flex min-h-12 items-center justify-center border-2 border-[#1d1d1b] bg-[#b4da33] px-5 font-mono text-sm font-black uppercase text-[#1d1d1b] shadow-[4px_4px_0_#1d1d1b] hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]';
const secondary =
    'inline-flex min-h-12 items-center justify-center border-2 border-[#1d1d1b] bg-white px-5 font-mono text-sm font-bold uppercase text-[#1d1d1b] shadow-[4px_4px_0_#b4da33] hover:bg-[#f3f1ec] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498]';

/**
 * Página de erro. Independe de login e de props compartilhadas: pode ser exibida
 * até quando o resto da aplicação falhou. Nunca mostra detalhes técnicos.
 */
export default function Error({
    status,
    kind,
    canDashboard,
    message,
    requestId,
}: {
    status: number;
    kind: Kind;
    canDashboard: boolean;
    message?: string | null;
    requestId?: string | null;
}) {
    const copy = COPY[kind] ?? COPY.server;

    return (
        <main className="grid min-h-[100dvh] place-items-center bg-[#1d1d1b] p-4 text-[#1d1d1b]">
            <Head title={copy.title} />
            <div className="w-full max-w-xl border-[3px] border-black bg-[#f3f1ec] p-6 shadow-[8px_8px_0_#464498] sm:p-9">
                <Link
                    aria-label="Estação Commit — início"
                    className="inline-flex items-center gap-3 font-black"
                    href="/"
                >
                    <BrandLogo dark />
                </Link>

                <p className="mt-8 font-mono text-xs font-bold uppercase tracking-[.2em] text-[#464498]">
                    Erro {status}
                </p>
                <h1 className="mt-2 text-3xl font-black leading-tight tracking-tight sm:text-4xl">
                    {copy.title}
                </h1>
                <p className="mt-3 leading-7 text-[#5b5953]">
                    {message || copy.body}
                </p>
                {requestId && (
                    <p className="mt-4 border-2 border-[#1d1d1b] bg-white px-3 py-2 font-mono text-xs text-[#5b5953]">
                        Código de suporte:{' '}
                        <strong className="text-[#1d1d1b]">{requestId}</strong>
                    </p>
                )}
                <p className="mt-5 border-l-4 border-[#b4da33] bg-white px-3 py-2 font-mono text-xs text-[#5b5953]">
                    git checkout -b <span className="font-black">voltar-ao-inicio</span>
                </p>

                <div className="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                    {copy.retry ? (
                        <button
                            type="button"
                            className={primary}
                            onClick={() => window.location.reload()}
                        >
                            Tentar de novo
                        </button>
                    ) : canDashboard ? (
                        <Link className={primary} href={route('dashboard')}>
                            Ir para a Dashboard
                        </Link>
                    ) : (
                        <Link className={primary} href={route('guest.create')}>
                            Jogar agora
                        </Link>
                    )}
                    <Link className={secondary} href="/">
                        Voltar ao início
                    </Link>
                    {canDashboard && kind === 'room' && (
                        <Link
                            className={secondary}
                            href={`${route('dashboard')}#entrar-convite`}
                        >
                            Entrar em outra sala
                        </Link>
                    )}
                </div>
            </div>
        </main>
    );
}
