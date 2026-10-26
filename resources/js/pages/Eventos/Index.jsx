import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Plus, Star } from 'lucide-react';
import AppLayout from '../../layouts/AppLayout';
import { KINDS } from '../../lib/activities';
import { useT } from '../../lib/i18n';
import { dayLabel, EVENT_TYPES, eventTitle, monthLabel, RATINGS } from '../../lib/lifeEvents';

function EventCard({ event }) {
    const t = useT();
    const { locale } = usePage().props;
    const { icon: Icon, kind } = EVENT_TYPES[event.type];
    const Rating = event.type === 'food' && event.rating ? RATINGS[event.rating] : null;

    return (
        <li>
            <Link href={`/eventos/${event.id}`} className="flex flex-col gap-2 rounded-[22px] bg-surface p-3 shadow-card focus-visible:outline-2 focus-visible:outline-primary">
                <span className="flex items-center gap-3">
                    <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                        <Icon aria-hidden="true" className="size-6" strokeWidth={1.75} />
                    </span>
                    <span className="flex min-w-0 grow flex-col">
                        <span className="text-base font-bold break-words">{eventTitle(t, event)}</span>
                        <span className="flex items-center gap-1.5 text-[13px] text-ink-soft">
                            {dayLabel(event.occurred_on, locale)}
                            {Rating && (
                                <>
                                    <span aria-hidden="true">·</span>
                                    <Rating aria-hidden="true" className="size-4" strokeWidth={1.75} />
                                    <span>{t(`registrar.solids.enjoy_${event.rating}`)}</span>
                                </>
                            )}
                        </span>
                        {event.seasonings && <span className="line-clamp-1 text-[13px] text-ink-soft/80 italic">{t('eventos.with_seasonings', { list: event.seasonings })}</span>}
                    </span>
                </span>
                {event.notes && <span className="line-clamp-2 text-sm text-ink-mid">{event.notes}</span>}
                {event.photos.length > 0 && (
                    <span className="flex gap-1.5">
                        {event.photos.map((p) => (
                            <img key={p.id} src={p.thumb_url} alt="" loading="lazy" className="size-16 rounded-xl object-cover" />
                        ))}
                    </span>
                )}
            </Link>
        </li>
    );
}

export default function Index({ events, tipo, counts }) {
    const t = useT();
    const { babies, activeBabyId, locale } = usePage().props;
    const baby = babies.find((b) => b.id === activeBabyId);

    const total = Object.values(counts).reduce((sum, n) => sum + Number(n), 0);

    const setType = (value) => router.get('/eventos', value ? { tipo: value } : {}, { preserveScroll: true, preserveState: true, replace: true, only: ['events', 'tipo', 'counts'] });

    const chip = (value, label) => {
        const selected = (tipo ?? null) === value;
        return (
            <button
                key={label}
                type="button"
                aria-pressed={selected}
                onClick={() => setType(value)}
                className={`flex min-h-11 shrink-0 items-center rounded-full px-5 text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${selected ? 'bg-primary font-semibold text-on-primary' : 'bg-surface font-medium text-ink'}`}
            >
                {label}
            </button>
        );
    };

    const groups = [];
    for (const event of events) {
        const key = event.occurred_on.slice(0, 7);
        const last = groups.at(-1);
        if (last?.key === key) last.events.push(event);
        else groups.push({ key, events: [event] });
    }

    return (
        <AppLayout title={t('eventos.title')}>
            <div className="flex flex-col gap-5">
                <Link href="/" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav.home')}
                </Link>

                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl leading-tight font-bold">{t('eventos.title')}</h1>
                        {baby && <div className="text-sm text-ink-soft">{t('eventos.subtitle', { name: baby.first_name })}</div>}
                    </div>
                    <Link
                        href="/eventos/nuevo"
                        className="inline-flex min-h-12 items-center gap-2 rounded-full bg-primary px-6 text-base font-semibold text-on-primary hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                        <Plus aria-hidden="true" className="size-5" />
                        {t('eventos.new')}
                    </Link>
                </header>

                {total > 0 && (
                    <nav aria-label={t('eventos.filters')} className="-mr-5 flex gap-2 overflow-x-auto pr-5 [scrollbar-width:none]">
                        {chip(null, `${t('eventos.all')} · ${total}`)}
                        {Object.keys(EVENT_TYPES)
                            .filter((type) => counts[type] > 0)
                            .map((type) => chip(type, `${t(`eventos.types.${type}`)} · ${counts[type]}`))}
                    </nav>
                )}

                {events.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center shadow-card">
                        <Star aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                        <p className="text-base text-ink-soft">{tipo ? t('eventos.empty_filter') : t('eventos.empty')}</p>
                    </div>
                ) : (
                    groups.map((group) => (
                        <section key={group.key} aria-label={monthLabel(group.key, locale)} className="flex flex-col gap-2.5">
                            <h2 className="text-[15px] font-semibold text-ink-soft">{monthLabel(group.key, locale)}</h2>
                            <ul className="flex flex-col gap-2.5">
                                {group.events.map((event) => (
                                    <EventCard key={event.id} event={event} />
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
