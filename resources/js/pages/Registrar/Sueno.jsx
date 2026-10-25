import { router } from '@inertiajs/react';
import { Moon } from 'lucide-react';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import MoreDetails from '../../components/form/MoreDetails';
import RegistersAs from '../../components/form/RegistersAs';
import TimeBlock from '../../components/form/TimeBlock';
import SheetLayout from '../../layouts/SheetLayout';
import { lastSubtitle, useActivityForm } from '../../lib/forms';
import { useT } from '../../lib/i18n';

export default function Sueno({ record, baby, clock, lastAt, openSleepId }) {
    const t = useT();
    const [form, save] = useActivityForm('sueno', {
        occurred_at: clock.now,
        in_progress: true,
        ended_at: clock.now,
        type: 'nap',
        quality: '',
        location: '',
    }, record);
    const { data, setData, errors } = form;

    // Ya hay un sueño abierto: lo único que tiene sentido es cerrarlo.
    if (openSleepId && !record) {
        return (
            <SheetLayout
                title={t('registrar.sleep.title')}
                subtitle={t('registrar.sleep.in_progress')}
                kind="sleep"
                icon={Moon}
                actionLabel={t('registrar.sleep.wake_now')}
                onSubmit={(e) => {
                    e.preventDefault();
                    router.patch(`/sueno/${openSleepId}/despertar`);
                }}
            >
                <p role="status" className="rounded-[20px] bg-canvas p-4 text-base">
                    {t('registrar.sleep.already_open')}
                </p>
                <RegistersAs />
            </SheetLayout>
        );
    }

    return (
        <SheetLayout
            title={t('registrar.sleep.title')}
            subtitle={lastSubtitle(t, baby.first_name, lastAt)}
            kind="sleep"
            icon={Moon}
            actionLabel={data.in_progress ? t('registrar.sleep.save_start') : t('registrar.sleep.save')}
            processing={form.processing}
            editing={Boolean(record)}
            onSubmit={(e) => {
                e.preventDefault();
                form.transform((d) => ({ ...d, ended_at: d.in_progress ? null : d.ended_at, quality: d.quality || null }));
                save();
            }}
        >
            <ChoiceChips
                label={t('registrar.sleep.mode')}
                value={data.in_progress}
                onChange={(v) => setData('in_progress', v)}
                options={[
                    { value: true, label: t('registrar.sleep.sleeping_now') },
                    { value: false, label: t('registrar.sleep.finished') },
                ]}
            />
            {errors.in_progress && (
                <p role="alert" className="-mt-3 text-sm font-medium text-red-700">
                    {errors.in_progress}
                </p>
            )}

            <TimeBlock label={t('registrar.sleep.fell_asleep')} value={data.occurred_at} onChange={(v) => setData('occurred_at', v)} now={clock.now} error={errors.occurred_at} />
            {!data.in_progress && (
                <TimeBlock label={t('registrar.sleep.woke_up')} value={data.ended_at} onChange={(v) => setData('ended_at', v)} now={clock.now} error={errors.ended_at} />
            )}

            <ChoiceChips
                label={t('registrar.type')}
                value={data.type}
                onChange={(v) => setData('type', v)}
                options={[
                    { value: 'nap', label: t('registrar.sleep.nap') },
                    { value: 'night_sleep', label: t('registrar.sleep.night') },
                ]}
            />

            <MoreDetails hint={t('registrar.sleep.details_hint')}>
                <ChoiceChips
                    label={t('registrar.sleep.quality')}
                    value={data.quality}
                    onChange={(v) => setData('quality', data.quality === v ? '' : v)}
                    options={['poor', 'fair', 'good', 'excellent'].map((q) => ({ value: q, label: t(`registrar.sleep.q_${q}`) }))}
                />
                <Field label={t('registrar.sleep.location')} optional={t('common.optional')} value={data.location} onChange={(e) => setData('location', e.target.value)} maxLength={60} />
            </MoreDetails>

            <RegistersAs />
        </SheetLayout>
    );
}
