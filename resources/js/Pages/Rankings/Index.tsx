import PlayerAvatar from '@/Components/PlayerAvatar';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Entry = {
    position: number;
    user_id: string;
    player: {
        username: string;
        avatar?: string | null;
        avatar_style?: string;
        avatar_seed?: string;
        profile_frame?: string;
    };
    played: number;
    won: number;
    lost: number;
    win_rate: number;
};

type Scope = 'weekly' | 'monthly' | 'overall';

type RankingPeriod = {
    start: string;
    end: string;
    previousStart: string;
    previousEnd: string;
    /** Semana ISO (só no semanal). */
    number?: number;
    /** Chave do mês, AAAA-MM (só no mensal). */
    key?: string;
    isCurrent?: boolean;
    /** Chave do período anterior/seguinte (data da segunda-feira, ou AAAA-MM). Nula se não houver. */
    previous?: string | null;
    next?: string | null;
    available?: string[];
};

type RankingPeriods = {
    weekly: RankingPeriod;
    monthly: RankingPeriod;
};

function formatPeriod(period: RankingPeriod, type: 'weekly' | 'monthly') {
    const formatDate = (date: string, options: Intl.DateTimeFormatOptions) =>
        new Intl.DateTimeFormat('pt-BR', {
            ...options,
            timeZone: 'UTC',
        }).format(new Date(`${date}T12:00:00Z`));

    if (type === 'monthly') {
        return formatDate(period.start, { month: 'long', year: 'numeric' });
    }

    const sameMonth = period.start.slice(0, 7) === period.end.slice(0, 7);
    const sameYear = period.start.slice(0, 4) === period.end.slice(0, 4);
    const start = formatDate(period.start, {
        day: 'numeric',
        ...(sameMonth ? {} : { month: 'long' }),
        ...(sameYear ? {} : { year: 'numeric' }),
    });
    const end = formatDate(period.end, {
        day: 'numeric',
        ...(sameMonth ? {} : { month: 'long' }),
        year: 'numeric',
    });

    return `${start} a ${end}`;
}

function TrophyIcon({ className = '' }: { className?: string }) {
    return (
        <svg
            aria-hidden="true"
            className={className}
            fill="none"
            stroke="currentColor"
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth="1.6"
            viewBox="0 0 24 24"
        >
            <path d="M8 21h8m-4-4v4m-5-17h10v5a5 5 0 0 1-10 0V4Zm0 2H4v2a4 4 0 0 0 4 4m8-6h4v2a4 4 0 0 1-4 4" />
            <path d="M9 8h6" />
        </svg>
    );
}

const MEDALS = ['#f0b429', '#c0c4cc', '#c98a52'];

function RankingSkeleton() {
    return (
        <div aria-busy="true" aria-label="Carregando classificação" className="mt-6 space-y-4" role="status">
            <div className="h-44 animate-pulse border-[3px] border-[#1d1d1b]/20 bg-white/60" />
            {Array.from({ length: 5 }, (_, i) => (
                <div className="h-14 animate-pulse border-2 border-[#1d1d1b]/15 bg-white/50" key={i} />
            ))}
        </div>
    );
}

