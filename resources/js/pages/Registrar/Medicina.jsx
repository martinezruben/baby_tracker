import { Pill } from 'lucide-react';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import RegistersAs from '../../components/form/RegistersAs';
import Stepper from '../../components/form/Stepper';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Medicina({ record, baby, clock, lastAt, medicines }) {
    const t = useT();
    const first = medicines[0];
    const [form, save] = useActivityForm('medicina', {
        occurred_at: clock.now,
        medicine_id: first?.id ?? '',
        new_medicine: '',
        dose: first?.typical_dose ? Number(first.typical_dose) : 1,
        unit: first?.unit ?? '',
    }, record);
    const { data, setData, errors } = form;
    const isNew = data.medicine_id === '';

    const pick = (id) => {
        const medicine = medicines.find((m) => m.id === id);
        setData((d) => ({
            ...d,
            medicine_id: id,
            new_medicine: '',
            dose: medicine?.typical_dose ? Number(medicine.typical_dose) : d.dose,
            unit: medicine?.unit ?? '',
        }));
    };

    return (
        <SheetLayout
            title={t('registrar.medicine.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="medicine"
            icon={Pill}
            actionLabel={t('registrar.medicine.save')}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({ ...d, medicine_id: d.medicine_id === '' ? null : d.medicine_id, unit: d.unit || null }));
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <ChoiceChips
                label={t('registrar.medicine.which')}
                value={data.medicine_id}
                onChange={pick}
                options={[...medicines.map((m) => ({ value: m.id, label: m.name })), { value: '', label: t('registrar.medicine.other') }]}
            />
            {errors.medicine_id && (
                <p role="alert" className="-mt-3 text-sm font-medium text-red-700">
                    {errors.medicine_id}
                </p>
            )}
            {isNew && (
                <Field
                    label={t('registrar.medicine.name')}
                    value={data.new_medicine}
                    onChange={(e) => setData('new_medicine', e.target.value)}
                    error={errors.new_medicine}
                    maxLength={80}
                />
            )}

            <Stepper label={t('registrar.medicine.dose')} value={data.dose} onChange={(v) => setData('dose', v)} step={0.5} min={0} max={10000} error={errors.dose} />
            <Field
                label={t('registrar.medicine.unit')}
                optional={t('common.optional')}
                placeholder="ml, gotas, mg…"
                value={data.unit}
                onChange={(e) => setData('unit', e.target.value)}
                maxLength={8}
            />

            <RegistersAs />
        </SheetLayout>
    );
}
