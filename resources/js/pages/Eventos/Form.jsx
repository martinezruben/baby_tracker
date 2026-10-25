import { router, useForm } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useRef, useState } from 'react';
import Button from '../../components/Button';
import Field from '../../components/Field';
import OptionGrid from '../../components/form/OptionGrid';
import RegistersAs from '../../components/form/RegistersAs';
import SheetLayout from '../../layouts/SheetLayout';
import { KINDS } from '../../lib/activities';
import { useT } from '../../lib/i18n';
import { EVENT_TYPES } from '../../lib/lifeEvents';

const DETAIL = { tooth: 'tooth', food: 'food', word: 'word', trip: 'trip' };

export default function Form({ event, baby, today, types }) {
    const t = useT();
    const editing = Boolean(event);
    const input = useRef(null);
    const [confirming, setConfirming] = useState(false);

    const form = useForm({
        type: event?.type ?? 'tooth',
        occurred_on: event?.occurred_on ?? today,
        title: event?.title ?? '',
        detail: event?.detail ?? '',
        notes: event?.notes ?? '',
        photos: [],
    });
    const { data, setData, errors } = form;

    const detailKey = DETAIL[data.type];

    return (
        <SheetLayout
            title={editing ? t('eventos.edit') : t('eventos.new')}
            subtitle={baby.first_name}
            kind="moment"
            icon={Star}
            closeHref={editing ? `/eventos/${event.id}` : '/eventos'}
            actionLabel={t('eventos.form.save')}
            disabled={data.type === 'other' && !data.title.trim()}
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({ ...d, title: d.title || null, detail: d.detail || null, notes: d.notes || null, ...(editing ? { photos: undefined } : {}) }));
                editing ? form.put(`/eventos/${event.id}`) : form.post('/eventos');
            }}
        >
            <OptionGrid
                label={t('eventos.form.type')}
                value={data.type}
                onChange={(v) => setData('type', v)}
                options={types.map((type) => {
                    const { icon, kind } = EVENT_TYPES[type];
                    return { value: type, label: t(`eventos.types.${type}`), icon, iconClass: KINDS[kind].ink };
                })}
            />
            {errors.type && <p role="alert" className="-mt-3 text-sm font-medium text-danger-ink">{errors.type}</p>}

            <Field label={t('eventos.form.date')} type="date" max={today} value={data.occurred_on} onChange={(e) => setData('occurred_on', e.target.value)} error={errors.occurred_on} />

            {data.type === 'other' && (
                <Field label={t('eventos.form.title')} value={data.title} onChange={(e) => setData('title', e.target.value)} error={errors.title} maxLength={120} />
            )}
            <Field
                label={detailKey ? t(`eventos.form.detail_${detailKey}`) : t('eventos.form.detail')}
                optional={t('common.optional')}
                placeholder={detailKey ? t(`eventos.form.detail_${detailKey}_ph`) : undefined}
                value={data.detail}
                onChange={(e) => setData('detail', e.target.value)}
                error={errors.detail}
                maxLength={120}
            />
            <Field label={t('eventos.form.notes')} optional={t('common.optional')} error={errors.notes}>
                {({ id }) => (
                    <textarea
                        id={id}
                        rows={3}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        maxLength={5000}
                        className="min-h-24 rounded-2xl border border-ink/20 bg-surface p-4 text-base focus-visible:outline-2 focus-visible:outline-primary"
                    />
                )}
            </Field>

            {!editing && (
                <div className="flex flex-col gap-2">
                    <div className="text-[15px] font-semibold text-ink-soft">{t('eventos.form.photos')}</div>
                    <input
                        ref={input}
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        aria-label={t('eventos.form.photos_add')}
                        onChange={(e) => setData('photos', Array.from(e.target.files))}
                    />
                    <Button variant="secondary" onClick={() => input.current?.click()}>
                        {t('eventos.form.photos_add')}
                    </Button>
                    {data.photos.length > 0 && <p className="text-sm text-ink-soft">{t.choice('eventos.form.photos_selected', data.photos.length)}</p>}
                    {(errors.photos || errors['photos.0']) && <p role="alert" className="text-sm font-medium text-danger-ink">{errors.photos ?? errors['photos.0']}</p>}
                </div>
            )}

            {editing &&
                (confirming ? (
                    <Button variant="secondary" className="border-danger-line text-danger-ink" onClick={() => router.delete(`/eventos/${event.id}`)}>
                        {t('eventos.form.delete_confirm')}
                    </Button>
                ) : (
                    <Button variant="ghost" className="text-danger-ink" onClick={() => setConfirming(true)}>
                        {t('eventos.form.delete')}
                    </Button>
                ))}

            <RegistersAs />
        </SheetLayout>
    );
}
