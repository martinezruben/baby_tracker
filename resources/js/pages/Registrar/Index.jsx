import { Link, router } from '@inertiajs/react';
import { ChevronLeft, History, Moon } from 'lucide-react';
import { useState } from 'react';
import useBreastTimer from '../../components/useBreastTimer';
import AppLayout from '../../layouts/AppLayout';
import { ACTIVITIES, GROUPS, KINDS } from '../../lib/activities';
import { agoLabel, useT } from '../../lib/i18n';
import { dayLabel } from '../../lib/lifeEvents';
import useFormat from '../../lib/useFormat';
import { formatClock, minutesSince } from '../../lib/time';

function BreastCard({ session }) {
    const t = useT();
    const format = useFormat();
    const { left, right } = useBreastTimer(session);
    const act = (op) => router.patch(`/pecho/${op}`, {}, { preserveScroll: true });
    const side = (key, seconds) => {
        const active = session.side === key;
        return (
            <div className={`flex flex-col gap-0.5 rounded-[18px] p-3.5 ${active ? 'bg-accent text-accent-ink' : 'bg-white/10'}`}>
                <div className={`text-[13px] ${active ? 'font-semibold' : 'text-white/80'}`}>
                    {t(`session.side_${key}_short`)}
                    {active && ` · ${session.paused ? t('session.paused') : t('session.active')}`}
                </div>
                <div className="text-[32px] leading-[1.1] font-bold">{formatClock(seconds)}</div>
            </div>
        );
    };

    const ghost = 'min-h-12 flex-1 rounded-full border border-white/45 text-[15px] font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white';

    return (
        <section aria-label={t('session.breast')} className="flex flex-col gap-3.5 rounded-[26px] bg-night p-[18px] text-white">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-lg font-bold">
                    <span aria-hidden="true" className={`size-2.5 rounded-full ${session.paused ? 'bg-white/50' : 'bg-live-soft'}`} />
                    {t('session.breast')}
                </div>
                <div className="text-[13px] text-white/80">{t('session.started', { time: format.time(session.started_at) })}</div>
            </div>
            <div className="grid grid-cols-2 gap-2.5">
                {side('left', left)}
                {side('right', right)}
            </div>
            <div className="flex gap-2">
                <button type="button" className={ghost} onClick={() => act(session.paused ? 'reanudar' : 'pausar')}>
                    {session.paused ? t('session.resume') : t('session.pause')}
                </button>
                <button type="button" className={ghost} onClick={() => act('cambiar')}>
                    {t('session.switch')}
                </button>
                <button
                    type="button"
                    onClick={() => act('finalizar')}
                    className="min-h-12 flex-1 rounded-full bg-white text-[15px] font-bold text-night focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                    {t('session.finish_short')}
                </button>
            </div>
        </section>
    );
}

function SleepCard({ session }) {
    const t = useT();
    const format = useFormat();
    return (
        <section className="flex items-center gap-3 rounded-[26px] bg-sleep p-[18px] text-ink">
            <Moon aria-hidden="true" className="size-6 shrink-0 text-sleep-ink" strokeWidth={1.75} />
            <div className="grow">
                <div className="text-lg font-bold">{t('session.sleeping')}</div>
                <div className="text-[13px] text-ink-mid">{t('session.sleeping_since', { time: format.time(session.started_at) })}</div>
            </div>
            <button
                type="button"
                onClick={() => router.patch(`/sueno/${session.id}/despertar`, {}, { preserveScroll: true })}
                className="min-h-12 rounded-full bg-ink px-5 text-[15px] font-semibold text-on-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                {t('session.wake')}
            </button>
        </section>
    );
}

function repeatLabel(t, entry) {
    const s = entry.summary;
    if (entry.type === 'biberon') return t('registrar.repeat_bottle', { amount: s.amount, unit: s.unit });
    if (entry.type === 'panal') return t('registrar.repeat_diaper', { type: t(`registrar.diaper.${s.type}`).toLowerCase() });
    return s.name ?? t('registrar.medicine.title');
}

