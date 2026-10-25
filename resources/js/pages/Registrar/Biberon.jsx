import { Milk } from 'lucide-react';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import MoreDetails from '../../components/form/MoreDetails';
import RegistersAs from '../../components/form/RegistersAs';
import Stepper from '../../components/form/Stepper';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Biberon({ record, baby, clock, lastAt, last, volumeUnit }) {
    const t = useT();
    const unit = record?.unit ?? last?.unit ?? volumeUnit;
    const quick = unit === 'oz' ? [2, 3, 4, 5] : [60, 90, 120, 150];
    const [form, save] = useActivityForm('biberon', {
        occurred_at: clock.now,
        amount: last?.amount ?? (unit === 'oz' ? 4 : 120),
        unit,
        bottle_type: '',
        notes: '',
    }, record);
    const { data, setData, errors } = form;

    return (
        <SheetLayout
            title={t('registrar.bottle.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="food"
            icon={Milk}
            actionLabel={t('registrar.bottle.save')}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <Stepper
                label={t('registrar.bottle.amount')}
                value={data.amount}
                onChange={(v) => setData('amount', v)}
                step={unit === 'oz' ? 0.5 : 10}
                min={0}
                max={unit === 'oz' ? 34 : 1000}
                unit={unit}
                error={errors.amount}
            />
            <ChoiceChips
                columns={4}
                options={quick.map((n) => ({ value: n, label: `${n} ${unit}` }))}
                value={Number(data.amount)}
                onChange={(v) => setData('amount', v)}
            />

            <MoreDetails hint={t('registrar.bottle.details_hint')}>
                <ChoiceChips
                    label={t('registrar.bottle.content')}
                    value={data.bottle_type}
                    onChange={(v) => setData('bottle_type', data.bottle_type === v ? '' : v)}
                    options={[
                        { value: 'formula', label: t('registrar.bottle.formula') },
                        { value: 'breast_milk', label: t('registrar.bottle.breast_milk') },
                        { value: 'other', label: t('registrar.bottle.other') },
                    ]}
                />
                <Field
                    label={t('registrar.note')}
                    optional={t('common.optional')}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    error={errors.notes}
                    maxLength={300}
                />
            </MoreDetails>

            <RegistersAs />
        </SheetLayout>
    );
}
