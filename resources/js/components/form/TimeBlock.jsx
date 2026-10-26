import { usePage } from '@inertiajs/react';
import { CalendarDays, Check, Clock } from 'lucide-react';
import { useId } from 'react';
import { useT } from '../../lib/i18n';
import useFormat from '../../lib/useFormat';
import { dateOf, parseLocal, shiftLocal, timeOf } from '../../lib/time';

/**
 * "Hora": reloj grande + atajos Ahora / Hace 15 min / Hace 30 min (docs/mockup/Panal.dc.html).
 * La hora y el **día** se cambian por separado (el día permite anotar cosas de días anteriores).
 * `dateOnly`: para lo que solo tiene día (medidas): sin reloj y con atajos Hoy / Ayer / Hace 7 días.
 * `value` y `now` son texto "YYYY-MM-DDTHH:mm" en hora de la familia.
 */
export default function TimeBlock({ label, value, onChange, now, error, shortcuts, dateOnly = false }) {
    const t = useT();
    const { locale } = usePage().props;
    const format = useFormat();
    const display = format.localTime(value);
    const id = useId();

    // "hoy" / "ayer" cuando aplica; otro día solo lleva la fecha (sin repetirla).
    const dayLabel = (() => {
        const today = dateOf(now);
        if (dateOf(value) === today) return t('time.today');
        if (dateOf(value) === dateOf(shiftLocal(now, -1440))) return t('time.yesterday');
        return null;
    })();

    const dayShort = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(parseLocal(value));

    const today = dateOf(now);
    const dayShortcuts = shortcuts ?? (dateOnly ? [0, 1, 7] : [0, 15, 30]);

    // Cambiar el día conserva la hora; nunca se acepta algo en el futuro.
    const onDateChange = (date) => {
        if (!date) return;
        let next = dateOnly ? (date === today ? now : `${date}T12:00`) : `${date}T${timeOf(value)}`;
        if (next > now) next = now;
        onChange(next);
    };

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
            <div className="flex min-h-16 items-stretch gap-2 rounded-[20px] bg-canvas focus-within:outline-2 focus-within:outline-primary">
                {!dateOnly && (
                    <div className="relative flex min-w-0 grow items-center gap-3 pl-[18px]">
                        <Clock aria-hidden="true" className="size-6 shrink-0 text-ink-soft" strokeWidth={1.75} />
                        <div aria-hidden="true" className="flex items-baseline gap-1">
                            <span className="text-[34px] leading-none font-bold whitespace-nowrap">{display.main}</span>
                            {display.suffix && <span className="text-base font-semibold whitespace-nowrap text-ink-soft">{display.suffix}</span>}
                        </div>
                        {/* El selector nativo cubre este lado: tocar la hora abre el reloj del teléfono. */}
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
                )}
                <div className={`relative flex min-h-16 shrink-0 items-center justify-end gap-2 pr-[18px] ${dateOnly ? 'grow pl-[18px]' : 'pl-2'}`}>
                    <CalendarDays aria-hidden="true" className="size-6 shrink-0 text-ink-soft" strokeWidth={1.75} />
                    <span aria-hidden="true" className={dateOnly ? 'mr-auto flex items-baseline gap-2' : 'flex flex-col items-end text-sm leading-tight whitespace-nowrap text-ink-soft'}>
                        {dateOnly ? (
                            <>
                                <span className="text-[28px] leading-none font-bold">{dayLabel ?? dayShort}</span>
                                {dayLabel && <span className="text-base text-ink-soft">{dayShort}</span>}
                            </>
                        ) : (
                            <>
                                {dayLabel && <span className="font-semibold text-ink">{dayLabel}</span>}
                                <span>{dayShort}</span>
                            </>
                        )}
                    </span>
                    <input
                        id={dateOnly ? id : undefined}
                        type="date"
                        value={dateOf(value)}
                        max={today}
                        onChange={(e) => onDateChange(e.target.value)}
                        aria-label={`${t('time.pick_day')}: ${[dayLabel, dayShort].filter(Boolean).join(', ')}`}
                        className="absolute inset-0 size-full cursor-pointer opacity-0"
                    />
                </div>
            </div>
            <div role="group" aria-label={label ?? t('registrar.time')} className="flex gap-2">
                {dayShortcuts.map((amount) => {
                    const target = dateOnly ? shiftLocal(now, -amount * 1440) : shiftLocal(now, -amount);
                    const selected = dateOnly ? dateOf(value) === dateOf(target) : value === target;
                    const minutes = amount;
                    return (
                        <button
                            key={amount}
                            type="button"
                            aria-pressed={selected}
                            onClick={() => onChange(dateOnly ? (amount === 0 ? now : `${dateOf(target)}T12:00`) : target)}
                            className={`flex min-h-12 min-w-0 flex-1 items-center justify-center gap-1.5 rounded-full text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                selected ? 'bg-primary font-semibold text-on-primary' : 'border border-line bg-surface font-medium text-ink'
                            }`}
                        >
                            {selected && <Check aria-hidden="true" className="size-4" strokeWidth={2.5} />}
                            {dateOnly
                                ? amount === 0
                                    ? t('time.today')
                                    : amount === 1
                                      ? t('time.yesterday')
                                      : t('time.days_ago', { n: amount })
                                : minutes === 0
                                  ? t('registrar.now')
                                  : t('registrar.minutes_ago', { m: minutes })}
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