function RoutineTile({ slug, subtitle, active }) {
    const t = useT();
    const { kind, icon: Icon, available, href } = ACTIVITIES[slug];
    const colors = KINDS[kind];
    const className = `relative flex h-[124px] flex-col items-start justify-between overflow-hidden rounded-3xl p-3.5 text-left ${colors.bg} ${
        active ? `border-2 ${colors.border}` : ''
    }`;

    const content = (
        <>
            <span aria-hidden="true" className={`absolute -top-6 -right-6 size-[92px] rounded-full ${colors.deco}`} />
            <span className={`relative flex size-11 items-center justify-center rounded-full bg-surface ${colors.ink}`}>
                <Icon aria-hidden="true" className="size-6" strokeWidth={1.75} />
            </span>
            <span className="relative flex flex-col gap-0.5">
                <span className="text-lg font-bold">{t(`registrar.tiles.${slug}`)}</span>
                <span className={`text-[13px] text-ink-mid ${active ? 'font-semibold' : ''}`}>{available ? subtitle : t('common.soon')}</span>
            </span>
        </>
    );

    return available ? (
        <Link href={href ?? `/registrar/${slug}`} className={`${className} focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary`}>
            {content}
        </Link>
    ) : (
        <div aria-disabled="true" className={`${className} opacity-55`}>
            {content}
        </div>
    );
}

