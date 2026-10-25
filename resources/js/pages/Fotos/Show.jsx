import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, EyeOff, Heart, MapPin, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import Field from '../../components/Field';
import Section from '../../components/form/Section';
import AppLayout from '../../layouts/AppLayout';
import { eventTitle } from '../../lib/lifeEvents';
import { useT } from '../../lib/i18n';

function TagsEditor({ value, onChange, suggestions }) {
    const t = useT();
    const [draft, setDraft] = useState('');

    const add = (raw) => {
        const name = raw.trim().replace(/\s+/g, ' ').toLowerCase().slice(0, 40);
        if (name && !value.includes(name)) onChange([...value, name]);
        setDraft('');
    };

    return (
        <div className="flex flex-col gap-3">
            {value.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {value.map((tag) => (
                        <li key={tag} className="flex items-center gap-1 rounded-full bg-growth py-1 pr-1 pl-4 text-sm font-semibold text-growth-ink">
                            {tag}
                            <button
                                type="button"
                                aria-label={`${t('common.remove')}: ${tag}`}
                                onClick={() => onChange(value.filter((v) => v !== tag))}
                                className="flex size-10 items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-primary"
                            >
                                <X aria-hidden="true" className="size-4" strokeWidth={2.5} />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            <div className="flex gap-2">
                <input
                    list="photo-tag-suggestions"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            add(draft);
                        }
                    }}
                    placeholder={t('fotos.detail.tag_ph')}
                    aria-label={t('fotos.detail.tag_ph')}
                    maxLength={40}
                    className="min-h-12 min-w-0 grow rounded-2xl border border-line bg-surface px-4 text-base focus-visible:outline-2 focus-visible:outline-primary"
                />
                <datalist id="photo-tag-suggestions">
                    {suggestions.filter((s) => !value.includes(s)).map((s) => (
                        <option key={s} value={s} />
                    ))}
                </datalist>
                <Button variant="secondary" onClick={() => add(draft)} disabled={!draft.trim()}>
                    {t('fotos.detail.tag_add')}
                </Button>
            </div>
        </div>
    );
}

export default function Show({ photo, newer_id, older_id, suggestions }) {
    const t = useT();
    const [confirming, setConfirming] = useState(false);

    const form = useForm({
        caption: photo.caption ?? '',
        taken_at: photo.taken_local,
        place_name: photo.place_name ?? '',
        tags: photo.tags,
    });
    const { data, setData, errors } = form;

    const nav = (id, label, Icon, side) =>
        id ? (
            <Link
                href={`/fotos/${id}`}
                aria-label={label}
                className={`absolute top-1/2 ${side} flex size-12 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white focus-visible:outline-2 focus-visible:outline-white`}
            >
                <Icon aria-hidden="true" className="size-6" strokeWidth={2} />
            </Link>
        ) : null;

    return (
        <AppLayout title={photo.caption || t('fotos.title')}>
            <div className="flex flex-col gap-4">
                <div className="flex items-center justify-between">
                    <Link href="/fotos" className="inline-flex min-h-12 items-center gap-2 text-base font-medium text-primary">
                        <ArrowLeft aria-hidden="true" className="size-4" />
                        {t('fotos.back')}
                    </Link>
                    <button
                        type="button"
                        aria-pressed={photo.favorite}
                        aria-label={photo.favorite ? t('fotos.detail.unfavorite') : t('fotos.detail.favorite')}
                        onClick={() => router.post(`/fotos/${photo.id}/favorita`, {}, { preserveScroll: true })}
                        className="flex size-12 items-center justify-center rounded-full bg-surface shadow-soft focus-visible:outline-2 focus-visible:outline-primary"
                    >
                        <Heart aria-hidden="true" className={`size-6 ${photo.favorite ? 'fill-danger text-danger' : 'text-ink'}`} strokeWidth={1.75} />
                    </button>
                </div>

                <div className="relative overflow-hidden rounded-3xl bg-track">
                    <img src={photo.url} alt={photo.caption ?? ''} width={photo.width} height={photo.height} className="mx-auto max-h-[65dvh] w-full object-contain" />
                    {nav(newer_id, t('fotos.detail.newer'), ChevronLeft, 'left-2')}
                    {nav(older_id, t('fotos.detail.older'), ChevronRight, 'right-2')}
                </div>

                {photo.event && (
                    <Link href={`/eventos/${photo.event.id}`} className="text-[15px] font-semibold text-primary underline">
                        {t('fotos.detail.from_event', { title: eventTitle(t, photo.event) })}
                    </Link>
                )}

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(`/fotos/${photo.id}`, { preserveScroll: true });
                    }}
                    className="flex flex-col gap-4"
                >
                    <Section>
                        <Field label={t('fotos.detail.caption')} optional={t('common.optional')} value={data.caption} onChange={(e) => setData('caption', e.target.value)} error={errors.caption} maxLength={500} />
                        <Field label={t('fotos.detail.taken')} type="datetime-local" value={data.taken_at} onChange={(e) => setData('taken_at', e.target.value)} error={errors.taken_at} />
                        <Field label={t('fotos.detail.place')} optional={t('common.optional')} placeholder={t('fotos.detail.place_ph')} value={data.place_name} onChange={(e) => setData('place_name', e.target.value)} error={errors.place_name} maxLength={120} />

                        {photo.latitude !== null && (
                            <div className="flex flex-wrap items-center gap-2">
                                <a
                                    href={`https://www.openstreetmap.org/?mlat=${photo.latitude}&mlon=${photo.longitude}#map=16/${photo.latitude}/${photo.longitude}`}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    className="inline-flex min-h-12 items-center gap-2 rounded-full border border-line px-4 text-[15px] font-medium focus-visible:outline-2 focus-visible:outline-primary"
                                >
                                    <MapPin aria-hidden="true" className="size-4" />
                                    {t('fotos.detail.map')}
                                </a>
                            </div>
                        )}
                        {photo.has_location && (
                            <Button variant="ghost" onClick={() => router.delete(`/fotos/${photo.id}/ubicacion`, { preserveScroll: true })}>
                                <EyeOff aria-hidden="true" className="size-5" />
                                {t('fotos.detail.hide_location')}
                            </Button>
                        )}
                    </Section>

                    <Section title={t('fotos.detail.tags')} error={errors.tags}>
                        <TagsEditor value={data.tags} onChange={(v) => setData('tags', v)} suggestions={suggestions} />
                    </Section>

                    <Button type="submit" disabled={form.processing || !form.isDirty}>
                        {t('fotos.detail.save')}
                    </Button>
                </form>

                <p className="text-sm text-ink-soft">
                    {photo.by && `${t('fotos.detail.by', { name: photo.by })}. `}
                    {t('fotos.detail.privacy')}
                </p>

                <div className="flex justify-center">
                    {confirming ? (
                        <Button variant="secondary" className="border-danger-line text-danger-ink" onClick={() => router.delete(`/fotos/${photo.id}`)}>
                            {t('fotos.detail.delete_confirm')}
                        </Button>
                    ) : (
                        <Button variant="ghost" className="text-danger-ink" onClick={() => setConfirming(true)}>
                            <Trash2 aria-hidden="true" className="size-5" />
                            {t('fotos.detail.delete')}
                        </Button>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