export default function Index({
    periods,
    rankings,
    viewerId,
}: {
    periods: RankingPeriods;
    rankings?: Record<Scope, Entry[]>;
    viewerId: string;
}) {
    const [periodType, setPeriodType] = useState<Scope>('weekly');
    const [busy, setBusy] = useState(false);

    // Troca de período: só os rankings são pedidos de novo (sem recarregar a página nem
    // abrir tela de espera); o resto da página fica como está.
    const goTo = (scope: Scope, key?: string | null) => {
        if (!key || scope === 'overall') return;
        router.get(
            route('rankings'),
            {
                week: scope === 'weekly' ? key : periods.weekly.start,
                month: scope === 'monthly' ? key : periods.monthly.key,
            },
            {
                only: ['periods', 'rankings'],
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );
    };
    const selectedPeriod = periods[periodType === 'overall' ? 'monthly' : periodType];
    const loading = rankings === undefined;
    const entries = rankings?.[periodType] ?? [];
    const podium = entries.slice(0, 3);
    const mine = entries.find((entry) => entry.user_id === viewerId);
    const label =
        periodType === 'weekly' ? 'Semanal' : periodType === 'monthly' ? 'Mensal' : 'Geral';

    return (
        <AuthenticatedLayout>
            <Head title="Rankings" />
            <main className="min-h-[calc(100vh-4.25rem)] bg-[#e8e5dc] px-3 py-8 text-[#1d1d1b] sm:px-4 lg:px-6 lg:py-12">
                <div className="w-full">
                    <header className="relative overflow-hidden border-[3px] border-[#1d1d1b] bg-[#1d1d1b] p-6 text-white shadow-[8px_8px_0_#464498] sm:p-9">
                        <div
                            aria-hidden="true"
                            className="absolute -right-10 -top-14 size-56 rounded-full border-[24px] border-[#b4da33]/10 sm:size-72"
                        />
                        <div className="relative flex flex-wrap items-end justify-between gap-6">
                            <div>
                                <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-[#b4da33]">
                                    Estação Commit / temporada
                                </p>
                                <h1 className="mt-2 text-4xl font-black sm:text-5xl">
                                    Rankings
                                </h1>
                                <p className="mt-3 max-w-xl text-sm leading-6 text-white/70 sm:text-base">
                                    Descubra quem está dominando o Estação
                                    Commit.
                                </p>
                            </div>
                            <div className="border-2 border-white/20 bg-white/5 px-4 py-3">
                                <p className="font-mono text-[10px] font-bold uppercase tracking-wider text-white/60">
                                    Jogadores classificados
                                </p>
                                <p className="mt-1 font-mono text-2xl font-black text-[#b4da33]">
                                    {entries.length}
                                </p>
                            </div>
                        </div>
                    </header>

                    <section className="mt-8">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="font-mono text-xs font-bold uppercase tracking-[.16em] text-[#464498]">
                                    Classificação
                                </p>
                                <h2 className="mt-1 text-2xl font-black">
                                    Melhores jogadores
                                </h2>
                            </div>
                            <div
                                aria-label="Período do ranking"
                                className="inline-flex border-2 border-[#1d1d1b] bg-[#f3f1ec] p-1 shadow-[3px_3px_0_#1d1d1b]"
                                role="group"
                            >
                                <button
                                    aria-pressed={periodType === 'weekly'}
                                    className={`min-h-10 px-4 font-mono text-xs font-black uppercase transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498] ${
                                        periodType === 'weekly'
                                            ? 'bg-[#464498] text-white'
                                            : 'text-[#5b5953] hover:bg-[#e8e5dc]'
                                    }`}
                                    onClick={() => setPeriodType('weekly')}
                                    type="button"
                                >
                                    Semanal
                                </button>
                                <button
                                    aria-pressed={periodType === 'monthly'}
                                    className={`min-h-10 px-4 font-mono text-xs font-black uppercase transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498] ${
                                        periodType === 'monthly'
                                            ? 'bg-[#464498] text-white'
                                            : 'text-[#5b5953] hover:bg-[#e8e5dc]'
                                    }`}
                                    onClick={() => setPeriodType('monthly')}
                                    type="button"
                                >
                                    Mensal
                                </button>
                                <button
                                    aria-pressed={periodType === 'overall'}
                                    className={`min-h-10 px-4 font-mono text-xs font-black uppercase transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498] ${
                                        periodType === 'overall'
                                            ? 'bg-[#464498] text-white'
                                            : 'text-[#5b5953] hover:bg-[#e8e5dc]'
                                    }`}
                                    onClick={() => setPeriodType('overall')}
                                    type="button"
                                >
                                    Geral
                                </button>
                            </div>
                        </div>
                        {periodType === 'overall' ? (
                            <p
                                aria-live="polite"
                                className="mt-3 font-mono text-xs font-bold uppercase tracking-wide text-[#68665f]"
                            >
                                Período:{' '}
                                <span className="text-[#464498]">
                                    todas as partidas, desde o começo
                                </span>
                            </p>
                        ) : (
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <button
                                    aria-label="Período anterior"
                                    className="grid size-10 place-items-center border-2 border-[#1d1d1b] bg-white font-mono text-sm font-black shadow-[2px_2px_0_#1d1d1b] transition enabled:hover:bg-[#b4da33] disabled:cursor-not-allowed disabled:opacity-35"
                                    disabled={!selectedPeriod.previous}
                                    onClick={() => goTo(periodType, selectedPeriod.previous)}
                                    type="button"
                                >
                                    ‹
                                </button>
                                <p
                                    aria-live="polite"
                                    className="min-w-0 flex-1 font-mono text-xs font-bold uppercase tracking-wide text-[#68665f] sm:flex-none"
                                >
                                    {periodType === 'weekly' && selectedPeriod.number ? (
                                        <span className="text-[#1d1d1b]">
                                            Semana {selectedPeriod.number} —{' '}
                                        </span>
                                    ) : null}
                                    <span className="text-[#464498]">
                                        {formatPeriod(selectedPeriod, periodType)}
                                    </span>
                                    {selectedPeriod.isCurrent ? (
                                        <span className="ml-2 border border-[#1d1d1b] bg-[#b4da33] px-1.5 py-0.5 text-[10px] text-[#1d1d1b]">
                                            atual
                                        </span>
                                    ) : null}
                                </p>
                                <button
                                    aria-label="Próximo período"
                                    className="grid size-10 place-items-center border-2 border-[#1d1d1b] bg-white font-mono text-sm font-black shadow-[2px_2px_0_#1d1d1b] transition enabled:hover:bg-[#b4da33] disabled:cursor-not-allowed disabled:opacity-35"
                                    disabled={!selectedPeriod.next}
                                    onClick={() => goTo(periodType, selectedPeriod.next)}
                                    type="button"
                                >
                                    ›
                                </button>
                                {periodType === 'monthly' && (selectedPeriod.available?.length ?? 0) > 1 ? (
                                    <select
                                        aria-label="Escolher mês"
                                        className="min-h-10 border-2 border-[#1d1d1b] bg-white px-2 font-mono text-xs font-bold"
                                        onChange={(event) => goTo('monthly', event.target.value)}
                                        value={selectedPeriod.key}
                                    >
                                        {selectedPeriod.available?.map((key) => (
                                            <option key={key} value={key}>
                                                {formatPeriod(
                                                    { ...selectedPeriod, start: `${key}-01` },
                                                    'monthly',
                                                )}
                                            </option>
                                        ))}
                                    </select>
                                ) : null}
                                {busy ? (
                                    <span
                                        aria-hidden="true"
                                        className="size-4 animate-spin rounded-full border-2 border-[#464498] border-t-transparent"
                                    />
                                ) : null}
                            </div>
                        )}
                    </section>

                    <Deferred data="rankings" fallback={<RankingSkeleton />}>
                        <>
                    <section
                        aria-labelledby="podium-title"
                        className="mt-6 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[5px_5px_0_#1d1d1b] sm:p-8"
                    >
                        <div className="flex items-center gap-3">
                            <span className="grid size-11 place-items-center border-2 border-[#1d1d1b] bg-[#f0b429] text-[#1d1d1b]">
                                <TrophyIcon className="size-6" />
                            </span>
                            <div>
                                <h2
                                    className="text-xl font-black"
                                    id="podium-title"
                                >
                                    Pódio
                                </h2>
                                <p className="font-mono text-[10px] font-bold uppercase tracking-wider text-[#68665f]">
                                    Destaques do período
                                </p>
                            </div>
                        </div>
                        {podium.length ? (
                            <ol className="mt-6 grid gap-3 sm:grid-cols-3">
                                {podium.map((entry, index) => (
                                    <li
                                        key={entry.user_id}
                                        className={`border-[3px] border-[#1d1d1b] bg-white p-4 text-center shadow-[4px_4px_0_#1d1d1b] ${index === 0 ? 'sm:order-2 sm:-mt-3' : index === 1 ? 'sm:order-1' : 'sm:order-3'}`}
                                    >
                                        <span
                                            className="mx-auto grid size-9 place-items-center border-2 border-[#1d1d1b] font-mono text-sm font-black"
                                            style={{ background: MEDALS[index] }}
                                        >
                                            {entry.position}º
                                        </span>
                                        <div className="mt-3 flex justify-center">
                                            <PlayerAvatar profile={entry.player} size="md" />
                                        </div>
                                        <Link
                                            className="mt-2 block truncate font-black hover:underline"
                                            href={route('profile.public', entry.player.username)}
                                        >
                                            @{entry.player.username}
                                        </Link>
                                        <p className="mt-1 font-mono text-xs font-bold uppercase text-[#464498]">
                                            {entry.won} {entry.won === 1 ? 'vitória' : 'vitórias'}
                                        </p>
                                        <p className="font-mono text-[10px] uppercase text-[#68665f]">
                                            {entry.played} partidas · {entry.win_rate}%
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <div className="mt-6 flex min-h-48 flex-col items-center justify-center border-2 border-dashed border-[#1d1d1b]/20 bg-white/60 px-5 py-8 text-center">
                                <span className="grid size-14 place-items-center rounded-full bg-[#f0b429]/20 text-[#8a6710]">
                                    <TrophyIcon className="size-7" />
                                </span>
                                <h3 className="mt-4 text-lg font-black">
                                    O pódio está aguardando resultados
                                </h3>
                                <p className="mt-2 max-w-xl text-sm leading-6 text-[#68665f]">
                                    Ninguém concluiu partidas neste período ainda. Jogue uma
                                    partida completa para aparecer aqui.
                                </p>
                            </div>
                        )}
                    </section>

                    <section className="mt-8">
                        <div className="border-[3px] border-[#1d1d1b] bg-[#f3f1ec] shadow-[5px_5px_0_#1d1d1b]">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b-2 border-[#1d1d1b] px-5 py-4">
                                <div>
                                    <h2 className="text-xl font-black">
                                        Classificação completa
                                    </h2>
                                    <p className="mt-1 text-xs text-[#68665f]">
                                        Ordenado por vitórias; empate em vitórias
                                        fica com quem perdeu menos
                                    </p>
                                </div>
                                <span className="border-2 border-[#464498]/20 bg-[#464498]/5 px-3 py-1 font-mono text-[10px] font-black uppercase text-[#464498]">
                                    {label}
                                </span>
                            </div>
                            {entries.length ? (
                                <ol>
                                    {entries.map((entry) => (
                                        <li
                                            key={entry.user_id}
                                            className={`flex items-center gap-3 border-b border-[#1d1d1b]/15 px-4 py-3 last:border-0 ${entry.user_id === viewerId ? 'bg-[#b4da33]/25' : ''}`}
                                        >
                                            <span className="w-8 shrink-0 text-center font-mono text-sm font-black">
                                                {entry.position}º
                                            </span>
                                            <PlayerAvatar profile={entry.player} size="xs" />
                                            <Link
                                                className="min-w-0 flex-1 truncate font-bold hover:underline"
                                                href={route('profile.public', entry.player.username)}
                                            >
                                                @{entry.player.username}
                                                {entry.user_id === viewerId && (
                                                    <span className="ml-2 font-mono text-[9px] uppercase text-[#464498]">
                                                        você
                                                    </span>
                                                )}
                                            </Link>
                                            <span className="font-mono text-sm font-black">
                                                {entry.won} V
                                            </span>
                                            <span className="hidden w-16 text-right font-mono text-xs text-[#68665f] sm:block">
                                                {entry.lost} D
                                            </span>
                                            <span className="hidden w-14 text-right font-mono text-xs text-[#68665f] sm:block">
                                                {entry.win_rate}%
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            ) : (
                                <div className="flex min-h-44 flex-col items-center justify-center px-5 py-8 text-center">
                                    <span className="font-mono text-3xl font-black text-[#464498]/30">
                                        —
                                    </span>
                                    <p className="mt-2 font-bold">
                                        Ainda não há jogadores classificados neste período.
                                    </p>
                                    <p className="mt-1 max-w-lg text-sm text-[#68665f]">
                                        As posições aparecem quando houver partidas concluídas.
                                    </p>
                                </div>
                            )}
                        </div>

                        <section className="mt-5 border-[3px] border-[#1d1d1b] bg-[#464498] p-5 text-white shadow-[5px_5px_0_#f481c6] sm:flex sm:items-center sm:justify-between sm:gap-6">
                            <p className="font-mono text-[10px] font-bold uppercase tracking-[.16em] text-[#b4da33]">
                                Sua temporada
                            </p>
                            <h2 className="mt-2 text-xl font-black">
                                Sua posição
                            </h2>
                            {mine ? (
                                <p className="mt-5 border-t border-white/20 pt-4 text-sm leading-6 text-white/85">
                                    Você está em{' '}
                                    <strong className="font-mono text-2xl text-[#b4da33]">{mine.position}º</strong>{' '}
                                    com {mine.won} {mine.won === 1 ? 'vitória' : 'vitórias'} em {mine.played} partidas
                                    ({mine.win_rate}%).
                                </p>
                            ) : (
                                <p className="mt-5 border-t border-white/20 pt-4 text-sm leading-6 text-white/75">
                                    Você ainda não aparece neste ranking. Conclua uma partida
                                    {periodType === 'overall' ? '' : ' neste período'} para entrar na classificação.
                                </p>
                            )}
                        </section>
                    </section>
                        </>
                    </Deferred>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
