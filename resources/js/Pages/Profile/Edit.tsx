import AchievementPicker, {
    AchievementDraft,
} from '@/Components/AchievementPicker';
import AvatarPicker from '@/Components/AvatarPicker';
import ConfirmDialog from '@/Components/ConfirmDialog';
import InputError from '@/Components/InputError';
import OptionPicker from '@/Components/OptionPicker';
import PlayerProfileCard, {
    PlayerProfile,
    ProfileBadge,
    ProfileLink,
} from '@/Components/PlayerProfileCard';
import ProfileExtras from '@/Components/ProfileExtras';
import ProfileImageEditor from '@/Components/ProfileImageEditor';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { checkImage } from '@/lib/imageSafety';
import { profileBackgroundStyle } from '@/lib/profileBackgrounds';
import { toast } from '@/lib/toast';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ChangeEvent,
    FormEvent,
    ReactNode,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

type Options = {
    frames: Record<string, string>;
    backgrounds?: Record<string, string>;
    premiumTiers?: PremiumTier[];
    wins?: number;
    badges: Record<string, string>;
    titles: Record<string, string>;
};

type EditableLink = ProfileLink & { is_public: boolean };

type PremiumTier = {
    name: string;
    min_wins: number;
    backgrounds: Record<string, string>;
};

/** Nome e começo de endereço sugeridos para cada rede (o servidor aceita qualquer URL HTTPS). */
const LINK_PLATFORMS: Record<string, { label: string; url: string }> = {
    github: { label: 'GitHub', url: 'https://github.com/' },
    linkedin: { label: 'LinkedIn', url: 'https://www.linkedin.com/in/' },
    instagram: { label: 'Instagram', url: 'https://www.instagram.com/' },
    youtube: { label: 'YouTube', url: 'https://www.youtube.com/@' },
    twitch: { label: 'Twitch', url: 'https://www.twitch.tv/' },
    portfolio: { label: 'Portfólio', url: 'https://' },
    website: { label: 'Site', url: 'https://' },
    other: { label: 'Link', url: 'https://' },
};

/**
 * Trocar a rede atualiza o nome e o endereço SÓ se a pessoa ainda não os personalizou (vazios ou iguais ao
 * padrão da rede anterior). Um nome ou endereço digitado à mão nunca é apagado.
 */
function switchPlatform(link: EditableLink, platform: string): EditableLink {
    const before = LINK_PLATFORMS[link.platform] ?? LINK_PLATFORMS.other;
    const after = LINK_PLATFORMS[platform] ?? LINK_PLATFORMS.other;
    const labelIsDefault = link.label.trim() === '' || link.label === before.label;
    const urlIsDefault = link.url.trim() === '' || link.url === before.url;

    return {
        ...link,
        platform,
        label: labelIsDefault ? after.label : link.label,
        url: urlIsDefault ? after.url : link.url,
    };
}
type ProfileExtrasData = {
    links: EditableLink[];
    badges: ProfileBadge[];
    achievement_display_mode: 'none' | 'custom' | 'all';
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
    cover_zoom: number;
    profile_frame: string;
    profile_background: string | null;
    primary_badge: string;
    profile_title: string | null;
    links: EditableLink[];
};

type EditorState = {
    kind: 'avatar' | 'cover';
    file?: File;
    src?: string;
    /** Proporção real da capa no card (largura/altura), medida ao abrir o editor. */
    coverAspect?: number;
};

