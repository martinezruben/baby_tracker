import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, BellRing, Pause, Pencil, Play, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';

const STATE_STYLES = {
    upcoming: 'bg-appointment text-appointment-ink',
    acknowledged: 'bg-vaccine text-vaccine-ink',
    done: 'bg-growth text-growth-ink',
    paused: 'bg-ink/10 text-ink-soft',
};

function formatWhen(local, locale) {
    if (!local) return '';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(local));
}

function Reminder({ reminder }) {
    const t = useT();
    const { locale } = usePage().props;
    const [confirming, setConfirming] = useState(false);

    const repeat = reminder.requires_ack
        ? t('reminders.summary.repeat_ack', { minutes: reminder.repeat_every_minutes })
        : reminder.repeat_every_minutes
          ? t('reminders.summary.repeat_n', { count: reminder.max_repeats, minutes: reminder.repeat_every_minutes })
          : t('reminders.summary.once');

    return (
        <li className="rounded-3xl bg-surface p-4 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="text-lg font-semibold break-words">{reminder.title}</h2>
                    <p className="text-sm text-ink-soft">{formatWhen(reminder.event_at, locale)}</p>
                </div>
                <span
                    className={`shrink-0 rounded-full px-3 py-1 text-sm font-medium ${STATE_STYLES[reminder.state]}`}
                >
                    {t(`reminders.states.${reminder.state}`)}
                </span>
            </div>

            <p className="mt-2 text-sm text-ink-soft">{repeat}</p>
            <p className="text-sm text-ink-soft">{t('reminders.summary.to', { names: reminder.recipients.join(', ') })}</p>

            <div className="mt-3 flex flex-wrap gap-2">
                <Link
                    href={`/recordatorios/${reminder.id}/editar`}
                    className="inline-flex min-h-12 items-center gap-2 rounded-full border border-ink/15 px-4 text-base font-medium hover:bg-ink/5"
                >
                    <Pencil aria-hidden="true" className="size-4" />
                    {t('reminders.edit')}
                </Link>
                <Button variant="secondary" onClick={() => router.patch(`/recordatorios/${reminder.id}/activar`, {}, { preserveScroll: true })}>
                    {reminder.is_active ? <Pause aria-hidden="true" className="size-4" /> : <Play aria-hidden="true" className="size-4" />}
                    {reminder.is_active ? t('reminders.pause') : t('reminders.resume')}
                </Button>
                {confirming ? (
                    <Button
                        variant="secondary"
                        className="border-red-300 text-red-700"
                        onClick={() => router.delete(`/recordatorios/${reminder.id}`)}
                    >
                        {t('reminders.delete_confirm')}
                    </Button>
                ) : (
                    <Button variant="ghost" className="text-red-700" onClick={() => setConfirming(true)}>
                        <Trash2 aria-hidden="true" className="size-4" />
                        {t('reminders.delete')}
                    </Button>
                )}
            </div>
        </li>
    );
}

export default function Index({ reminders }) {
    const t = useT();

    return (
        <AppLayout title={t('reminders.title')}>
            <Link href="/mas" className="inline-flex min-h-12 items-center gap-2 text-base font-medium text-primary">
                <ArrowLeft aria-hidden="true" className="size-4" />
                {t('nav.more')}
            </Link>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">{t('reminders.title')}</h1>
                <Link
                    href="/recordatorios/nuevo"
                    className="inline-flex min-h-12 items-center gap-2 rounded-full bg-primary px-6 text-base font-semibold text-white hover:bg-primary/90"
                >
                    <Plus aria-hidden="true" className="size-5" />
                    {t('reminders.new')}
                </Link>
            </div>
            <p className="mt-2 text-base text-ink-soft">{t('reminders.intro')}</p>

            {reminders.length === 0 ? (
                <div className="mt-8 flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center">
                    <BellRing aria-hidden="true" className="size-10 text-primary" />
                    <p className="text-base text-ink-soft">{t('reminders.empty')}</p>
                </div>
            ) : (
                <ul className="mt-6 grid gap-4 md:grid-cols-2">
                    {reminders.map((reminder) => (
                        <Reminder key={reminder.id} reminder={reminder} />
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
