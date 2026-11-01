import { Link, router, usePage } from '@inertiajs/react';
import { Bell, ChevronDown, ChevronRight, Droplet, Milk, Moon, MoonStar, Pill, Plus, Ruler, Stethoscope, Syringe, Baby as BabyIcon } from 'lucide-react';
import ActiveSessions from '../components/ActiveSessions';
import BabyAvatar from '../components/BabyAvatar';
import Sky from '../components/Sky';
import useTicker from '../components/useTicker';
import AppLayout from '../layouts/AppLayout';
import { KINDS } from '../lib/activities';
import { ageLabel, durationLabel, useT } from '../lib/i18n';
import useFormat from '../lib/useFormat';
import { calendarDaysFrom, longDate, minutesSince } from '../lib/time';

const card = 'rounded-[28px] border border-line bg-surface';

function SectionTitle({ children, href, linkLabel }) {
    return (
        <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold">{children}</h2>
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
            <div className="relative shrink-0">
                <BabyAvatar className="size-14" label={baby.first_name} />
                <span aria-hidden="true" className="absolute right-0 bottom-px size-3.5 rounded-full border-[3px] border-canvas bg-live" />
            </div>
            <div className="flex min-w-0 grow flex-col">
                <div className="text-sm leading-tight text-ink-soft">
                    {longDate(now, locale, clock.timezone)} · {ageLabel(t, baby.birth_date, now)}
                </div>
                {babies.length > 1 ? (
                    <div className="relative flex items-center gap-1 text-[28px] leading-tight font-semibold">
                        <select
                            aria-label={t('common.select_baby')}
                            value={activeBabyId}
                            onChange={(e) => router.post(`/bebes/${e.target.value}/seleccionar`)}
                            className="max-w-full appearance-none bg-transparent pr-7 text-[28px] font-semibold focus-visible:outline-2 focus-visible:outline-primary"
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
                    <div className="truncate text-[28px] leading-tight font-semibold">{baby.first_name}</div>
                )}
            </div>
            <Link
                href="/nursery"
                aria-label={t('nursery.open')}
                className="flex size-12 shrink-0 items-center justify-center rounded-full border border-line bg-surface text-ink focus-visible:outline-2 focus-visible:outline-primary"
            >
                <MoonStar aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
            </Link>
            <Link
                href="/recordatorios"
                aria-label={hasAlerts ? t('home.reminders_pending') : t('home.reminders')}
                className="relative flex size-12 shrink-0 items-center justify-center rounded-full border border-line bg-surface text-ink focus-visible:outline-2 focus-visible:outline-primary"
            >
                <Bell aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
                {hasAlerts && <span aria-hidden="true" className="absolute top-[11px] right-3 size-[9px] rounded-full border-2 border-surface bg-chart-food" />}
            </Link>
        </header>
    );
}

// ---- Anillo de 24 horas ------------------------------------------------------------------------------------

const R = 96;
const C = 2 * Math.PI * R;
const polar = (minute, r = R) => {
    const a = (minute / 1440) * 2 * Math.PI;
    return [130 + r * Math.sin(a), 130 - r * Math.cos(a)];
};
const arc = (from, to) => ({ strokeDasharray: `${Math.max(((to - from) / 1440) * C, 1)} ${C}`, strokeDashoffset: -(from / 1440) * C });

