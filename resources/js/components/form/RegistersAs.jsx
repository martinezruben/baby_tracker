import { usePage } from '@inertiajs/react';
import { useT } from '../../lib/i18n';

/** "Registra Rubén": quién queda como autor del registro. */
export default function RegistersAs() {
    const { auth } = usePage().props;
    const t = useT();
    const name = auth.caretaker.name;

    return (
        <div className="flex items-center gap-2 text-sm text-ink-soft">
            <div className="flex size-[26px] items-center justify-center rounded-full bg-sleep text-xs font-bold text-sleep-ink">
                {name.slice(0, 1).toUpperCase()}
            </div>
            {t('registrar.registers_as', { name })}
        </div>
    );
}
