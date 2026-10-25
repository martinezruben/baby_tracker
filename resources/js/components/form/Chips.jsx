/** Elección única en chips (hasta 5 opciones): sustituye a las listas desplegables. */
/** `columns`: ordena las opciones en una rejilla de ese número de columnas (cantidades rápidas). */
export default function ChoiceChips({ label, options, value, onChange, columns }) {
    return (
        <div className="flex flex-col gap-2.5">
            {label && <div className="text-[15px] font-semibold text-ink-soft">{label}</div>}
            <div role="radiogroup" aria-label={label} className={columns ? 'grid gap-2' : 'flex flex-wrap gap-2'} style={columns ? { gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` } : undefined}>
                {options.map((option) => {
                    const selected = option.value === value;
                    return (
                        <button
                            key={option.value}
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            onClick={() => onChange(option.value)}
                            className={`min-h-12 rounded-full ${columns ? 'px-1' : 'px-5'} text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                selected ? 'bg-primary font-semibold text-on-primary' : 'border border-line bg-surface font-medium text-ink'
                            }`}
                        >
                            {option.label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
