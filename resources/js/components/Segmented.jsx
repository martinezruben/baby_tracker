/** Botones segmentados para pocas opciones (≤ 5), en lugar de listas desplegables. */
export default function Segmented({ options, value, onChange, label }) {
    return (
        <div role="radiogroup" aria-label={label} className="flex flex-wrap gap-2">
            {options.map((option) => {
                const selected = option.value === value;
                return (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => onChange(option.value)}
                        className={`min-h-12 rounded-full border px-5 text-base font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                            selected
                                ? 'border-primary bg-primary text-white'
                                : 'border-ink/20 bg-surface text-ink hover:bg-ink/5'
                        }`}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