function DayRing({ marks, nowMinute, label, children }) {
    const t = useT();
    const sleeps = marks.filter((m) => m.kind === 'sleep');
    const feeds = marks.filter((m) => m.kind === 'feed');
    const small = marks.filter((m) => m.kind === 'diaper' || m.kind === 'medicine');
    const [nx, ny] = polar(nowMinute);
    const text = 'fill-ink-soft text-[10px]';

    return (
        <div className="relative size-[292px]">
            <svg viewBox="0 0 260 260" role="img" aria-label={label} className="size-full">
                <circle cx="130" cy="130" r="117" fill="none" strokeWidth="6" strokeDasharray="1.5 29.2" transform="rotate(-90.4 130 130)" className="stroke-tick" />
                <circle cx="130" cy="130" r={R} fill="none" strokeWidth="20" className="stroke-track" />
                <g transform="rotate(-90 130 130)" fill="none" strokeWidth="20">
                    <circle cx="130" cy="130" r={R} className="stroke-nightband" style={arc(0, 360)} />
                    <circle cx="130" cy="130" r={R} className="stroke-nightband" style={arc(1260, 1440)} />
                    {sleeps.map((s, i) => (
                        <circle key={i} cx="130" cy="130" r={R} strokeLinecap="round" className="stroke-chart-sleep" style={arc(s.minute, Math.max(s.end ?? nowMinute, s.minute + 8))} />
                    ))}
                </g>
                {small.map((m, i) => {
                    const [x, y] = polar(m.minute, 74);
                    return <circle key={i} cx={x} cy={y} r="4" className={m.kind === 'diaper' ? 'fill-chart-diaper' : 'fill-chart-medicine'} />;
                })}
                {feeds.map((m, i) => {
                    const [x, y] = polar(m.minute);
                    return (
                        <g key={i}>
                            <circle cx={x} cy={y} r="13" className="fill-chart-food stroke-surface" strokeWidth="3" />
                            <g transform={`translate(${x - 7} ${y - 7}) scale(0.58)`} fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M8 2h8" />
                                <path d="M9 2v2.8a4 4 0 0 1-1 2.6 4 4 0 0 0-1 2.6V20a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V10a4 4 0 0 0-1-2.6 4 4 0 0 1-1-2.6V2" />
                            </g>
                        </g>
                    );
                })}
                <circle cx={nx} cy={ny} r="14" className="fill-ink opacity-10" />
                <circle cx={nx} cy={ny} r="9" className="fill-ink stroke-surface" strokeWidth="3" />
                <text x="130" y="11" textAnchor="middle" className={text}>{t('home.hour_0')}</text>
                <text x="250" y="133" textAnchor="end" className={text}>{t('home.hour_6')}</text>
                <text x="130" y="256" textAnchor="middle" className={text}>{t('home.hour_12')}</text>
                <text x="10" y="133" textAnchor="start" className={text}>{t('home.hour_18')}</text>
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center gap-0.5 text-center">{children}</div>
        </div>
    );
}

function Legend() {
    const t = useT();
    const items = [
        ['bg-chart-food', 'home.legend_feed'],
        ['bg-chart-sleep', 'home.legend_sleep'],
        ['bg-ink', 'home.now'],
        ['bg-nightband', 'home.legend_night'],
    ];
    return (
        <ul className="relative flex flex-wrap justify-center gap-x-4 gap-y-1 text-[13px] text-ink-soft">
            {items.map(([dot, key]) => (
                <li key={key} className="flex items-center gap-1.5">
                    <span aria-hidden="true" className={`size-2.5 rounded-full ${dot}`} />
                    {t(key)}
                </li>
            ))}
        </ul>
    );
}

