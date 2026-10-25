import { Link, router, usePage } from '@inertiajs/react';
import { Bell, BookOpen, ChevronLeft, ChevronRight, Heart, Milk, Moon, Pill, Plus, Stethoscope, Syringe, Baby, Star } from 'lucide-react';
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
    overdue: 'bg-red-100 text-red-800',
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
        <section aria-live="polite" className="flex flex-col gap-3 rounded-3xl bg-surface p-4 shadow-card">
            <h2 className="text-[19px] font-bold">{dayLong(date, format.locale)}</h2>

            {items.length === 0 && !hasCounts && <p className="text-[15px] text-ink-soft">{t('calendario.empty')}</p>}

            {items.length > 0 && (
                <ul className="flex flex-col gap-2">
                    {items.map((item) => {
                        const { icon: Icon, kind } = ITEM_STYLES[item.kind];
                        return (
                            <li key={`${item.kind}-${item.id}`}>
                                <Link href={item.href} className="flex items-center gap-3 rounded-2xl bg-canvas p-2 pr-3 focus-visible:outline-2 focus-visible:outline-primary">
                                    <span className={`flex size-11 shrink-0 items-center justify-center rounded-xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                                        <Icon aria-hidden="true" className="size-5" strokeWidth={1.75} />
                                    </span>
                                    <span className="flex min-w-0 grow flex-col">
                                        <span className="text-[15px] font-bold break-words">{item.title}</span>
                                        <span className="text-[13px] text-ink-soft">
                                            {t(`calendario.kinds.${item.kind}`)} · {item.at ? format.time(item.at) : t('calendario.all_day')}
                                        </span>
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
                    <Link href={`/bitacora?dia=${date}`} className="inline-flex min-h-12 items-center gap-2 self-start text-[15px] font-semibold text-primary">
                        <BookOpen aria-hidden="true" className="size-4" />
                        {t('calendario.log_link')}
                    </Link>
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
    const { locale } = usePage().props;
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

    const navButton = 'flex size-12 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary';
    const summary = (day) => (day && (day.items.length || Object.keys(day.counts).length) ? t.choice('calendario.events_count', day.items.length + Object.keys(day.counts).length) : t('calendario.no_events'));

    return (
        <AppLayout title={t('calendario.title')}>
            <div className="flex flex-col gap-4">
                <header className="flex items-center justify-between gap-2">
                    <button type="button" aria-label={t('calendario.prev')} onClick={() => go(shiftMonth(month, -1))} className={navButton}>
                        <ChevronLeft aria-hidden="true" className="size-6" strokeWidth={2} />
                    </button>
                    <div className="flex min-w-0 flex-col items-center">
                        <h1 className="text-xl font-bold">{monthLabel(month, locale)}</h1>
                        {month !== currentMonth && (
                            <button type="button" onClick={() => go(currentMonth)} className="min-h-8 text-sm font-semibold text-primary">
                                {t('calendario.today')}
                            </button>
                        )}
                    </div>
                    <button type="button" aria-label={t('calendario.next')} onClick={() => go(shiftMonth(month, 1))} className={navButton}>
                        <ChevronRight aria-hidden="true" className="size-6" strokeWidth={2} />
                    </button>
                </header>

                <section className="rounded-3xl bg-surface p-3 shadow-card">
                    <div className="grid grid-cols-7 text-center text-xs font-semibold text-ink-soft" aria-hidden="true">
                        {weekdays.map((w, i) => (
                            <span key={i} className="py-1.5 uppercase">
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
                                    className={`mx-auto flex h-14 w-full max-w-12 flex-col items-center justify-center gap-1 rounded-2xl text-[15px] focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary ${
                                        isSelected ? 'bg-primary font-bold text-white' : isToday ? 'font-bold ring-2 ring-primary ring-inset' : 'font-medium hover:bg-canvas'
                                    }`}
                                >
                                    <span>{n}</span>
                                    <span className="flex h-1.5 items-center gap-0.5" aria-hidden="true">
                                        {dots.slice(0, 4).map(({ key, kind }) => (
                                            <span key={key} className={`size-1.5 rounded-full ${KINDS[kind].chip} ${isSelected ? 'ring-1 ring-white' : ''}`} />
                                        ))}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </section>

                {selected ? <DayPanel date={selected} day={days[selected]} today={today} /> : <p className="text-center text-[15px] text-ink-soft">{t('calendario.day_hint')}</p>}

                <details className="rounded-2xl bg-surface px-4 py-2 shadow-card">
                    <summary className="flex min-h-12 cursor-pointer items-center text-[15px] font-semibold">{t('calendario.legend.title')}</summary>
                    <ul className="flex flex-wrap gap-x-4 gap-y-2 pb-2 text-sm">
                        {[['appointment', 'appointment'], ['vaccine', 'vaccine'], ['medicine', 'medicine'], ['life_event', 'moment'], ['feed', 'food'], ['sleep', 'sleep'], ['diaper', 'diaper']].map(([key, kind]) => (
                            <li key={key} className="flex items-center gap-1.5">
                                <span aria-hidden="true" className={`size-2.5 rounded-full ${KINDS[kind].chip}`} />
                                {t(`calendario.legend.${key}`)}
                            </li>
                        ))}
                    </ul>
                </details>
            </div>
        </AppLayout>
    );
}
