import AvatarPicker from '@/Components/AvatarPicker';
import HowToPlayButton from '@/Components/HowToPlayButton';
import InputError from '@/Components/InputError';
import PlayerAvatar from '@/Components/PlayerAvatar';
import GuestLayout from '@/Layouts/GuestLayout';
import { type AvatarStyle, randomSeed } from '@/lib/avatarArt';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';

type Availability =
    | { state: 'idle' }
    | { state: 'checking' }
    | { state: 'available' }
    | { state: 'unavailable'; message: string; suggestions?: string[] };

/**
 * "Jogar agora": o visitante escolhe nome e avatar e entra, sem e-mail nem senha.
 * A disponibilidade do nome é conferida enquanto digita, mas quem decide de verdade
 * é o servidor (duas pessoas podem escolher o mesmo nome ao mesmo tempo).
 */
export default function Create({
    styles,
    name: nameRules,
}: {
    styles: AvatarStyle[];
    name: { min: number; max: number };
}) {
    const [initialSeed] = useState(() => randomSeed());
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        avatar_style: styles[0] ?? 'pixel',
        avatar_seed: initialSeed,
    });
    const [availability, setAvailability] = useState<Availability>({
        state: 'idle',
    });
    const latestCheck = useRef(0);

    // Confere o nome com um pequeno atraso (e ignora respostas atrasadas).
    useEffect(() => {
        const trimmed = data.name.trim();
        if (trimmed.length < nameRules.min) {
            setAvailability({ state: 'idle' });
            return;
        }

        setAvailability({ state: 'checking' });
        const check = ++latestCheck.current;
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    route('guest.name', { name: trimmed }),
                    { headers: { Accept: 'application/json' } },
                );
                if (!response.ok) throw new Error('indisponível');
                const result = (await response.json()) as {
                    available: boolean;
                    message: string | null;
                    suggestions?: string[];
                };
                if (check !== latestCheck.current) return;
                setAvailability(
                    result.available
                        ? { state: 'available' }
                        : {
                              state: 'unavailable',
                              suggestions: result.suggestions ?? [],
                              message:
                                  result.message ??
                                  'Esse nome não está disponível.',
                          },
                );
            } catch {
                // Sem a verificação instantânea o envio continua funcionando.
                if (check === latestCheck.current) {
                    setAvailability({ state: 'idle' });
                }
            }
        }, 400);

        return () => window.clearTimeout(timer);
    }, [data.name, nameRules.min]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('guest.store'));
    };

    const preview = {
        username: data.name.trim() || 'Você',
        avatar_style: data.avatar_style,
        avatar_seed: data.avatar_seed,
    };
    const nameError =
        errors.name ??
        (availability.state === 'unavailable'
            ? availability.message
            : undefined);

    return (
        <GuestLayout wide>
            <Head title="Jogar agora" />
            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">
                Jogar agora
            </p>
            {/* Título normal, sem caixa: a hierarquia (tamanho e peso) é que organiza a leitura. */}
            <h1 className="mt-2 text-2xl font-black leading-tight tracking-tight sm:text-3xl">
                Entre sem criar uma conta
            </h1>
            <div className="text-ink/65 mt-3 border-l-4 border-[#b4da33] bg-white px-3 py-2 text-sm leading-5">
                Comece imediatamente com um perfil temporário. Ele é removido
                após 6 horas sem jogar e o progresso não é transferido para uma
                conta permanente.
            </div>

            <h2 className="mt-5 text-lg font-black tracking-tight">
                Escolha seu nome e seu avatar
            </h2>

            <form className="mt-3" onSubmit={submit} noValidate>
                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)]">
                    <div>
                        <div className="flex items-center gap-3">
                            <PlayerAvatar profile={preview} size="lg" />
                            <div className="min-w-0 flex-1">
                                <label
                                    className="auth-label"
                                    htmlFor="guest-name"
                                >
                                    Seu nome
                                </label>
                                <input
                                    id="guest-name"
                                    className="auth-input"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData('name', event.target.value)
                                    }
                                    maxLength={nameRules.max + 12}
                                    autoComplete="nickname"
                                    autoFocus
                                    required
                                    aria-invalid={Boolean(nameError)}
                                    aria-describedby="guest-name-status"
                                />
                            </div>
                        </div>
                        <p
                            id="guest-name-status"
                            aria-live="polite"
                            className={`mt-2 min-h-5 text-sm font-semibold ${
                                nameError
                                    ? 'text-[#d01600]'
                                    : availability.state === 'available'
                                      ? 'text-[#2d6a00]'
                                      : 'text-ink/55'
                            }`}
                        >
                            {nameError ??
                                (availability.state === 'checking'
                                    ? 'Verificando...'
                                    : availability.state === 'available'
                                      ? 'Nome disponível!'
                                      : `De ${nameRules.min} a ${nameRules.max} caracteres.`)}
                        </p>
                        <InputError className="mt-1" message={errors.name} />
                        {availability.state === 'unavailable' &&
                            (availability.suggestions?.length ?? 0) > 0 && (
                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                    <span className="text-ink/70 text-sm">
                                        Que tal:
                                    </span>
                                    {availability.suggestions!.map(
                                        (suggestion) => (
                                            <button
                                                key={suggestion}
                                                type="button"
                                                onClick={() =>
                                                    setData('name', suggestion)
                                                }
                                                className="min-h-9 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-black hover:bg-[#b4da33]"
                                            >
                                                Usar {suggestion}
                                            </button>
                                        ),
                                    )}
                                </div>
                            )}
                    </div>

                    <div>
                        <AvatarPicker
                            styles={styles}
                            style={data.avatar_style}
                            seed={data.avatar_seed}
                            previewName={preview.username}
                            onChange={(style, seed) =>
                                setData((current) => ({
                                    ...current,
                                    avatar_style: style as AvatarStyle,
                                    avatar_seed: seed,
                                }))
                            }
                        />
                    </div>
                </div>
                <InputError
                    className="mt-1"
                    message={errors.avatar_style ?? errors.avatar_seed}
                />

                <button
                    className="auth-submit mt-4"
                    disabled={
                        processing || availability.state === 'unavailable'
                    }
                >
                    {processing
                        ? 'Entrando...'
                        : 'Continuar com conta temporária'}
                </button>
            </form>

            <div className="text-ink/40 my-4 flex items-center gap-3 font-mono text-[11px] font-black uppercase">
                <span className="bg-ink/15 h-px flex-1" />
                ou
                <span className="bg-ink/15 h-px flex-1" />
            </div>

            <div className="grid gap-3 sm:grid-cols-[1fr_auto]">
                <Link
                    className="flex min-h-12 w-full items-center justify-center border-2 border-[#1d1d1b] bg-white px-4 text-center font-mono text-xs font-black uppercase shadow-[4px_4px_0_#464498] transition hover:-translate-y-0.5 hover:bg-[#f3f1ec]"
                    href={route('register')}
                >
                    Criar conta permanente com e-mail
                </Link>
                <HowToPlayButton />
            </div>

            <p className="text-ink/55 mt-5 text-center text-sm">
                Já tem uma conta?{' '}
                <Link
                    href={route('login')}
                    className="font-bold text-purple-700 hover:underline"
                >
                    Entrar
                </Link>
            </p>
        </GuestLayout>
    );
}
