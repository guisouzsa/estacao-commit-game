import PlayerAvatar from '@/Components/PlayerAvatar';
import { AVATAR_STYLE_LABELS, type AvatarStyle, randomSeed } from '@/lib/avatarArt';
import { useState } from 'react';

const OPTIONS = 8;

/**
 * Escolha de ícone gerado: estilo + um entre vários ícones sorteados.
 * Usado na entrada do visitante e no editor de perfil das contas.
 *
 * `seed` pode ser null (nenhum ícone escolhido ainda, ex.: a conta usa uma foto).
 */
export default function AvatarPicker({
    styles,
    style,
    seed,
    previewName,
    onChange,
}: {
    styles: readonly string[];
    style: string;
    seed: string | null;
    previewName: string;
    onChange: (style: string, seed: string) => void;
}) {
    // O ícone atual entra na lista, para continuar visível e selecionado.
    const [seeds, setSeeds] = useState<string[]>(() => [
        ...(seed ? [seed] : []),
        ...Array.from({ length: OPTIONS - (seed ? 1 : 0) }, () => randomSeed()),
    ]);

    const reroll = () => {
        const next = Array.from({ length: OPTIONS }, () => randomSeed());
        setSeeds(next);
        onChange(style, next[0]);
    };

    return (
        <div>
            <fieldset>
                <legend className="auth-label">Estilo do ícone</legend>
                <div className="mt-2 flex flex-wrap gap-2">
                    {styles.map((option) => (
                        <button
                            key={option}
                            type="button"
                            aria-pressed={style === option}
                            onClick={() => onChange(option, seed ?? seeds[0])}
                            className={`min-h-10 border-2 border-[#1d1d1b] px-3 font-mono text-[11px] font-black uppercase focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498] ${
                                style === option ? 'bg-[#b4da33]' : 'bg-white'
                            }`}
                        >
                            {AVATAR_STYLE_LABELS[option as AvatarStyle] ?? option}
                        </button>
                    ))}
                </div>
            </fieldset>

            <fieldset className="mt-4">
                <legend className="auth-label">Escolha seu ícone</legend>
                <div
                    className="mt-2 grid grid-cols-4 gap-3"
                    role="radiogroup"
                    aria-label="Ícones disponíveis"
                >
                    {seeds.map((option) => (
                        <button
                            key={option}
                            type="button"
                            role="radio"
                            aria-checked={seed === option}
                            aria-label="Ícone"
                            onClick={() => onChange(style, option)}
                            className={`grid place-items-center p-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#464498] ${
                                seed === option
                                    ? 'bg-[#b4da33]'
                                    : 'bg-transparent hover:bg-black/5'
                            }`}
                        >
                            <PlayerAvatar
                                profile={{
                                    username: previewName,
                                    avatar_style: style,
                                    avatar_seed: option,
                                }}
                                size="sm"
                            />
                        </button>
                    ))}
                </div>
                <button
                    type="button"
                    onClick={reroll}
                    className="mt-3 font-mono text-xs font-bold uppercase text-purple-700 underline decoration-2 underline-offset-4"
                >
                    Sortear outros ícones
                </button>
            </fieldset>
        </div>
    );
}
