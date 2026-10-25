import { Check } from 'lucide-react';

/**
 * Opciones grandes con icono (tipo de pañal): rejilla de 3, 96 px de alto; la elegida lleva borde
 * primario, fondo verde azulado y una marca arriba a la derecha.
 */
export default function OptionGrid({ label, options, value, onChange, columns = 3 }) {
    return (
        <div className="flex flex-col gap-2.5">
            <div className="text-[15px] font-semibold text-ink-soft">{label}</div>
            <div
                role="radiogroup"
                aria-label={label}
                className={`grid gap-2.5 ${columns === 2 ? 'grid-cols-2' : 'grid-cols-3'}`}
            >
                {options.map(({ value: optionValue, label: optionLabel, icon: Icon, iconClass }) => {
                    const selected = optionValue === value;
                    return (
                        <button
                            key={optionValue}
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            onClick={() => onChange(optionValue)}
                            className={`relative flex min-h-24 flex-col items-center justify-center gap-2 rounded-[22px] text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                selected
                                    ? 'border-2 border-primary bg-growth font-bold'
                                    : 'border border-line bg-surface font-semibold'
                            }`}
                        >
                            {selected && (
                                <span className="absolute top-2 right-2 flex size-[22px] items-center justify-center rounded-full bg-primary text-on-primary">
                                    <Check aria-hidden="true" className="size-3.5" strokeWidth={3} />
                                </span>
                            )}
                            <Icon aria-hidden="true" className={`size-7 ${iconClass}`} strokeWidth={1.75} />
                            {optionLabel}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
