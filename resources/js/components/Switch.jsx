import { useId } from 'react';

export default function Switch({ checked, onChange, label, help }) {
    const id = useId();

    return (
        <div className="flex min-h-12 items-start justify-between gap-4">
            <div>
                <label htmlFor={id} className="text-base font-medium text-ink">
                    {label}
                </label>
                {help && <p className="text-sm text-ink-soft">{help}</p>}
            </div>
            <button
                id={id}
                type="button"
                role="switch"
                aria-checked={checked}
                onClick={() => onChange(!checked)}
                className={`relative mt-0.5 h-8 w-14 shrink-0 rounded-full transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                    checked ? 'bg-primary' : 'bg-ink/25'
                }`}
            >
                <span
                    aria-hidden="true"
                    className={`absolute top-1 left-1 size-6 rounded-full bg-white shadow transition-transform ${
                        checked ? 'translate-x-6' : ''
                    }`}
                />
            </button>
        </div>
    );
}
