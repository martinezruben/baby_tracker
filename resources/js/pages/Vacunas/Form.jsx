import { useForm } from '@inertiajs/react';
import { Syringe } from 'lucide-react';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import MoreDetails from '../../components/form/MoreDetails';
import RegistersAs from '../../components/form/RegistersAs';
import SheetLayout from '../../layouts/SheetLayout';
import { useT } from '../../lib/i18n';

export default function Form({ baby, clock, pending, selected }) {
    const t = useT();
    const initial = pending.find((p) => p.id === selected)?.id ?? pending[0]?.id ?? '';

    const form = useForm({
        vaccine_dose_id: initial,
        vaccine_name: '',
        dose_label: '',
        administered_at: clock.now,
        lot: '',
        place: '',
        administered_by: '',
        notes: '',
    });
    const { data, setData, errors } = form;
    const isOther = data.vaccine_dose_id === '';

    return (
        <SheetLayout
            title={t('vacunas.form.title')}
            subtitle={baby.first_name}
            kind="vaccine"
            icon={Syringe}
            closeHref="/vacunas"
            actionLabel={t('vacunas.form.save')}
            disabled={isOther && !data.vaccine_name.trim()}
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({
                    ...d,
                    vaccine_dose_id: d.vaccine_dose_id === '' ? null : d.vaccine_dose_id,
                    vaccine_name: d.vaccine_dose_id === '' ? d.vaccine_name : null,
                    dose_label: d.vaccine_dose_id === '' ? d.dose_label || null : null,
                    lot: d.lot || null,
                    place: d.place || null,
                    administered_by: d.administered_by || null,
                    notes: d.notes || null,
                }));
                form.post('/vacunas');
            }}
        >
            {pending.length === 0 && <p className="rounded-[20px] bg-canvas p-4 text-base">{t('vacunas.form.none_pending')}</p>}

            <ChoiceChips
                label={t('vacunas.form.which')}
                value={data.vaccine_dose_id}
                onChange={(v) => setData('vaccine_dose_id', v)}
                options={[...pending.map((p) => ({ value: p.id, label: [p.name, p.label].filter(Boolean).join(' · ') })), { value: '', label: t('vacunas.form.other') }]}
            />
            {errors.vaccine_dose_id && (
                <p role="alert" className="-mt-3 text-sm font-medium text-danger-ink">
                    {errors.vaccine_dose_id}
                </p>
            )}

            {isOther && (
                <>
                    <Field label={t('vacunas.form.name')} value={data.vaccine_name} onChange={(e) => setData('vaccine_name', e.target.value)} error={errors.vaccine_name} maxLength={80} />
                    <Field label={t('vacunas.form.label')} optional={t('common.optional')} placeholder={t('vacunas.form.label_ph')} value={data.dose_label} onChange={(e) => setData('dose_label', e.target.value)} maxLength={40} />
                </>
            )}

            <Field label={t('vacunas.form.when')} type="datetime-local" max={clock.now} value={data.administered_at} onChange={(e) => setData('administered_at', e.target.value)} error={errors.administered_at} />

            <MoreDetails hint={t('vacunas.form.details_hint')}>
                <Field label={t('vacunas.form.lot')} optional={t('common.optional')} value={data.lot} onChange={(e) => setData('lot', e.target.value)} maxLength={60} />
                <Field label={t('vacunas.form.place')} optional={t('common.optional')} value={data.place} onChange={(e) => setData('place', e.target.value)} maxLength={120} />
                <Field label={t('vacunas.form.by')} optional={t('common.optional')} value={data.administered_by} onChange={(e) => setData('administered_by', e.target.value)} maxLength={120} />
                <Field label={t('vacunas.form.notes')} optional={t('common.optional')} value={data.notes} onChange={(e) => setData('notes', e.target.value)} maxLength={2000} />
            </MoreDetails>

            <RegistersAs />
        </SheetLayout>
    );
}
