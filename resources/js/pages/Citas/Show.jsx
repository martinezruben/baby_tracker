import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronDown, Pencil, Pill, Plus, SkipForward, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AppointmentFiles from '../../components/AppointmentFiles';
import Button from '../../components/Button';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import Section from '../../components/form/Section';
import Stepper from '../../components/form/Stepper';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';
import { shiftLocal } from '../../lib/time';
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

function AddPrescription({ appointment, medicines, intervals, clock }) {
    const t = useT();
    const format = useFormat();
    const [open, setOpen] = useState(false);
    const first = medicines[0];

    const form = useForm({
        medicine_id: first?.id ?? '',
        new_medicine: '',
        dose: first?.typical_dose ? Number(first.typical_dose) : 1,
        unit: first?.unit ?? '',
        interval_hours: 8,
        days: 7,
        starts_at: clock.now,
        instructions: '',
    });
    const { data, setData, errors } = form;

    const totalDoses = Math.max(1, Math.ceil((Number(data.days) * 24) / data.interval_hours));
    const lastAt = shiftLocal(data.starts_at, (totalDoses - 1) * data.interval_hours * 60);
    const lastLabel = new Intl.DateTimeFormat(format.locale, { day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${lastAt}:00Z`));
    const isNew = data.medicine_id === '';

    const pick = (id) => {
        const medicine = medicines.find((m) => m.id === id);
        setData((d) => ({ ...d, medicine_id: id, new_medicine: '', dose: medicine?.typical_dose ? Number(medicine.typical_dose) : d.dose, unit: medicine?.unit ?? '' }));
    };

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({
            medicine_id: d.medicine_id === '' ? null : d.medicine_id,
            new_medicine: d.new_medicine || null,
            dose: d.dose,
            unit: d.unit || null,
            interval_hours: d.interval_hours,
            total_doses: totalDoses,
            starts_at: d.starts_at,
            instructions: d.instructions || null,
        }));
        form.post(`/citas/${appointment.id}/medicinas`, { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    if (!open) {
        return (
            <Button variant="secondary" onClick={() => setOpen(true)}>
                <Plus aria-hidden="true" className="size-5" />
                {t('citas.rx.add')}
            </Button>
        );
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-4 rounded-3xl bg-canvas p-4">
            <ChoiceChips
                label={t('citas.rx.which')}
                value={data.medicine_id}
                onChange={pick}
                options={[...medicines.map((m) => ({ value: m.id, label: m.name })), { value: '', label: t('citas.rx.other') }]}
            />
            {errors.medicine_id && <p role="alert" className="text-sm font-medium text-danger-ink">{errors.medicine_id}</p>}
            {isNew && <Field label={t('citas.rx.name')} value={data.new_medicine} onChange={(e) => setData('new_medicine', e.target.value)} error={errors.new_medicine} maxLength={80} />}

            <Stepper label={t('citas.rx.dose')} value={data.dose} onChange={(v) => setData('dose', v)} step={0.5} min={0} max={10000} error={errors.dose} />
            <Field label={t('citas.rx.unit')} optional={t('common.optional')} placeholder="ml, gotas, mg…" value={data.unit} onChange={(e) => setData('unit', e.target.value)} maxLength={8} />

            <ChoiceChips
                label={t('citas.rx.every')}
                value={data.interval_hours}
                onChange={(v) => setData('interval_hours', v)}
                options={intervals.map((h) => ({ value: h, label: t('citas.rx.hours', { h }) }))}
            />
            <ChoiceChips
                label={t('citas.rx.days')}
                value={data.days}
                onChange={(v) => setData('days', v)}
                options={[3, 5, 7, 10, 14].map((n) => ({ value: n, label: t('citas.rx.days_n', { n }) }))}
            />
            <Field label={t('citas.rx.start')} type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} error={errors.starts_at} />
            <Field label={t('citas.rx.instructions')} optional={t('common.optional')} placeholder={t('citas.rx.instructions_ph')} value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} error={errors.instructions} maxLength={300} />

            <p role="status" className="text-sm font-medium text-primary">
                {t('citas.rx.preview', { n: totalDoses, date: lastLabel })}
            </p>
            {(errors.total_doses || errors.interval_hours) && <p role="alert" className="text-sm font-medium text-danger-ink">{errors.total_doses ?? errors.interval_hours}</p>}
            <p className="text-sm text-ink-soft">{t('citas.rx.auto_note')}</p>

            <div className="flex gap-3">
                <Button variant="secondary" onClick={() => setOpen(false)}>
                    {t('common.back')}
                </Button>
                <Button type="submit" className="flex-1" disabled={form.processing || (isNew && !data.new_medicine.trim())}>
                    {t('citas.rx.save')}
                </Button>
            </div>
        </form>
    );
}

const DOSE_STYLES = { pending: 'bg-appointment text-appointment-ink', given: 'bg-vaccine text-vaccine-ink', skipped: 'bg-ink/10 text-ink-soft' };

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
                    <AddPrescription appointment={appointment} medicines={medicines} intervals={intervals} clock={clock} />
                </Section>
            </div>
        </AppLayout>
    );
}
