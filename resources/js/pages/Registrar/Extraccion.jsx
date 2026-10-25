import { Droplet } from 'lucide-react';
import Field from '../../components/Field';
import MoreDetails from '../../components/form/MoreDetails';
import RegistersAs from '../../components/form/RegistersAs';
import Stepper from '../../components/form/Stepper';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Extraccion({ record, baby, clock, lastAt, volumeUnit }) {
    const t = useT();
    const unit = record?.unit ?? volumeUnit;
    const [form, save] = useActivityForm('extraccion', { occurred_at: clock.now, left_amount: 0, right_amount: 0, unit, duration_minutes: '' }, record);
    const { data, setData, errors } = form;
    const step = unit === 'oz' ? 0.5 : 10;
    const total = (Number(data.left_amount) || 0) + (Number(data.right_amount) || 0);

    return (
        <SheetLayout
            title={t('registrar.pump.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="food"
            icon={Droplet}
            actionLabel={t('registrar.pump.save')}
            disabled={total <= 0}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />
            <Stepper label={t('registrar.pump.left')} value={data.left_amount} onChange={(v) => setData('left_amount', v)} step={step} min={0} max={1000} unit={unit} error={errors.left_amount} />
            <Stepper label={t('registrar.pump.right')} value={data.right_amount} onChange={(v) => setData('right_amount', v)} step={step} min={0} max={1000} unit={unit} error={errors.right_amount} />
            <p role="status" className="text-center text-base font-semibold">
                {t('registrar.pump.total', { amount: String(Math.round(total * 100) / 100).replace('.', ','), unit })}
            </p>

            <MoreDetails hint={t('registrar.pump.details_hint')} defaultOpen={Boolean(record?.duration_minutes)}>
                <Field
                    label={t('registrar.pump.duration')}
                    optional={t('common.optional')}
                    type="number"
                    inputMode="numeric"
                    min="1"
                    max="240"
                    value={data.duration_minutes}
                    onChange={(e) => setData('duration_minutes', e.target.value)}
                    error={errors.duration_minutes}
                />
            </MoreDetails>

            <RegistersAs />
        </SheetLayout>
    );
}
