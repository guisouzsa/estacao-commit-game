import { avatarDataUri } from '@/lib/avatarArt';
import { useEffect, useState } from 'react';
import type { PlayerProfile } from './PlayerProfileCard';

export const frameStyles: Record<string, string> = {
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

type Size = 'xs' | 'sm' | 'md' | 'lg';

const sizes: Record<Size, string> = {
    xs: 'size-8 border-2 p-0.5 text-xs',
    sm: 'size-11 border-2 p-0.5 text-sm',
    md: 'size-16 border-[3px] p-1 text-xl',
    lg: 'size-24 border-[3px] p-1 text-3xl',
};

/**
 * Avatar ÚNICO de todo o projeto (navbar, Dashboard, sala, busca, perfil).
 *
 * - Sempre há um desenho: gerado pela semente (estável por jogador). Ele fica
 *   por baixo e cobre o carregamento da foto e qualquer falha.
 * - Se o jogador enviou uma foto, ela vai por cima. Se a foto falhar (arquivo
 *   perdido, rede, URL errada), a foto sai de cena e sobra o desenho: nunca
 *   aparece imagem quebrada nem espaço vazio.
 * - Bots recebem a etiqueta BOT.
 */
export default function PlayerAvatar({
    profile,
    size = 'md',
    className = '',
}: {
    profile: PlayerProfile;
    size?: Size;
    className?: string;
}) {
    const [photoFailed, setPhotoFailed] = useState(false);

    // Uma foto nova (ou outra pessoa) merece uma nova tentativa.
    useEffect(() => setPhotoFailed(false), [profile.avatar]);

    const frame = frameStyles[profile.profile_frame ?? 'standard'] ?? frameStyles.standard;
    const art = avatarDataUri(
        profile.avatar_style ?? 'pixel',
        profile.avatar_seed || profile.username || 'sem-nome',
    );
    const showPhoto = Boolean(profile.avatar) && !photoFailed;
    const label = profile.is_bot
        ? `Avatar do bot ${profile.username}`
        : `Avatar de ${profile.username}`;

    return (
        <div
            className={`relative shrink-0 rounded-full bg-[#f3f1ec] ${sizes[size]} ${frame} ${className}`}
        >
            <div className="relative size-full overflow-hidden rounded-full bg-[#464498]">
                <img
                    alt={showPhoto ? '' : label}
                    aria-hidden={showPhoto || undefined}
                    className="absolute inset-0 size-full object-cover"
                    draggable={false}
                    src={art}
                    style={{ imageRendering: 'pixelated' }}
                />
                {showPhoto && (
                    <img
                        alt={label}
                        className="absolute inset-0 size-full object-cover"
                        onError={() => setPhotoFailed(true)}
                        src={profile.avatar as string}
                        style={{
                            objectPosition: `${profile.avatar_position_x ?? 50}% ${profile.avatar_position_y ?? 50}%`,
                            transform: `scale(${(profile.avatar_zoom ?? 100) / 100})`,
                        }}
                    />
                )}
            </div>
            {profile.is_bot && (
                <span
                    aria-hidden="true"
                    className="absolute -bottom-1 -right-1 border-2 border-[#1d1d1b] bg-[#b4da33] px-1 font-mono text-[8px] font-black uppercase leading-3 text-[#1d1d1b] sm:text-[9px]"
                >
                    Bot
                </span>
            )}
        </div>
    );
}