function Hero({ clock, last, marks }) {
    const t = useT();
    const format = useFormat();
    useTicker(30000);

    const hour = Number(clock.now.slice(11, 13));
    const nowMinute = hour * 60 + Number(clock.now.slice(14, 16));
    const greeting = hour < 12 ? 'home.greet_morning' : hour < 19 ? 'home.greet_afternoon' : 'home.greet_evening';
    const feed = last.feed;
    const feedDetail = feed
        ? [t(`home.feed_type.${feed.type}`), feed.amount ? `${String(feed.amount).replace('.', ',')} ${feed.unit}` : null, format.time(feed.at)].filter(Boolean).join(' · ')
        : t('home.no_record_hint');
    const clockText = format.localTime(clock.now);
    const clockLabel = `${clockText.main} ${clockText.suffix}`.trim();

    return (
        <section aria-label={t('home.since_last')} className="relative flex flex-col items-center gap-4 overflow-hidden rounded-[36px] border border-line bg-gradient-to-b from-sky-1 to-sky-2 px-4 pt-5 pb-[18px]">
            <Sky />
            <div className="relative flex min-h-8 w-full items-center justify-between">
                <span className="text-base font-semibold">{t(greeting)}</span>
                <span className="rounded-full border border-line bg-surface px-3 py-1.5 text-[13px] font-semibold text-ink-soft">{clockLabel}</span>
            </div>

            <DayRing marks={marks} nowMinute={nowMinute} label={t('home.ring_label', { time: clockLabel })}>
                <span className="text-[13px] text-ink-soft">{t('home.last_feed_ago')}</span>
                <span className="text-[52px] leading-none font-semibold tracking-tight">{feed ? durationLabel(t, minutesSince(feed.at)) : '—'}</span>
                <span className="px-10 text-[13px] text-ink-soft">{feedDetail}</span>
            </DayRing>
            <Legend />

            <Link
                href="/registrar/biberon"
                className="relative flex min-h-[58px] w-full items-center justify-center gap-2.5 rounded-full bg-primary text-[17px] font-semibold text-on-primary shadow-fab focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                <Plus aria-hidden="true" className="size-[22px]" strokeWidth={2.25} />
                {t('home.log_feed')}
            </Link>

            <div className="relative grid w-full grid-cols-2 gap-2.5">
                <Link href="/registrar/panal" className="relative flex min-h-[104px] flex-col gap-0.5 overflow-hidden rounded-3xl bg-diaper p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <Droplet aria-hidden="true" className="absolute -right-2 -bottom-2 size-16 text-diaper-ink opacity-20" strokeWidth={1.25} />
                    <span className="text-[13px] text-ink-soft">{t('home.last_diaper')}</span>
                    <span className="text-2xl font-semibold">{last.diaper ? durationLabel(t, minutesSince(last.diaper.at)) : t('home.no_record')}</span>
                    <span className="text-[13px] text-ink-soft">{last.diaper ? t(`registrar.diaper.${last.diaper.type}`) : t('home.no_record_hint')}</span>
                </Link>
                <Link href="/registrar/sueno" className="relative flex min-h-[104px] flex-col gap-0.5 overflow-hidden rounded-3xl bg-sleep p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <Moon aria-hidden="true" className="absolute -right-2 -bottom-2 size-16 text-sleep-ink opacity-20" strokeWidth={1.25} />
                    <span className="text-[13px] text-ink-soft">{t('home.last_sleep')}</span>
                    <span className="text-2xl font-semibold">{last.sleep ? durationLabel(t, minutesSince(last.sleep.at)) : t('home.no_record')}</span>
                    <span className="text-[13px] text-ink-soft">{last.sleep ? t('home.sleep_of', { duration: durationLabel(t, last.sleep.minutes) }) : t('home.no_record_hint')}</span>
                </Link>
            </div>
        </section>
    );
}

// ---- Registro rápido ---------------------------------------------------------------------------------------

