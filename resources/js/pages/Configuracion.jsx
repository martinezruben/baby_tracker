import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../components/Button';
import Field from '../components/Field';
import Segmented from '../components/Segmented';
import GuestLayout from '../layouts/GuestLayout';
import { useT } from '../lib/i18n';

const STEP_FIELDS = [
    ['family_name', 'language'],
    ['admin_name', 'pin', 'pin_confirmation'],
    ['baby_first_name', 'baby_birth_date', 'baby_gender', 'feed_warning_minutes', 'diaper_warning_minutes'],
];

const LANGUAGES = [
    { value: 'es', label: 'Español' },
    { value: 'en', label: 'English' },
];

export default function Configuracion() {
    const t = useT();
    const [step, setStep] = useState(0);
    const form = useForm({
        family_name: '',
        language: 'es',
        admin_name: '',
        pin: '',
        pin_confirmation: '',
        baby_first_name: '',
        baby_birth_date: '',
        baby_gender: '',
        feed_warning_minutes: 180,
        diaper_warning_minutes: 180,
    });

    const canContinue = [
        form.data.family_name.trim() !== '',
        form.data.admin_name.trim() !== '' && form.data.pin.length >= 4 && form.data.pin === form.data.pin_confirmation,
        form.data.baby_first_name.trim() !== '' && form.data.baby_birth_date !== '',
    ][step];

    const submit = (e) => {
        e.preventDefault();
        if (step < 2) return setStep(step + 1);

        form.post('/configuracion', {
            onError: (errors) => {
                // Volver al primer paso que tenga un error.
                const first = STEP_FIELDS.findIndex((fields) => fields.some((f) => errors[f]));
                if (first >= 0) setStep(first);
            },
        });
    };

    const bind = (name) => ({
        value: form.data[name] ?? '',
        onChange: (e) => form.setData(name, e.target.value),
        error: form.errors[name],
    });

    return (
        <GuestLayout title={t('setup.title')}>
            <p className="text-sm font-medium text-ink-soft">{t('setup.step_of', { current: step + 1, total: 3 })}</p>

            <form onSubmit={submit} className="mt-2 flex flex-col gap-5">
                {step === 0 && (
                    <>
                        <div>
                            <h1 className="text-2xl font-semibold">{t('setup.family.title')}</h1>
                            <p className="mt-1 text-base text-ink-soft">{t('setup.family.intro')}</p>
                        </div>
                        <Field
                            label={t('setup.family.name')}
                            placeholder={t('setup.family.name_placeholder')}
                            autoComplete="off"
                            {...bind('family_name')}
                        />
                        <Field label={t('setup.family.language')} error={form.errors.language}>
                            {() => (
                                <Segmented
                                    label={t('setup.family.language')}
                                    options={LANGUAGES}
                                    value={form.data.language}
                                    onChange={(v) => form.setData('language', v)}
                                />
                            )}
                        </Field>
                    </>
                )}

                {step === 1 && (
                    <>
                        <div>
                            <h1 className="text-2xl font-semibold">{t('setup.security.title')}</h1>
                            <p className="mt-1 text-base text-ink-soft">{t('setup.security.intro')}</p>
                        </div>
                        <Field label={t('setup.security.admin_name')} autoComplete="name" {...bind('admin_name')} />
                        <Field
                            label={t('setup.security.pin')}
                            hint={t('setup.security.pin_hint')}
                            type="password"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            autoComplete="new-password"
                            {...bind('pin')}
                        />
                        <Field
                            label={t('setup.security.pin_confirmation')}
                            type="password"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            autoComplete="new-password"
                            {...bind('pin_confirmation')}
                        />
                    </>
                )}

                {step === 2 && (
                    <>
                        <div>
                            <h1 className="text-2xl font-semibold">{t('setup.baby.title')}</h1>
                            <p className="mt-1 text-base text-ink-soft">{t('setup.baby.intro')}</p>
                        </div>
                        <Field label={t('setup.baby.first_name')} autoComplete="off" {...bind('baby_first_name')} />
                        <Field
                            label={t('setup.baby.birth_date')}
                            type="date"
                            max={new Date().toISOString().slice(0, 10)}
                            {...bind('baby_birth_date')}
                        />
                        <Field label={t('setup.baby.gender')} optional={t('common.optional')} error={form.errors.baby_gender}>
                            {() => (
                                <Segmented
                                    label={t('setup.baby.gender')}
                                    options={[
                                        { value: 'female', label: t('setup.baby.female') },
                                        { value: 'male', label: t('setup.baby.male') },
                                        { value: '', label: t('setup.baby.unspecified') },
                                    ]}
                                    value={form.data.baby_gender}
                                    onChange={(v) => form.setData('baby_gender', v)}
                                />
                            )}
                        </Field>
                        <Field
                            label={t('setup.baby.feed_warning')}
                            type="number"
                            inputMode="numeric"
                            min="15"
                            max="720"
                            {...bind('feed_warning_minutes')}
                        />
                        <Field
                            label={t('setup.baby.diaper_warning')}
                            type="number"
                            inputMode="numeric"
                            min="15"
                            max="720"
                            {...bind('diaper_warning_minutes')}
                        />
                    </>
                )}

                <div className="flex gap-3">
                    {step > 0 && (
                        <Button variant="secondary" onClick={() => setStep(step - 1)}>
                            {t('common.back')}
                        </Button>
                    )}
                    <Button type="submit" className="flex-1" disabled={!canContinue || form.processing}>
                        {step < 2 ? t('common.continue') : t('setup.baby.finish')}
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
