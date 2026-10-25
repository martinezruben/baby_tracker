import { usePage } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';
import { useT } from '../lib/i18n';

export default function Inicio() {
    const { auth } = usePage().props;
    const t = useT();

    return (
        <AppLayout title={t('home.title')}>
            <h1 className="text-2xl font-semibold">{t('home.hello', { name: auth.caretaker.name })}</h1>
            <p className="mt-2 text-base text-ink-soft">{t('home.placeholder')}</p>
        </AppLayout>
    );
}
