import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Baby, Bell, ChevronDown, ChevronRight, Milk, Moon, Ruler } from 'lucide-react';
import ActiveSessions from '../components/ActiveSessions';
import DonutChart from '../components/DonutChart';
import AppLayout from '../layouts/AppLayout';
import { KINDS, MODULES } from '../lib/activities';
import { ageLabel, durationLabel, useT } from '../lib/i18n';
import useFormat from '../lib/useFormat';
import { calendarDaysFrom, longDate } from '../lib/time';

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
    const now = new Date();

    return (
        <header className="flex min-h-14 items-center gap-3">
            <div
                aria-hidden="true"
                className="flex size-[52px] shrink-0 items-center justify-center rounded-full border-[3px] border-white bg-diaper text-diaper-ink shadow-soft"
            >
                <Baby className="size-7" strokeWidth={1.75} />
            </div>
            <div className="flex min-w-0 grow flex-col">
                <div className="truncate text-sm text-ink-soft">
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
                href="/recordatorios"
                aria-label={hasAlerts ? t('home.reminders_pending') : t('home.reminders')}
                className="relative flex size-12 shrink-0 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary"
            >
                <Bell aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
                {hasAlerts && <span aria-hidden="true" className="absolute top-[11px] right-3 size-[9px] rounded-full border-2 border-white bg-danger" />}
            </Link>
        </header>
    );
}