/** Mede a faixa da capa do card nesta tela, para a prévia do editor ter exatamente a mesma proporção. */
const measureCoverAspect = () => {
    const frame = document.querySelector('[data-cover-frame]');
    const box = frame?.getBoundingClientRect();
    return box && box.width > 0 && box.height > 0 ? box.width / box.height : 5;
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

/** Igual a config/achievements.php (featured_limit); o servidor valida de novo. */
/** Fundos premium (animados): liberados a partir de `minWins` vitórias; antes disso aparecem com cadeado. */
const TIER_COLORS: Record<string, string> = {
    Premium: '#f0b429',
    Elite: '#7dd3fc',
    Lendário: '#f481c6',
};

function PremiumBackgrounds({
    name,
    options,
    selected,
    wins,
    minWins,
    onPick,
}: {
    name: string;
    options: Record<string, string>;
    selected: string | null;
    wins: number;
    minWins: number;
    onPick: (key: string) => void;
}) {
    const unlocked = wins >= minWins;
    const color = TIER_COLORS[name] ?? '#f0b429';

    return (
        <div className="mt-5">
            <p className="mb-2 flex flex-wrap items-center gap-2 text-sm font-bold">
                <span
                    className="border-2 border-[#1d1d1b] px-1.5 font-mono text-[10px] font-black uppercase"
                    style={{ background: color }}
                >
                    {name}
                </span>
                Animados · {minWins} vitórias
                <span className="font-mono text-xs font-normal text-[#5b5953]">
                    {unlocked
                        ? 'liberados'
                        : `${Math.min(wins, minWins)}/${minWins} vitórias para liberar`}
                </span>
            </p>
            <div
                aria-label={`Fundos ${name}`}
                className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6"
                role="group"
            >
                {Object.entries(options).map(([key, label]) => {
                    const active = selected === key;
                    const locked = !unlocked && !active;

                    return (
                        <button
                            aria-label={`Fundo ${name} ${label}${locked ? ', bloqueado' : ''}`}
                            aria-pressed={active}
                            className={`relative flex h-16 flex-col justify-end overflow-hidden border-2 p-1 text-left disabled:cursor-not-allowed ${
                                active
                                    ? 'border-[#1d1d1b] shadow-[3px_3px_0_var(--tier)]'
                                    : 'border-[#1d1d1b]/25'
                            }`}
                            data-profile-background={locked ? undefined : key}
                            disabled={locked}
                            key={key}
                            onClick={() => onPick(key)}
                            style={{
                                ...profileBackgroundStyle(key),
                                ['--tier' as string]: color,
                            }}
                            title={
                                locked
                                    ? `${label}: libera com ${minWins} vitórias`
                                    : label
                            }
                            type="button"
                        >
                            {locked && (
                                <span
                                    aria-hidden="true"
                                    className="absolute inset-0 grid place-items-center bg-[#1d1d1b]/55 text-lg"
                                >
                                    🔒
                                </span>
                            )}
                            <span className="relative truncate bg-white/90 px-1 font-mono text-[9px] font-black uppercase">
                                {label}
                            </span>
                            {active && (
                                <span
                                    aria-hidden="true"
                                    className="absolute right-1 top-1 grid size-4 place-items-center text-[10px] font-black"
                                    style={{ background: color }}
                                >
                                    ✓
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

const EDIT_SECTIONS = [
    { id: 'identidade', short: 'Identidade' },
    { id: 'foto', short: 'Foto' },
    { id: 'banner', short: 'Banner e fundo' },
    { id: 'conquistas', short: 'Conquistas' },
    { id: 'links', short: 'Links' },
];

/** Uma seção da edição: só um número, um título e uma linha separando da próxima (sem card). */
function EditSection({
    id,
    number,
    title,
    hint,
    children,
}: {
    id: string;
    number: number;
    title: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <section
            aria-labelledby={`editar-${id}-titulo`}
            className="scroll-mt-24 py-7 first:pt-0 last:pb-0"
            id={`editar-${id}`}
        >
            <header className="mb-4 flex items-start gap-3">
                <span
                    aria-hidden="true"
                    className="grid size-7 shrink-0 place-items-center bg-[#1d1d1b] font-mono text-xs font-black text-[#b4da33]"
                >
                    {number}
                </span>
                <div>
                    <h3
                        className="text-base font-black uppercase tracking-wide"
                        id={`editar-${id}-titulo`}
                    >
                        {title}
                    </h3>
                    {hint && (
                        <p className="mt-0.5 text-xs text-[#5b5953]">{hint}</p>
                    )}
                </div>
            </header>
            {children}
        </section>
    );
}

const ACHIEVEMENT_LIMIT = 11;

const emptyExtras: ProfileExtrasData = {
    links: [],
    badges: [],
    achievement_display_mode: 'none',
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
        cover_zoom: user.cover_zoom ?? 100,
        profile_frame: user.profile_frame ?? 'standard',
        profile_background: (user.profile_background ?? null) as string | null,
        primary_badge: user.primary_badge ?? 'terminal',
        profile_title: user.profile_title ?? null,
        links: profileExtras.links,
    };
}

export default function Edit({
    appearanceOptions,
    avatarStyles = ['pixel', 'robot', 'terminal'],
    profileExtras = emptyExtras,
    status,
}: {
    appearanceOptions: Options;
    avatarStyles?: string[];
    profileExtras: ProfileExtrasData;
    status?: string;
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
        if (!status) return;
        if (status === 'Conta Google vinculada com sucesso.') {
            toast.success(status, { key: 'google-account-linked' });
        } else {
            toast.info(status, { key: 'google-account-status' });
        }
    }, [status]);

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

    // "Editar perfil" abre a edição e leva a pessoa até ela (antes a tela parecia não mudar).
    const scrollToEditor = useRef(false);
    useEffect(() => {
        if (!editing || !scrollToEditor.current) return;
        // Espera a seção de edição aparecer na tela e então rola até ela.
        const timer = window.setTimeout(() => {
            scrollToEditor.current = false;
            document
                .getElementById('modo-edicao')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 60);
        return () => window.clearTimeout(timer);
    }, [editing]);

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
        resetAchievements();
        setEditing(false);
    };

    // Trocar o identificador libera o antigo: pede confirmação SÓ ao clicar em Salvar (nunca ao digitar).
    const [confirmRename, setConfirmRename] = useState(false);
    // Cancelar a edição com alterações não salvas pede confirmação (mesmo visual dos outros avisos).
    const [confirmDiscard, setConfirmDiscard] = useState(false);
    const renaming = data.username !== user.username;

    // Escolha das conquistas exibidas: editada junto com o resto do perfil e salva pelo mesmo botão.
    const savedAchievementMode = profileExtras.achievement_display_mode;
    const savedAchievementIds = useMemo(() => {
        const unlocked = profileExtras.badges.filter((badge) => badge.unlocked);
        if (savedAchievementMode === 'all')
            return unlocked
                .slice(0, ACHIEVEMENT_LIMIT)
                .map((badge) => badge.id);
        return unlocked
            .filter((badge) => badge.featured_order)
            .sort((a, b) => (a.featured_order ?? 0) - (b.featured_order ?? 0))
            .map((badge) => badge.id);
    }, [profileExtras.badges, savedAchievementMode]);
    const [achievementsOn, setAchievementsOn] = useState(
        savedAchievementMode !== 'none',
    );
    const [achievementIds, setAchievementIds] =
        useState<number[]>(savedAchievementIds);
    const [achievementsTouched, setAchievementsTouched] = useState(false);
    const resetAchievements = useCallback(() => {
        setAchievementsOn(savedAchievementMode !== 'none');
        setAchievementIds(savedAchievementIds);
        setAchievementsTouched(false);
    }, [savedAchievementMode, savedAchievementIds]);
    useEffect(resetAchievements, [resetAchievements]);
    const achievementDraft: AchievementDraft = {
        mode: achievementsOn ? 'custom' : 'none',
        featured: achievementsOn ? achievementIds : [],
        dirty:
            achievementsTouched &&
            ((achievementsOn ? 'custom' : 'none') !== savedAchievementMode ||
                (achievementsOn &&
                    achievementIds.join(',') !==
                        savedAchievementIds.join(','))),
    };
    const achievementsDirty = achievementDraft.dirty;
    // Prévia do card durante a edição: só as conquistas escolhidas, na ordem escolhida.
    const previewBadges = profileExtras.badges.map((badge) => {
        const position = achievementsOn ? achievementIds.indexOf(badge.id) : -1;

        return {
            ...badge,
            displayed: position >= 0,
            featured_order: position >= 0 ? position + 1 : null,
        };
    });
    const hasChanges = isDirty || achievementsDirty;

    const saveAchievements = (draft: AchievementDraft, onDone: () => void) => {
        router.put(
            route('profile.achievements.featured'),
            { mode: draft.mode, featured: draft.featured },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAchievementsTouched(false);
                    onDone();
                },
                onError: (errs: Record<string, string>) =>
                    toast.error(
                        String(
                            errs.featured ??
                                errs.mode ??
                                'Não foi possível salvar as conquistas agora.',
                        ),
                        {
                            id: 'profile:achievements',
                        },
                    ),
            },
        );
    };

    const finishSaved = () => {
        setEditor(null);
        setEditing(false);
        toast.success('Perfil atualizado com sucesso!', {
            id: 'profile:saved',
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (processing || !hasChanges) return;
        if (!isDirty && achievementDraft.dirty) {
            saveAchievements(achievementDraft, finishSaved);

            return;
        }
        if (renaming) {
            setConfirmRename(true);

            return;
        }
        save();
    };

    const save = () => {
        setConfirmRename(false);
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
                const pendingAchievements = achievementDraft.dirty
                    ? achievementDraft
                    : null;
                if (pendingAchievements)
                    saveAchievements(pendingAchievements, finishSaved);
                else finishSaved();
            },
        });
    };

    const startCrop = (kind: 'avatar' | 'cover', file?: File) => {
        cropSnapshot.current = { ...data };
        setEditor(
            file
                ? { kind, file, coverAspect: measureCoverAspect() }
                : {
                      kind,
                      coverAspect: measureCoverAspect(),
                      src:
                          kind === 'avatar'
                              ? (avatarUrl ?? undefined)
                              : (coverUrl ?? undefined),
                  },
        );
    };

    const [checkingImage, setCheckingImage] = useState<
        'avatar' | 'cover' | null
    >(null);

    const chooseImage = async (
        kind: 'avatar' | 'cover',
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file || checkingImage) return;

        // Foto e capa passam por uma checagem de conteúdo impróprio (+18) antes de entrar no editor.
        setCheckingImage(kind);
        const verdict = await checkImage(file);
        setCheckingImage(null);
        if (!verdict.allowed) {
            toast.error(
                'Essa imagem parece ter conteúdo impróprio ou +18 e não pode ser usada no perfil. Escolha outra.',
            );
            return;
        }

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
        setEditor({ kind, file, coverAspect: measureCoverAspect() });
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
                cover_zoom: Math.round(crop.zoom),
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
                                    onClick={() => {
                                        setEditing(true);
                                        scrollToEditor.current = true;
                                    }}
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
                                    ? { ...preview, badges: previewBadges }
                                    : {
                                          ...user,
                                          badges: profileExtras.badges,
                                          statistics: profileExtras.statistics,
                                          links: profileExtras.links,
                                      }
                            }
                        >
                            <ProfileExtras
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
                            <section
                                className="mt-6 scroll-mt-4 border-[3px] border-[#1d1d1b] bg-[#f3f1ec] p-4 shadow-[6px_6px_0_#464498] sm:p-6"
                                id="modo-edicao"
                            >
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
                                    {hasChanges && (
                                        <span className="border-2 border-[#d01600] bg-white px-2 py-1 font-mono text-xs font-bold uppercase text-[#d01600]">
                                            Alterações não salvas
                                        </span>
                                    )}
                                </div>

                                <nav
                                    aria-label="Seções da edição"
                                    className="-mx-1 mb-6 flex gap-1.5 overflow-x-auto px-1 pb-1"
                                >
                                    {EDIT_SECTIONS.map((section, index) => (
                                        <a
                                            className="shrink-0 border-2 border-[#1d1d1b]/25 bg-white px-2.5 py-1.5 font-mono text-[10px] font-bold uppercase hover:border-[#1d1d1b]"
                                            href={`#editar-${section.id}`}
                                            key={section.id}
                                        >
                                            {index + 1}. {section.short}
                                        </a>
                                    ))}
                                </nav>

                                <div className="divide-y-2 divide-[#1d1d1b]/15">
                                    <EditSection id="identidade" number={1} title="Identidade" hint="Seu @ público e a biografia.">
                                        <div className="max-w-2xl space-y-5">
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
                                        <div
                                            className={`border-l-4 px-3 py-2 text-sm leading-6 ${renaming ? 'border-[#d01600] bg-[#fff2ef] text-[#6e160d]' : 'border-[#1d1d1b]/30 bg-white/60 text-[#5b5953]'}`}
                                        >
                                            <strong>Atenção:</strong> ao alterar
                                            seu identificador, o atual será
                                            liberado. Se outra pessoa o
                                            registrar, você poderá perdê-lo
                                            definitivamente.
                                        </div>

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
                                        </div>
                                    </EditSection>

                                    <EditSection id="foto" number={2} title="Foto do perfil" hint="Envie uma foto ou use um ícone gerado. A moldura vale para os dois.">
                                        <div className="grid gap-6 lg:grid-cols-2">
                                            <div>
                                                <h4 className="text-sm font-bold">
                                                    Sua foto
                                                </h4>
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
                                                    disabled={
                                                        checkingImage !== null
                                                    }
                                                    onClick={() =>
                                                        avatarInput.current?.click()
                                                    }
                                                    type="button"
                                                >
                                                    {checkingImage === 'avatar'
                                                        ? 'Verificando imagem…'
                                                        : 'Trocar foto'}
                                                </button>
                                                {avatarUrl &&
                                                    !data.avatar_generated && (
                                                        <button
                                                            className="min-h-11 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase"
                                                            onClick={() =>
                                                                startCrop(
                                                                    'avatar',
                                                                )
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
                                            </div>
                                            <div>
                                                <h4 className="text-sm font-bold">
                                                    Ou use um ícone gerado
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
                                        </div>
                                        <div className="mt-6">
                                                <fieldset>
                                                    <legend className="mb-2 text-sm font-bold">
                                                        Moldura de avatar
                                                    </legend>
                                                    <div className="grid grid-cols-3 gap-2 sm:gap-3">
                                                        {Object.entries(
                                                            appearanceOptions.frames,
                                                        ).map(
                                                            ([key, label]) => (
                                                                <button
                                                                    aria-pressed={
                                                                        data.profile_frame ===
                                                                        key
                                                                    }
                                                                    className={`flex flex-col items-center gap-1.5 border-2 bg-white p-2 text-center text-[11px] font-bold leading-tight sm:min-h-20 sm:flex-row sm:gap-3 sm:p-3 sm:text-left sm:text-xs ${
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
                                                                        className={`size-8 shrink-0 rounded-full border-[3px] bg-[#464498] sm:size-9 ${frameColors[key] ?? frameColors.standard}`}
                                                                    />
                                                                    {label}
                                                                </button>
                                                            ),
                                                        )}
                                                    </div>
                                                </fieldset>
                                        </div>
                                    </EditSection>

                                    <EditSection id="banner" number={3} title="Banner e fundo" hint="Uma imagem sua no topo do perfil ou um dos fundos da Estação.">
                                        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                                            <div>
                                                <h4 className="text-sm font-bold">
                                                    Imagem do banner
                                                </h4>
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
                                                    disabled={
                                                        checkingImage !== null
                                                    }
                                                    onClick={() =>
                                                        coverInput.current?.click()
                                                    }
                                                    type="button"
                                                >
                                                    {checkingImage === 'cover'
                                                        ? 'Verificando imagem…'
                                                        : 'Trocar banner'}
                                                </button>
                                                {(coverUrl || data.cover) && (
                                                    <button
                                                        className="min-h-11 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase"
                                                        onClick={() =>
                                                            startCrop('cover')
                                                        }
                                                        type="button"
                                                    >
                                                        Ajustar enquadramento
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
                                            </div>
                                            <div>
                                                {appearanceOptions.backgrounds && (
                                                    <fieldset>
                                                        <legend className="mb-1 text-sm font-bold">
                                                            Fundo do perfil
                                                        </legend>
                                                        <p className="mb-2 text-xs text-[#5b5953]">
                                                            A pré-visualização
                                                            acima muda ao
                                                            escolher. São fundos
                                                            leves, sem imagem
                                                            para baixar.
                                                        </p>
                                                        <div
                                                            aria-label="Fundos de perfil"
                                                            className="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6"
                                                            role="group"
                                                        >
                                                            <button
                                                                aria-pressed={
                                                                    !data.profile_background
                                                                }
                                                                className={`relative flex h-16 flex-col justify-end border-2 bg-[linear-gradient(135deg,#464498_0_52%,#b4da33_52%_56%,#1d1d1b_56%)] p-1 text-left ${
                                                                    !data.profile_background
                                                                        ? 'border-[#1d1d1b] shadow-[3px_3px_0_#b4da33]'
                                                                        : 'border-[#1d1d1b]/25'
                                                                }`}
                                                                onClick={() =>
                                                                    setData(
                                                                        'profile_background',
                                                                        null,
                                                                    )
                                                                }
                                                                type="button"
                                                            >
                                                                <span className="bg-white/90 px-1 font-mono text-[9px] font-black uppercase">
                                                                    Padrão
                                                                </span>
                                                                {!data.profile_background && (
                                                                    <span
                                                                        aria-hidden="true"
                                                                        className="absolute right-1 top-1 grid size-4 place-items-center bg-[#b4da33] text-[10px] font-black"
                                                                    >
                                                                        ✓
                                                                    </span>
                                                                )}
                                                            </button>
                                                            {Object.entries(
                                                                appearanceOptions.backgrounds,
                                                            ).map(
                                                                ([
                                                                    key,
                                                                    label,
                                                                ]) => (
                                                                    <button
                                                                        aria-label={`Fundo ${label}`}
                                                                        aria-pressed={
                                                                            data.profile_background ===
                                                                            key
                                                                        }
                                                                        className={`relative flex h-16 flex-col justify-end border-2 p-1 text-left ${
                                                                            data.profile_background ===
                                                                            key
                                                                                ? 'border-[#1d1d1b] shadow-[3px_3px_0_#b4da33]'
                                                                                : 'border-[#1d1d1b]/25'
                                                                        }`}
                                                                        key={
                                                                            key
                                                                        }
                                                                        onClick={() =>
                                                                            setData(
                                                                                'profile_background',
                                                                                key,
                                                                            )
                                                                        }
                                                                        data-profile-background={
                                                                            key
                                                                        }
                                                                        style={profileBackgroundStyle(
                                                                            key,
                                                                        )}
                                                                        title={
                                                                            label
                                                                        }
                                                                        type="button"
                                                                    >
                                                                        <span className="truncate bg-white/90 px-1 font-mono text-[9px] font-black uppercase">
                                                                            {
                                                                                label
                                                                            }
                                                                        </span>
                                                                        {data.profile_background ===
                                                                            key && (
                                                                            <span
                                                                                aria-hidden="true"
                                                                                className="absolute right-1 top-1 grid size-4 place-items-center bg-[#b4da33] text-[10px] font-black"
                                                                            >
                                                                                ✓
                                                                            </span>
                                                                        )}
                                                                    </button>
                                                                ),
                                                            )}
                                                        </div>
                                                        {appearanceOptions.premiumTiers?.map(
                                                            (tier) => (
                                                                <PremiumBackgrounds
                                                                    key={
                                                                        tier.name
                                                                    }
                                                                    minWins={
                                                                        tier.min_wins
                                                                    }
                                                                    name={
                                                                        tier.name
                                                                    }
                                                                    onPick={(
                                                                        key,
                                                                    ) =>
                                                                        setData(
                                                                            'profile_background',
                                                                            key,
                                                                        )
                                                                    }
                                                                    options={
                                                                        tier.backgrounds
                                                                    }
                                                                    selected={
                                                                        data.profile_background
                                                                    }
                                                                    wins={
                                                                        appearanceOptions.wins ??
                                                                        0
                                                                    }
                                                                />
                                                            ),
                                                        )}
                                                        <InputError
                                                            message={
                                                                errors.profile_background
                                                            }
                                                            className="mt-2"
                                                        />
                                                    </fieldset>
                                                )}
                                            </div>
                                        </div>
                                    </EditSection>

                                    <EditSection id="conquistas" number={4} title="Conquistas, selo e título" hint="O que aparece junto do seu nome e no seu card.">
                                        <div className="grid gap-5 lg:grid-cols-2">
                                        <OptionPicker
                                            hint="Aparece junto do seu nome no perfil."
                                            label="Selo do perfil"
                                            onChange={(value) =>
                                                setData('primary_badge', value)
                                            }
                                            options={Object.entries(
                                                appearanceOptions.badges,
                                            ).map(([key, label]) => ({
                                                value: key,
                                                label,
                                                accent: '#464498',
                                            }))}
                                            value={data.primary_badge}
                                        />

                                        <OptionPicker
                                            hint="Uma frase curta que descreve você."
                                            label="Título disponível"
                                            onChange={(value) =>
                                                setData(
                                                    'profile_title',
                                                    value || null,
                                                )
                                            }
                                            options={[
                                                {
                                                    value: '',
                                                    label: 'Sem título',
                                                    description:
                                                        'Nenhum título aparece no perfil.',
                                                },
                                                ...Object.entries(
                                                    appearanceOptions.titles,
                                                ).map(([key, label]) => ({
                                                    value: key,
                                                    label,
                                                    accent: '#b4da33',
                                                })),
                                            ]}
                                            value={data.profile_title ?? ''}
                                        />
                                        </div>
                                        <div className="mt-5">
                                        <AchievementPicker
                                            badges={profileExtras.badges}
                                            enabled={achievementsOn}
                                            limit={ACHIEVEMENT_LIMIT}
                                            onChange={({
                                                enabled,
                                                selected,
                                            }) => {
                                                setAchievementsOn(enabled);
                                                setAchievementIds(selected);
                                                setAchievementsTouched(true);
                                            }}
                                            selected={achievementIds}
                                        />
                                        </div>
                                    </EditSection>

                                    <EditSection id="links" number={5} title="Links" hint="Até oito links HTTPS no perfil público.">
                                        <fieldset>
                                        <div className="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <legend className="sr-only">
                                                    Links do perfil
                                                </legend>
                                            </div>
                                            <button
                                                className="min-h-11 border-2 border-[#1d1d1b] bg-white px-3 font-mono text-xs font-bold uppercase disabled:opacity-40"
                                                disabled={
                                                    data.links.length >= 8
                                                }
                                                onClick={() =>
                                                    setData('links', [
                                                        ...data.links,
                                                        {
                                                            platform: 'github',
                                                            label: LINK_PLATFORMS
                                                                .github.label,
                                                            url: LINK_PLATFORMS
                                                                .github.url,
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
                                                    className="grid gap-2 border-2 border-[#1d1d1b] bg-white p-3"
                                                    // Chave pela posição, nunca pelo conteúdo: com a URL na chave,
                                                    // cada letra recriava o campo e o teclado fechava.
                                                    key={index}
                                                >
                                                    <select
                                                        aria-label="Plataforma do link"
                                                        className="min-h-10 border border-[#1d1d1b] text-sm"
                                                        onChange={(event) => {
                                                            const links = [
                                                                ...data.links,
                                                            ];
                                                            links[index] =
                                                                switchPlatform(
                                                                    links[index],
                                                                    event.target
                                                                        .value,
                                                                );
                                                            setData(
                                                                'links',
                                                                links,
                                                            );
                                                        }}
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
                                                                event.target
                                                                    .value,
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
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        autoCapitalize="none"
                                                        autoComplete="url"
                                                        autoCorrect="off"
                                                        inputMode="url"
                                                        spellCheck={false}
                                                        type="url"
                                                        value={link.url}
                                                    />
                                                    <div className="flex items-center gap-1">
                                                        <button
                                                            aria-label="Mover para cima"
                                                            className="size-10 border border-[#1d1d1b] disabled:opacity-30"
                                                            disabled={
                                                                index === 0
                                                            }
                                                            onClick={() => {
                                                                const links = [
                                                                    ...data.links,
                                                                ];
                                                                [
                                                                    links[
                                                                        index -
                                                                            1
                                                                    ],
                                                                    links[
                                                                        index
                                                                    ],
                                                                ] = [
                                                                    links[
                                                                        index
                                                                    ],
                                                                    links[
                                                                        index -
                                                                            1
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
                                                                data.links
                                                                    .length -
                                                                    1
                                                            }
                                                            onClick={() => {
                                                                const links = [
                                                                    ...data.links,
                                                                ];
                                                                [
                                                                    links[
                                                                        index
                                                                    ],
                                                                    links[
                                                                        index +
                                                                            1
                                                                    ],
                                                                ] = [
                                                                    links[
                                                                        index +
                                                                            1
                                                                    ],
                                                                    links[
                                                                        index
                                                                    ],
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
                                                                        (
                                                                            _,
                                                                            item,
                                                                        ) =>
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
                                                    <label className="flex min-h-10 items-center gap-2 text-sm">
                                                        <input
                                                            checked={
                                                                link.is_public
                                                            }
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
                                                        className=""
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
                                    </EditSection>
                                </div>
                            </section>
                        )}
                        {editing && (
                            <div className="sticky bottom-3 z-30 mt-10 flex items-center justify-between gap-3 border-[3px] border-[#1d1d1b] bg-[#1d1d1b] p-3 text-white shadow-[6px_6px_0_#464498]">
                                <p
                                    className={`hidden text-sm font-bold sm:block ${Object.keys(errors).length ? 'text-[#f481c6]' : ''}`}
                                >
                                    {Object.keys(errors).length
                                        ? 'Revise os campos destacados antes de salvar.'
                                        : hasChanges
                                          ? 'Existem alterações não salvas.'
                                          : 'Seu perfil está atualizado.'}
                                </p>
                                <div className="ml-auto flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            hasChanges
                                                ? setConfirmDiscard(true)
                                                : cancelEditing()
                                        }
                                        className="min-h-12 border-2 border-white/60 px-4 font-mono text-xs font-bold uppercase"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={processing || !hasChanges}
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

                    <ConfirmDialog
                        show={confirmDiscard}
                        title="Descartar as alterações?"
                        eyebrow="Alterações não salvas"
                        confirmLabel="Descartar alterações"
                        cancelLabel="Continuar editando"
                        onConfirm={() => {
                            setConfirmDiscard(false);
                            cancelEditing();
                        }}
                        onClose={() => setConfirmDiscard(false)}
                    >
                        Tudo o que você mudou neste perfil e ainda não salvou
                        será perdido. Essa ação não poderá ser desfeita.
                    </ConfirmDialog>

                    <ConfirmDialog
                        show={confirmRename}
                        title="Alterar seu identificador?"
                        eyebrow="Atenção"
                        confirmLabel="Sim, alterar"
                        busyLabel="Salvando..."
                        busy={processing}
                        onConfirm={save}
                        onClose={() => setConfirmRename(false)}
                    >
                        O identificador atual (<strong>@{user.username}</strong>
                        ) será liberado e você passará a ser{' '}
                        <strong>@{data.username}</strong>. Se outra pessoa
                        registrar o antigo, você poderá perdê-lo
                        definitivamente. Deseja continuar?
                    </ConfirmDialog>
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
                        zoom:
                            editor.kind === 'avatar'
                                ? data.avatar_zoom
                                : data.cover_zoom,
                    }}
                    coverAspect={editor.coverAspect}
                    freshCover={editor.kind === 'cover' && Boolean(editor.file)}
                    kind={editor.kind}
                    onCancel={cancelCrop}
                    onConfirm={confirmCrop}
                    src={editor.src}
                />
            )}
        </AuthenticatedLayout>
    );
}
