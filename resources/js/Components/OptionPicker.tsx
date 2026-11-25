import {
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from '@headlessui/react';

export type PickerOption = {
    value: string;
    label: string;
    /** Linha de apoio (ex.: descrição da conquista). */
    description?: string;
    /** Etiqueta curta à direita (ex.: raridade). */
    tag?: string;
    /** Cor do marcador à esquerda. */
    accent?: string;
};

/**
 * Seletor do perfil (selo, título, conquista): no lugar do `<select>` padrão do navegador,
 * um popup no estilo do Estação Commit. Mostra a escolha atual, destaca o item selecionado
 * (barra + ✓), responde ao teclado (setas, Enter, Esc) e mantém todas as opções.
 */
export default function OptionPicker({
    label,
    hint,
    value,
    options,
    onChange,
}: {
    label: string;
    hint?: string;
    value: string;
    options: PickerOption[];
    onChange: (value: string) => void;
}) {
    const current = options.find((option) => option.value === value) ?? options[0];

    return (
        <Listbox onChange={onChange} value={current?.value ?? ''}>
            <div className="relative">
                <Listbox.Label className="block text-sm font-bold">
                    {label}
                </Listbox.Label>
                {hint ? (
                    <p className="mt-0.5 text-xs text-[#68665f]">{hint}</p>
                ) : null}

                <ListboxButton className="group mt-2 flex min-h-12 w-full items-center gap-3 border-2 border-[#1d1d1b] bg-white px-3 py-2 text-left transition hover:bg-[#f3f1ec] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#464498] focus-visible:ring-offset-2 data-[open]:shadow-[3px_3px_0_#b4da33]">
                    <span
                        aria-hidden="true"
                        className="h-6 w-1.5 shrink-0"
                        style={{ background: current?.accent ?? '#1d1d1b' }}
                    />
                    <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-black">
                            {current?.label}
                        </span>
                        {current?.description ? (
                            <span className="block truncate text-xs text-[#68665f]">
                                {current.description}
                            </span>
                        ) : null}
                    </span>
                    {current?.tag ? (
                        <span className="shrink-0 border border-[#1d1d1b]/40 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-[#5b5953]">
                            {current.tag}
                        </span>
                    ) : null}
                    <svg
                        aria-hidden="true"
                        className="size-4 shrink-0 text-[#5b5953] transition group-data-[open]:rotate-180"
                        fill="none"
                        stroke="currentColor"
                        strokeLinecap="square"
                        strokeWidth="2.5"
                        viewBox="0 0 24 24"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </ListboxButton>

                <ListboxOptions
                    className="absolute left-0 right-0 top-full z-50 mt-1.5 max-h-72 overflow-y-auto border-2 border-[#1d1d1b] bg-white p-1 shadow-[4px_4px_0_#1d1d1b] focus:outline-none"
                >
                    {options.map((option) => (
                        <ListboxOption
                            className="group relative flex cursor-pointer select-none items-center gap-3 border-l-4 border-transparent px-3 py-2.5 text-left data-[focus]:bg-[#f3f1ec] data-[selected]:border-[#b4da33] data-[selected]:bg-[#f6f9e4]"
                            key={option.value || '__none'}
                            value={option.value}
                        >
                            <span
                                aria-hidden="true"
                                className="size-3 shrink-0 border border-[#1d1d1b]"
                                style={{ background: option.accent ?? '#ffffff' }}
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-bold group-data-[selected]:font-black">
                                    {option.label}
                                </span>
                                {option.description ? (
                                    <span className="mt-0.5 block text-xs leading-5 text-[#5b5953]">
                                        {option.description}
                                    </span>
                                ) : null}
                            </span>
                            {option.tag ? (
                                <span className="shrink-0 font-mono text-[10px] font-bold uppercase text-[#68665f]">
                                    {option.tag}
                                </span>
                            ) : null}
                            <span
                                aria-hidden="true"
                                className="invisible grid size-5 shrink-0 place-items-center bg-[#b4da33] text-[11px] font-black group-data-[selected]:visible"
                            >
                                ✓
                            </span>
                        </ListboxOption>
                    ))}
                </ListboxOptions>
            </div>
        </Listbox>
    );
}