function ListTile({ slug, subtitle }) {
    const t = useT();
    const { kind, icon: Icon, available, href } = ACTIVITIES[slug];
    const colors = KINDS[kind];
    const className = 'flex min-h-[84px] items-center gap-3 rounded-[22px] bg-surface p-3 text-left shadow-card';

    const content = (
        <>
            <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${colors.bg} ${colors.ink}`}>
                <Icon aria-hidden="true" className="size-6" strokeWidth={1.75} />
            </span>
            <span className="flex min-w-0 flex-col gap-0.5">
                <span className="text-base font-bold">{t(`registrar.tiles.${slug}`)}</span>
                <span className="text-[13px] text-ink-soft">{available ? subtitle : t('common.soon')}</span>
            </span>
        </>
    );

    return available ? (
        <Link href={href ?? `/registrar/${slug}`} className={`${className} focus-visible:outline-2 focus-visible:outline-primary`}>
            {content}
        </Link>
    ) : (
        <div aria-disabled="true" className={`${className} opacity-55`}>
            {content}
        </div>
    );
}

function GroupTitle({ id, title, count }) {
    const t = useT();
    return (
        <div id={id} className="flex scroll-mt-4 items-baseline justify-between">
            <h2 className="text-xl font-bold">{title}</h2>
            <div className="text-[13px] text-ink-soft">{t.choice('registrar.types_count', count)}</div>
        </div>
    );
}

export default function Index({ baby, clock, sessions, last, lastBreastAt, nextAppointmentAt, nextVaccine }) {
    const t = useT();
    const format = useFormat();
    const [section, setSection] = useState('todo');
    const breast = useBreastTimer(sessions.breast);

    const lastBy = Object.fromEntries(last.map((entry) => [entry.type, entry]));
    const ago = (iso) => agoLabel(t, minutesSince(iso));

    const subtitle = (slug) => {
        if (slug === 'pecho') {
            if (sessions.breast) return t('registrar.in_progress', { time: formatClock(breast.total) });
            return lastBreastAt ? ago(lastBreastAt) : t('registrar.none');
        }
        if (slug === 'vacuna') return nextVaccine ? (nextVaccine.overdue ? `${t('vacunas.overdue_card')} · ${nextVaccine.name}` : `${t('vacunas.sections.soon')} ${dayLabel(nextVaccine.due_on, format.locale)}`) : t('registrar.none');
        if (slug === 'cita') return nextAppointmentAt ? `${format.date(nextAppointmentAt)} · ${format.time(nextAppointmentAt)}` : t('registrar.none');
        if (slug === 'sueno' && sessions.sleep) return t('session.sleeping_since', { time: format.time(sessions.sleep.started_at) });
        const entry = lastBy[slug];
        if (!entry) return t('registrar.none');
        if (slug === 'medida') return `${String(entry.summary.value).replace('.', ',')} ${entry.summary.unit} · ${format.date(entry.at)}`;
        return ago(entry.at);
    };

    const repeatable = last.filter((entry) => entry.repeatable).sort((a, b) => Date.parse(b.at) - Date.parse(a.at)).slice(0, 3);
    const dots = { biberon: 'bg-chart-food', panal: 'bg-chart-diaper', medicina: 'bg-chart-medicine' };

    const chip = (id, label) => (
        <a
            key={id}
            href={`#${id}`}
            onClick={() => setSection(id)}
            aria-current={section === id ? 'true' : undefined}
            className={`flex min-h-11 shrink-0 items-center rounded-full px-5 text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                section === id ? 'bg-primary font-semibold text-on-primary' : 'bg-surface font-medium text-ink'
            }`}
        >
            {label}
        </a>
    );

    return (
        <AppLayout title={t('registrar.title')}>
            <div className="flex flex-col gap-5">
                <header className="flex min-h-14 items-center justify-between">
                    <Link href="/" aria-label={t('registrar.back_home')} className="flex size-12 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary">
                        <ChevronLeft aria-hidden="true" className="size-[22px]" strokeWidth={2} />
                    </Link>
                    <div className="flex flex-col items-center">
                        <h1 className="text-[21px] font-bold">{t('registrar.title')}</h1>
                        <div className="text-[13px] text-ink-soft">{t('registrar.subtitle', { name: baby.first_name, time: `${format.localTime(clock.now).main} ${format.localTime(clock.now).suffix}`.trim() })}</div>
                    </div>
                    <span className="size-12" aria-hidden="true" />
                </header>

                <nav aria-label={t('registrar.sections')} className="-mr-5 flex gap-2 overflow-x-auto pr-5 [scrollbar-width:none]">
                    {chip('todo', t('registrar.all'))}
                    {chip('rutina', t('registrar.routine'))}
                    {chip('salud', t('registrar.health'))}
                    {chip('momentos', t('registrar.moments'))}
                </nav>

                <div id="todo" className="flex flex-col gap-3">
                    {sessions.breast && <BreastCard session={sessions.breast} />}
                    {sessions.sleep && <SleepCard session={sessions.sleep} />}
                </div>

                {repeatable.length > 0 && (
                    <section className="flex flex-col gap-2.5">
                        <div className="flex items-center gap-2 text-[15px] font-semibold text-ink-soft">
                            <History aria-hidden="true" className="size-[18px]" strokeWidth={2} />
                            {t('registrar.repeat')}
                        </div>
                        <ul className="-mr-5 flex gap-2 overflow-x-auto pr-5 pb-1 [scrollbar-width:none]">
                            {repeatable.map((entry) => (
                                <li key={entry.type} className="shrink-0">
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/registrar/${entry.type}/repetir`)}
                                        className="flex min-h-12 items-center gap-2 rounded-full bg-surface px-4 text-sm font-semibold shadow-card focus-visible:outline-2 focus-visible:outline-primary"
                                    >
                                        <span aria-hidden="true" className={`size-2.5 rounded-[3px] ${dots[entry.type]}`} />
                                        {repeatLabel(t, entry)}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <GroupTitle id="rutina" title={t('registrar.routine_title')} count={GROUPS.rutina.length} />
                <div className="-mt-1.5 grid grid-cols-2 gap-3">
                    {GROUPS.rutina.map((slug) => (
                        <RoutineTile key={slug} slug={slug} subtitle={subtitle(slug)} active={slug === 'pecho' && Boolean(sessions.breast)} />
                    ))}
                </div>

                <GroupTitle id="salud" title={t('registrar.health')} count={GROUPS.salud.length} />
                <div className="-mt-1.5 grid grid-cols-2 gap-3">
                    {GROUPS.salud.map((slug) => (
                        <ListTile key={slug} slug={slug} subtitle={subtitle(slug)} />
                    ))}
                </div>

                <GroupTitle id="momentos" title={t('registrar.moments')} count={GROUPS.momentos.length} />
                <div className="-mt-1.5 grid grid-cols-2 gap-3">
                    {GROUPS.momentos.map((slug) => (
                        <ListTile key={slug} slug={slug} subtitle={subtitle(slug)} />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
