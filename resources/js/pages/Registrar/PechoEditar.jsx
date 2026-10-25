import { Heart } from 'lucide-react';
import Field from '../../components/Field';
import Stepper from '../../components/form/Stepper';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

/** Corregir una lactancia ya guardada (nueva se registra con el cronómetro). */
export default function PechoEditar({ record, clock }) {
    const t = useT();
    const [form, save] = useActivityForm('pecho', { occurred_at: clock.now, left_minutes: 0, right_minutes: 0, notes: '' }, record);
    const { data, setData, errors } = form;

    return (
        <SheetLayout
            title={t('registrar.breast_edit.title')}
            kind="food"
            icon={Heart}
            actionLabel={t('registrar.save_changes')}
            editing
            disabled={Number(data.left_minutes) + Number(data.right_minutes) <= 0}
            processing={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />
            <Stepper label={t('registrar.breast_edit.left')} value={data.left_minutes} onChange={(v) => setData('left_minutes', v)} step={1} min={0} max={240} error={errors.left_minutes} />
            <Stepper label={t('registrar.breast_edit.right')} value={data.right_minutes} onChange={(v) => setData('right_minutes', v)} step={1} min={0} max={240} error={errors.right_minutes} />
            <Field label={t('registrar.note')} optional={t('common.optional')} value={data.notes} onChange={(e) => setData('notes', e.target.value)} error={errors.notes} maxLength={300} />
        </SheetLayout>
    );
}
