import { Utensils } from 'lucide-react';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import MoreDetails from '../../components/form/MoreDetails';
import RegistersAs from '../../components/form/RegistersAs';
import Stepper from '../../components/form/Stepper';
import TimeBlock from '../../components/form/TimeBlock';
import Switch from '../../components/Switch';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

const ENJOYMENT = ['hated', 'disliked', 'neutral', 'liked', 'loved'];

export default function Solidos({ record, baby, clock, lastAt, foods }) {
    const t = useT();
    const [form, save] = useActivityForm(
        'solidos',
        { occurred_at: clock.now, food_id: foods[0]?.id ?? '', new_food: '', amount: '', unit: 'g', enjoyment: '', had_reaction: false, reaction_notes: '' },
        record,
    );
    const { data, setData, errors } = form;
    const isNew = data.food_id === '';

    return (
        <SheetLayout
            title={t('registrar.solids.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="food"
            icon={Utensils}
            actionLabel={t('registrar.solids.save')}
            disabled={isNew && !data.new_food.trim()}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({ ...d, food_id: d.food_id === '' ? null : d.food_id, amount: Number(d.amount) > 0 ? d.amount : null }));
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <ChoiceChips
                label={t('registrar.solids.which')}
                value={data.food_id}
                onChange={(v) => setData((d) => ({ ...d, food_id: v, new_food: '' }))}
                options={[...foods.map((f) => ({ value: f.id, label: f.name })), { value: '', label: t('registrar.solids.other') }]}
            />
            {errors.food_id && (
                <p role="alert" className="-mt-3 text-sm font-medium text-red-700">
                    {errors.food_id}
                </p>
            )}
            {isNew && <Field label={t('registrar.solids.name')} value={data.new_food} onChange={(e) => setData('new_food', e.target.value)} error={errors.new_food} maxLength={80} autoComplete="off" />}

            <ChoiceChips
                label={t('registrar.solids.enjoyment')}
                value={data.enjoyment}
                onChange={(v) => setData('enjoyment', data.enjoyment === v ? '' : v)}
                options={ENJOYMENT.map((k) => ({ value: k, label: t(`registrar.solids.enjoy_${k}`) }))}
            />

            <MoreDetails hint={t('registrar.solids.details_hint')} defaultOpen={Boolean(record && (record.amount !== '' || record.had_reaction))}>
                <Stepper label={t('registrar.solids.amount')} value={data.amount} onChange={(v) => setData('amount', v)} step={5} min={0} max={1000} unit={data.unit} error={errors.amount} />
                <ChoiceChips
                    options={[{ value: 'g', label: 'g' }, { value: 'tbsp', label: 'tbsp' }, { value: 'oz', label: 'oz' }]}
                    value={data.unit}
                    onChange={(v) => setData('unit', v)}
                />
                <Switch label={t('registrar.solids.reaction')} checked={data.had_reaction} onChange={(v) => setData('had_reaction', v)} />
                {data.had_reaction && (
                    <Field
                        label={t('registrar.solids.reaction_notes')}
                        value={data.reaction_notes}
                        onChange={(e) => setData('reaction_notes', e.target.value)}
                        error={errors.reaction_notes}
                        maxLength={300}
                    />
                )}
            </MoreDetails>

            <RegistersAs />
        </SheetLayout>
    );
}
