import { Link, router, usePage } from '@inertiajs/react';
import { Baby, Bell, ChevronDown, ChevronRight, Droplet, Milk, Moon, MoonStar, Pill, Plus, Ruler, Stethoscope, Syringe } from 'lucide-react';
import ActiveSessions from '../components/ActiveSessions';
import useTicker from '../components/useTicker';
import AppLayout from '../layouts/AppLayout';
import { KINDS } from '../lib/activities';
import { ageLabel, agoLabel, durationLabel, useT } from '../lib/i18n';
import useFormat from '../lib/useFormat';
import { calendarDaysFrom, longDate, minutesSince } from '../lib/time';

const card = 'rounded-3xl bg-surface shadow-card';

function SectionTitle({ children, href, linkLabel }) {
    return (
        <div className="flex items-center justify-between">
            <h2 className="text-[19px] font-bold">{children}</h2>
            {href && (
                <Link href={href} className="flex min-h-11 items-center gap-0.5 text-[15px] font-semibold text-primary hover:text-primary-dark">
                    {linkLabel}
                    <ChevronRight aria-hidden="true" className="size-[18px]" strokeWidth={2} />
                </Link>
            )}
        </div>
    );
}

function Header({ baby, clock, babies, activeBabyId, hasAlerts }) {
    const t = useT();
    const { locale } = usePage().props;
    const now = new Date(useTicker(30000));

    return (
        <header className="flex min-h-14 items-center gap-3">
            <div aria-hidden="true" className="relative flex size-[52px] shrink-0 items-center justify-center rounded-full bg-diaper text-xl font-bold text-diaper-ink">
                {baby.first_name.slice(0, 1).toUpperCase()}
                <span className="absolute right-0 bottom-0 size-3.5 rounded-full border-2 border-canvas bg-live" />
            </div>
            <div className="flex min-w-0 grow flex-col">
                <div className="text-[13px] leading-tight text-ink-soft">
                    {longDate(now, locale, clock.timezone)} · {ageLabel(t, baby.birth_date, now)}
                </div>
                {babies.length > 1 ? (
                    <div className="relative flex items-center gap-1 text-2xl leading-tight font-bold">
                        <select
                            aria-label={t('common.select_baby')}
                            value={activeBabyId}
                            onChange={(e) => router.post(`/bebes/${e.target.value}/seleccionar`)}
                            className="max-w-full appearance-none bg-transparent pr-6 text-2xl font-bold focus-visible:outline-2 focus-visible:outline-primary"
                        >
                            {babies.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.first_name}
                                </option>
                            ))}
                        </select>
                        <ChevronDown aria-hidden="true" className="pointer-events-none absolute right-0 size-5" strokeWidth={2} />
                    </div>
                ) : (
                    <div className="truncate text-2xl leading-tight font-bold">{baby.first_name}</div>
                )}
            </div>
            <Link
                href="/nursery"
                aria-label={t('nursery.open')}
                className="flex size-12 shrink-0 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary"
            >
                <MoonStar aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
            </Link>
            <Link
                href="/recordatorios"
                aria-label={hasAlerts ? t('home.reminders_pending') : t('home.reminders')}
                className="relative flex size-12 shrink-0 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary"
            >
                <Bell aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
                {hasAlerts && <span aria-hidden="true" className="absolute top-[11px] right-3 size-[9px] rounded-full border-2 border-surface bg-danger" />}
            </Link>
        </header>
    );
}

function minutesOf(iso) {
    return minutesSince(iso);
}

