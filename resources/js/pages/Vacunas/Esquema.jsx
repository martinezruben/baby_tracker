import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import Field from '../../components/Field';
import Section from '../../components/form/Section';
import Switch from '../../components/Switch';
import AppLayout from '../../layouts/AppLayout';
import { doseAgeLabel, useT } from '../../lib/i18n';

function RenameForm({ name }) {
    const t = useT();
    const form = useForm({ name });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.patch('/vacunas/esquema/nombre', { preserveScroll: true });
            }}
            className="flex flex-col gap-3"
        >
            <Field label={t('vacunas.schedule.name')} placeholder={t('vacunas.schedule.name_ph')} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} maxLength={80} />
            <Button type="submit" variant="secondary" disabled={form.processing || !form.isDirty || !form.data.name.trim()}>
                {t('common.save')}
            </Button>
        </form>
    );
}

function AddDose() {
    const t = useT();
    const form = useForm({ vaccine_name: '', dose_label: '', age_months: '', notes: '' });
    const { data, setData, errors } = form;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({ ...d, dose_label: d.dose_label || null, notes: d.notes || null, age_months: d.age_months === '' ? null : Number(String(d.age_months).replace(',', '.')) }));
                form.post('/vacunas/esquema/dosis', { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="flex flex-col gap-3"
        >
            <Field label={t('vacunas.schedule.vaccine')} value={data.vaccine_name} onChange={(e) => setData('vaccine_name', e.target.value)} error={errors.vaccine_name} maxLength={80} />
            <div className="grid grid-cols-2 gap-3">
                <Field label={t('vacunas.schedule.dose')} optional={t('common.optional')} value={data.dose_label} onChange={(e) => setData('dose_label', e.target.value)} error={errors.dose_label} maxLength={40} />
                <Field label={t('vacunas.schedule.age')} inputMode="decimal" value={data.age_months} onChange={(e) => setData('age_months', e.target.value)} error={errors.age_months} />
            </div>
            <p className="-mt-1 text-sm text-ink-soft">{t('vacunas.schedule.age_help')}</p>
            <Field label={t('vacunas.schedule.notes')} optional={t('common.optional')} value={data.notes} onChange={(e) => setData('notes', e.target.value)} error={errors.notes} maxLength={200} />
            <Button type="submit" disabled={form.processing || !data.vaccine_name.trim() || data.age_months === ''}>
                {t('vacunas.schedule.save_dose')}
            </Button>
        </form>
    );
}

function ImportForm({ hasDoses }) {
    const t = useT();
    const form = useForm({ text: '', replace: false });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post('/vacunas/esquema/importar', { preserveScroll: true, onSuccess: () => form.reset('text') });
            }}
            className="flex flex-col gap-3"
        >
            <p className="text-sm text-ink-soft">{t('vacunas.schedule.import_help')}</p>
            <Field label={t('vacunas.schedule.import_label')} error={form.errors.text}>
                {({ id, describedBy }) => (
                    <textarea
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={form.errors.text ? 'true' : undefined}
                        rows={7}
                        value={form.data.text}
                        onChange={(e) => form.setData('text', e.target.value)}
                        placeholder={t('vacunas.schedule.import_example')}
                        spellCheck={false}
                        className="min-h-40 rounded-2xl border border-ink/20 bg-surface p-4 font-mono text-[15px] focus-visible:outline-2 focus-visible:outline-primary"
                    />
                )}
            </Field>
            {hasDoses && <Switch label={t('vacunas.schedule.import_replace')} checked={form.data.replace} onChange={(v) => form.setData('replace', v)} />}
            <Button type="submit" disabled={form.processing || !form.data.text.trim()}>
                {t('vacunas.schedule.import_button')}
            </Button>
        </form>
    );
}

function DoseItem({ dose }) {
    const t = useT();
    const [confirming, setConfirming] = useState(false);

    return (
        <li className="flex items-center gap-2 rounded-2xl bg-canvas p-2 pl-3">
            <span className="min-w-0 grow">
                <span className="block text-[15px] font-semibold break-words">{[dose.name, dose.label].filter(Boolean).join(' · ')}</span>
                <span className="text-[13px] text-ink-soft">
                    {doseAgeLabel(t, dose.age_days)}
                    {dose.notes && ` · ${dose.notes}`}
                </span>
            </span>
            {confirming ? (
                <Button variant="secondary" className="border-red-300 px-4 text-red-700" onClick={() => router.delete(`/vacunas/esquema/dosis/${dose.id}`, { preserveScroll: true })}>
                    {t('vacunas.schedule.delete_confirm')}
                </Button>
            ) : (
                <button
                    type="button"
                    aria-label={`${t('vacunas.schedule.delete')}: ${dose.name}`}
                    onClick={() => setConfirming(true)}
                    className="flex size-12 shrink-0 items-center justify-center rounded-full bg-surface text-red-700 focus-visible:outline-2 focus-visible:outline-primary"
                >
                    <Trash2 aria-hidden="true" className="size-5" strokeWidth={1.75} />
                </button>
            )}
        </li>
    );
}

export default function Esquema({ name, doses }) {
    const t = useT();

    return (
        <AppLayout title={t('vacunas.schedule.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/vacunas" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('vacunas.back')}
                </Link>
                <header>
                    <h1 className="text-2xl leading-tight font-bold">{t('vacunas.schedule.title')}</h1>
                    <p className="text-sm text-ink-soft">{t('vacunas.schedule.intro')}</p>
                </header>

                <Section>
                    <RenameForm name={name} />
                </Section>

                <Section title={t('vacunas.schedule.import_title')}>
                    <ImportForm hasDoses={doses.length > 0} />
                </Section>

                <Section title={t('vacunas.schedule.list')}>
                    {doses.length === 0 ? (
                        <p className="text-[15px] text-ink-soft">{t('vacunas.schedule.empty')}</p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {doses.map((d) => (
                                <DoseItem key={d.id} dose={d} />
                            ))}
                        </ul>
                    )}
                </Section>

                <Section title={t('vacunas.schedule.add')}>
                    <AddDose />
                </Section>
            </div>
        </AppLayout>
    );
}
