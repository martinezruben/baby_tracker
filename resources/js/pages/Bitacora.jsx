import { Link, router, usePage } from '@inertiajs/react';
import { BookOpen, Pencil, Trash2, X } from 'lucide-react';
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
        <li className="rounded-[22px] bg-surface shadow-card">
            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen(!open)}
                className="flex min-h-[72px] w-full items-center gap-3 p-3 text-left focus-visible:outline-2 focus-visible:outline-primary"
            >
                <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                    <Icon aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </span>
                <span className="flex min-w-0 grow flex-col gap-0.5">
                    <span className="text-base font-bold break-words">{title}</span>
                    {detail && <span className="line-clamp-2 text-[13px] break-words text-ink-soft">{detail}</span>}
                </span>
                <span className="flex shrink-0 flex-col items-end gap-0.5">
                    <span className="text-sm font-semibold">{format.time(entry.at)}</span>
                    {entry.by && <span className="max-w-20 truncate text-xs text-ink-soft">{entry.by}</span>}
                </span>
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
                    selected ? 'bg-primary font-semibold text-on-primary' : 'bg-surface font-medium text-ink'
                }`}
            >
                {label}
            </button>
        );
    };

    return (
        <AppLayout title={t('log.title')}>
            <div className="flex flex-col gap-5">
                <header className="flex min-h-14 flex-col justify-center">
                    <h1 className="text-2xl leading-tight font-bold">{t('log.title')}</h1>
                    {baby && <div className="text-sm text-ink-soft">{t('log.subtitle', { name: baby.first_name })}</div>}
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
                            <h2 className="text-[15px] font-semibold text-ink-soft first-letter:uppercase">{heading(group)}</h2>
                            <ul className="flex flex-col gap-2.5">
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
