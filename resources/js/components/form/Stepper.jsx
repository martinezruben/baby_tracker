import { Minus, Plus } from 'lucide-react';
import { useId } from 'react';

/** Cantidad con botones − / + de 48 px y teclado numérico (`inputmode="decimal"`). */
export default function Stepper({ label, value, onChange, step = 10, min = 0, max = 1000, unit, error }) {
    const id = useId();
    const number = Number(value) || 0;
    const clamp = (n) => Math.min(max, Math.max(min, Math.round(n * 100) / 100));

    const button = 'flex size-12 shrink-0 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary disabled:opacity-40';

    return (
        <div className="flex flex-col gap-2.5">
            <label htmlFor={id} className="text-[15px] font-semibold text-ink-soft">
                {label}
            </label>
            <div className="flex items-center justify-between gap-3 rounded-[20px] bg-canvas p-2">
                <button type="button" aria-label="−" className={button} disabled={number <= min} onClick={() => onChange(clamp(number - step))}>
                    <Minus aria-hidden="true" className="size-5" strokeWidth={2} />
                </button>
                <div className="flex min-w-0 items-baseline justify-center gap-1.5">
                    <input
                        id={id}
                        type="text"
                        inputMode="decimal"
                        value={value}
                        onChange={(e) => onChange(e.target.value.replace(',', '.'))}
                        aria-invalid={error ? 'true' : undefined}
                        className="w-24 bg-transparent text-center text-[34px] leading-none font-bold focus-visible:outline-2 focus-visible:outline-primary"
                    />
                    {unit && <span className="text-base text-ink-soft">{unit}</span>}
                </div>
                <button type="button" aria-label="+" className={button} disabled={number >= max} onClick={() => onChange(clamp(number + step))}>
                    <Plus aria-hidden="true" className="size-5" strokeWidth={2} />
                </button>
            </div>
            {error && (
                <p role="alert" className="text-sm font-medium text-danger-ink">
                    {error}
                </p>
            )}
        </div>
    );
}
