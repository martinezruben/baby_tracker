import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { useT } from '../lib/i18n';
import { shiftLocal } from '../lib/time';
import useFormat from '../lib/useFormat';
import Button from './Button';
import Field from './Field';
import ChoiceChips from './form/Chips';
import Stepper from './form/Stepper';

/** Formulario de un tratamiento (medicina cada N horas durante X días); lo usan la cita y la pantalla Medicinas. */
export default function PrescriptionForm({ action, medicines, intervals, clock, openLabel }) {
    const t = useT();
    const format = useFormat();
    const [open, setOpen] = useState(false);
    const first = medicines[0];

    const form = useForm({
        medicine_id: first?.id ?? '',
        new_medicine: '',
        dose: first?.typical_dose ? Number(first.typical_dose) : 1,
        unit: first?.unit ?? '',
        interval_hours: 8,
        days: 7,
        starts_at: clock.now,
        instructions: '',
    });
    const { data, setData, errors } = form;

    const totalDoses = Math.max(1, Math.ceil((Number(data.days) * 24) / data.interval_hours));
    const lastAt = shiftLocal(data.starts_at, (totalDoses - 1) * data.interval_hours * 60);
    const lastLabel = new Intl.DateTimeFormat(format.locale, { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${lastAt}:00Z`));
    const isNew = data.medicine_id === '';

    const pick = (id) => {
        const medicine = medicines.find((m) => m.id === id);
        setData((d) => ({ ...d, medicine_id: id, new_medicine: '', dose: medicine?.typical_dose ? Number(medicine.typical_dose) : d.dose, unit: medicine?.unit ?? '' }));
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({
            medicine_id: d.medicine_id === '' ? null : d.medicine_id,
            new_medicine: d.new_medicine || null,
            dose: d.dose,
            unit: d.unit || null,
            interval_hours: d.interval_hours,
            total_doses: totalDoses,
            starts_at: d.starts_at,
            instructions: d.instructions || null,
        }));
        form.post(action, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    if (!open) {
        return (
            <Button variant="secondary" onClick={() => setOpen(true)}>
                <Plus aria-hidden="true" className="size-5" />
                {openLabel ?? t('citas.rx.add')}
            </Button>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-4 rounded-3xl bg-canvas p-4">
            <ChoiceChips
                label={t('citas.rx.which')}
                value={data.medicine_id}
                onChange={pick}
                options={[...medicines.map((m) => ({ value: m.id, label: m.name })), { value: '', label: t('citas.rx.other') }]}
            />
            {errors.medicine_id && <p role="alert" className="text-sm font-medium text-danger-ink">{errors.medicine_id}</p>}
            {isNew && <Field label={t('citas.rx.name')} value={data.new_medicine} onChange={(e) => setData('new_medicine', e.target.value)} error={errors.new_medicine} maxLength={80} />}

            <Stepper label={t('citas.rx.dose')} value={data.dose} onChange={(v) => setData('dose', v)} step={0.5} min={0} max={10000} error={errors.dose} />
            <Field label={t('citas.rx.unit')} optional={t('common.optional')} placeholder="ml, gotas, mg…" value={data.unit} onChange={(e) => setData('unit', e.target.value)} maxLength={8} />

            <ChoiceChips
                label={t('citas.rx.every')}
                value={data.interval_hours}
                onChange={(v) => setData('interval_hours', v)}
                options={intervals.map((h) => ({ value: h, label: t('citas.rx.hours', { h }) }))}
            />
            <ChoiceChips
                label={t('citas.rx.days')}
                value={data.days}
                onChange={(v) => setData('days', v)}
                options={[3, 5, 7, 10, 14].map((n) => ({ value: n, label: t('citas.rx.days_n', { n }) }))}
            />
            <Field label={t('citas.rx.start')} type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} error={errors.starts_at} />
            <Field label={t('citas.rx.instructions')} optional={t('common.optional')} placeholder={t('citas.rx.instructions_ph')} value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} error={errors.instructions} maxLength={300} />

            <p role="status" className="text-sm font-medium text-primary">
                {t('citas.rx.preview', { n: totalDoses, date: lastLabel })}
            </p>
            {(errors.total_doses || errors.interval_hours) && <p role="alert" className="text-sm font-medium text-danger-ink">{errors.total_doses ?? errors.interval_hours}</p>}
            <p className="text-sm text-ink-soft">{t('citas.rx.auto_note')}</p>

            <div className="flex gap-3">
                <Button variant="secondary" onClick={() => setOpen(false)}>
                    {t('common.back')}
                </Button>
                <Button type="submit" className="flex-1" disabled={form.processing || (isNew && !data.new_medicine.trim())}>
                    {t('citas.rx.save')}
                </Button>
            </div>
        </form>
    );
}

const DOSE_STYLES = { pending: 'bg-appointment text-appointment-ink', given: 'bg-vaccine text-vaccine-ink', skipped: 'bg-ink/10 text-ink-soft' };
