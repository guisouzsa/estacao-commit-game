import { profileBackgroundStyle } from '@/lib/profileBackgrounds';
import { toast } from '@/lib/toast';
import AvatarPicker from '@/Components/AvatarPicker';
import InputError from '@/Components/InputError';
import PlayerProfileCard, {
    PlayerProfile,
    ProfileBadge,
    ProfileLink,
} from '@/Components/PlayerProfileCard';
import ProfileExtras from '@/Components/ProfileExtras';
import ProfileImageEditor from '@/Components/ProfileImageEditor';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ChangeEvent,
    FormEvent,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

type Options = {
    frames: Record<string, string>;
    backgrounds?: Record<string, string>;
    badges: Record<string, string>;
    titles: Record<string, string>;
};

type EditableLink = ProfileLink & { is_public: boolean };
type ProfileExtrasData = {
    links: EditableLink[];
    badges: ProfileBadge[];
    achievement_badge_id: number | null;
    statistics: PlayerProfile['statistics'];
};

type FormData = {
    _method: 'patch';
    username: string;
    bio: string;
    avatar: File | null;
    /** Ícone gerado escolhido, no formato "estilo:semente" (alternativa à foto). */
    avatar_generated: string | null;
    cover: File | null;
    remove_avatar: boolean;
    remove_cover: boolean;
    avatar_position_x: number;
    avatar_position_y: number;
    avatar_zoom: number;
    cover_position_x: number;
    cover_position_y: number;
    profile_frame: string;
    profile_background: string | null;
    primary_badge: string;
    profile_title: string | null;
    achievement_badge_id: number | null;
    links: EditableLink[];
};

type EditorState = {
    kind: 'avatar' | 'cover';
    file?: File;
    src?: string;
};

const frameColors: Record<string, string> = {
    standard: 'border-[#1d1d1b] shadow-[0_0_0_4px_#1d1d1b]',
    lime: 'border-[#b4da33] shadow-[0_0_0_4px_#b4da33]',
    violet: 'border-[#464498] shadow-[0_0_0_4px_#464498]',
    pink: 'border-[#f481c6] shadow-[0_0_0_4px_#f481c6]',
    red: 'border-[#d01600] shadow-[0_0_0_4px_#d01600]',
    terminal: 'border-[#1d1d1b] shadow-[0_0_0_3px_#b4da33,0_0_0_6px_#1d1d1b]',
    merge: 'border-[#f481c6] shadow-[-4px_0_0_#464498,4px_0_0_#b4da33]',
    circuit: 'border-[#b4da33] shadow-[0_0_0_3px_#464498,4px_4px_0_#1d1d1b]',
    checkout: 'border-[#464498] shadow-[4px_-4px_0_#f481c6,-4px_4px_0_#b4da33]',
    hotfix: 'border-[#d01600] shadow-[0_0_0_3px_#1d1d1b,0_0_0_6px_#d01600]',
    neon: 'border-[#b4da33] shadow-[0_0_0_3px_#f481c6,0_0_0_6px_#464498]',
    sunset: 'border-[#f481c6] shadow-[4px_4px_0_#d01600,-4px_-4px_0_#f0b429]',
    ocean: 'border-[#464498] shadow-[0_0_0_3px_#7dd3fc,0_0_0_6px_#1d1d1b]',
    gold: 'border-[#f0b429] shadow-[0_0_0_3px_#1d1d1b,0_0_0_6px_#f0b429]',
};

const emptyExtras: ProfileExtrasData = {
    links: [],
    badges: [],
    achievement_badge_id: null,
    statistics: null,
};

function initialFormData(
    user: PlayerProfile,
    profileExtras: ProfileExtrasData,
): FormData {
    return {
        _method: 'patch',
        username: user.username,
        bio: user.bio ?? '',
        avatar: null,
        avatar_generated: null,
        cover: null,
        remove_avatar: false,
        remove_cover: false,
        avatar_position_x: user.avatar_position_x ?? 50,
        avatar_position_y: user.avatar_position_y ?? 50,
        avatar_zoom: user.avatar_zoom ?? 100,
        cover_position_x: user.cover_position_x ?? 50,
        cover_position_y: user.cover_position_y ?? 50,
        profile_frame: user.profile_frame ?? 'standard',
        profile_background: (user.profile_background ?? null) as string | null,
        primary_badge: user.primary_badge ?? 'terminal',
        profile_title: user.profile_title ?? null,
        achievement_badge_id: profileExtras.achievement_badge_id,
        links: profileExtras.links,
    };
}

