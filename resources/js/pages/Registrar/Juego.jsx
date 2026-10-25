import { Globe } from 'lucide-react';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import MoreDetails from '../../components/form/MoreDetails';
import RegistersAs from '../../components/form/RegistersAs';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

const TYPES = ['tummy_time', 'indoor_play', 'outdoor_play', 'walk', 'custom'];

export default function Juego({ record, baby, clock, lastAt, last }) {
    const t = useT();
    const [form, save] = useActivityForm('juego', { occurred_at: clock.now, type: last?.type ?? 'tummy_time', duration_minutes: '', activities: '' }, record);
    const { data, setData, errors } = form;

    return (
        <SheetLayout
            title={t('registrar.play.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="moment"
            icon={Globe}
            actionLabel={t('registrar.play.save')}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <ChoiceChips label={t('registrar.play.type')} value={data.type} onChange={(v) => setData('type', v)} options={TYPES.map((k) => ({ value: k, label: t(`registrar.play.${k}`) }))} />

            <MoreDetails hint={t('registrar.play.details_hint')} defaultOpen={Boolean(record?.duration_minutes || record?.activities)}>
                <Field
                    label={t('registrar.play.duration')}
                    optional={t('common.optional')}
                    type="number"
                    inputMode="numeric"
                    min="1"
                    max="720"
                    value={data.duration_minutes}
                    onChange={(e) => setData('duration_minutes', e.target.value)}
                    error={errors.duration_minutes}
                />
                <Field label={t('registrar.play.activities')} optional={t('common.optional')} value={data.activities} onChange={(e) => setData('activities', e.target.value)} error={errors.activities} maxLength={300} />
            </MoreDetails>

            <RegistersAs />
        </SheetLayout>
    );
}
