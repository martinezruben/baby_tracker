import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, FolderSearch, Paperclip, Pill, Plus, Stethoscope } from 'lucide-react';
import AppLayout from '../../layouts/AppLayout';
import { KINDS } from '../../lib/activities';
import { useT } from '../../lib/i18n';
import useFormat from '../../lib/useFormat';

const STATE_STYLES = {
    scheduled: 'bg-appointment text-appointment-ink',
    completed: 'bg-vaccine text-vaccine-ink',
    cancelled: 'bg-ink/10 text-ink-soft',
};

function AppointmentCard({ appointment, past }) {
    const t = useT();
    const format = useFormat();
    const { locale, timezone } = usePage().props;
    const parts = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', timeZone: timezone }).formatToParts(new Date(appointment.scheduled_at));
    const day = parts.find((p) => p.type === 'day')?.value;
    const month = parts.find((p) => p.type === 'month')?.value;
    const sub = [appointment.doctor_name, appointment.location].filter(Boolean).join(' · ');

    return (
        <li>
            <Link
                href={`/citas/${appointment.id}`}
                className="flex items-center gap-3 rounded-[22px] bg-surface p-3 shadow-card focus-visible:outline-2 focus-visible:outline-primary"
            >
                <span className={`flex size-[58px] shrink-0 flex-col items-center justify-center rounded-[18px] ${KINDS.appointment.bg} ${KINDS.appointment.ink}`}>
                    <span className="text-[22px] leading-none font-bold">{day}</span>
                    <span className="text-xs font-semibold uppercase">{month?.replace('.', '')}</span>
                </span>
                <span className="flex min-w-0 grow flex-col gap-0.5">
                    <span className="text-base font-bold break-words">{appointment.reason}</span>
                    <span className="text-[13px] text-ink-soft">
                        {format.time(appointment.scheduled_at)}
                        {sub && ` · ${sub}`}
                    </span>
                    {(appointment.files_count > 0 || appointment.meds_count > 0) && (
                        <span className="mt-0.5 flex gap-3 text-xs text-ink-soft">
                            {appointment.files_count > 0 && (
                                <span className="flex items-center gap-1">
                                    <Paperclip aria-hidden="true" className="size-3.5" />
                                    {t.choice('citas.summary.files', appointment.files_count)}
                                </span>
                            )}
                            {appointment.meds_count > 0 && (
                                <span className="flex items-center gap-1">
                                    <Pill aria-hidden="true" className="size-3.5" />
                                    {t.choice('citas.summary.meds', appointment.meds_count)}
                                </span>
                            )}
                        </span>
                    )}
                </span>
                {past && (
                    <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium ${STATE_STYLES[appointment.status]}`}>
                        {t(`citas.states.${appointment.status}`)}
                    </span>
                )}
            </Link>
        </li>
    );
}

export default function Index({ upcoming, past }) {
    const t = useT();
    const { babies, activeBabyId } = usePage().props;
    const baby = babies.find((b) => b.id === activeBabyId);

    return (
        <AppLayout title={t('citas.title')}>
            <div className="flex flex-col gap-5">
                <Link href="/" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav.home')}
                </Link>

                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl leading-tight font-bold">{t('citas.title')}</h1>
                        {baby && <div className="text-sm text-ink-soft">{t('citas.subtitle', { name: baby.first_name })}</div>}
                    </div>
                    <Link
                        href="/citas/nueva"
                        className="inline-flex min-h-12 items-center gap-2 rounded-full bg-primary px-6 text-base font-semibold text-on-primary hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                        <Plus aria-hidden="true" className="size-5" />
                        {t('citas.new')}
                    </Link>
                </header>

                <Link href="/documentos" className="flex min-h-14 items-center gap-3 rounded-[22px] bg-surface p-3 shadow-card focus-visible:outline-2 focus-visible:outline-primary">
                    <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS.appointment.bg} ${KINDS.appointment.ink}`}>
                        <FolderSearch aria-hidden="true" className="size-6" strokeWidth={1.75} />
                    </span>
                    <span className="flex min-w-0 grow flex-col">
                        <span className="text-base font-bold">{t('documentos.link')}</span>
                        <span className="text-[13px] text-ink-soft">{t('documentos.link_help')}</span>
                    </span>
                </Link>

                {upcoming.length === 0 && past.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center shadow-card">
                        <Stethoscope aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                        <p className="text-base text-ink-soft">{t('citas.empty')}</p>
                    </div>
                ) : (
                    <>
                        <section className="flex flex-col gap-2.5">
                            <h2 className="text-[19px] font-bold">{t('citas.upcoming')}</h2>
                            {upcoming.length === 0 ? (
                                <p className="text-[15px] text-ink-soft">{t('citas.empty_upcoming')}</p>
                            ) : (
                                <ul className="flex flex-col gap-2.5">
                                    {upcoming.map((a) => (
                                        <AppointmentCard key={a.id} appointment={a} />
                                    ))}
                                </ul>
                            )}
                        </section>

                        {past.length > 0 && (
                            <section className="flex flex-col gap-2.5">
                                <h2 className="text-[19px] font-bold">{t('citas.past')}</h2>
                                <ul className="flex flex-col gap-2.5">
                                    {past.map((a) => (
                                        <AppointmentCard key={a.id} appointment={a} past />
                                    ))}
                                </ul>
                            </section>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
