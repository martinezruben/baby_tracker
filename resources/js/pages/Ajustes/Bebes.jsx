import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Plus } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import Section from '../../components/form/Section';
import AppLayout from '../../layouts/AppLayout';
import { ageLabel, useT } from '../../lib/i18n';
import { dayLabel } from '../../lib/lifeEvents';
import useFormat from '../../lib/useFormat';

function BabyForm({ baby, today, onDone }) {
    const t = useT();
    const editing = Boolean(baby);
    const form = useForm({
        first_name: baby?.first_name ?? '',
        last_name: baby?.last_name ?? '',
        birth_date: baby?.birth_date ?? '',
        gender: baby?.gender ?? '',
        feed_warning_minutes: baby?.feed_warning_minutes ?? 180,
        diaper_warning_minutes: baby?.diaper_warning_minutes ?? 180,
    });
    const { data, setData, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, last_name: d.last_name || null, gender: d.gender || null }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) form.reset();
                onDone?.();
            },
        };
        editing ? form.put(`/ajustes/bebes/${baby.id}`, options) : form.post('/ajustes/bebes', options);
    };

    const bind = (name) => ({ value: data[name] ?? '', onChange: (e) => setData(name, e.target.value), error: errors[name] });

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <Field label={t('ajustes.babies.first_name')} maxLength={80} autoComplete="off" {...bind('first_name')} />
            <Field label={t('ajustes.babies.last_name')} optional={t('common.optional')} maxLength={80} {...bind('last_name')} />
            <Field label={t('ajustes.babies.birth')} type="date" max={today} {...bind('birth_date')} />
            <ChoiceChips
                label={t('ajustes.babies.gender')}
                value={data.gender}
                onChange={(v) => setData('gender', v)}
                options={[{ value: 'female', label: t('ajustes.babies.female') }, { value: 'male', label: t('ajustes.babies.male') }, { value: '', label: t('ajustes.babies.unspecified') }]}
            />
            <Field label={t('ajustes.babies.feed_warning')} type="number" inputMode="numeric" min="15" max="720" {...bind('feed_warning_minutes')} />
            <Field label={t('ajustes.babies.diaper_warning')} type="number" inputMode="numeric" min="15" max="720" {...bind('diaper_warning_minutes')} />
            <Button type="submit" disabled={form.processing || !data.first_name.trim() || !data.birth_date}>
                {editing ? t('ajustes.babies.save') : t('ajustes.babies.create')}
            </Button>
        </form>
    );
}

function Row({ baby, today, last }) {
    const t = useT();
    const format = useFormat();
    const [open, setOpen] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const { delete: destroy, processing } = useForm({});

    return (
        <li className="rounded-[22px] bg-surface shadow-card">
            <button type="button" aria-expanded={open} onClick={() => setOpen(!open)} className="flex min-h-[72px] w-full items-center gap-3 p-3 text-left focus-visible:outline-2 focus-visible:outline-primary">
                <span aria-hidden="true" className="flex size-[46px] shrink-0 items-center justify-center rounded-full bg-diaper text-lg font-bold text-diaper-ink">
                    {baby.first_name.slice(0, 1).toUpperCase()}
                </span>
                <span className="flex min-w-0 grow flex-col">
                    <span className="text-base font-bold break-words">{[baby.first_name, baby.last_name].filter(Boolean).join(' ')}</span>
                    <span className="text-[13px] text-ink-soft">
                        {ageLabel(t, baby.birth_date)} · {dayLabel(baby.birth_date, format.locale, true)}
                    </span>
                </span>
                <ChevronDown aria-hidden="true" className={`size-5 shrink-0 text-ink-soft transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>
            {open && (
                <div className="flex flex-col gap-4 border-t border-line p-4">
                    <BabyForm baby={baby} today={today} onDone={() => setOpen(false)} />
                    <p className="text-sm text-ink-soft">{t('ajustes.babies.hint')}</p>
                    {!last &&
                        (confirming ? (
                            <Button variant="secondary" className="border-red-300 text-red-700" disabled={processing} onClick={() => destroy(`/ajustes/bebes/${baby.id}`, { preserveScroll: true })}>
                                {t('ajustes.babies.delete_confirm')}
                            </Button>
                        ) : (
                            <Button variant="ghost" className="text-red-700" onClick={() => setConfirming(true)}>
                                {t('ajustes.babies.delete')}
                            </Button>
                        ))}
                </div>
            )}
        </li>
    );
}

export default function Bebes({ babies, today }) {
    const t = useT();
    const [adding, setAdding] = useState(false);

    return (
        <AppLayout title={t('ajustes.babies.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/ajustes" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('ajustes.back')}
                </Link>
                <h1 className="text-2xl leading-tight font-bold">{t('ajustes.babies.title')}</h1>

                <ul className="flex flex-col gap-3">
                    {babies.map((b) => (
                        <Row key={b.id} baby={b} today={today} last={babies.length === 1} />
                    ))}
                </ul>

                {adding ? (
                    <Section title={t('ajustes.babies.add')}>
                        <BabyForm today={today} onDone={() => setAdding(false)} />
                        <Button variant="ghost" onClick={() => setAdding(false)}>
                            {t('ajustes.babies.cancel')}
                        </Button>
                    </Section>
                ) : (
                    <Button onClick={() => setAdding(true)}>
                        <Plus aria-hidden="true" className="size-5" />
                        {t('ajustes.babies.add')}
                    </Button>
                )}
            </div>
        </AppLayout>
    );
}
