import { Link, usePage } from '@inertiajs/react';
import { BellRing } from 'lucide-react';
import PushToggle from '../components/PushToggle';
import AppLayout from '../layouts/AppLayout';
import { useT } from '../lib/i18n';

export default function Inicio() {
    const { auth } = usePage().props;
    const t = useT();

    return (
        <AppLayout title={t('home.title')}>
            <h1 className="text-2xl font-semibold">{t('home.hello', { name: auth.caretaker.name })}</h1>
            <p className="mt-2 text-base text-ink-soft">{t('home.placeholder')}</p>
            <div className="mt-6 flex flex-col gap-4">
                <Link
                    href="/recordatorios"
                    className="flex min-h-12 items-center gap-3 rounded-3xl bg-surface p-4 text-base font-semibold shadow-sm hover:bg-ink/5"
                >
                    <BellRing aria-hidden="true" className="size-6 text-primary" />
                    {t('home.reminders')}
                </Link>
                <PushToggle />
            </div>
        </AppLayout>
    );
}
