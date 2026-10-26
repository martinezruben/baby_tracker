import { Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronDown, Pencil, Pill, SkipForward, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AppointmentFiles from '../../components/AppointmentFiles';
import PrescriptionForm from '../../components/PrescriptionForm';
import Button from '../../components/Button';
import Section from '../../components/form/Section';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';
import useFormat from '../../lib/useFormat';

const STATE_STYLES = {
    scheduled: 'bg-appointment text-appointment-ink',
    completed: 'bg-vaccine text-vaccine-ink',
    cancelled: 'bg-ink/10 text-ink-soft',
};

function Fact({ label, children }) {
    if (!children) return null;
    return (
        <div>
            <dt className="text-[13px] font-semibold text-ink-soft">{label}</dt>
            <dd className="text-base break-words whitespace-pre-line">{children}</dd>
        </div>
    );
}

function Prescription({ rx }) {
    const t = useT();
    const format = useFormat();
    const [open, setOpen] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const active = rx.status === 'active';
    const act = (dose, op) => router.post(`/dosis/${dose.id}/${op}`, {}, { preserveScroll: true });
    const dayTime = (iso) => `${format.date(iso)} · ${format.time(iso)}`;

    return (
        <li className="flex flex-col gap-3 rounded-2xl bg-canvas p-3">
            <div className="flex items-start gap-3">
                <span className="flex size-[46px] shrink-0 items-center justify-center rounded-2xl bg-medicine text-medicine-ink">
                    <Pill aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </span>
                <div className="min-w-0 grow">
                    <h3 className="text-base font-bold break-words">{rx.medicine}</h3>
                    <p className="text-[13px] text-ink-soft">
                        {rx.dose}
                        {rx.unit ? ` ${rx.unit}` : ''} · {t('citas.rx.every_label', { h: rx.interval_hours })}
                    </p>
                    {rx.instructions && <p className="text-[13px] text-ink-soft">{rx.instructions}</p>}
                </div>
                {rx.status !== 'active' && (
                    <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium ${rx.status === 'completed' ? 'bg-vaccine text-vaccine-ink' : 'bg-ink/10 text-ink-soft'}`}>
                        {rx.status === 'completed' ? t('citas.rx.done') : t('citas.rx.cancelled')}
                    </span>
                )}
            </div>

            <div>
                <div className="mb-1 flex justify-between text-[13px]">
                    <span className="font-semibold">{t('citas.rx.progress', { given: rx.given, total: rx.total_doses })}</span>
                    {rx.next_at && <span className="text-ink-soft">{t('citas.rx.next', { when: dayTime(rx.next_at) })}</span>}
                </div>
                <div
                    role="progressbar"
                    aria-valuemin={0}
                    aria-valuemax={rx.total_doses}
                    aria-valuenow={rx.given}
                    className="h-2 overflow-hidden rounded-full bg-track"
                >
                    <div className="h-full rounded-full bg-primary" style={{ width: `${(rx.given / rx.total_doses) * 100}%` }} />
                </div>
            </div>

            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen(!open)}
                className="flex min-h-12 items-center justify-between rounded-full bg-surface px-4 text-[15px] font-semibold focus-visible:outline-2 focus-visible:outline-primary"
            >
                {t('citas.rx.doses')}
                <ChevronDown aria-hidden="true" className={`size-5 text-ink-soft transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <ul className="flex flex-col gap-2">
                    {rx.doses.map((dose) => (
                        <li key={dose.id} className="flex items-center gap-2 rounded-2xl bg-surface p-2 pl-3">
                            <span className="grow text-sm">{dayTime(dose.at)}</span>
                            {dose.status === 'pending' && active ? (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => act(dose, 'dada')}
                                        className="flex min-h-12 items-center gap-1.5 rounded-full bg-primary px-4 text-sm font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                    >
                                        <Check aria-hidden="true" className="size-4" strokeWidth={2.5} />
                                        {t('citas.rx.give')}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => act(dose, 'omitir')}
                                        aria-label={`${t('citas.rx.skip')}: ${dayTime(dose.at)}`}
                                        className="flex min-h-12 items-center gap-1.5 rounded-full border border-line px-3 text-sm font-medium focus-visible:outline-2 focus-visible:outline-primary"
                                    >
                                        <SkipForward aria-hidden="true" className="size-4" />
                                        {t('citas.rx.skip')}
                                    </button>
                                </>
                            ) : (
                                <span className={`rounded-full px-3 py-1 text-xs font-medium ${DOSE_STYLES[dose.status]}`}>
                                    {t(`citas.rx.${dose.status}`)}
                                    {dose.given_at && ` · ${format.time(dose.given_at)}`}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {active &&
                (confirming ? (
                    <Button variant="secondary" className="border-danger-line text-danger-ink" onClick={() => router.delete(`/recetas/${rx.id}`, { preserveScroll: true })}>
                        {t('citas.rx.suspend_confirm')}
                    </Button>
                ) : (
                    <Button variant="ghost" className="text-danger-ink" onClick={() => setConfirming(true)}>
                        {t('citas.rx.suspend')}
                    </Button>
                ))}
        </li>
    );
}

export default function Show({ appointment, clock, medicines, intervals }) {
    const t = useT();
    const format = useFormat();
    const hasResult = appointment.symptoms || appointment.diagnosis || appointment.notes;
    const offsets = appointment.reminder?.offsets?.map((m) => (m === 0 ? t('citas.show.offset_at') : t(`reminders.offsets.${Math.abs(m)}`).toLowerCase())).join(', ');

    return (
        <AppLayout title={appointment.reason}>
            <div className="flex flex-col gap-4">
                <Link href="/citas" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('citas.back')}
                </Link>

                <header className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-2xl leading-tight font-bold break-words">{appointment.reason}</h1>
                        <span className={`mt-2 inline-block rounded-full px-3 py-1 text-sm font-medium ${STATE_STYLES[appointment.status]}`}>
                            {t(`citas.states.${appointment.status}`)}
                        </span>
                    </div>
                    <Link
                        href={`/citas/${appointment.id}/editar`}
                        className="inline-flex min-h-12 shrink-0 items-center gap-2 rounded-full border border-line bg-surface px-5 text-base font-semibold focus-visible:outline-2 focus-visible:outline-primary"
                    >
                        <Pencil aria-hidden="true" className="size-4" />
                        {t('citas.edit')}
                    </Link>
                </header>

                <Section>
                    <dl className="flex flex-col gap-3">
                        <Fact label={t('citas.show.when')}>{`${format.fullDate(appointment.scheduled_at)} · ${format.time(appointment.scheduled_at)}`}</Fact>
                        <Fact label={t('citas.show.doctor')}>{[appointment.doctor_name, appointment.specialty].filter(Boolean).join(' · ')}</Fact>
                        <Fact label={t('citas.show.location')}>{appointment.location}</Fact>
                        {appointment.status === 'scheduled' && <Fact label={t('citas.show.reminders')}>{offsets || t('citas.show.reminders_off')}</Fact>}
                        <Fact label={t('citas.show.follow_up')}>{appointment.follow_up_at && `${format.fullDate(appointment.follow_up_at)} · ${format.time(appointment.follow_up_at)}`}</Fact>
                    </dl>
                </Section>

                {appointment.status === 'completed' && (
                    <Section title={t('citas.show.result')}>
                        {hasResult ? (
                            <dl className="flex flex-col gap-3">
                                <Fact label={t('citas.form.symptoms')}>{appointment.symptoms}</Fact>
                                <Fact label={t('citas.form.diagnosis')}>{appointment.diagnosis}</Fact>
                                <Fact label={t('citas.form.notes')}>{appointment.notes}</Fact>
                            </dl>
                        ) : (
                            <p className="text-[15px] text-ink-soft">{t('citas.show.no_result')}</p>
                        )}
                        {appointment.vitals.length > 0 && (
                            <ul aria-label={t('citas.show.vitals')} className="flex flex-wrap gap-2">
                                {appointment.vitals.map((v) => (
                                    <li key={v.type} className="rounded-full bg-growth px-4 py-2 text-sm font-semibold text-growth-ink">
                                        {t(`registrar.measure.${v.type}`)} · {String(v.value).replace('.', ',')} {v.unit}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>
                )}

                <AppointmentFiles appointment={appointment} />

                <Section title={t('citas.rx.title')}>
                    {appointment.prescriptions.length === 0 ? (
                        <p className="text-[15px] text-ink-soft">{t('citas.rx.none')}</p>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {appointment.prescriptions.map((rx) => (
                                <Prescription key={rx.id} rx={rx} />
                            ))}
                        </ul>
                    )}
                    <PrescriptionForm action={`/citas/${appointment.id}/medicinas`} medicines={medicines} intervals={intervals} clock={clock} />
                </Section>
            </div>
        </AppLayout>
    );
}