/** "Desde la última vez": última toma, último pañal y último sueño de un vistazo. */
function SinceLast({ baby, last, clock }) {
    const t = useT();
    const format = useFormat();
    useTicker(30000);

    const big = (iso) => durationLabel(t, minutesOf(iso));
    const feed = last.feed;
    const feedDetail = feed
        ? [t(`home.feed_type.${feed.type}`), feed.amount ? `${String(feed.amount).replace('.', ',')} ${feed.unit}` : null, format.time(feed.at)].filter(Boolean).join(' · ')
        : t('home.no_record_hint');

    return (
        <section className="flex flex-col gap-3">
            <div className="flex items-baseline justify-between">
                <h2 className="text-[19px] font-bold">{t('home.since_last')}</h2>
                <div className="text-xs text-ink-soft">{t('home.updated', { time: `${format.localTime(clock.now).main} ${format.localTime(clock.now).suffix}`.trim() })}</div>
            </div>

            <div className="flex items-center gap-3 rounded-[28px] bg-food p-4 pl-[18px]">
                <span aria-hidden="true" className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-surface text-food-ink">
                    <Milk className="size-6" strokeWidth={1.75} />
                </span>
                <div className="flex min-w-0 grow flex-col">
                    <div className="text-xs font-semibold text-food-ink">{t('home.last_feed')}</div>
                    <div className="text-[32px] leading-[1.1] font-bold">{feed ? big(feed.at) : t('home.no_record')}</div>
                    <div className="truncate text-xs text-ink-mid">{feedDetail}</div>
                </div>
                <Link
                    href="/registrar/biberon"
                    aria-label={t('home.log_feed')}
                    className="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-food-ink text-on-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    <Plus aria-hidden="true" className="size-6" strokeWidth={2.25} />
                </Link>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <Link href="/registrar/panal" className="flex min-h-[104px] flex-col gap-1 rounded-3xl bg-diaper p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <span className="flex items-center justify-between text-xs font-semibold text-diaper-ink">
                        {t('home.last_diaper')}
                        <Droplet aria-hidden="true" className="size-[18px]" strokeWidth={1.75} />
                    </span>
                    <span className="text-xl leading-tight font-bold">{last.diaper ? big(last.diaper.at) : t('home.no_record')}</span>
                    <span className="text-xs text-ink-mid">{last.diaper ? `${t(`registrar.diaper.${last.diaper.type}`)} · ${agoLabel(t, minutesOf(last.diaper.at))}` : t('home.no_record_hint')}</span>
                </Link>
                <Link href="/registrar/sueno" className="flex min-h-[104px] flex-col gap-1 rounded-3xl bg-sleep p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <span className="flex items-center justify-between text-xs font-semibold text-sleep-ink">
                        {t('home.last_sleep')}
                        <Moon aria-hidden="true" className="size-[18px]" strokeWidth={1.75} />
                    </span>
                    <span className="text-xl leading-tight font-bold">{last.sleep ? big(last.sleep.at) : t('home.no_record')}</span>
                    <span className="text-xs text-ink-mid">{last.sleep ? t('home.sleep_of', { duration: durationLabel(t, last.sleep.minutes) }) : t('home.no_record_hint')}</span>
                </Link>
            </div>
        </section>
    );
}

const QUICK = [
    { key: 'biberon', icon: Milk, kind: 'food', href: '/registrar/biberon' },
    { key: 'sueno', icon: Moon, kind: 'sleep', href: '/registrar/sueno' },
    { key: 'panal', icon: Baby, kind: 'diaper', href: '/registrar/panal' },
    { key: 'medida', icon: Ruler, kind: 'growth', href: '/registrar/medida' },
];

