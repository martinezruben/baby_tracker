import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, Download, Pencil, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import Button from '../components/Button';
import AppLayout from '../layouts/AppLayout';
import { ACTIVITIES, KINDS } from '../lib/activities';
import { durationLabel, useT } from '../lib/i18n';
import { formatClock } from '../lib/time';
import useFormat from '../lib/useFormat';

/** Título y detalle de un registro, con textos de los archivos de traducción. */
function describe(t, format, entry) {
    const d = entry.data;
    const join = (...parts) => parts.filter(Boolean).join(' · ');
    const n = (value) => String(value).replace('.', ',');

    switch (entry.type) {
        case 'panal':
            return {
                title: `${t('registrar.tiles.panal')} ${t(`registrar.diaper.${d.type}`).toLowerCase()}`,
                detail: join(d.cream && t('log.cream'), d.blowout && t('log.blowout'), d.notes),
            };
        case 'biberon':
            return {
                title: `${t('registrar.tiles.biberon')} · ${n(d.amount)} ${d.unit}`,
                detail: join(d.bottle_type && t(`registrar.bottle.${d.bottle_type}`), d.notes),
            };
        case 'pecho': {
            const sides = [d.left > 0 && `${t('log.left_short')} ${formatClock(d.left)}`, d.right > 0 && `${t('log.right_short')} ${formatClock(d.right)}`];
            return {
                title: `${t('registrar.tiles.pecho')} · ${durationLabel(t, Math.max(1, Math.round(d.seconds / 60)))}`,
                detail: join(...sides),
            };
        }
        case 'sueno':
            return {
                title: d.open ? t('session.sleeping') : `${t('registrar.tiles.sueno')} · ${durationLabel(t, d.minutes ?? 0)}`,
                detail: join(
                    d.type === 'night_sleep' ? t('registrar.sleep.night') : t('registrar.sleep.nap'),
                    d.ended_at && `${format.time(entry.at)} – ${format.time(d.ended_at)}`,
                    d.location,
                ),
            };
        case 'medicina':
            return { title: d.name ?? t('registrar.tiles.medicina'), detail: join(`${n(d.dose)}${d.unit ? ` ${d.unit}` : ''}`) };
        case 'medida':
            return { title: `${t(`registrar.measure.${d.type}`)} · ${n(d.value)} ${d.unit}`, detail: '' };
        case 'solidos':
            return {
                title: `${t('registrar.tiles.solidos')} · ${d.food ?? ''}`.replace(/ · $/, ''),
                detail: join(d.amount && `${n(d.amount)} ${d.unit ?? ''}`.trim(), d.enjoyment && t(`registrar.solids.enjoy_${d.enjoyment}`), d.reaction && t('registrar.solids.reaction')),
            };
        case 'extraccion':
            return { title: `${t('registrar.tiles.extraccion')} · ${n(d.amount)} ${d.unit}`, detail: join(d.minutes && durationLabel(t, d.minutes)) };
        case 'bano':
            return { title: t('registrar.tiles.bano'), detail: join(d.bath_type && t(`registrar.bath.${d.bath_type}`), d.soap && t('registrar.bath.soap'), d.shampoo && t('registrar.bath.shampoo')) };
        case 'juego':
            return { title: `${t('registrar.tiles.juego')} · ${t(`registrar.play.${d.type}`)}`, detail: join(d.minutes && durationLabel(t, d.minutes), d.notes) };
        case 'animo':
            return { title: `${t('registrar.tiles.animo')} · ${t(`registrar.mood.${d.mood}`)}`, detail: join(d.intensity && `${t('registrar.mood.intensity_short')} ${d.intensity}`, d.minutes && durationLabel(t, d.minutes)) };
        default:
            return { title: t('registrar.tiles.nota'), detail: d.content };
    }
}

