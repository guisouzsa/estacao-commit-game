import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { PointerEvent, useState } from 'react';

const journey = [
    { phase: 'Treinamento', title: 'Git Login', command: 'git config', text: 'Configure a autoria dos seus commits.', color: 'lime' },
    { phase: 'Treinamento', title: 'Clone', command: 'git clone', text: 'Crie uma cópia local do repositório remoto.', color: 'lime' },
    { phase: 'Treinamento', title: 'Branch', command: 'git checkout -b', text: 'Crie sua própria branch para desenvolver a contribuição.', color: 'lime' },
    { phase: 'Desenvolvimento', title: 'Quatro ciclos', command: 'bloco → add → commit', text: 'Monte o código, prepare as alterações e registre um commit. Repita com os quatro blocos.', color: 'pink', featured: true },
    { phase: 'Envio', title: 'Pull', command: 'git pull', text: 'Atualize seu repositório local antes do envio.', color: 'violet' },
    { phase: 'Envio', title: 'Push', command: 'git push', text: 'Envie os commits da sua branch para o repositório remoto.', color: 'violet' },
    { phase: 'GitHub', title: 'Pull Request', command: 'recurso do GitHub', text: 'Proponha a integração das alterações e conclua sua contribuição.', color: 'red', finish: true },
];

function Brand() {
    return <span className="flex items-center gap-3 font-display text-xl font-black tracking-tight text-white">
        <img src="/images/estacao-commit/mascots/gato-login.png" alt="" className="h-12 w-14 object-contain" />
        <span>ESTAÇÃO <span className="text-lime">COMMIT</span></span>
    </span>;
}

