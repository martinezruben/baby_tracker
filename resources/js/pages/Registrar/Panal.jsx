import { Annoyed, Baby, Droplet, Droplets } from 'lucide-react';
import Field from '../../components/Field';
import MoreDetails from '../../components/form/MoreDetails';
import OptionGrid from '../../components/form/OptionGrid';
import RegistersAs from '../../components/form/RegistersAs';
import TimeBlock from '../../components/form/TimeBlock';
import Switch from '../../components/Switch';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Panal({ record, baby, clock, lastAt, last }) {
    const t = useT();
    const [form, save] = useActivityForm('panal', {
        occurred_at: clock.now,
        type: last?.type && last.type !== 'dry' ? last.type : 'wet',
        cream_applied: false,
        blowout: false,
        notes: '',
    }, record);
    const { data, setData, errors } = form;

    return (
        <SheetLayout
            title={t('registrar.diaper.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="diaper"
            icon={Baby}
            actionLabel={t('registrar.diaper.save')}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                save();
            }}
        >
            <TimeBlock value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />

            <OptionGrid
                label={t('registrar.type')}
                value={data.type}
                onChange={(v) => setData('type', v)}
                options={[
                    { value: 'wet', label: t('registrar.diaper.wet'), icon: Droplet, iconClass: 'text-diaper-ink' },
                    { value: 'dirty', label: t('registrar.diaper.dirty'), icon: Annoyed, iconClass: 'text-food-ink' },
                    { value: 'both', label: t('registrar.diaper.both'), icon: Droplets, iconClass: 'text-sleep-ink' },
                ]}
            />
            {errors.type && (
                <p role="alert" className="-mt-3 text-sm font-medium text-danger-ink">
                    {errors.type}
                </p>
            )}

            <MoreDetails hint={t('registrar.diaper.details_hint')}>
                <Switch label={t('registrar.diaper.cream')} checked={data.cream_applied} onChange={(v) => setData('cream_applied', v)} />
                <Switch label={t('registrar.diaper.blowout')} checked={data.blowout} onChange={(v) => setData('blowout', v)} />
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