/** Tomas, sueño y pañales del día, calculados con lo que ya se cargó. */
function DaySummary({ entries }) {
    const t = useT();
    const feeds = entries.filter((e) => e.type === 'biberon' || e.type === 'pecho');
    const bottle = entries.filter((e) => e.type === 'biberon');
    const unit = bottle[0]?.data.unit;
    const amount = bottle.reduce((sum, e) => sum + Number(e.data.amount), 0);
    const sleep = entries.filter((e) => e.type === 'sueno').reduce((sum, e) => sum + (e.data.minutes ?? 0), 0);
    const diapers = entries.filter((e) => e.type === 'panal').length;
    const tiles = [
        ['food', feeds.length ? `${feeds.length}${amount > 0 ? ` · ${String(Math.round(amount * 10) / 10).replace('.', ',')} ${unit}` : ''}` : '0', 'home.summary.feed'],
        ['sleep', sleep > 0 ? durationLabel(t, sleep) : '0', 'home.summary.sleep'],
        ['diaper', diapers, 'home.summary.diaper'],
    ];

    return (
        <ul className="grid grid-cols-3 gap-2.5">
            {tiles.map(([kind, value, label]) => (
                <li key={label} className={`flex min-w-0 flex-col rounded-[18px] px-3 py-2.5 ${KINDS[kind].bg}`}>
                    <span className="truncate text-lg font-semibold">{value}</span>
                    <span className="text-xs text-ink-soft">{t(label)}</span>
                </li>
            ))}
        </ul>
    );
}

function dayKey(iso, timezone) {
    return new Intl.DateTimeFormat('en-CA', { timeZone: timezone }).format(new Date(iso));
}

