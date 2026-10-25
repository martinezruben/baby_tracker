import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import { useT } from '../../lib/i18n';

/** Campos opcionales colapsados bajo "Más detalles" para que el formulario básico quepa sin scroll. */
export default function MoreDetails({ hint, children, defaultOpen = false }) {
    const t = useT();
    const id = useId();
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className="flex flex-col gap-4">
            <button
                type="button"
                aria-expanded={open}
                aria-controls={id}
                onClick={() => setOpen(!open)}
                className="flex min-h-[60px] items-center justify-between rounded-[20px] bg-canvas px-[18px] text-left text-base font-semibold focus-visible:outline-2 focus-visible:outline-primary"
            >
                <span>
                    {t('registrar.more_details')}
                    {hint && <span className="text-sm font-normal text-ink-soft"> · {hint}</span>}
                </span>
                <ChevronDown aria-hidden="true" className={`size-[22px] text-ink-soft transition-transform ${open ? 'rotate-180' : ''}`} strokeWidth={2} />
            </button>
            {open && (
                <div id={id} className="flex flex-col gap-4">
                    {children}
                </div>
            )}
        </div>
    );
}
