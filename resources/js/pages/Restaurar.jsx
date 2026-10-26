import { Link, useForm } from '@inertiajs/react';
import Button from '../components/Button';
import Field from '../components/Field';
import GuestLayout from '../layouts/GuestLayout';
import { useT } from '../lib/i18n';

export default function Restaurar() {
    const t = useT();
    const form = useForm({ backup: null, pin: '', pin_confirmation: '' });
    const { data, setData, errors } = form;

    return (
        <GuestLayout title={t('respaldos.restore_page_title')}>
            <h1 className="text-2xl font-semibold">{t('respaldos.restore_page_title')}</h1>
            <p className="mt-1 text-base text-ink-soft">{t('respaldos.restore_page_intro')}</p>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/configuracion/restaurar', { forceFormData: true });
                }}
                className="mt-4 flex flex-col gap-5"
            >
                <Field label={t('respaldos.file')} type="file" accept=".zip,application/zip" onChange={(e) => setData('backup', e.target.files?.[0] ?? null)} error={errors.backup} />
                <Field label={t('respaldos.new_pin')} hint={t('respaldos.new_pin_help')} type="password" inputMode="numeric" autoComplete="new-password" value={data.pin} onChange={(e) => setData('pin', e.target.value)} error={errors.pin} />
                <Field label={t('respaldos.new_pin_confirm')} type="password" inputMode="numeric" autoComplete="new-password" value={data.pin_confirmation} onChange={(e) => setData('pin_confirmation', e.target.value)} />
                <Button type="submit" disabled={form.processing || !data.backup || !data.pin}>
                    {form.processing ? t('respaldos.restoring') : t('respaldos.restore_button')}
                </Button>
                <Link href="/configuracion" className="inline-flex min-h-12 items-center justify-center text-base font-medium text-primary">
                    {t('respaldos.back_to_setup')}
                </Link>
            </form>
        </GuestLayout>
    );
}
