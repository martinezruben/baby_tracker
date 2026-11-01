import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronLeft, ChevronRight, Heart, Milk, Moon, Pill, Plus, Stethoscope, Syringe, Baby, Star } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppLayout from '../layouts/AppLayout';
import { KINDS } from '../lib/activities';
import { useT } from '../lib/i18n';
import { monthLabel } from '../lib/lifeEvents';
import useFormat from '../lib/useFormat';

const ITEM_STYLES = {
    appointment: { icon: Stethoscope, kind: 'appointment' },
    reminder: { icon: Bell, kind: 'appointment' },
    vaccine: { icon: Syringe, kind: 'vaccine' },
    dose: { icon: Pill, kind: 'medicine' },
    life_event: { icon: Star, kind: 'moment' },
};

// Orden y color de los puntos de cada día.
const DOTS = [
    ['appointment', 'appointment'],
    ['vaccine', 'vaccine'],
    ['dose', 'medicine'],
    ['reminder', 'appointment'],
    ['life_event', 'moment'],
    ['feed', 'food'],
    ['sleep', 'sleep'],
    ['diaper', 'diaper'],
    ['medicine', 'medicine'],
];

const STATE_STYLES = {
    done: 'bg-vaccine text-vaccine-ink',
    pending: 'bg-appointment text-appointment-ink',
    overdue: 'bg-danger-soft text-danger-ink',
    skipped: 'bg-ink/10 text-ink-soft',
};

const pad = (n) => String(n).padStart(2, '0');

function shiftMonth(month, delta) {
    const [y, m] = month.split('-').map(Number);
    const d = new Date(Date.UTC(y, m - 1 + delta, 1));
    return `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}`;
}

function dotsFor(day) {
    if (!day) return [];
    const kinds = new Set([...day.items.map((i) => i.kind), ...Object.keys(day.counts)]);
    return DOTS.filter(([key]) => kinds.has(key)).map(([key, kind]) => ({ key, kind }));
}