function QuickLog() {
    const t = useT();

    return (
        <section className="flex flex-col gap-3">
            <SectionTitle href="/registrar" linkLabel={t('home.all')}>
                {t('home.quick')}
            </SectionTitle>
            <div className="grid grid-cols-4 gap-2.5">
                {QUICK.map(({ key, icon: Icon, kind, href }) => (
                    <Link
                        key={key}
                        href={href}
                        className="flex flex-col items-center gap-2 rounded-[22px] bg-surface px-1 pt-3.5 pb-3 text-[13px] font-semibold shadow-card focus-visible:outline-2 focus-visible:outline-primary"
                    >
                        <span className={`flex size-[46px] items-center justify-center rounded-2xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                            <Icon aria-hidden="true" className="size-[24px]" strokeWidth={1.75} />
                        </span>
                        {t(`home.quick_${key}`)}
                    </Link>
                ))}
            </div>
        </section>
    );
}

const MARK_COLORS = { feed: 'bg-chart-food', sleep: 'bg-chart-sleep', diaper: 'bg-chart-diaper', medicine: 'bg-chart-medicine' };

/** Línea del día (0–24 h) con una marca por registro y la hora actual. */
function DayLine({ marks, clock }) {
    const t = useT();
    const nowMinute = Number(clock.now.slice(11, 13)) * 60 + Number(clock.now.slice(14, 16));
    const hours = ['0', '6', '12', '18'];

    return (
        <div className="flex flex-col gap-1.5">
            <div role="img" aria-label={t('home.timeline_label', { n: marks.length })} className="relative h-7 rounded-full bg-track">
                {marks.map((m, i) => (
                    <span
                        key={i}
                        aria-hidden="true"
                        className={`absolute top-1.5 h-4 w-1 -translate-x-1/2 rounded-full ${MARK_COLORS[m.kind]}`}
                        style={{ left: `${(m.minute / 1440) * 100}%` }}
                    />
                ))}
                <span aria-hidden="true" className="absolute inset-y-0 w-0.5 -translate-x-1/2 bg-primary" style={{ left: `${(nowMinute / 1440) * 100}%` }} />
            </div>
            <div aria-hidden="true" className="flex justify-between text-[11px] text-ink-soft">
                {hours.map((h) => (
                    <span key={h}>{t(`home.hour_${h}`)}</span>
                ))}
                <span>{t('home.now')}</span>
            </div>
        </div>
    );
}

const COUNTS = [
    ['feed', 'home.summary.feed', 'bg-chart-food'],
    ['sleep', 'home.summary.sleep', 'bg-chart-sleep'],
    ['diaper', 'home.summary.diaper', 'bg-chart-diaper'],
    ['medicine', 'home.summary.medicine', 'bg-chart-medicine'],
    ['play', 'home.summary.play', 'bg-chart-play'],
];

function Summary({ summary, marks, clock }) {
    const t = useT();

    const value = {
        feed: summary.feed.amount > 0 ? `${summary.feed.count} · ${String(summary.feed.amount).replace('.', ',')} ${summary.feed.unit}` : summary.feed.count,
        sleep: summary.sleep.minutes > 0 ? durationLabel(t, summary.sleep.minutes) : '0',
        diaper: summary.diaper,
        medicine: summary.medicine,
        play: summary.play,
    };

    const setRange = (rango) => router.get('/', { rango }, { only: ['summary'], preserveState: true, preserveScroll: true, replace: true });

    return (
        <section className={`${card} flex flex-col gap-3.5 p-[18px]`}>
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-[19px] font-bold">{t('home.summary.today')}</h2>
                <div role="group" aria-label={t('home.summary.title')} className="flex rounded-full bg-canvas p-1">
                    {['hoy', 'semana', 'mes'].map((rango) => {
                        const selected = summary.range === rango;
                        return (
                            <button
                                key={rango}
                                type="button"
                                aria-pressed={selected}
                                onClick={() => setRange(rango)}
                                className={`min-h-10 rounded-full px-3 text-[13px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                    selected ? 'bg-surface font-bold text-ink shadow-soft' : 'font-medium text-ink-soft'
                                }`}
                            >
                                {t(`home.summary.range_${rango}`)}
                            </button>
                        );
                    })}
                </div>
            </div>

            {summary.range === 'hoy' && <DayLine marks={marks} clock={clock} />}

            <ul className="grid grid-cols-2 gap-2">
                {COUNTS.map(([key, label, dot]) => (
                    <li key={key} className="flex min-h-12 items-center gap-2 rounded-2xl bg-canvas px-3 text-sm">
                        <span aria-hidden="true" className={`size-2.5 shrink-0 rounded-full ${dot}`} />
                        <span className="grow text-ink-mid">{t(label)}</span>
                        <span className="font-bold">{value[key]}</span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

const GROWTH_TILES = [
    ['weight', 'bg-diaper', 'text-diaper-ink'],
    ['height', 'bg-vaccine', 'text-vaccine-ink'],
    ['head_circumference', 'bg-vaccine', 'text-vaccine-ink'],
];

function Growth({ growth }) {
    const t = useT();
    const by = Object.fromEntries(growth.map((g) => [g.type, g]));

    return (
        <section className={`${card} flex flex-col gap-3 p-[18px]`}>
            <SectionTitle href="/crecimiento" linkLabel={t('home.see_curve')}>
                {t('home.growth')}
            </SectionTitle>
            <ul className="grid grid-cols-3 gap-2">
                {GROWTH_TILES.map(([type, bg, ink]) => (
                    <li key={type} className={`flex flex-col gap-0.5 rounded-2xl p-3 ${bg}`}>
                        <span className={`text-xs font-semibold ${ink}`}>{t(`home.growth_${type}`)}</span>
                        <span className="text-xl font-bold">
                            {by[type] ? String(by[type].value).replace('.', ',') : '—'}
                            {by[type] && <span className="ml-0.5 text-xs font-medium text-ink-mid">{by[type].unit}</span>}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

const EVENT_STYLES = {
    reminder: { kind: 'appointment', icon: Bell },
    appointment: { kind: 'appointment', icon: Stethoscope },
    dose: { kind: 'medicine', icon: Pill },
    vaccine: { kind: 'vaccine', icon: Syringe },
};

function Upcoming({ upcoming }) {
    const t = useT();
    const { clock } = usePage().props;
    const format = useFormat();

    const when = (at) => {
        const days = calendarDaysFrom(at, clock.now, clock.timezone);
        const word = days === 0 ? t('time.today') : days === 1 ? t('time.tomorrow') : format.date(at);
        const day = word.charAt(0).toUpperCase() + word.slice(1);
        return `${day} · ${format.time(at)}`;
    };

    return (
        <section className="flex flex-col gap-3">
            <SectionTitle href="/recordatorios" linkLabel={t('home.reminders')}>
                {t('home.upcoming')}
            </SectionTitle>
            {upcoming.length === 0 ? (
                <div className="flex items-center gap-3 rounded-3xl border-2 border-dashed border-line p-4">
                    <span aria-hidden="true" className="flex size-11 shrink-0 items-center justify-center rounded-full bg-appointment text-appointment-ink">
                        <Bell className="size-5" strokeWidth={1.75} />
                    </span>
                    <div className="flex min-w-0 grow flex-col">
                        <span className="text-[15px] font-bold">{t('home.upcoming_empty_title')}</span>
                        <span className="text-[13px] text-ink-soft">{t('home.upcoming_empty_text')}</span>
                    </div>
                    <Link
                        href="/recordatorios/nuevo"
                        className="flex min-h-12 shrink-0 items-center rounded-full bg-primary px-5 text-[15px] font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                        {t('home.create')}
                    </Link>
                </div>
            ) : (
                <ul className="-mr-5 flex snap-x gap-3 overflow-x-auto pr-5 pb-1 [scrollbar-width:none]">
                    {upcoming.map((event) => {
                        const { kind, icon: Icon } = EVENT_STYLES[event.kind];
                        return (
                            <li key={`${event.kind}-${event.id}`} className="snap-start">
                                <Link
                                    href={event.href}
                                    className={`relative flex size-[156px] flex-col justify-between overflow-hidden rounded-3xl p-4 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${KINDS[kind].bg}`}
                                >
                                    <span aria-hidden="true" className={`absolute -right-[30px] -bottom-[30px] size-[100px] rounded-full ${KINDS[kind].deco}`} />
                                    <span className={`relative flex size-11 items-center justify-center rounded-full bg-surface ${KINDS[kind].ink}`}>
                                        <Icon aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
                                    </span>
                                    <span className="relative flex flex-col gap-0.5">
                                        <span className="line-clamp-2 text-lg leading-[1.15] font-bold">{event.title}</span>
                                        <span className="text-[13px] text-ink-mid">{event.overdue ? t('vacunas.overdue_card') : when(event.at)}</span>
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

export default function Inicio({ baby, clock, summary, sessions, upcoming, last, growth, marks }) {
    const t = useT();
    const { babies, activeBabyId } = usePage().props;

    return (
        <AppLayout title={t('home.title')}>
            <div className="flex flex-col gap-5">
                <Header baby={baby} clock={clock} babies={babies} activeBabyId={activeBabyId} hasAlerts={upcoming.length > 0} />
                <ActiveSessions sessions={sessions} />
                <SinceLast baby={baby} last={last} clock={clock} />
                <QuickLog />
                <Summary summary={summary} marks={marks} clock={clock} />
                <Growth growth={growth} />
                <Upcoming upcoming={upcoming} />
            </div>
        </AppLayout>
    );
}