function Entry({ entry }) {
    const t = useT();
    const format = useFormat();
    const [open, setOpen] = useState(false);
    const { kind, icon: Icon } = ACTIVITIES[entry.type];
    const { title, detail } = describe(t, format, entry);

    return (
        <li className="relative flex items-start gap-3.5">
            <span aria-hidden="true" className={`relative z-[1] flex size-[46px] shrink-0 items-center justify-center rounded-full border-[3px] border-canvas box-content -m-[3px] ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                <Icon className="size-[22px]" strokeWidth={1.75} />
            </span>
            <div className="min-w-0 grow rounded-[22px] border border-line bg-surface">
            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen(!open)}
                className="flex min-h-[64px] w-full flex-col justify-center gap-1.5 px-4 py-3 text-left focus-visible:outline-2 focus-visible:outline-primary"
            >
                <span className="flex items-baseline justify-between gap-2">
                    <span className="min-w-0 text-[17px] font-semibold break-words">{title}</span>
                    <span className="shrink-0 text-sm text-ink-soft">{format.time(entry.at)}</span>
                </span>
                {entry.type === 'sueno' && !entry.data.open && <span aria-hidden="true" className="h-2 rounded-full bg-chart-sleep" />}
                {(detail || entry.by) && <span className="line-clamp-2 text-[13px] break-words text-ink-soft">{[detail, entry.by && t('home.logged_by', { name: entry.by })].filter(Boolean).join(' · ')}</span>}
            </button>
            {open && (
                <div className="flex flex-wrap items-center gap-1 border-t border-line px-3 py-2">
                    {entry.editable !== false && (
                        <Link
                            href={`/registros/${entry.type}/${entry.id}/editar`}
                            className="inline-flex min-h-12 items-center gap-2 rounded-full px-4 text-base font-semibold text-primary hover:bg-canvas focus-visible:outline-2 focus-visible:outline-primary"
                        >
                            <Pencil aria-hidden="true" className="size-5" />
                            {t('log.edit')}
                        </Link>
                    )}
                    <Button
                        variant="ghost"
                        className="text-danger-ink hover:bg-danger-soft"
                        onClick={() => router.delete(`/registros/${entry.type}/${entry.id}?desde=bitacora`, { preserveScroll: true })}
                    >
                        <Trash2 aria-hidden="true" className="size-5" />
                        {t('log.delete')}
                    </Button>
                </div>
            )}
            </div>
        </li>
    );
}

export default function Bitacora({ tipo, dia, types, entries, next }) {
    const t = useT();
    const format = useFormat();
    const { babies, activeBabyId, timezone } = usePage().props;
    const baby = babies.find((b) => b.id === activeBabyId);

    const setType = (value) =>
        router.get('/bitacora', { ...(value ? { tipo: value } : {}), ...(dia ? { dia } : {}) }, { only: ['tipo', 'dia', 'entries', 'next'], reset: ['entries'], preserveScroll: true, preserveState: true });

    const loadMore = () => router.reload({ data: { antes: next, ...(tipo ? { tipo } : {}), ...(dia ? { dia } : {}) }, only: ['entries', 'next'], preserveScroll: true, preserveUrl: true });

    const today = dayKey(new Date().toISOString(), timezone);
    const yesterday = dayKey(new Date(Date.now() - 86400000).toISOString(), timezone);

    const groups = [];
    for (const entry of entries) {
        const key = dayKey(entry.at, timezone);
        const last = groups.at(-1);
        if (last?.key === key) last.entries.push(entry);
        else groups.push({ key, at: entry.at, entries: [entry] });
    }

    const heading = (group) => {
        if (group.key === today) return t('time.today');
        if (group.key === yesterday) return t('time.yesterday');
        return new Intl.DateTimeFormat(format.locale, { weekday: 'long', day: 'numeric', month: 'long', timeZone: timezone }).format(new Date(group.at));
    };

    const chip = (value, label) => {
        const selected = (tipo ?? null) === value;
        return (
            <button
                key={label}
                type="button"
                aria-pressed={selected}
                onClick={() => setType(value)}
                className={`flex min-h-11 shrink-0 items-center rounded-full px-5 text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                    selected ? 'bg-ink font-semibold text-on-ink' : 'border border-line bg-surface font-medium text-ink'
                }`}
            >
                {label}
            </button>
        );
    };

    return (
        <AppLayout title={t('log.title')}>
            <div className="flex flex-col gap-5">
                <header className="flex items-center gap-3">
                    <div className="flex min-w-0 grow flex-col">
                        {baby && <span className="text-sm text-ink-soft">{t('log.subtitle', { name: baby.first_name })}</span>}
                        <h1 className="text-[34px] leading-tight font-semibold tracking-tight">{t('log.title')}</h1>
                    </div>
                    <a href="/reportes/exportar" aria-label={t('log.export')} className="flex size-12 shrink-0 items-center justify-center rounded-full border border-line bg-surface text-ink focus-visible:outline-2 focus-visible:outline-primary">
                        <Download aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
                    </a>
                </header>

                {dia && (
                    <div className="flex items-center gap-2">
                        <span className="rounded-full bg-appointment px-4 py-2 text-sm font-semibold text-appointment-ink">
                            {t('log.day_filter', { date: new Intl.DateTimeFormat(format.locale, { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${dia}T12:00:00Z`)) })}
                        </span>
                        <button
                            type="button"
                            aria-label={t('log.clear_day')}
                            onClick={() => router.get('/bitacora', tipo ? { tipo } : {}, { only: ['tipo', 'dia', 'entries', 'next'], reset: ['entries'], preserveScroll: true, preserveState: true, replace: true })}
                            className="flex size-12 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary"
                        >
                            <X aria-hidden="true" className="size-5" />
                        </button>
                    </div>
                )}

                <nav aria-label={t('log.filters')} className="-mr-5 flex gap-2 overflow-x-auto pr-5 [scrollbar-width:none]">
                    {chip(null, t('log.all'))}
                    {types.map((type) => chip(type, t(`registrar.tiles.${type}`)))}
                </nav>

                {entries.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center shadow-card">
                        <BookOpen aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                        <p className="text-base text-ink-soft">{t('log.empty')}</p>
                    </div>
                ) : (
                    groups.map((group) => (
                        <section key={group.key} aria-label={heading(group)} className="flex flex-col gap-2.5">
                            <div className="flex items-baseline justify-between">
                                <h2 className="text-xl font-semibold first-letter:uppercase">
                                    {heading(group)}
                                    {(group.key === today || group.key === yesterday) && (
                                        <span className="font-normal text-ink-soft"> · {new Intl.DateTimeFormat(format.locale, { weekday: 'long', day: 'numeric', month: 'short', timeZone: timezone }).format(new Date(group.at))}</span>
                                    )}
                                </h2>
                                <span className="text-[13px] text-ink-soft">{t.choice('log.records', group.entries.length)}</span>
                            </div>
                            <DaySummary entries={group.entries} />
                            <ul className="relative flex flex-col gap-2.5 before:absolute before:top-6 before:bottom-6 before:left-[22px] before:w-0.5 before:bg-line">
                                {group.entries.map((entry) => (
                                    <Entry key={`${entry.type}-${entry.id}`} entry={entry} />
                                ))}
                            </ul>
                        </section>
                    ))
                )}

                {next && (
                    <Button variant="secondary" className="w-full" onClick={loadMore}>
                        {t('log.load_more')}
                    </Button>
                )}
            </div>
        </AppLayout>
    );
}