function Hero({ baby, today }) {
    const t = useT();
    const empty = today.total === 0;

    const parts = [
        today.feeds > 0 && t.choice('home.hero.feeds', today.feeds),
        today.diapers > 0 && t.choice('home.hero.diapers', today.diapers),
        today.sleep_minutes > 0 && t('home.hero.sleep', { duration: durationLabel(t, today.sleep_minutes) }),
    ].filter(Boolean);

    return (
        <Link
            href="/registrar"
            className="relative flex min-h-[200px] flex-col gap-2.5 overflow-hidden rounded-[28px] bg-night p-5 text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        >
            <span aria-hidden="true" className="absolute -top-[50px] -right-[50px] size-[210px] rounded-full bg-night-1" />
            <span aria-hidden="true" className="absolute -bottom-[70px] right-[60px] size-[150px] rounded-full bg-night-2" />
            <span
                aria-hidden="true"
                className="absolute top-[30px] right-[18px] flex size-[118px] flex-col items-center justify-center gap-0.5 rounded-full border-4 border-white/25 bg-diaper text-diaper-ink"
            >
                <Baby className="size-[52px]" strokeWidth={1.5} />
                <span className="text-xs font-semibold">{baby.first_name}</span>
            </span>
            <span className="relative self-start rounded-full border border-white/40 px-3 py-1 text-[13px]">{t('home.hero.today_with', { name: baby.first_name })}</span>
            <span className="relative max-w-[190px] text-[27px] leading-[1.12] font-bold">{empty ? t('home.hero.empty_title') : t('home.hero.title')}</span>
            <span className="relative max-w-[180px] text-sm text-white/80">
                {empty ? t('home.hero.empty_text') : parts.join(', ').replace(/, ([^,]*)$/, ` ${t('home.hero.and')} $1`)}
            </span>
            <span className="relative mt-1.5 flex min-h-12 items-center gap-2.5 self-start rounded-full bg-accent py-0 pr-[7px] pl-5 text-base font-bold text-accent-ink">
                {t('home.hero.cta')}
                <span className="flex size-[34px] items-center justify-center rounded-full bg-white">
                    <ArrowRight aria-hidden="true" className="size-[18px]" strokeWidth={2} />
                </span>
            </span>
        </Link>
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
                        className="flex flex-col items-center gap-2.5 rounded-[22px] bg-surface px-1 pt-3.5 pb-3 text-sm font-semibold shadow-card focus-visible:outline-2 focus-visible:outline-primary"
                    >
                        <span className={`flex size-[52px] items-center justify-center rounded-full ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                            <Icon aria-hidden="true" className="size-[26px]" strokeWidth={1.75} />
                        </span>
                        {t(`home.quick_${key}`)}
                    </Link>
                ))}
            </div>
        </section>
    );
}

const LEGEND = [
    ['feed', 'home.summary.feed'],
    ['sleep', 'home.summary.sleep'],
    ['diaper', 'home.summary.diaper'],
    ['medicine', 'home.summary.medicine'],
    ['play', 'home.summary.play'],
];

function Summary({ summary }) {
    const t = useT();

    const value = {
        feed: summary.feed.ml > 0 ? `${summary.feed.count} · ${summary.feed.ml} ml` : summary.feed.count,
        sleep: summary.sleep.minutes > 0 ? durationLabel(t, summary.sleep.minutes) : '0',
        diaper: summary.diaper,
        medicine: summary.medicine,
        play: summary.play,
    };
    const dots = { feed: 'bg-chart-food', sleep: 'bg-chart-sleep', diaper: 'bg-chart-diaper', medicine: 'bg-chart-medicine', play: 'bg-chart-play' };

    const setRange = (rango) =>
        router.get('/', { rango }, { only: ['summary'], preserveState: true, preserveScroll: true, replace: true });

    return (
        <section className={`${card} flex flex-col gap-3.5 p-[18px]`}>
            <h2 className="text-[19px] font-bold">{t('home.summary.title')}</h2>
            <div role="group" aria-label={t('home.summary.title')} className="flex gap-2">
                {['hoy', 'semana', 'mes'].map((rango) => {
                    const selected = summary.range === rango;
                    return (
                        <button
                            key={rango}
                            type="button"
                            aria-pressed={selected}
                            onClick={() => setRange(rango)}
                            className={`min-h-10 rounded-full px-[18px] text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                selected ? 'bg-primary font-semibold text-white' : 'border border-line bg-surface font-medium text-ink'
                            }`}
                        >
                            {t(`home.summary.range_${rango}`)}
                        </button>
                    );
                })}
            </div>

            <div className="flex items-center gap-5">
                <DonutChart
                    segments={summary.segments}
                    total={summary.total}
                    caption={t('home.summary.records')}
                    label={t('home.summary.donut_label', { total: summary.total })}
                />
                <ul className="flex grow flex-col gap-[7px] text-sm">
                    {LEGEND.map(([key, label]) => (
                        <li key={key} className="flex items-center gap-2">
                            <span aria-hidden="true" className={`size-2.5 rounded-[3px] ${dots[key]}`} />
                            <span className="grow">{t(label)}</span>
                            <span className="font-bold">{value[key]}</span>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

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
                <Link
                    href="/recordatorios/nuevo"
                    className="flex min-h-24 items-center justify-center rounded-3xl border-2 border-dashed border-line px-4 text-center text-[15px] text-ink-soft hover:bg-surface"
                >
                    {t('home.upcoming_empty')}
                </Link>
            ) : (
                <ul className="-mr-5 flex snap-x gap-3 overflow-x-auto pr-5 pb-1 [scrollbar-width:none]">
                    {upcoming.map((event) => (
                        <li key={event.id} className="snap-start">
                            <Link
                                href={`/recordatorios/${event.id}/editar`}
                                className={`relative flex size-[156px] flex-col justify-between overflow-hidden rounded-3xl p-4 text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${KINDS.appointment.bg}`}
                            >
                                <span aria-hidden="true" className={`absolute -right-[30px] -bottom-[30px] size-[100px] rounded-full ${KINDS.appointment.deco}`} />
                                <span className={`relative flex size-11 items-center justify-center rounded-full bg-white ${KINDS.appointment.ink}`}>
                                    <Bell aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
                                </span>
                                <span className="relative flex flex-col gap-0.5">
                                    <span className="line-clamp-2 text-lg leading-[1.15] font-bold">{event.title}</span>
                                    <span className="text-[13px] text-ink-mid">{when(event.at)}</span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function Modules({ baby }) {
    const t = useT();

    return (
        <section className="flex flex-col gap-3.5">
            <h2 className="text-[19px] font-bold">{t('home.everything', { name: baby.first_name })}</h2>
            <ul className="grid grid-cols-4 gap-x-2 gap-y-4">
                {MODULES.map(({ key, icon: Icon, kind, href }) => {
                    const inner = (
                        <>
                            <span className={`flex size-[58px] items-center justify-center rounded-[20px] ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                                <Icon aria-hidden="true" className="size-[26px]" strokeWidth={1.75} />
                            </span>
                            <span className="text-[13px] font-medium">{t(`home.modules.${key}`)}</span>
                        </>
                    );
                    return (
                        <li key={key} className="flex justify-center">
                            {href ? (
                                <Link href={href} className="flex flex-col items-center gap-2 focus-visible:outline-2 focus-visible:outline-primary">
                                    {inner}
                                </Link>
                            ) : (
                                <span aria-disabled="true" title={t('common.soon')} className="flex flex-col items-center gap-2 opacity-45">
                                    {inner}
                                    <span className="sr-only">({t('common.soon')})</span>
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

export default function Inicio({ baby, clock, today, summary, sessions, upcoming }) {
    const t = useT();
    const { babies, activeBabyId } = usePage().props;

    return (
        <AppLayout title={t('home.title')}>
            <div className="flex flex-col gap-5">
                <Header baby={baby} clock={clock} babies={babies} activeBabyId={activeBabyId} hasAlerts={upcoming.length > 0} />
                <ActiveSessions sessions={sessions} />
                <Hero baby={baby} today={today} />
                <QuickLog />
                <Summary summary={summary} />
                <Upcoming upcoming={upcoming} />
                <Modules baby={baby} />
            </div>
        </AppLayout>
    );
}
