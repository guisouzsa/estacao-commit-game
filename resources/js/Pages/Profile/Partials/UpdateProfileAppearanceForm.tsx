import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PlayerProfileCard, {
    PlayerProfile,
} from '@/Components/PlayerProfileCard';
import PrimaryButton from '@/Components/PrimaryButton';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type AppearanceData = {
    avatar: File | null;
    remove_avatar: boolean;
    avatar_position_x: number;
    avatar_position_y: number;
    profile_frame: string;
    primary_badge: string;
    profile_title: string | null;
};

type AppearanceOptions = {
    frames: Record<string, string>;
    badges: Record<string, string>;
    titles: Record<string, string>;
};

const frameColors: Record<string, string> = {
    standard: 'bg-[#1d1d1b]',
    lime: 'bg-[#b4da33]',
    violet: 'bg-[#464498]',
    pink: 'bg-[#f481c6]',
    red: 'bg-[#d01600]',
};

export default function UpdateProfileAppearanceForm({
    profile,
    options,
    onPreviewChange,
}: {
    profile: PlayerProfile;
    options: AppearanceOptions;
    onPreviewChange: (updates: Partial<PlayerProfile>) => void;
}) {
    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm<AppearanceData>({
            avatar: null,
            remove_avatar: false,
            avatar_position_x: profile.avatar_position_x ?? 50,
            avatar_position_y: profile.avatar_position_y ?? 50,
            profile_frame: profile.profile_frame ?? 'standard',
            primary_badge: profile.primary_badge ?? 'terminal',
            profile_title: profile.profile_title ?? 'explorer',
        });
    const [previewUrl, setPreviewUrl] = useState<string | null>(
        profile.avatar ?? null,
    );

    useEffect(() => {
        let objectUrl: string | null = null;

        if (data.avatar) {
            objectUrl = URL.createObjectURL(data.avatar);
            setPreviewUrl(objectUrl);
        } else {
            setPreviewUrl(data.remove_avatar ? null : (profile.avatar ?? null));
        }

        onPreviewChange({
            avatar: data.remove_avatar
                ? null
                : (objectUrl ?? profile.avatar ?? null),
            avatar_position_x: data.avatar_position_x,
            avatar_position_y: data.avatar_position_y,
            profile_frame: data.profile_frame,
            primary_badge: data.primary_badge,
            profile_title: data.profile_title,
        });

        return () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
        };
    }, [
        data.avatar,
        data.remove_avatar,
        data.avatar_position_x,
        data.avatar_position_y,
        data.profile_frame,
        data.primary_badge,
        data.profile_title,
        profile.avatar,
        onPreviewChange,
    ]);

    const previewProfile: PlayerProfile = {
        ...profile,
        avatar: previewUrl,
        avatar_position_x: data.avatar_position_x,
        avatar_position_y: data.avatar_position_y,
        profile_frame: data.profile_frame,
        primary_badge: data.primary_badge,
        profile_title: data.profile_title,
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        patch(route('profile.appearance.update'), {
            forceFormData: true,
            onSuccess: () => {
                setData((current) => ({
                    ...current,
                    avatar: null,
                    remove_avatar: false,
                }));
            },
        });
    };

    return (
        <section className="space-y-6 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-5 shadow-[6px_6px_0_#1d1d1b] sm:p-7">
            <header className="border-b border-[#1d1d1b]/20 pb-4">
                <p className="font-mono text-xs uppercase tracking-[0.18em] text-[#464498]">
                    Personalização / 02
                </p>
                <h2 className="mt-2 text-2xl font-black text-[#1d1d1b]">
                    Aparência do perfil
                </h2>
                <p className="mt-1 text-sm leading-6 text-[#5b5953]">
                    Ajuste seu avatar, moldura e sinais visuais. As opções
                    atuais são cosméticas e não representam conquistas de jogo.
                </p>
            </header>

            <form onSubmit={submit} className="space-y-7">
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(240px,0.8fr)]">
                    <div className="space-y-5">
                        <div>
                            <InputLabel
                                htmlFor="avatar"
                                value="Foto de perfil"
                            />
                            <input
                                id="avatar"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                className="mt-2 block w-full cursor-pointer border border-[#1d1d1b]/30 bg-white p-2 text-sm text-[#1d1d1b] file:mr-3 file:border-0 file:bg-[#1d1d1b] file:px-3 file:py-2 file:font-mono file:text-xs file:font-bold file:uppercase file:text-white"
                                onChange={(event) => {
                                    const file =
                                        event.target.files?.[0] ?? null;
                                    setData((current) => ({
                                        ...current,
                                        avatar: file,
                                        remove_avatar: false,
                                    }));
                                }}
                            />
                            <p className="mt-2 text-xs leading-5 text-[#68665f]">
                                JPG, PNG ou WebP, até 2 MB. O arquivo fica no
                                armazenamento público de avatares.
                            </p>
                            <InputError
                                message={errors.avatar}
                                className="mt-2"
                            />
                            {previewUrl && (
                                <button
                                    type="button"
                                    className="mt-3 font-mono text-xs font-bold uppercase text-[#464498] underline decoration-2 underline-offset-4 hover:text-[#d01600]"
                                    onClick={() => {
                                        setData((current) => ({
                                            ...current,
                                            avatar: null,
                                            remove_avatar: true,
                                        }));
                                    }}
                                >
                                    Remover foto
                                </button>
                            )}
                            {data.remove_avatar && (
                                <button
                                    type="button"
                                    className="mt-3 font-mono text-xs font-bold uppercase text-[#464498] underline decoration-2 underline-offset-4 hover:text-[#d01600]"
                                    onClick={() =>
                                        setData('remove_avatar', false)
                                    }
                                >
                                    Manter avatar atual
                                </button>
                            )}
                        </div>

                        <fieldset className="space-y-3">
                            <legend className="text-sm font-bold text-[#1d1d1b]">
                                Posicionamento do recorte
                            </legend>
                            <div>
                                <label
                                    htmlFor="avatar_position_x"
                                    className="flex justify-between text-xs text-[#5b5953]"
                                >
                                    Horizontal{' '}
                                    <span>{data.avatar_position_x}%</span>
                                </label>
                                <input
                                    id="avatar_position_x"
                                    type="range"
                                    min="0"
                                    max="100"
                                    value={data.avatar_position_x}
                                    className="mt-1 w-full accent-[#464498]"
                                    onChange={(event) =>
                                        setData(
                                            'avatar_position_x',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </div>
                            <div>
                                <label
                                    htmlFor="avatar_position_y"
                                    className="flex justify-between text-xs text-[#5b5953]"
                                >
                                    Vertical{' '}
                                    <span>{data.avatar_position_y}%</span>
                                </label>
                                <input
                                    id="avatar_position_y"
                                    type="range"
                                    min="0"
                                    max="100"
                                    value={data.avatar_position_y}
                                    className="mt-1 w-full accent-[#464498]"
                                    onChange={(event) =>
                                        setData(
                                            'avatar_position_y',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend className="mb-3 text-sm font-bold text-[#1d1d1b]">
                                Moldura
                            </legend>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                {Object.entries(options.frames).map(
                                    ([key, label]) => (
                                        <button
                                            key={key}
                                            type="button"
                                            aria-pressed={
                                                data.profile_frame === key
                                            }
                                            className={`flex items-center gap-2 border-2 px-2 py-2 text-left text-xs font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498] ${
                                                data.profile_frame === key
                                                    ? 'border-[#1d1d1b] bg-white shadow-[3px_3px_0_#b4da33]'
                                                    : 'border-[#1d1d1b]/20 bg-white/60 hover:border-[#1d1d1b]'
                                            }`}
                                            onClick={() =>
                                                setData('profile_frame', key)
                                            }
                                        >
                                            <span
                                                className={`h-4 w-4 shrink-0 border-2 border-[#1d1d1b] ${frameColors[key] ?? frameColors.standard}`}
                                            />
                                            {label}
                                        </button>
                                    ),
                                )}
                            </div>
                            <InputError
                                message={errors.profile_frame}
                                className="mt-2"
                            />
                        </fieldset>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel
                                    htmlFor="primary_badge"
                                    value="Badge pessoal"
                                />
                                <select
                                    id="primary_badge"
                                    value={data.primary_badge}
                                    className="mt-2 block w-full border border-[#1d1d1b]/30 bg-white px-3 py-2 text-sm text-[#1d1d1b] focus:border-[#464498] focus:ring-[#464498]"
                                    onChange={(event) =>
                                        setData(
                                            'primary_badge',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {Object.entries(options.badges).map(
                                        ([key, label]) => (
                                            <option key={key} value={key}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError
                                    message={errors.primary_badge}
                                    className="mt-2"
                                />
                            </div>
                            <div>
                                <InputLabel
                                    htmlFor="profile_title"
                                    value="Título"
                                />
                                <select
                                    id="profile_title"
                                    value={data.profile_title ?? ''}
                                    className="mt-2 block w-full border border-[#1d1d1b]/30 bg-white px-3 py-2 text-sm text-[#1d1d1b] focus:border-[#464498] focus:ring-[#464498]"
                                    onChange={(event) =>
                                        setData(
                                            'profile_title',
                                            event.target.value || null,
                                        )
                                    }
                                >
                                    <option value="">Sem título</option>
                                    {Object.entries(options.titles).map(
                                        ([key, label]) => (
                                            <option key={key} value={key}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                <InputError
                                    message={errors.profile_title}
                                    className="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <div>
                        <p className="mb-3 font-mono text-xs font-bold uppercase tracking-[0.16em] text-[#464498]">
                            Prévia ao vivo
                        </p>
                        <PlayerProfileCard profile={previewProfile} compact />
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-4 border-t border-[#1d1d1b]/20 pt-5">
                    <PrimaryButton
                        disabled={processing}
                        className="border-2 border-[#1d1d1b] bg-[#b4da33] text-[#1d1d1b] hover:bg-[#c5e84b] focus:ring-[#464498]"
                    >
                        {processing ? 'Salvando...' : 'Salvar aparência'}
                    </PrimaryButton>
                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-out duration-200"
                        enterFrom="translate-y-1 opacity-0"
                        leave="transition ease-in duration-150"
                        leaveTo="opacity-0"
                    >
                        <p
                            role="status"
                            className="font-mono text-xs font-bold text-[#464498]"
                        >
                            Aparência salva.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
