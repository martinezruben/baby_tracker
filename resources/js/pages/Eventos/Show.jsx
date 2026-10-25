import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ImagePlus, Pencil } from 'lucide-react';
import { useRef, useState } from 'react';
import Button from '../../components/Button';
import Section from '../../components/form/Section';
import AppLayout from '../../layouts/AppLayout';
import { KINDS } from '../../lib/activities';
import { useT } from '../../lib/i18n';
import { dayLabel, EVENT_TYPES, eventTitle } from '../../lib/lifeEvents';

export default function Show({ event }) {
    const t = useT();
    const { locale } = usePage().props;
    const { icon: Icon, kind } = EVENT_TYPES[event.type];
    const input = useRef(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const upload = (fileList) => {
        const photos = Array.from(fileList);
        if (photos.length === 0) return;
        setBusy(true);
        setError(null);
        router.post(`/eventos/${event.id}/fotos`, { photos }, {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => setError(Object.values(errors)[0]),
            onFinish: () => {
                setBusy(false);
                if (input.current) input.current.value = '';
            },
        });
    };

    return (
        <AppLayout title={eventTitle(t, event)}>
            <div className="flex flex-col gap-4">
                <Link href="/eventos" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('eventos.back')}
                </Link>

                <header className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className={`flex size-[52px] shrink-0 items-center justify-center rounded-2xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                            <Icon aria-hidden="true" className="size-7" strokeWidth={1.75} />
                        </span>
                        <div className="min-w-0">
                            <h1 className="text-2xl leading-tight font-bold break-words">{eventTitle(t, event)}</h1>
                            <div className="text-sm text-ink-soft">{dayLabel(event.occurred_on, locale, true)}</div>
                        </div>
                    </div>
                    <Link
                        href={`/eventos/${event.id}/editar`}
                        className="inline-flex min-h-12 shrink-0 items-center gap-2 rounded-full border border-line bg-surface px-5 text-base font-semibold focus-visible:outline-2 focus-visible:outline-primary"
                    >
                        <Pencil aria-hidden="true" className="size-4" />
                        {t('eventos.edit')}
                    </Link>
                </header>

                {event.notes && (
                    <Section>
                        <p className="text-base whitespace-pre-line">{event.notes}</p>
                    </Section>
                )}

                <Section title={t('eventos.show.photos')}>
                    {event.photos.length === 0 ? (
                        <p className="text-[15px] text-ink-soft">{t('eventos.show.no_photos')}</p>
                    ) : (
                        <ul className="grid grid-cols-3 gap-1.5">
                            {event.photos.map((p) => (
                                <li key={p.id}>
                                    <Link href={`/fotos/${p.id}`} className="block aspect-square overflow-hidden rounded-2xl bg-track focus-visible:outline-2 focus-visible:outline-primary">
                                        <img src={p.thumb_url} alt="" loading="lazy" className="size-full object-cover" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        aria-label={t('eventos.show.add_photos')}
                        onChange={(e) => upload(e.target.files)}
                    />
                    <Button variant="secondary" disabled={busy} onClick={() => input.current?.click()}>
                        <ImagePlus aria-hidden="true" className="size-5" />
                        {busy ? t('fotos.uploading') : t('eventos.show.add_photos')}
                    </Button>
                    {error && (
                        <p role="alert" className="text-sm font-medium text-red-700">
                            {error}
                        </p>
                    )}
                </Section>
            </div>
        </AppLayout>
    );
}
