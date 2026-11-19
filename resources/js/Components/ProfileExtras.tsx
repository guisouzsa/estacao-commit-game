import { ProfileBadge, ProfileLink } from './PlayerProfileCard';
import { useState } from 'react';

const platformLabels: Record<string,string> = { github:'GitHub', linkedin:'LinkedIn', instagram:'Instagram', youtube:'YouTube', twitch:'Twitch', portfolio:'Portfólio', website:'Site', other:'Link' };
const rarityColors: Record<string,string> = { common:'#b4da33', uncommon:'#f481c6', rare:'#464498', epic:'#d01600', legendary:'#f0b429' };

export default function ProfileExtras({ username, links = [], badges = [], statistics = null, share = false }: { username: string; links?: ProfileLink[]; badges?: ProfileBadge[]; statistics?: {matches:number;wins:number;losses:number;win_rate:number}|null; share?: boolean }) {
    const [message, setMessage] = useState('');
    const shareProfile = async () => {
        const url = `${window.location.origin}/u/${username}`;
        if (navigator.share) { await navigator.share({ title:`@${username} na Estação Commit`, url }); setMessage('Perfil compartilhado.'); }
        else { await navigator.clipboard.writeText(url); setMessage('Link copiado.'); }
    };
    return <div className="mt-7 space-y-7">
        <section className="grid gap-5 border-y-[3px] border-[#1d1d1b] py-6 lg:grid-cols-[.8fr_1.2fr]">
            <div><div className="flex items-center justify-between gap-3"><h2 className="text-xl font-black">Desempenho</h2>{share && <button type="button" onClick={shareProfile} className="h-10 border-2 border-[#1d1d1b] bg-[#b4da33] px-4 font-mono text-xs font-black uppercase shadow-[3px_3px_0_#1d1d1b]">Compartilhar</button>}</div>{message && <p role="status" className="mt-2 text-sm font-bold text-[#464498]">{message}</p>}{statistics ? <div className="mt-4 grid grid-cols-2 gap-2"><Stat label="Partidas" value={statistics.matches}/><Stat label="Vitórias" value={statistics.wins}/><Stat label="Derrotas" value={statistics.losses}/><Stat label="Taxa" value={`${statistics.win_rate}%`}/></div> : <p className="mt-4 border-l-4 border-[#464498] bg-white p-3 text-sm leading-6 text-[#5b5953]">As estatísticas aparecerão quando houver partidas finalizadas e resultados verificáveis.</p>}</div>
            <div><h2 className="text-xl font-black">Conecte-se comigo</h2>{links.length ? <div className="mt-4 flex flex-wrap gap-2">{links.map((link,index)=><a key={`${link.url}-${index}`} href={link.url} target="_blank" rel="noopener noreferrer" className="inline-flex min-h-10 items-center border-2 border-[#1d1d1b] bg-white px-3 text-sm font-bold shadow-[2px_2px_0_#f481c6] hover:-translate-y-0.5"><span className="mr-2 font-mono text-xs text-[#464498]">{platformLabels[link.platform] ?? 'Link'}</span>{link.label}</a>)}</div> : <p className="mt-4 text-sm text-[#68665f]">Nenhum link público adicionado.</p>}</div>
        </section>
        <section><div className="flex items-end justify-between gap-3"><div><p className="font-mono text-xs font-bold uppercase tracking-[.16em] text-[#464498]">Coleção</p><h2 className="mt-1 text-2xl font-black">Conquistas</h2></div><p className="font-mono text-xs font-bold">{badges.filter(b=>b.unlocked).length}/{badges.length} desbloqueadas</p></div><div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">{badges.map((badge)=><article key={badge.id} title={badge.description} className={`relative min-h-40 border-[3px] border-[#1d1d1b] p-3 ${badge.unlocked ? 'bg-white shadow-[4px_4px_0_#1d1d1b]' : 'bg-[#d8d5cd] opacity-65'}`}><div className="mx-auto grid size-16 place-items-center border-[3px] border-[#1d1d1b]" style={{backgroundColor: badge.unlocked ? rarityColors[badge.rarity] ?? '#b4da33' : '#888'}}><span className="font-mono text-xl font-black">{badge.unlocked ? badge.name.slice(0,2).toUpperCase() : '—'}</span></div><h3 className="mt-3 text-center text-sm font-black leading-tight">{badge.name}</h3><p className="mt-1 text-center font-mono text-[10px] uppercase">{badge.unlocked ? badge.rarity : 'Bloqueada'}</p></article>)}</div></section>
    </div>;
}
function Stat({label,value}:{label:string;value:string|number}) { return <div className="border-2 border-[#1d1d1b] bg-white p-3"><p className="font-mono text-[10px] uppercase text-[#68665f]">{label}</p><p className="mt-1 text-2xl font-black">{value}</p></div> }
