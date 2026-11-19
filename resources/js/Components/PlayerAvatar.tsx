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

export default function PlayerAvatar({ profile, size = 'md', className = '' }: { profile: PlayerProfile; size?: 'xs'|'sm'|'md'|'lg'; className?: string }) {
    const sizes = { xs:'size-8 border-2 p-0.5 text-xs', sm:'size-11 border-2 p-0.5 text-sm', md:'size-16 border-[3px] p-1 text-xl', lg:'size-24 border-[3px] p-1 text-3xl' };
    const frame = frameStyles[profile.profile_frame ?? 'standard'] ?? frameStyles.standard;
    return <div className={`shrink-0 rounded-full bg-[#f3f1ec] ${sizes[size]} ${frame} ${className}`}>
        <div className="flex size-full items-center justify-center overflow-hidden rounded-full bg-[#464498] font-black text-white">
            {profile.avatar ? <img src={profile.avatar} alt={`Avatar de @${profile.username}`} className="size-full object-cover" style={{ objectPosition:`${profile.avatar_position_x ?? 50}% ${profile.avatar_position_y ?? 50}%`, transform:`scale(${(profile.avatar_zoom ?? 100)/100})` }} /> : <span aria-hidden="true">{profile.username.trim().charAt(0).toUpperCase() || '?'}</span>}
        </div>
    </div>;
}
