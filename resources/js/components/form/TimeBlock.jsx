import { usePage } from '@inertiajs/react';
import { Check, Clock } from 'lucide-react';
import { useId } from 'react';
import { useT } from '../../lib/i18n';
import useFormat from '../../lib/useFormat';
import { dateOf, parseLocal, shiftLocal, timeOf } from '../../lib/time';

/**
 * "Hora": reloj grande + atajos Ahora / Hace 15 min / Hace 30 min (docs/mockup/Panal.dc.html).
 * `value` y `now` son texto "YYYY-MM-DDTHH:mm" en hora de la familia.
 */
export default function TimeBlock({ label, value, onChange, now, error, shortcuts = [0, 15, 30] }) {
    const t = useT();
    const { locale } = usePage().props;
    const format = useFormat();
    const display = format.localTime(value);
    const id = useId();

    const dayLabel = (() => {
        const today = dateOf(now);
        if (dateOf(value) === today) return t('time.today');
        if (dateOf(value) === dateOf(shiftLocal(now, -1440))) return t('time.yesterday');
        return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(parseLocal(value));
    })();

    const dayShort = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(parseLocal(value));

    // Cambiar solo la hora conserva el día; si queda en el futuro, es la de ayer.
    const onTimeChange = (time) => {
        if (!time) return;
        let next = `${dateOf(value)}T${time}`;
        if (next > now) next = shiftLocal(next, -1440);
        onChange(next);
    };

    return (
        <div className="flex flex-col gap-2.5">
            <label htmlFor={id} className="text-[15px] font-semibold text-ink-soft">
                {label ?? t('registrar.time')}
            </label>
            <div className="relative flex min-h-16 items-center gap-3 rounded-[20px] bg-canvas px-[18px] focus-within:outline-2 focus-within:outline-primary">
                <Clock aria-hidden="true" className="size-6 shrink-0 text-ink-soft" strokeWidth={1.75} />
                <div aria-hidden="true" className="flex items-baseline gap-1">
                    <span className="text-[34px] leading-none font-bold">{display.main}</span>
                    {display.suffix && <span className="text-base font-semibold text-ink-soft">{display.suffix}</span>}
                </div>
                <span aria-hidden="true" className="ml-auto text-sm whitespace-nowrap text-ink-soft">
                    {dayLabel}, {dayShort}
                </span>
                {/* El selector nativo cubre todo el bloque: tocar la hora abre el reloj del teléfono. */}
                <input
                    id={id}
                    type="time"
                    value={timeOf(value)}
                    onChange={(e) => onTimeChange(e.target.value)}
                    aria-invalid={error ? 'true' : undefined}
                    aria-label={`${label ?? t('registrar.time')}: ${display.main} ${display.suffix}`.trim()}
                    className="absolute inset-0 size-full cursor-pointer opacity-0"
                />
            </div>
            <div role="group" aria-label={label ?? t('registrar.time')} className="flex gap-2">
                {shortcuts.map((minutes) => {
                    const target = shiftLocal(now, -minutes);
                    const selected = value === target;
                    return (
                        <button
                            key={minutes}
                            type="button"
                            aria-pressed={selected}
                            onClick={() => onChange(target)}
                            className={`flex min-h-12 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-full text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                selected ? 'bg-primary font-semibold text-on-primary' : 'border border-line bg-surface font-medium text-ink'
                            }`}
                        >
                            {selected && <Check aria-hidden="true" className="size-4" strokeWidth={2.5} />}
                            {minutes === 0 ? t('registrar.now') : t('registrar.minutes_ago', { m: minutes })}
                        </button>
                    );
                })}
            </div>
            {error && (
                <p role="alert" className="text-sm font-medium text-danger-ink">
                    {error}
                </p>
            )}
        </div>
    );
}
