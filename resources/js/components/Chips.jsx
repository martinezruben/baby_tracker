/** Selección múltiple con chips (varias opciones a la vez). */
export default function Chips({ options, value, onChange, label }) {
    const toggle = (optionValue) =>
        onChange(value.includes(optionValue) ? value.filter((v) => v !== optionValue) : [...value, optionValue]);

    return (
        <div role="group" aria-label={label} className="flex flex-wrap gap-2">
            {options.map((option) => {
                const selected = value.includes(option.value);
                return (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={selected}
                        onClick={() => toggle(option.value)}
                        className={`min-h-12 rounded-full border px-4 text-base font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                            selected
                                ? 'border-primary bg-primary text-on-primary'
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
