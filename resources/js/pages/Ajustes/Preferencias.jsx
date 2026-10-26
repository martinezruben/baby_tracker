import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Button from '../../components/Button';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import Section from '../../components/form/Section';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';

export default function Preferencias({ prefs, languages, timezones }) {
    const t = useT();
    const form = useForm({ ...prefs });
    const { data, setData, errors } = form;

    const chips = (name, label, options) => (
        <div className="flex flex-col gap-1">
            <ChoiceChips label={label} value={data[name]} onChange={(v) => setData(name, v)} options={options} />
            {errors[name] && <p role="alert" className="text-sm font-medium text-danger-ink">{errors[name]}</p>}
        </div>
    );

    return (
        <AppLayout title={t('ajustes.prefs.title')}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/ajustes/preferencias', { preserveScroll: true });
                }}
                className="flex flex-col gap-4"
            >
                <Link href="/ajustes" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('ajustes.back')}
                </Link>
                <h1 className="text-2xl leading-tight font-bold">{t('ajustes.prefs.title')}</h1>

                <Section>
                    <Field label={t('ajustes.prefs.family_name')} value={data.family_name} onChange={(e) => setData('family_name', e.target.value)} error={errors.family_name} maxLength={80} />
                    {chips('language', t('ajustes.prefs.language'), languages.map((l) => ({ value: l, label: l === 'es' ? 'Español' : 'English' })))}
                </Section>

                <Section>
                    <Field label={t('ajustes.prefs.timezone')} value={data.timezone} onChange={(e) => setData('timezone', e.target.value)} error={errors.timezone} list="timezones" autoComplete="off" spellCheck={false} />
                    <datalist id="timezones">
                        {timezones.map((z) => (
                            <option key={z} value={z} />
                        ))}
                    </datalist>
                    <p className="-mt-1 text-sm text-ink-soft">{t('ajustes.prefs.timezone_help')}</p>
                    {chips('time_format', t('ajustes.prefs.time_format'), [{ value: '12', label: t('ajustes.prefs.h12') }, { value: '24', label: t('ajustes.prefs.h24') }])}
                </Section>

                <Section title={t('ajustes.prefs.units')}>
                    {chips('volume_unit', t('ajustes.prefs.volume'), [{ value: 'ml', label: 'ml' }, { value: 'oz', label: 'oz' }])}
                    <p className="-mt-2 text-sm text-ink-soft">{t('ajustes.prefs.volume_help')}</p>
                    {chips('weight_unit', t('ajustes.prefs.weight'), [{ value: 'kg', label: 'kg' }, { value: 'lb', label: 'lb' }])}
                    {chips('height_unit', t('ajustes.prefs.height'), [{ value: 'cm', label: 'cm' }, { value: 'in', label: 'in' }])}
                    {chips('temperature_unit', t('ajustes.prefs.temperature'), [{ value: 'C', label: '°C' }, { value: 'F', label: '°F' }])}
                    {chips('growth_chart_standard', t('ajustes.prefs.growth'), [{ value: 'who', label: t('ajustes.prefs.who') }, { value: 'cdc', label: t('ajustes.prefs.cdc') }])}
                </Section>

                <Button type="submit" disabled={form.processing || !form.isDirty}>
                    {t('ajustes.prefs.save')}
                </Button>
            </form>
        </AppLayout>
    );
}