const QUICK = [
    { key: 'biberon', icon: Milk, kind: 'food', href: '/registrar/biberon' },
    { key: 'sueno', icon: Moon, kind: 'sleep', href: '/registrar/sueno' },
    { key: 'panal', icon: BabyIcon, kind: 'diaper', href: '/registrar/panal' },
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
                    <Link key={key} href={href} className="flex flex-col items-center gap-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-primary">
                        <span className={`flex size-[72px] items-center justify-center rounded-[28px] border border-line ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                            <Icon aria-hidden="true" className="size-[30px]" strokeWidth={1.75} />
                        </span>
                        {t(`home.quick_${key}`)}
                    </Link>
                ))}
            </div>
        </section>
    );
}

// ---- Hoy ---------------------------------------------------------------------------------------------------

const MARK_COLORS = { feed: 'bg-chart-food', diaper: 'bg-chart-diaper', medicine: 'bg-chart-medicine' };

/** Línea del día (0–24 h): noche sombreada, sueños como tramos, una marca por registro y la hora actual. */
function DayLine({ marks, nowMinute }) {
    const t = useT();
    const pct = (m) => `${(m / 1440) * 100}%`;
    const hours = ['home.hour_0', 'home.hour_6', 'home.hour_12', 'home.hour_18'];

    return (
        <div className="flex flex-col gap-1.5">
            <div role="img" aria-label={t('home.timeline_label', { n: marks.length })} className="relative h-[30px] overflow-hidden rounded-full bg-track">
                <span aria-hidden="true" className="absolute inset-y-0 left-0 w-1/4 bg-nightband" />
                <span aria-hidden="true" className="absolute inset-y-0 right-0 left-[87.5%] bg-nightband" />
                {marks
                    .filter((m) => m.kind === 'sleep')
                    .map((m, i) => (
                        <span key={`s${i}`} aria-hidden="true" className="absolute inset-y-[5px] rounded-full bg-chart-sleep" style={{ left: pct(m.minute), width: pct(Math.max((m.end ?? nowMinute) - m.minute, 10)) }} />
                    ))}
                {marks
                    .filter((m) => m.kind !== 'sleep')
                    .map((m, i) => (
                        <span key={`m${i}`} aria-hidden="true" className={`absolute inset-y-[3px] -ml-[3px] w-1.5 rounded-full border border-surface ${MARK_COLORS[m.kind]}`} style={{ left: pct(m.minute) }} />
                    ))}
                <span aria-hidden="true" className="absolute inset-y-0 -ml-px w-[3px] bg-ink" style={{ left: pct(nowMinute) }} />
            </div>
            <div aria-hidden="true" className="flex justify-between text-[11px] text-ink-soft">
                {hours.map((h) => (
                    <span key={h}>{t(h)}</span>
                ))}
                <span>{t('home.now')}</span>
            </div>
        </div>
    );
}

const COUNTS = [
    ['feed', 'home.summary.feed', 'food', Milk],
    ['sleep', 'home.summary.sleep', 'sleep', Moon],
    ['diaper', 'home.summary.diaper', 'diaper', Droplet],
    ['medicine', 'home.summary.medicine', 'medicine', Pill],
    ['play', 'home.summary.play', 'growth', BabyIcon],
];

function Summary({ summary, marks, clock }) {
    const t = useT();
    const nowMinute = Number(clock.now.slice(11, 13)) * 60 + Number(clock.now.slice(14, 16));

    const value = {
        feed: summary.feed.count,
        sleep: summary.sleep.minutes > 0 ? durationLabel(t, summary.sleep.minutes) : '0',
        diaper: summary.diaper,
        medicine: summary.medicine,
        play: summary.play,
    };

    const setRange = (rango) => router.get('/', { rango }, { only: ['summary'], preserveState: true, preserveScroll: true, replace: true });

    return (
        <section className="flex flex-col gap-3">
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-xl font-semibold">{t('home.summary.today')}</h2>
                <div role="group" aria-label={t('home.summary.title')} className="flex rounded-full bg-track p-[3px]">
                    {['hoy', 'semana', 'mes'].map((rango) => {
                        const selected = summary.range === rango;
                        return (
                            <button
                                key={rango}
                                type="button"
                                aria-pressed={selected}
                                onClick={() => setRange(rango)}
                                className={`min-h-[42px] rounded-full px-3.5 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                    selected ? 'bg-surface font-semibold text-ink' : 'font-medium text-ink-soft'
                                }`}
                            >
                                {t(`home.summary.range_${rango}`)}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className={`${card} flex flex-col gap-4 p-[18px]`}>
                {summary.range === 'hoy' && <DayLine marks={marks} nowMinute={nowMinute} />}
                <ul className="grid grid-cols-2 gap-2.5">
                    {COUNTS.map(([key, label, kind, Icon]) => (
                        <li key={key} className="flex items-center gap-3 rounded-[20px] bg-canvas p-3">
                            <span aria-hidden="true" className={`flex size-10 shrink-0 items-center justify-center rounded-full ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                                <Icon className="size-5" strokeWidth={1.75} />
                            </span>
                            <span className="flex min-w-0 flex-col leading-tight">
                                <span className="truncate text-xl font-semibold">{value[key]}</span>
                                <span className="truncate text-[13px] text-ink-soft">{key === 'feed' && summary.feed.amount > 0 ? `${t(label)} · ${String(summary.feed.amount).replace('.', ',')} ${summary.feed.unit}` : t(label)}</span>
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

// ---- Crecimiento -------------------------------------------------------------------------------------------

const GROWTH = [
    ['weight', 'home.growth_weight'],
    ['height', 'home.growth_height'],
    ['head_circumference', 'home.growth_head_circumference'],
];

/** Curva ilustrativa (franjas y mediana): da forma a la tarjeta, no muestra percentiles; esos están en «Ver curva». */
function GrowthArt() {
    const t = useT();

    return (
        <svg viewBox="0 0 310 132" role="img" aria-label={t('home.growth_art')} className="h-auto w-full">
            <path d="M10 82C90 66 170 44 300 18L300 62C170 90 90 106 10 118Z" className="fill-band-1" />
            <path d="M10 92C90 78 170 58 300 36L300 54C170 78 90 96 10 108Z" className="fill-band-2" />
            <path d="M10 100C90 87 170 67 300 45" fill="none" strokeWidth="2" strokeDasharray="4 4" className="stroke-primary" />
            <line x1="10" y1="124" x2="300" y2="124" strokeWidth="1" className="stroke-line" />
            <circle cx="266" cy="51" r="12" className="fill-primary opacity-20" />
            <circle cx="266" cy="51" r="6.5" strokeWidth="3" className="fill-primary stroke-surface" />
            <text x="10" y="131" className="fill-ink-soft text-[10px]">{t('home.birth')}</text>
            <text x="300" y="131" textAnchor="end" className="fill-ink-soft text-[10px]">{t('home.today_short')}</text>
        </svg>
    );
}

function Growth({ growth }) {
    const t = useT();
    const by = Object.fromEntries(growth.map((g) => [g.type, g]));

    return (
        <section className="flex flex-col gap-3">
            <SectionTitle href="/crecimiento" linkLabel={t('home.see_curve')}>
                {t('home.growth')}
            </SectionTitle>
            <div className={`${card} flex flex-col gap-3.5 p-[18px]`}>
                <GrowthArt />
                <ul className="grid grid-cols-3 gap-2.5">
                    {GROWTH.map(([type, label]) => (
                        <li key={type} className="flex flex-col">
                            <span className="text-[13px] text-ink-soft">{t(label)}</span>
                            <span className="text-2xl font-semibold">
                                {by[type] ? String(by[type].value).replace('.', ',') : '—'}
                                {by[type] && <span className="ml-0.5 text-sm font-medium text-ink-soft">{by[type].unit}</span>}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

// ---- Vacunas -----------------------------------------------------------------------------------------------

function Vaccines({ vaccines }) {
    const t = useT();
    if (vaccines.total === 0) return null;

    const r = 38;
    const c = 2 * Math.PI * r;
    const applied = (vaccines.applied / vaccines.total) * c;
    const overdue = (vaccines.overdue / vaccines.total) * c;
    const rows = [
        ['bg-vaccine-ink', 'home.vaccines_applied', vaccines.applied],
        ['bg-danger-ink', 'home.vaccines_overdue', vaccines.overdue],
        ['border border-ink-soft bg-track', 'home.vaccines_upcoming', vaccines.upcoming],
    ];

    return (
        <section className="flex flex-col gap-3">
            <SectionTitle href="/vacunas" linkLabel={t('home.see_schedule')}>
                {t('home.vaccines')}
            </SectionTitle>
            <div className={`${card} flex items-center gap-5 p-[18px]`}>
                <div className="relative size-24 shrink-0">
                    <svg viewBox="0 0 96 96" role="img" aria-label={t('home.vaccines_label', { applied: vaccines.applied, total: vaccines.total, overdue: vaccines.overdue })} className="size-full">
                        <circle cx="48" cy="48" r={r} fill="none" strokeWidth="11" className="stroke-track" />
                        {applied > 0 && <circle cx="48" cy="48" r={r} fill="none" strokeWidth="11" strokeLinecap="round" strokeDasharray={`${applied} ${c}`} transform="rotate(-90 48 48)" className="stroke-vaccine-ink" />}
                        {overdue > 0 && <circle cx="48" cy="48" r={r} fill="none" strokeWidth="11" strokeLinecap="round" strokeDasharray={`${overdue} ${c}`} strokeDashoffset={-(applied + 8)} transform="rotate(-90 48 48)" className="stroke-danger-ink" />}
                    </svg>
                    <div className="absolute inset-0 flex flex-col items-center justify-center leading-none">
                        <span className="text-[26px] font-semibold">{vaccines.applied}</span>
                        <span className="text-xs text-ink-soft">{t('home.of_total', { total: vaccines.total })}</span>
                    </div>
                </div>
                <ul className="flex grow flex-col gap-2.5">
                    {rows.map(([dot, label, n]) => (
                        <li key={label} className="flex items-center gap-2.5 text-[15px]">
                            <span aria-hidden="true" className={`size-2.5 rounded-full ${dot}`} />
                            <span className="grow">{t(label)}</span>
                            <span className="font-semibold">{n}</span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

// ---- Próximos ----------------------------------------------------------------------------------------------

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
                <ul className="flex flex-col gap-2.5">
                    {upcoming.map((event) => {
                        const { kind, icon: Icon } = EVENT_STYLES[event.kind];
                        return (
                            <li key={`${event.kind}-${event.id}`}>
                                <Link href={event.href} className="flex min-h-[76px] items-center gap-3.5 rounded-3xl border border-line bg-surface px-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                                    <span aria-hidden="true" className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                                        <Icon className="size-[22px]" strokeWidth={1.75} />
                                    </span>
                                    <span className="flex min-w-0 grow flex-col">
                                        <span className="truncate text-base font-semibold">{event.title}</span>
                                        <span className="text-[13px] text-ink-soft">{t(`home.event_${event.kind}`)}</span>
                                    </span>
                                    {event.overdue ? (
                                        <span className="shrink-0 rounded-full bg-danger-soft px-3 py-1.5 text-[13px] font-semibold text-danger-ink">{t('vacunas.overdue_card')}</span>
                                    ) : (
                                        <span className="shrink-0 text-sm text-ink-soft">{when(event.at)}</span>
                                    )}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

export default function Inicio({ baby, clock, summary, sessions, upcoming, last, growth, marks, vaccines }) {
    const t = useT();
    const { babies, activeBabyId } = usePage().props;

    return (
        <AppLayout title={t('home.title')}>
            <div className="flex flex-col gap-7">
                <Header baby={baby} clock={clock} babies={babies} activeBabyId={activeBabyId} hasAlerts={upcoming.length > 0} />
                <ActiveSessions sessions={sessions} />
                <Hero clock={clock} last={last} marks={marks} />
                <QuickLog />
                <Summary summary={summary} marks={marks} clock={clock} />
                <Growth growth={growth} />
                <Vaccines vaccines={vaccines} />
                <Upcoming upcoming={upcoming} />
            </div>
        </AppLayout>
    );
}