export default function Edit({
    appearanceOptions,
    avatarStyles = ['pixel', 'robot', 'terminal'],
    profileExtras = emptyExtras,
    status,
}: {
    status?: string;
    appearanceOptions: Options;
    avatarStyles?: string[];
    profileExtras: ProfileExtrasData;
}) {
    const page = usePage();
    const user = page.props.auth.user as PlayerProfile & { email: string };
    const [editing, setEditing] = useState(false);
    const [editor, setEditor] = useState<EditorState | null>(null);
    const [avatarUrl, setAvatarUrl] = useState<string | null>(
        user.avatar ?? null,
    );
    const [coverUrl, setCoverUrl] = useState<string | null>(user.cover ?? null);
    const avatarInput = useRef<HTMLInputElement>(null);
    const coverInput = useRef<HTMLInputElement>(null);
    const cropSnapshot = useRef<FormData | null>(null);
    const {
        data,
        setData,
        setDefaults,
        post,
        errors,
        processing,
        reset,
        isDirty,
    } = useForm<FormData>(initialFormData(user, profileExtras));

    useEffect(() => {
        if (data.avatar) {
            const url = URL.createObjectURL(data.avatar);
            setAvatarUrl(url);

            return () => URL.revokeObjectURL(url);
        }
        setAvatarUrl(user.avatar ?? null);
    }, [data.avatar, user.avatar]);

    useEffect(() => {
        if (data.cover) {
            const url = URL.createObjectURL(data.cover);
            setCoverUrl(url);

            return () => URL.revokeObjectURL(url);
        }
        setCoverUrl(user.cover ?? null);
    }, [data.cover, user.cover]);

    const generated = data.avatar_generated
        ? data.avatar_generated.split(':')
        : null;

    const preview = useMemo<PlayerProfile>(
        () => ({
            ...user,
            ...data,
            ...(generated
                ? { avatar_style: generated[0], avatar_seed: generated[1] }
                : {}),
            avatar: generated || data.remove_avatar ? null : avatarUrl,
            cover: data.remove_cover ? null : coverUrl,
            badges: profileExtras.badges,
            statistics: profileExtras.statistics,
            achievement_badge:
                profileExtras.badges.find(
                    (badge) => badge.id === data.achievement_badge_id,
                ) ?? null,
        }),
        [
            user,
            data,
            generated,
            avatarUrl,
            coverUrl,
            profileExtras.badges,
            profileExtras.statistics,
        ],
    );

    const cancelEditing = () => {
        reset();
        setAvatarUrl(user.avatar ?? null);
        setCoverUrl(user.cover ?? null);
        setEditor(null);
        cropSnapshot.current = null;
        setEditing(false);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('profile.appearance.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (response) => {
                const props = response.props as typeof response.props & {
                    auth: { user: PlayerProfile & { email: string } };
                    profileExtras: ProfileExtrasData;
                };
                const saved = initialFormData(
                    props.auth.user,
                    props.profileExtras ?? emptyExtras,
                );
                setData(saved);
                setDefaults(saved);
                setAvatarUrl(props.auth.user.avatar ?? null);
                setCoverUrl(props.auth.user.cover ?? null);
                setEditor(null);
                setEditing(false);
                toast.success('Perfil atualizado com sucesso!', { id: 'profile:saved' });
            },
        });
    };

    const startCrop = (kind: 'avatar' | 'cover', file?: File) => {
        cropSnapshot.current = { ...data };
        setEditor(
            file
                ? { kind, file }
                : {
                      kind,
                      src:
                          kind === 'avatar'
                              ? (avatarUrl ?? undefined)
                              : (coverUrl ?? undefined),
                  },
        );
    };

    const chooseImage = (
        kind: 'avatar' | 'cover',
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;

        cropSnapshot.current = { ...data };
        if (kind === 'avatar') {
            setData((current) => ({
                ...current,
                avatar: file,
                avatar_generated: null,
                remove_avatar: false,
            }));
        } else {
            setData((current) => ({
                ...current,
                cover: file,
                remove_cover: false,
            }));
        }
        setEditor({ kind, file });
    };

    const cancelCrop = () => {
        if (cropSnapshot.current) setData(cropSnapshot.current);
        cropSnapshot.current = null;
        setEditor(null);
    };

    const confirmCrop = (crop: { x: number; y: number; zoom: number }) => {
        if (editor?.kind === 'avatar') {
            setData((current) => ({
                ...current,
                avatar_position_x: Math.round(crop.x),
                avatar_position_y: Math.round(crop.y),
                avatar_zoom: Math.round(crop.zoom),
            }));
        } else if (editor?.kind === 'cover') {
            setData((current) => ({
                ...current,
                cover_position_x: Math.round(crop.x),
                cover_position_y: Math.round(crop.y),
            }));
        }
        cropSnapshot.current = null;
        setEditor(null);
    };

    const updateLink = (
        index: number,
        field: keyof EditableLink,
        value: string | boolean,
    ) => {
        const links = [...data.links];
        links[index] = { ...links[index], [field]: value };
        setData('links', links);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Meu perfil" />
            <main className="min-h-screen overflow-x-hidden bg-[#e8e5dc] px-3 py-6 text-[#1d1d1b] sm:px-4 sm:py-10 lg:px-6">
                <div className="w-full">
                    <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-[#464498]">
                                Estação Commit / perfil
                            </p>
                            <h1 className="mt-1 text-2xl font-black sm:text-3xl">
                                Sua página de jogador
                            </h1>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link
                                className="min-h-11 border-2 border-[#1d1d1b] bg-white px-4 py-2 font-mono text-xs font-bold uppercase shadow-[3px_3px_0_#464498]"
                                href={route('profile.show', user.username)}
                            >
                                Ver página pública
                            </Link>
                            {!editing && (
                                <button
                                    className="min-h-11 border-2 border-[#1d1d1b] bg-[#b4da33] px-4 py-2 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b]"
                                    onClick={() => setEditing(true)}
                                    type="button"
                                >
                                    Editar perfil
                                </button>
                            )}
                        </div>
                    </header>

                    <form noValidate onSubmit={submit}>
                        <PlayerProfileCard
                            profile={
                                editing
                                    ? preview
                                    : {
                                          ...user,
                                          badges: profileExtras.badges,
                                          statistics: profileExtras.statistics,
                                          links: profileExtras.links,
                                          achievement_badge:
                                              profileExtras.badges.find(
                                                  (badge) =>
                                                      badge.id ===
                                                      profileExtras.achievement_badge_id,
                                              ) ?? null,
                                      }
                            }
                        >
                            <ProfileExtras
                                badges={profileExtras.badges}
                                links={
                                    editing
                                        ? data.links.filter(
                                              (link) => link.is_public,
                                          )
                                        : profileExtras.links.filter(
                                              (link) => link.is_public,
                                          )
                                }
                                share={!editing}
                                statistics={profileExtras.statistics}
                                username={data.username}
                            />
                        </PlayerProfileCard>

                        {editing && (
                            <section className="mt-6 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-4 shadow-[6px_6px_0_#464498] sm:p-6">
                                <div className="mb-5 flex flex-wrap items-start justify-between gap-3 border-b-2 border-[#1d1d1b]/15 pb-4">
                                    <div>
                                        <h2 className="text-xl font-black">
                                            Modo de edição
                                        </h2>
                                        <p className="mt-1 text-sm text-[#5b5953]">
                                            Ajuste sua página e confira a prévia
                                            acima. As alterações só são enviadas
                                            ao salvar.
                                        </p>
                                    </div>
                                    {isDirty && (
                                        <span className="border-2 border-[#d01600] bg-white px-2 py-1 font-mono text-xs font-bold uppercase text-[#d01600]">
                                            Alterações não salvas
                                        </span>
                                    )}
                                </div>

                                <div className="grid gap-7 lg:grid-cols-2">
                                    <div className="space-y-5">
                                        <label className="block text-sm font-bold">
                                            Username público
                                            <input
                                                autoComplete="username"
                                                className="mt-2 block min-h-11 w-full border-2 border-[#1d1d1b] bg-white px-3 py-2"
                                                maxLength={30}
                                                minLength={3}
                                                onChange={(event) =>
                                                    setData(
                                                        'username',
                                                        event.target.value
                                                            .toLowerCase()
                                                            .replace(
                                                                /[^a-z0-9_-]/g,
                                                                '',
                                                            ),
                                                    )
                                                }
                                                pattern="[A-Za-z0-9_-]{3,30}"
                                                required
                                                value={data.username}
                                            />
                                        </label>
                                        <InputError message={errors.username} />
                                        {data.username !== user.username && (
                                            <div className="border-l-4 border-[#d01600] bg-[#fff2ef] px-3 py-2 text-sm leading-6 text-[#6e160d]">
                                                <strong>Atenção:</strong> ao
                                                alterar seu identificador, o
                                                atual será liberado. Se outra
                                                pessoa o registrar, você poderá
                                                perdê-lo definitivamente.
                                            </div>
                                        )}

                                        <label className="block text-sm font-bold">
                                            Biografia
                                            <textarea
                                                className="mt-2 block min-h-28 w-full border-2 border-[#1d1d1b] bg-white px-3 py-2"
                                                maxLength={500}
                                                onChange={(event) =>
                                                    setData(
                                                        'bio',
                                                        event.target.value,
                                                    )
                                                }
                                                rows={4}
                                                value={data.bio}
                                            />
                                            <span className="mt-1 block text-right font-mono text-xs font-normal">
                                                {data.bio.length}/500
                                            </span>
                                        </label>
                                        <InputError message={errors.bio} />

                                        <fieldset>
                                            <legend className="mb-2 text-sm font-bold">
                                                Moldura de avatar
                                            </legend>
                                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                {Object.entries(
                                                    appearanceOptions.frames,
                                                ).map(([key, label]) => (
                                                    <button
                                                        aria-pressed={
                                                            data.profile_frame ===
                                                            key
                                                        }
                                                        className={`flex min-h-20 items-center gap-3 border-2 bg-white p-3 text-left text-xs font-bold ${
                                                            data.profile_frame ===
                                                            key
                                                                ? 'border-[#1d1d1b] shadow-[3px_3px_0_#b4da33]'
                                                                : 'border-[#1d1d1b]/25'
                                                        }`}
                                                        key={key}
                                                        onClick={() =>
                                                            setData(
                                                                'profile_frame',
                                                                key,
                                                            )
                                                        }
                                                        type="button"
                                                    >
                                                        <span
                                                            aria-hidden="true"
                                                            className={`size-9 shrink-0 rounded-full border-[3px] bg-[#464498] ${frameColors[key] ?? frameColors.standard}`}
                                                        />
                                                        {label}
                                                    </button>
                                                ))}
                                            </div>
                                        </fieldset>

                                        {appearanceOptions.backgrounds && (
                                            <fieldset>
                                                <legend className="mb-1 text-sm font-bold">
                                                    Fundo do perfil
                                                </legend>
                                                <p className="mb-2 text-xs text-[#5b5953]">
                                                    A pré-visualização acima muda ao escolher. São fundos leves, sem imagem para baixar.
                                                </p>
                                                <div
                                                    aria-label="Fundos de perfil"
                                                    className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6"
                                                    role="group"
                                                >
                                                    <button
                                                        aria-pressed={!data.profile_background}
                                                        className={`relative flex h-16 flex-col justify-end border-2 bg-[linear-gradient(135deg,#464498_0_52%,#b4da33_52%_56%,#1d1d1b_56%)] p-1 text-left ${
                                                            !data.profile_background
                                                                ? 'border-[#1d1d1b] shadow-[3px_3px_0_#b4da33]'
                                                                : 'border-[#1d1d1b]/25'
                                                        }`}
                                                        onClick={() => setData('profile_background', null)}
                                                        type="button"
                                                    >
                                                        <span className="bg-white/90 px-1 font-mono text-[9px] font-black uppercase">
                                                            Padrão
                                                        </span>
                                                        {!data.profile_background && (
                                                            <span aria-hidden="true" className="absolute right-1 top-1 grid size-4 place-items-center bg-[#b4da33] text-[10px] font-black">✓</span>
                                                        )}
                                                    </button>
                                                    {Object.entries(appearanceOptions.backgrounds).map(([key, label]) => (
                                                        <button
                                                            aria-label={`Fundo ${label}`}
                                                            aria-pressed={data.profile_background === key}
                                                            className={`relative flex h-16 flex-col justify-end border-2 p-1 text-left ${
                                                                data.profile_background === key
                                                                    ? 'border-[#1d1d1b] shadow-[3px_3px_0_#b4da33]'
                                                                    : 'border-[#1d1d1b]/25'
                                                            }`}
                                                            key={key}
                                                            onClick={() => setData('profile_background', key)}
                                                            style={profileBackgroundStyle(key)}
                                                            title={label}
                                                            type="button"
                                                        >
                                                            <span className="truncate bg-white/90 px-1 font-mono text-[9px] font-black uppercase">
                                                                {label}
                                                            </span>
                                                            {data.profile_background === key && (
                                                                <span aria-hidden="true" className="absolute right-1 top-1 grid size-4 place-items-center bg-[#b4da33] text-[10px] font-black">✓</span>
                                                            )}
                                                        </button>
                                                    ))}
                                                </div>
                                                <InputError message={errors.profile_background} className="mt-2" />
                                            </fieldset>
                                        )}

                                        <label className="block text-sm font-bold">
                                            Selo do perfil
                                            <select
                                                className="mt-2 block min-h-11 w-full border-2 border-[#1d1d1b] bg-white px-3 py-2"
                                                onChange={(event) =>
                                                    setData(
                                                        'primary_badge',
                                                        event.target.value,
                                                    )
                                                }
                                                value={data.primary_badge}
                                            >
                                                {Object.entries(
                                                    appearanceOptions.badges,
                                                ).map(([key, label]) => (
                                                    <option
                                                        key={key}
                                                        value={key}
                                                    >
                                                        {label}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>

                                        <label className="block text-sm font-bold">
                                            Título disponível
                                            <select
                                                className="mt-2 block min-h-11 w-full border-2 border-[#1d1d1b] bg-white px-3 py-2"
                                                onChange={(event) =>
                                                    setData(
                                                        'profile_title',
                                                        event.target.value ||
                                                            null,
                                                    )
                                                }
                                                value={data.profile_title ?? ''}
                                            >
                                                <option value="">
                                                    Sem título
                                                </option>
                                                {Object.entries(
                                                    appearanceOptions.titles,
                                                ).map(([key, label]) => (
                                                    <option
                                                        key={key}
                                                        value={key}
                                                    >
                                                        {label}
                                                    </option>
                                                ))}
                                            </select>
                                        </label>

                                        <label className="block text-sm font-bold">
                                            Conquista em destaque
                                            <select
                                                className="mt-2 block min-h-11 w-full border-2 border-[#1d1d1b] bg-white px-3 py-2"
                                                onChange={(event) =>
                                                    setData(
                                                        'achievement_badge_id',
                                                        event.target.value
                                                            ? Number(
                                                                  event.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    )
                                                }
                                                value={
                                                    data.achievement_badge_id ??
                                                    ''
                                                }
                                            >
                                                <option value="">
                                                    Sem destaque
                                                </option>
                                                {profileExtras.badges
                                                    .filter(
                                                        (badge) =>
                                                            badge.unlocked,
                                                    )
                                                    .map((badge) => (
                                                        <option
                                                            key={badge.id}
                                                            value={badge.id}
                                                        >
                                                            {badge.name}
                                                        </option>
                                                    ))}
                                            </select>
                                        </label>
                                        <InputError
                                            message={
                                                errors.achievement_badge_id
                                            }
                                        />
                                    </div>

                                    <div className="space-y-6">
                                        <section>
                                            <h3 className="text-sm font-bold">
                                                Avatar
                                            </h3>
                                            <p className="mt-1 text-xs text-[#68665f]">
                                                JPG, PNG ou WebP, até 2 MB.
                                            </p>
                                            <input
                                                accept="image/jpeg,image/png,image/webp"
                                                className="sr-only"
                                                onChange={(event) =>
                                                    chooseImage('avatar', event)
                                                }
                                                ref={avatarInput}
                                                type="file"
                                            />
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                <button
                                                    className="min-h-11 border-2 border-[#1d1d1b] bg-[#1d1d1b] px-4 font-mono text-xs font-bold uppercase text-white shadow-[3px_3px_0_#b4da33]"
                                                    onClick={() =>
                                                        avatarInput.current?.click()
                                                    }
                                                    type="button"
                                                >
                                                    Trocar foto
                                                </button>
                                                {avatarUrl && !data.avatar_generated && (
                                                    <button
                                                        className="min-h-11 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase"
                                                        onClick={() =>
                                                            startCrop('avatar')
                                                        }
                                                        type="button"
                                                    >
                                                        Ajustar recorte
                                                    </button>
                                                )}
                                                <button
                                                    className="min-h-11 border-2 border-[#d01600] px-3 font-mono text-xs font-bold uppercase text-[#d01600]"
                                                    onClick={() => {
                                                        setData(
                                                            'remove_avatar',
                                                            !data.remove_avatar,
                                                        );
                                                        setData('avatar', null);
                                                        setData(
                                                            'avatar_generated',
                                                            null,
                                                        );
                                                    }}
                                                    type="button"
                                                >
                                                    {data.remove_avatar
                                                        ? 'Manter avatar'
                                                        : 'Remover avatar'}
                                                </button>
                                            </div>
                                            <InputError
                                                className="mt-2"
                                                message={errors.avatar}
                                            />

                                            <div className="mt-5 border-t-2 border-[#1d1d1b]/15 pt-4">
                                                <h4 className="text-sm font-bold">
                                                    Ou escolha um ícone
                                                </h4>
                                                <p className="mb-3 mt-1 text-xs text-[#68665f]">
                                                    Sem foto? Use um ícone
                                                    gerado. Se você enviar uma
                                                    foto, ela tem prioridade.
                                                </p>
                                                <AvatarPicker
                                                    styles={avatarStyles}
                                                    style={
                                                        generated?.[0] ??
                                                        user.avatar_style ??
                                                        avatarStyles[0]
                                                    }
                                                    seed={
                                                        generated
                                                            ? generated[1]
                                                            : avatarUrl
                                                              ? null
                                                              : (user.avatar_seed ??
                                                                null)
                                                    }
                                                    previewName={user.username}
                                                    onChange={(style, seed) =>
                                                        setData((current) => ({
                                                            ...current,
                                                            avatar: null,
                                                            remove_avatar: false,
                                                            avatar_generated: `${style}:${seed}`,
                                                        }))
                                                    }
                                                />
                                                <InputError
                                                    className="mt-2"
                                                    message={
                                                        errors.avatar_generated
                                                    }
                                                />
                                            </div>
                                        </section>

                                        <section className="border-t-2 border-[#1d1d1b]/15 pt-5">
                                            <h3 className="text-sm font-bold">
                                                Banner de fundo
                                            </h3>
                                            <p className="mt-1 text-xs text-[#68665f]">
                                                Imagem horizontal exibida ao
                                                fundo do seu perfil. JPG, PNG ou
                                                WebP, até 4 MB.
                                            </p>
                                            <input
                                                accept="image/jpeg,image/png,image/webp"
                                                className="sr-only"
                                                onChange={(event) =>
                                                    chooseImage('cover', event)
                                                }
                                                ref={coverInput}
                                                type="file"
                                            />
                                            <div className="mt-3 flex flex-wrap gap-2">
                                                <button
                                                    className="min-h-11 border-2 border-[#1d1d1b] bg-[#1d1d1b] px-4 font-mono text-xs font-bold uppercase text-white shadow-[3px_3px_0_#b4da33]"
                                                    onClick={() =>
                                                        coverInput.current?.click()
                                                    }
                                                    type="button"
                                                >
                                                    Trocar banner
                                                </button>
                                                {(coverUrl || data.cover) && (
                                                    <button
                                                        className="min-h-11 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase"
                                                        onClick={() =>
                                                            startCrop('cover')
                                                        }
                                                        type="button"
                                                    >
                                                        Reposicionar
                                                    </button>
                                                )}
                                                <button
                                                    className="min-h-11 border-2 border-[#d01600] px-3 font-mono text-xs font-bold uppercase text-[#d01600]"
                                                    onClick={() => {
                                                        setData(
                                                            'remove_cover',
                                                            !data.remove_cover,
                                                        );
                                                        setData('cover', null);
                                                    }}
                                                    type="button"
                                                >
                                                    {data.remove_cover
                                                        ? 'Manter banner'
                                                        : 'Usar fundo padrão'}
                                                </button>
                                            </div>
                                            <InputError
                                                className="mt-2"
                                                message={errors.cover}
                                            />
                                        </section>
                                    </div>
                                </div>

                                <fieldset className="mt-7 border-t-2 border-[#1d1d1b]/15 pt-5">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <legend className="text-lg font-black">
                                                Conecte-se comigo
                                            </legend>
                                            <p className="mt-1 text-sm text-[#68665f]">
                                                Até oito links HTTPS no perfil
                                                público.
                                            </p>
                                        </div>
                                        <button
                                            className="min-h-11 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase disabled:opacity-40"
                                            disabled={data.links.length >= 8}
                                            onClick={() =>
                                                setData('links', [
                                                    ...data.links,
                                                    {
                                                        platform: 'github',
                                                        label: 'GitHub',
                                                        url: 'https://github.com/',
                                                        is_public: true,
                                                    },
                                                ])
                                            }
                                            type="button"
                                        >
                                            Adicionar link
                                        </button>
                                    </div>
                                    <div className="mt-4 space-y-3">
                                        {data.links.map((link, index) => (
                                            <div
                                                className="grid gap-2 border-2 border-[#1d1d1b] bg-white p-3 md:grid-cols-[130px_1fr_1.5fr_auto]"
                                                key={`${index}-${link.url}`}
                                            >
                                                <select
                                                    aria-label="Plataforma do link"
                                                    className="min-h-10 border border-[#1d1d1b] text-sm"
                                                    onChange={(event) =>
                                                        updateLink(
                                                            index,
                                                            'platform',
                                                            event.target.value,
                                                        )
                                                    }
                                                    value={link.platform}
                                                >
                                                    <option value="github">
                                                        GitHub
                                                    </option>
                                                    <option value="linkedin">
                                                        LinkedIn
                                                    </option>
                                                    <option value="instagram">
                                                        Instagram
                                                    </option>
                                                    <option value="youtube">
                                                        YouTube
                                                    </option>
                                                    <option value="twitch">
                                                        Twitch
                                                    </option>
                                                    <option value="portfolio">
                                                        Portfólio
                                                    </option>
                                                    <option value="website">
                                                        Site
                                                    </option>
                                                    <option value="other">
                                                        Outro
                                                    </option>
                                                </select>
                                                <input
                                                    aria-label="Nome do link"
                                                    className="min-h-10 border border-[#1d1d1b] px-2 text-sm"
                                                    maxLength={60}
                                                    onChange={(event) =>
                                                        updateLink(
                                                            index,
                                                            'label',
                                                            event.target.value,
                                                        )
                                                    }
                                                    value={link.label}
                                                />
                                                <input
                                                    aria-label="URL HTTPS"
                                                    className="min-h-10 border border-[#1d1d1b] px-2 text-sm"
                                                    onChange={(event) =>
                                                        updateLink(
                                                            index,
                                                            'url',
                                                            event.target.value,
                                                        )
                                                    }
                                                    type="url"
                                                    value={link.url}
                                                />
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        aria-label="Mover para cima"
                                                        className="size-10 border border-[#1d1d1b] disabled:opacity-30"
                                                        disabled={index === 0}
                                                        onClick={() => {
                                                            const links = [
                                                                ...data.links,
                                                            ];
                                                            [
                                                                links[
                                                                    index - 1
                                                                ],
                                                                links[index],
                                                            ] = [
                                                                links[index],
                                                                links[
                                                                    index - 1
                                                                ],
                                                            ];
                                                            setData(
                                                                'links',
                                                                links,
                                                            );
                                                        }}
                                                        type="button"
                                                    >
                                                        ↑
                                                    </button>
                                                    <button
                                                        aria-label="Mover para baixo"
                                                        className="size-10 border border-[#1d1d1b] disabled:opacity-30"
                                                        disabled={
                                                            index ===
                                                            data.links.length -
                                                                1
                                                        }
                                                        onClick={() => {
                                                            const links = [
                                                                ...data.links,
                                                            ];
                                                            [
                                                                links[index],
                                                                links[
                                                                    index + 1
                                                                ],
                                                            ] = [
                                                                links[
                                                                    index + 1
                                                                ],
                                                                links[index],
                                                            ];
                                                            setData(
                                                                'links',
                                                                links,
                                                            );
                                                        }}
                                                        type="button"
                                                    >
                                                        ↓
                                                    </button>
                                                    <button
                                                        aria-label="Remover link"
                                                        className="size-10 border border-[#d01600] font-bold text-[#d01600]"
                                                        onClick={() =>
                                                            setData(
                                                                'links',
                                                                data.links.filter(
                                                                    (_, item) =>
                                                                        item !==
                                                                        index,
                                                                ),
                                                            )
                                                        }
                                                        type="button"
                                                    >
                                                        ×
                                                    </button>
                                                </div>
                                                <label className="flex min-h-10 items-center gap-2 text-sm md:col-span-4">
                                                    <input
                                                        checked={link.is_public}
                                                        onChange={(event) =>
                                                            updateLink(
                                                                index,
                                                                'is_public',
                                                                event.target
                                                                    .checked,
                                                            )
                                                        }
                                                        type="checkbox"
                                                    />
                                                    Exibir no perfil público
                                                </label>
                                                <InputError
                                                    className="md:col-span-4"
                                                    message={
                                                        errors[
                                                            `links.${index}.url`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </fieldset>
                            </section>
                        )}
                        {editing && (
                            <div className="sticky bottom-3 z-30 mt-10 flex items-center justify-between gap-3 border-[3px] border-[#1d1d1b] bg-[#1d1d1b] p-3 text-white shadow-[6px_6px_0_#464498]">
                                <p
                                    className={`hidden text-sm font-bold sm:block ${Object.keys(errors).length ? 'text-[#f481c6]' : ''}`}
                                >
                                    {Object.keys(errors).length
                                        ? 'Revise os campos destacados antes de salvar.'
                                        : isDirty
                                          ? 'Existem alterações não salvas.'
                                          : 'Seu perfil está atualizado.'}
                                </p>
                                <div className="ml-auto flex gap-2">
                                    <button
                                        type="button"
                                        onClick={cancelEditing}
                                        className="min-h-12 border-2 border-white/60 px-4 font-mono text-xs font-bold uppercase"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing || !isDirty}
                                        className="min-h-12 border-2 border-white bg-[#b4da33] px-6 font-mono text-sm font-black uppercase text-[#1d1d1b] shadow-[3px_3px_0_#f481c6] disabled:opacity-50"
                                    >
                                        {processing
                                            ? 'Salvando...'
                                            : 'Salvar alterações'}
                                    </button>
                                </div>
                            </div>
                        )}
                    </form>

                    {!editing && (
                        <section className="mt-10 border-t-[3px] border-[#1d1d1b] pt-7">
                            <div className="mb-5">
                                <p className="font-mono text-xs font-bold uppercase tracking-[.18em] text-[#464498]">
                                    Conta e segurança
                                </p>
                                <h2 className="mt-1 text-2xl font-black">
                                    Acesso à conta
                                </h2>
                                <p className="mt-1 text-sm text-[#5b5953]">
                                    Configurações privadas, separadas do perfil
                                    público.
                                </p>
                            </div>
                            <div className="grid items-stretch gap-4 lg:grid-cols-3">
                                <UpdateProfileInformationForm
                                    className="h-full"
                                />
                                <UpdatePasswordForm />
                                <DeleteUserForm />
                            </div>
                        </section>
                    )}
                </div>
            </main>


            {editor && (editor.file || editor.src) && (
                <ProfileImageEditor
                    file={editor.file}
                    frame={
                        frameColors[data.profile_frame] ?? frameColors.standard
                    }
                    initial={{
                        x:
                            editor.kind === 'avatar'
                                ? data.avatar_position_x
                                : data.cover_position_x,
                        y:
                            editor.kind === 'avatar'
                                ? data.avatar_position_y
                                : data.cover_position_y,
                        zoom: data.avatar_zoom,
                    }}
                    kind={editor.kind}
                    onCancel={cancelCrop}
                    onConfirm={confirmCrop}
                    src={editor.src}
                />
            )}
        </AuthenticatedLayout>
    );
}