export default function Welcome({ auth }: PageProps) {
    const [menuOpen, setMenuOpen] = useState(false);
    const playHref = auth.user ? route('dashboard') : route('register');

    const moveDeck = (event: PointerEvent<HTMLDivElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        event.currentTarget.style.setProperty('--deck-x', `${((event.clientX - rect.left) / rect.width - .5) * 12}px`);
        event.currentTarget.style.setProperty('--deck-y', `${((event.clientY - rect.top) / rect.height - .5) * 10}px`);
    };

    return <>
        <Head title="Estação Commit — Aprenda Git jogando">
            <meta name="description" content="Jogo educacional de tabuleiro e cartas sobre Git e GitHub." />
        </Head>
        <main className="min-h-screen overflow-hidden bg-ink text-white selection:bg-[#f481c6] selection:text-[#1d1d1b]">
            <header className="fixed inset-x-0 top-0 z-50 border-b-2 border-white/15 bg-[#1d1d1b]/90 backdrop-blur-xl">
                <div className="mx-auto flex h-20 w-full max-w-[1500px] items-center justify-between px-5 lg:px-8">
                    <Link href="/" aria-label="Estação Commit"><Brand /></Link>
                    <nav className="hidden items-center gap-7 text-sm font-bold text-white/70 lg:flex">
                        <a href="#inicio">Início</a><a href="#como-funciona">Como funciona</a><a href="#jogo">O jogo</a><a href="#aprender">Aprenda jogando</a>
                    </nav>
                    <div className="hidden items-center gap-3 lg:flex">
                        {!auth.user && <Link href={route('login')} className="btn-ghost">Entrar</Link>}
                        <Link href={playHref} className="btn-primary">{auth.user ? 'Ir ao painel' : 'Jogar agora'}</Link>
                    </div>
                    <button onClick={() => setMenuOpen(!menuOpen)} className="grid size-11 place-items-center border-2 border-white/30 lg:hidden" aria-label="Abrir menu" aria-expanded={menuOpen}>{menuOpen ? '×' : '☰'}</button>
                </div>
                {menuOpen && <nav className="border-t border-white/10 bg-[#1d1d1b] px-5 py-5 lg:hidden"><div className="grid gap-4 font-bold"><a href="#como-funciona" onClick={() => setMenuOpen(false)}>Como funciona</a><a href="#jogo" onClick={() => setMenuOpen(false)}>O jogo</a><a href="#aprender" onClick={() => setMenuOpen(false)}>Aprenda jogando</a><div className="mt-2 flex gap-3"><Link href={route('login')} className="btn-ghost">Entrar</Link><Link href={playHref} className="btn-primary">Jogar agora</Link></div></div></nav>}
            </header>

            <section id="inicio" className="relative mx-auto grid min-h-[min(720px,100dvh)] w-full max-w-[1500px] items-center gap-8 px-5 pb-12 pt-24 lg:grid-cols-[.88fr_1.12fr] lg:px-8">
                <div className="relative z-20 pb-4">
                    <div className="eyebrow">Jogo educacional de Git & GitHub</div>
                    <h1 className="mt-7 max-w-3xl font-display text-5xl font-black leading-[.95] tracking-[-.055em] sm:text-7xl">Jogue suas cartas. <span className="text-pink">Complete a contribuição.</span></h1>
                    <p className="mt-7 max-w-xl text-lg leading-8 text-white/70">Treine comandos, monte o código de um foguete e percorra o fluxo de colaboração. Uma experiência de mesa para aprender Git fazendo escolhas.</p>
                    <div className="mt-9 flex flex-col gap-4 sm:flex-row"><Link href={playHref} className="btn-primary justify-center px-7 py-4">Criar conta e jogar</Link><a href="#como-funciona" className="btn-ghost justify-center px-7 py-4">Conhecer a jornada</a></div>
                    <p className="mt-9 border-l-4 border-pink pl-4 font-mono text-sm text-lime/80">$ git checkout -b sua-missao</p>
                </div>
                <div className="hero-deck" onPointerMove={moveDeck} onPointerLeave={e => { e.currentTarget.style.setProperty('--deck-x', '0px'); e.currentTarget.style.setProperty('--deck-y', '0px'); }} aria-label="Cartas oficiais do Estação Commit">
                    <div className="deck-grid" aria-hidden="true" />
                    <img src="/images/estacao-commit/corrected/git-login.png" alt="Carta Git Login" className="deck-card deck-card-login" />
                    <img src="/images/estacao-commit/corrected/git-commit.png" alt="Carta git commit" className="deck-card deck-card-commit" />
                    <img src="/images/estacao-commit/corrected/git-push.png" alt="Carta git push" className="deck-card deck-card-push" />
                    <img src="/images/estacao-commit/corrected/bloco-3.png" alt="Carta Bloco de Código 3" className="deck-card deck-card-block" />
                    <span className="deck-branch" aria-hidden="true" /><span className="deck-node deck-node-a" aria-hidden="true" /><span className="deck-node deck-node-b" aria-hidden="true" />
                </div>
            </section>

            <section id="como-funciona" className="border-y-2 border-white/10 bg-[#171717] py-16 sm:py-20">
                <div className="mx-auto w-full max-w-[1500px] px-5 lg:px-8">
                    <div className="grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-end"><div><div className="eyebrow">Uma única jornada</div><h2 className="section-title mt-6">Do primeiro comando à contribuição.</h2></div><p className="max-w-xl text-lg leading-8 text-white/65 lg:justify-self-end">Treinamento, desenvolvimento e envio formam uma sequência contínua. Cada carta jogada registra o avanço até a Pull Request final.</p></div>
                    <div className="journey mt-16">
                        {journey.map((step, index) => <article key={step.title} className={`journey-step journey-${step.color} ${step.featured ? 'journey-featured' : ''} ${step.finish ? 'journey-finish' : ''}`}>
                            <div className="journey-index">{String(index + 1).padStart(2, '0')}</div>
                            <div className="journey-copy"><span className="journey-phase">{step.phase}</span><h3>{step.title}</h3><code>{step.command}</code><p>{step.text}</p></div>
                        </article>)}
                    </div>
                </div>
            </section>

            <section id="jogo" className="bg-paper py-16 text-ink sm:py-20">
                <div className="mx-auto w-full max-w-[1500px] px-5 lg:px-8">
                    <div className="grid gap-8 lg:grid-cols-2 lg:items-end"><div><div className="eyebrow !border-[#1d1d1b]/30 !text-[#1d1d1b]/60">Conheça o jogo</div><h2 className="section-title mt-6">A estação onde a missão toma forma.</h2></div><p className="max-w-xl text-lg leading-8 text-[#1d1d1b]/65">O tabuleiro organiza duas áreas: o Treinamento e a Estação de Montagem. As próprias cartas jogadas formam o percurso e tornam o progresso de cada participante visível.</p></div>
                    <div className="board-stage mt-10"><div className="board-frame"><img src="/images/estacao-commit/tabuleiro.png" alt="Tabuleiro original do Estação Commit" /></div><aside className="board-notes"><Note color="lime" title="Treinamento">Git Login, clone e criação da branch preparam cada agente.</Note><Note color="pink" title="Montagem">Quatro ciclos unem bloco de código, add e commit.</Note><Note color="violet" title="Quatro bases">Rosa, azul, verde e vermelho identificam os percursos dos jogadores.</Note></aside></div>
                    <div className="mx-auto mt-14 grid max-w-5xl grid-cols-2 gap-5 sm:grid-cols-4"><CardFigure src="git-clone" label="git clone" /><CardFigure src="git-add" label="git add ." /><CardFigure src="git-commit" label="git commit" /><CardFigure src="git-push" label="git push" /></div>
                </div>
            </section>

            <section id="aprender" className="relative overflow-hidden bg-purple py-16 sm:py-20">
                <div className="absolute -right-24 top-8 size-80 rotate-12 border-[28px] border-pink/15" aria-hidden="true" />
                <div className="relative mx-auto grid w-full max-w-[1500px] gap-14 px-5 lg:grid-cols-[1.05fr_.95fr] lg:items-center lg:px-8">
                    <div><div className="eyebrow">Aprenda jogando</div><h2 className="section-title mt-6">Repetição com propósito. Estratégia em cada turno.</h2><p className="mt-6 max-w-2xl text-lg leading-8 text-white/70">Estação Commit não é um curso tradicional nem uma ferramenta real de versionamento. É uma simulação educativa que ajuda iniciantes a reconhecer a ordem e o papel de cada etapa.</p></div>
                    <div className="learning-list"><Learning command="git add ." text="Prepare as alterações para o próximo registro." /><Learning command="git commit" text="Registre localmente o avanço do código." /><Learning command="git push" text="Envie os commits da sua branch ao remoto." /><Learning command="Pull Request" text="Proponha a integração pelo GitHub." /></div>
                </div>
            </section>

            <section className="px-5 py-16 sm:py-20 lg:px-8"><div className="mx-auto max-w-5xl border-[3px] border-[#1d1d1b] bg-lime p-8 text-center text-ink shadow-[12px_12px_0_#f481c6] sm:p-12"><p className="font-mono text-sm font-bold uppercase tracking-[.2em]">Pronto para a missão?</p><h2 className="mt-5 font-display text-4xl font-black tracking-tight sm:text-6xl">Prepare sua branch.<br />A próxima contribuição começa aqui.</h2><div className="mt-8 flex flex-col justify-center gap-4 sm:flex-row"><Link href={playHref} className="btn-dark justify-center">Criar conta</Link><Link href={route('login')} className="btn-light justify-center">Entrar na plataforma</Link></div></div></section>
            <footer className="border-t border-white/10"><div className="mx-auto flex w-full max-w-[1500px] flex-col gap-5 px-5 py-10 sm:flex-row sm:items-center sm:justify-between lg:px-8"><Brand /><p className="text-sm text-white/45">Jogo educacional de tabuleiro e cartas sobre Git e GitHub.</p></div></footer>
        </main>
    </>;
}

function Note({ color, title, children }: { color: string; title: string; children: React.ReactNode }) { return <div className={`board-note board-note-${color}`}><strong>{title}</strong><p>{children}</p></div>; }
function CardFigure({ src, label }: { src: string; label: string }) { return <figure className="card-figure"><div><img src={`/images/estacao-commit/corrected/${src}.png`} alt={`Carta oficial ${label}`} /></div><figcaption>{label}</figcaption></figure>; }
function Learning({ command, text }: { command: string; text: string }) { return <div className="learning-item"><code>{command}</code><p>{text}</p></div>; }
