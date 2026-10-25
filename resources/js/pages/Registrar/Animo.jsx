import { Frown, Laugh, Meh, Smile } from 'lucide-react';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import MoreDetails from '../../components/form/MoreDetails';
import OptionGrid from '../../components/form/OptionGrid';
import RegistersAs from '../../components/form/RegistersAs';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Animo({ record, baby, clock, lastAt }) {
    const t = useT();
    const [form, save] = useActivityForm('animo', { occurred_at: clock.now, mood: 'happy', intensity: '', duration_minutes: '' }, record);
    const { data, setData, errors } = form;

    return (
        <SheetLayout
            title={t('registrar.mood.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="moment"
            icon={Smile}
            actionLabel={t('registrar.mood.save')}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <OptionGrid
                label={t('registrar.mood.mood')}
                columns={2}
                value={data.mood}
                onChange={(v) => setData('mood', v)}
                options={[
                    { value: 'happy', label: t('registrar.mood.happy'), icon: Laugh, iconClass: 'text-food-ink' },
                    { value: 'calm', label: t('registrar.mood.calm'), icon: Smile, iconClass: 'text-growth-ink' },
                    { value: 'fussy', label: t('registrar.mood.fussy'), icon: Meh, iconClass: 'text-moment-ink' },
                    { value: 'crying', label: t('registrar.mood.crying'), icon: Frown, iconClass: 'text-diaper-ink' },
                ]}
            />

            <MoreDetails hint={t('registrar.mood.details_hint')} defaultOpen={Boolean(record?.intensity || record?.duration_minutes)}>
                <ChoiceChips
                    label={t('registrar.mood.intensity')}
                    columns={5}
                    value={data.intensity}
                    onChange={(v) => setData('intensity', data.intensity === v ? '' : v)}
                    options={[1, 2, 3, 4, 5].map((n) => ({ value: n, label: String(n) }))}
                />
                <Field
                    label={t('registrar.mood.duration')}
                    optional={t('common.optional')}
                    type="number"
                    inputMode="numeric"
                    min="1"
                    max="720"
                    value={data.duration_minutes}
                    onChange={(e) => setData('duration_minutes', e.target.value)}
                    error={errors.duration_minutes}
                />
            </MoreDetails>

            <RegistersAs />
        </SheetLayout>
    );
}
