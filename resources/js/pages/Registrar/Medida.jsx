import { Ruler } from 'lucide-react';
import ChoiceChips from '../../components/form/Chips';
import RegistersAs from '../../components/form/RegistersAs';
import Stepper from '../../components/form/Stepper';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Medida({ record, baby, clock, lastAt, last, units }) {
    const t = useT();
    const [form, save] = useActivityForm('medida', { occurred_at: clock.now, type: last?.type ?? 'weight', value: last?.value ?? '' }, record);
    const { data, setData, errors } = form;

    const unitOf = { weight: units.weight, height: units.height, head_circumference: units.height, temperature: `°${units.temperature}` };
    const stepOf = { weight: 0.1, height: 0.5, head_circumference: 0.5, temperature: 0.1 };

    return (
        <SheetLayout
            title={t('registrar.measure.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="growth"
            icon={Ruler}
            actionLabel={t('registrar.measure.save')}
            disabled={data.value === '' || Number(data.value) <= 0}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <ChoiceChips
                label={t('registrar.type')}
                value={data.type}
                onChange={(v) => setData('type', v)}
                options={['weight', 'height', 'head_circumference', 'temperature'].map((k) => ({ value: k, label: t(`registrar.measure.${k}`) }))}
            />

            <Stepper
                label={t('registrar.measure.value')}
                value={data.value}
                onChange={(v) => setData('value', v)}
                step={stepOf[data.type]}
                min={0}
                max={500}
                unit={unitOf[data.type]}
                error={errors.value}
            />

            <TimeBlock label={t('registrar.measure.date')} value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <RegistersAs />
        </SheetLayout>
    );
}
