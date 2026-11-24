import InputError from '@/Components/InputError';
import AvatarPicker from '@/Components/AvatarPicker';
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
                                  result.message ?? 'Esse nome não está disponível.',
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
        (availability.state === 'unavailable' ? availability.message : undefined);

    return (
        <GuestLayout>
            <Head title="Jogar agora" />
            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-purple-700">
                Jogar agora
            </p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">
                Quem vai jogar?
            </h1>

            {/* Deixa claro que NÃO é um login: é uma conta temporária, sem e-mail nem senha. */}
            <section
                aria-labelledby="temp-account-title"
                className="mt-4 border-2 border-[#1d1d1b] bg-[#f6f9e4] p-4 shadow-[4px_4px_0_#b4da33]"
            >
                <p
                    className="font-mono text-[11px] font-black uppercase tracking-wider text-[#464498]"
                    id="temp-account-title"
                >
                    Conta temporária · sem login
                </p>
                <p className="mt-1 text-lg font-black leading-snug">
                    Você está entrando com uma conta temporária.
                </p>
                <p className="mt-2 text-sm leading-6 text-ink/70">
                    Você pode começar a jogar imediatamente sem precisar criar
                    uma conta. Entretanto, essa conta é temporária e poderá ser
                    removida após um período de inatividade (6 horas sem
                    jogar).
                </p>
                <p className="mt-2 text-sm leading-6 text-ink/70">
                    Quer guardar seu perfil e suas vitórias no ranking? Crie uma
                    conta permanente com e-mail. Ela é nova: o que você jogar
                    como visitante não é transferido.
                </p>
            </section>

            <form className="mt-6" onSubmit={submit} noValidate>
                <div className="flex items-center gap-4">
                    <PlayerAvatar profile={preview} size="lg" />
                    <div className="min-w-0 flex-1">
                        <label className="auth-label" htmlFor="guest-name">
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
                            <span className="text-sm text-ink/70">
                                Que tal:
                            </span>
                            {availability.suggestions!.map((suggestion) => (
                                <button
                                    key={suggestion}
                                    type="button"
                                    onClick={() => setData('name', suggestion)}
                                    className="min-h-9 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-black hover:bg-[#b4da33]"
                                >
                                    Usar {suggestion}
                                </button>
                            ))}
                        </div>
                    )}

                <div className="mt-5">
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
                <InputError className="mt-1" message={errors.avatar_style ?? errors.avatar_seed} />

                <button
                    className="auth-submit mt-6"
                    disabled={processing || availability.state === 'unavailable'}
                >
                    {processing ? 'Entrando...' : 'Continuar com conta temporária'}
                </button>
            </form>

            <div className="my-5 flex items-center gap-3 font-mono text-[11px] font-black uppercase text-ink/40">
                <span className="h-px flex-1 bg-ink/15" />
                ou
                <span className="h-px flex-1 bg-ink/15" />
            </div>

            <Link
                className="flex min-h-12 w-full items-center justify-center border-2 border-[#1d1d1b] bg-white px-4 text-center font-mono text-xs font-black uppercase shadow-[4px_4px_0_#464498] transition hover:-translate-y-0.5 hover:bg-[#f3f1ec]"
                href={route('register')}
            >
                Criar conta permanente com e-mail
            </Link>

            <p className="mt-8 text-center text-sm text-ink/55">
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