function dayLong(date, locale) {
    const text = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`));
    return text.charAt(0).toUpperCase() + text.slice(1);
}

function DayPanel({ date, day, today }) {
    const t = useT();
    const format = useFormat();
    const items = day?.items ?? [];
    const counts = day?.counts ?? {};
    const hasCounts = Object.keys(counts).length > 0;
    const COUNT_ICONS = { feed: Milk, sleep: Moon, diaper: Baby, medicine: Pill };

    return (
        <section aria-live="polite" className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between">
                <h2 className="text-xl font-semibold">{dayLong(date, format.locale)}</h2>
                {hasCounts && (
                    <Link href={`/bitacora?dia=${date}`} className="flex min-h-11 shrink-0 items-center text-[15px] font-semibold whitespace-nowrap text-primary">
                        {t('calendario.log_link')}
                    </Link>
                )}
            </div>

            {items.length === 0 && !hasCounts && <p className="text-[15px] text-ink-soft">{t('calendario.empty')}</p>}

            {items.length > 0 && (
                <ul className="flex flex-col gap-2">
                    {items.map((item) => {
                        const { icon: Icon, kind } = ITEM_STYLES[item.kind];
                        return (
                            <li key={`${item.kind}-${item.id}`}>
                                <Link href={item.href} className="flex min-h-[68px] items-center gap-3.5 rounded-3xl border border-line bg-surface px-4 focus-visible:outline-2 focus-visible:outline-primary">
                                    <span className="w-[68px] shrink-0 text-sm whitespace-nowrap text-ink-soft">{item.at ? format.time(item.at) : t('calendario.all_day')}</span>
                                    <span aria-hidden="true" className={`w-1 shrink-0 self-stretch rounded-full ${KINDS[kind].chip} my-3.5`} />
                                    <Icon aria-hidden="true" className={`size-5 shrink-0 ${KINDS[kind].ink}`} strokeWidth={1.75} />
                                    <span className="flex min-w-0 grow flex-col">
                                        <span className="text-[15px] font-bold break-words">{item.title}</span>
                                        <span className="text-[13px] text-ink-soft">{t(`calendario.kinds.${item.kind}`)}</span>
                                    </span>
                                    <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ${STATE_STYLES[item.state]}`}>{t(`calendario.states.${item.state}`)}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}

            {hasCounts && (
                <div className="flex flex-col gap-2">
                    <h3 className="text-[15px] font-semibold text-ink-soft">{t('calendario.activity')}</h3>
                    <ul className="flex flex-wrap gap-2">
                        {Object.entries(counts).map(([type, n]) => {
                            const Icon = COUNT_ICONS[type];
                            const kind = DOTS.find(([key]) => key === type)[1];
                            return (
                                <li key={type} className={`flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                                    <Icon aria-hidden="true" className="size-4" strokeWidth={1.75} />
                                    {t.choice(`calendario.counts.${type}`, n)}
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}

            {date >= today && (
                <div className="flex flex-wrap gap-2">
                    <Link href="/citas/nueva" className="inline-flex min-h-12 items-center gap-2 rounded-full border border-line px-4 text-[15px] font-semibold focus-visible:outline-2 focus-visible:outline-primary">
                        <Plus aria-hidden="true" className="size-4" />
                        {t('calendario.new_appointment')}
                    </Link>
                    <Link href="/recordatorios/nuevo" className="inline-flex min-h-12 items-center gap-2 rounded-full border border-line px-4 text-[15px] font-semibold focus-visible:outline-2 focus-visible:outline-primary">
                        <Plus aria-hidden="true" className="size-4" />
                        {t('calendario.new_reminder')}
                    </Link>
                </div>
            )}
        </section>
    );
}

export default function Calendario({ today, data }) {
    const t = useT();
    const { locale, babies, activeBabyId } = usePage().props;
    const baby = babies.find((b) => b.id === activeBabyId);
    const { month, days } = data;
    const currentMonth = today.slice(0, 7);
    const [selected, setSelected] = useState(month === currentMonth ? today : null);

    // Al cambiar de mes se elige hoy (si es el mes actual) o se limpia la selección.
    useEffect(() => {
        setSelected(month === currentMonth ? today : null);
    }, [month]);

    const go = (target) => router.get('/calendario', target === currentMonth ? {} : { mes: target }, { only: ['data', 'today'], preserveState: true, preserveScroll: true, replace: true });

    const firstDayOfWeek = /^(es|en)/.test(locale) ? 0 : 1;
    const [y, m] = month.split('-').map(Number);
    const daysInMonth = new Date(Date.UTC(y, m, 0)).getUTCDate();
    const offset = (new Date(Date.UTC(y, m - 1, 1)).getUTCDay() - firstDayOfWeek + 7) % 7;
    const weekdays = Array.from({ length: 7 }, (_, i) => {
        const d = new Date(Date.UTC(2026, 10, 1 + ((firstDayOfWeek + i) % 7))); // 1 nov 2026 es domingo
        return { narrow: new Intl.DateTimeFormat(locale, { weekday: 'narrow', timeZone: 'UTC' }).format(d), full: new Intl.DateTimeFormat(locale, { weekday: 'long', timeZone: 'UTC' }).format(d) };
    });

    const cells = [...Array(offset).fill(null), ...Array.from({ length: daysInMonth }, (_, i) => i + 1)];

    const navButton = 'flex size-12 shrink-0 items-center justify-center rounded-full border border-line bg-surface text-ink focus-visible:outline-2 focus-visible:outline-primary';
    const summary = (day) => (day && (day.items.length || Object.keys(day.counts).length) ? t.choice('calendario.events_count', day.items.length + Object.keys(day.counts).length) : t('calendario.no_events'));

    return (
        <AppLayout title={t('calendario.title')}>
            <div className="flex flex-col gap-4">
                <header className="flex items-center gap-2.5">
                    <div className="flex min-w-0 grow flex-col">
                        {baby && <span className="text-sm text-ink-soft">{t('calendario.of', { name: baby.first_name })}</span>}
                        <h1 className="text-[34px] leading-tight font-semibold tracking-tight" aria-label={monthLabel(month, locale)}>
                            {new Intl.DateTimeFormat(locale, { month: 'long', timeZone: 'UTC' }).format(new Date(`${month}-01T12:00:00Z`)).replace(/^./, (c) => c.toUpperCase())} <span className="font-normal text-ink-soft">{y}</span>
                        </h1>
                        {month !== currentMonth && (
                            <button type="button" onClick={() => go(currentMonth)} className="min-h-8 self-start text-sm font-semibold text-primary">
                                {t('calendario.today')}
                            </button>
                        )}
                    </div>
                    <button type="button" aria-label={t('calendario.prev')} onClick={() => go(shiftMonth(month, -1))} className={navButton}>
                        <ChevronLeft aria-hidden="true" className="size-6" strokeWidth={2} />
                    </button>
                    <button type="button" aria-label={t('calendario.next')} onClick={() => go(shiftMonth(month, 1))} className={navButton}>
                        <ChevronRight aria-hidden="true" className="size-6" strokeWidth={2} />
                    </button>
                </header>

                <section className="flex flex-col gap-1.5 rounded-[32px] border border-line bg-surface px-3 pt-4 pb-3">
                    <div className="grid grid-cols-7 text-center text-xs font-semibold text-ink-soft" aria-hidden="true">
                        {weekdays.map((w, i) => (
                            <span key={i} className="py-1.5 font-medium uppercase">
                                {w.narrow}
                            </span>
                        ))}
                    </div>
                    <div role="grid" aria-label={monthLabel(month, locale)} className="grid grid-cols-7 gap-y-1">
                        {cells.map((n, i) => {
                            if (n === null) return <span key={`b${i}`} />;
                            const date = `${month}-${pad(n)}`;
                            const day = days[date];
                            const isSelected = selected === date;
                            const isToday = date === today;
                            const dots = dotsFor(day);

                            return (
                                <button
                                    key={date}
                                    type="button"
                                    role="gridcell"
                                    aria-selected={isSelected}
                                    aria-current={isToday ? 'date' : undefined}
                                    aria-label={`${dayLong(date, locale)}, ${summary(day)}`}
                                    onClick={() => setSelected(date)}
                                    className="mx-auto flex min-h-[52px] w-full flex-col items-center gap-[3px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary"
                                >
                                    <span
                                        className={`flex size-10 items-center justify-center rounded-full text-base ${
                                            isToday && isSelected
                                                ? 'bg-primary font-semibold text-on-primary'
                                                : isSelected
                                                  ? 'border-2 border-primary font-semibold'
                                                  : isToday
                                                    ? 'bg-primary font-semibold text-on-primary'
                                                    : 'font-normal'
                                        }`}
                                    >
                                        {n}
                                    </span>
                                    <span className="flex h-1.5 items-center gap-0.5" aria-hidden="true">
                                        {dots.slice(0, 4).map(({ key, kind }) => (
                                            <span key={key} className={`size-[5px] rounded-full ${KINDS[kind].chip}`} />
                                        ))}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    <ul className="flex flex-wrap justify-center gap-x-4 gap-y-1.5 pt-2 pb-1 text-[13px] text-ink-soft">
                        {[['appointment', 'appointment'], ['vaccine', 'vaccine'], ['medicine', 'medicine'], ['life_event', 'moment'], ['feed', 'food'], ['sleep', 'sleep'], ['diaper', 'diaper']].map(([key, kind]) => (
                            <li key={key} className="flex items-center gap-1.5">
                                <span aria-hidden="true" className={`size-2 rounded-full ${KINDS[kind].chip}`} />
                                {t(`calendario.legend.${key}`)}
                            </li>
                        ))}
                    </ul>
                </section>

                {selected ? <DayPanel date={selected} day={days[selected]} today={today} /> : <p className="text-center text-[15px] text-ink-soft">{t('calendario.day_hint')}</p>}


            </div>
        </AppLayout>
    );
}
