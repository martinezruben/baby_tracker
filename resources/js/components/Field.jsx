import { useId } from 'react';

export default function Field({ label, error, hint, optional, children, ...inputProps }) {
    const id = useId();
    const describedBy = error ? `${id}-error` : hint ? `${id}-hint` : undefined;

    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className="text-sm font-medium text-ink">
                {label}
                {optional && <span className="font-normal text-ink-soft"> · {optional}</span>}
            </label>
            {children ? (
                children({ id, describedBy })
            ) : (
                <input
                    id={id}
                    aria-invalid={error ? 'true' : undefined}
                    aria-describedby={describedBy}
                    className="min-h-12 rounded-2xl border border-ink/20 bg-surface px-4 text-base text-ink focus-visible:outline-2 focus-visible:outline-primary"
                    {...inputProps}
                />
            )}
            {hint && !error && (
                <p id={`${id}-hint`} className="text-sm text-ink-soft">
                    {hint}
                </p>
            )}
            {error && (
                <p id={`${id}-error`} role="alert" className="text-sm font-medium text-danger-ink">
                    {error}
                </p>
            )}
        </div>
    );
}
