import { Link, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, ArrowLeft, Check, ChevronDown, Clock, Pill, Plus } from 'lucide-react';
import { useState } from 'react';
import Button from '../components/Button';
import Field from '../components/Field';
import ChoiceChips from '../components/form/Chips';
import Section from '../components/form/Section';
import PrescriptionForm from '../components/PrescriptionForm';
import Switch from '../components/Switch';
import AppLayout from '../layouts/AppLayout';
import { KINDS } from '../lib/activities';
import { agoLabel, useT } from '../lib/i18n';
import { minutesSince } from '../lib/time';
import useFormat from '../lib/useFormat';

const num = (v) => String(v).replace('.', ',');

function Treatment({ rx }) {
    const t = useT();
    const format = useFormat();
    const [confirming, setConfirming] = useState(false);
    const pct = Math.round((rx.given / rx.total_doses) * 100);

    return (
        <li className="flex flex-col gap-3 rounded-[22px] bg-surface p-4 shadow-card">
            <div className="flex items-start gap-3">
                <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS.medicine.bg} ${KINDS.medicine.ink}`}>
                    <Pill aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </span>
                <div className="min-w-0 grow">
                    <h3 className="text-base font-bold break-words">{rx.medicine}</h3>
                    <p className="text-[13px] text-ink-soft">
                        {num(rx.dose)}
                        {rx.unit ? ` ${rx.unit}` : ''} · {t('citas.rx.every_label', { h: rx.interval_hours })}
                    </p>
                </div>
            </div>
            <div className="flex flex-col gap-1.5">
                <div className="flex justify-between text-sm">
                    <span>{t('medicinas.progress', { given: rx.given, total: rx.total_doses })}</span>
                    {rx.next && (
                        <span className="font-semibold">
                            {t('medicinas.next')} {format.date(rx.next.at)} {format.time(rx.next.at)}
                        </span>
                    )}
                </div>
                <div role="progressbar" aria-valuemin={0} aria-valuemax={rx.total_doses} aria-valuenow={rx.given} className="h-2 overflow-hidden rounded-full bg-track">
                    <div className="h-full rounded-full bg-primary" style={{ width: `${pct}%` }} />
                </div>
            </div>
            {rx.instructions && <p className="text-sm text-ink-mid">{rx.instructions}</p>}
            {rx.appointment && (
                <Link href={`/citas/${rx.appointment.id}`} className="flex min-h-12 items-center text-[13px] text-primary">
                    {t('medicinas.from_visit', { reason: rx.appointment.reason })}
                </Link>
            )}
            <div className="flex flex-wrap gap-2">
                {rx.next && (
                    <Button onClick={() => router.post(`/dosis/${rx.next.id}/dada`, {}, { preserveScroll: true })}>
                        <Check aria-hidden="true" className="size-5" />
                        {t('medicinas.give_now')}
                    </Button>
                )}
                {confirming ? (
                    <Button variant="secondary" className="border-danger-line text-danger-ink" onClick={() => router.delete(`/recetas/${rx.id}`, { preserveScroll: true })}>
                        {t('medicinas.stop_confirm')}
                    </Button>
                ) : (
                    <Button variant="ghost" className="text-danger-ink" onClick={() => setConfirming(true)}>
                        {t('medicinas.stop')}
                    </Button>
                )}
            </div>
        </li>
    );
}

function MedicineForm({ medicine, onDone }) {
    const t = useT();
    const editing = Boolean(medicine);
    const form = useForm({
        name: medicine?.name ?? '',
        typical_dose: medicine?.typical_dose ?? '',
        unit: medicine?.unit ?? '',
        min_interval_hours: medicine?.min_interval_hours ?? '',
        is_supplement: medicine?.is_supplement ?? false,
        notes: medicine?.notes ?? '',
    });
    const { data, setData, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) form.reset();
                onDone?.();
            },
        };
        editing ? form.put(`/medicinas/${medicine.id}`, options) : form.post('/medicinas', options);
    };

    const bind = (name) => ({ value: data[name], onChange: (e) => setData(name, e.target.value), error: errors[name] });

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <Field label={t('medicinas.name')} maxLength={80} autoComplete="off" {...bind('name')} />
            <Field label={t('medicinas.typical_dose')} optional={t('common.optional')} type="number" inputMode="decimal" step="any" min="0" {...bind('typical_dose')} />
            <Field label={t('medicinas.unit')} optional={t('common.optional')} placeholder="ml, gotas, mg…" maxLength={8} {...bind('unit')} />
            <Field label={t('medicinas.min_interval')} optional={t('common.optional')} hint={t('medicinas.min_interval_help')} type="number" inputMode="decimal" step="any" min="0" {...bind('min_interval_hours')} />
            <Switch label={t('medicinas.supplement')} checked={data.is_supplement} onChange={(v) => setData('is_supplement', v)} />
            <Field label={t('medicinas.notes')} optional={t('common.optional')} maxLength={1000} {...bind('notes')} />
            <Button type="submit" disabled={form.processing || !data.name.trim()}>
                {t('common.save')}
            </Button>
        </form>
    );
}

function MedicineRow({ medicine }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(false);

    return (
        <li className={`rounded-[22px] bg-surface shadow-card ${medicine.is_active ? '' : 'opacity-75'}`}>
            <button type="button" aria-expanded={open} onClick={() => setOpen(!open)} className="flex min-h-[72px] w-full items-center gap-3 p-3 text-left focus-visible:outline-2 focus-visible:outline-primary">
                <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS.medicine.bg} ${KINDS.medicine.ink}`}>
                    <Pill aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </span>
                <span className="flex min-w-0 grow flex-col">
                    <span className="text-base font-bold break-words">{medicine.name}</span>
                    <span className="text-[13px] text-ink-soft">
                        {[
                            medicine.typical_dose ? `${num(medicine.typical_dose)}${medicine.unit ? ` ${medicine.unit}` : ''}` : null,
                            medicine.is_supplement ? t('medicinas.supplement_short') : null,
                            medicine.last_given_at ? `${t('medicinas.last')} ${agoLabel(t, minutesSince(medicine.last_given_at)).toLowerCase()}` : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </span>
                {!medicine.is_active && <span className="shrink-0 rounded-full bg-ink/10 px-3 py-1 text-xs font-medium text-ink-soft">{t('medicinas.archived_tag')}</span>}
                <ChevronDown aria-hidden="true" className={`size-5 shrink-0 text-ink-soft transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="flex flex-col gap-4 border-t border-line p-4">
                    {editing ? (
                        <MedicineForm medicine={medicine} onDone={() => setEditing(false)} />
                    ) : (
                        <>
                            {medicine.min_interval_hours && (
                                <p className="flex items-center gap-2 text-base text-ink-mid">
                                    <Clock aria-hidden="true" className="size-5 shrink-0" strokeWidth={1.75} />
                                    {t('medicinas.min_interval_info', { h: num(medicine.min_interval_hours) })}
                                </p>
                            )}
                            {medicine.notes && <p className="text-base whitespace-pre-line text-ink-mid">{medicine.notes}</p>}
                            <div className="flex flex-wrap gap-1">
                                <Button variant="ghost" onClick={() => setEditing(true)}>
                                    {t('common.edit')}
                                </Button>
                                <Button variant="ghost" onClick={() => router.post(`/medicinas/${medicine.id}/archivar`, {}, { preserveScroll: true })}>
                                    {medicine.is_active ? <Archive aria-hidden="true" className="size-5" /> : <ArchiveRestore aria-hidden="true" className="size-5" />}
                                    {medicine.is_active ? t('medicinas.archive') : t('medicinas.unarchive')}
                                </Button>
                            </div>
                            <Link href="/registrar/medicina" className="text-sm font-semibold text-primary">
                                {t('medicinas.log_dose')}
                            </Link>
                        </>
                    )}
                </div>
            )}
        </li>
    );
}

export default function Medicinas({ baby, clock, treatments, medicines, history, intervals }) {
    const t = useT();
    const format = useFormat();
    const [adding, setAdding] = useState(false);
    const active = medicines.filter((m) => m.is_active);

    return (
        <AppLayout title={t('medicinas.title')}>
            <div className="flex flex-col gap-5">
                <Link href="/" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav.home')}
                </Link>
                <header>
                    <h1 className="text-2xl leading-tight font-bold">{t('medicinas.title')}</h1>
                    <p className="text-sm text-ink-soft">{t('medicinas.subtitle', { name: baby.first_name })}</p>
                </header>

                <section className="flex flex-col gap-3">
                    <h2 className="text-[19px] font-bold">{t('medicinas.treatments')}</h2>
                    {treatments.length === 0 ? (
                        <p className="text-[15px] text-ink-soft">{t('medicinas.no_treatments')}</p>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {treatments.map((rx) => (
                                <Treatment key={rx.id} rx={rx} />
                            ))}
                        </ul>
                    )}
                    <PrescriptionForm action="/medicinas/tratamientos" medicines={active} intervals={intervals} clock={clock} openLabel={t('medicinas.new_treatment')} />
                    <Link href="/registrar/medicina" className="flex min-h-14 items-center justify-center gap-2 rounded-full bg-surface text-base font-semibold shadow-soft focus-visible:outline-2 focus-visible:outline-primary">
                        <Pill aria-hidden="true" className="size-5" strokeWidth={1.75} />
                        {t('medicinas.log_dose')}
                    </Link>
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-[19px] font-bold">{t('medicinas.catalog')}</h2>
                    {medicines.length === 0 ? (
                        <p className="text-[15px] text-ink-soft">{t('medicinas.empty')}</p>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {medicines.map((m) => (
                                <MedicineRow key={m.id} medicine={m} />
                            ))}
                        </ul>
                    )}
                    {adding ? (
                        <Section title={t('medicinas.add')}>
                            <MedicineForm onDone={() => setAdding(false)} />
                            <Button variant="ghost" onClick={() => setAdding(false)}>
                                {t('common.cancel')}
                            </Button>
                        </Section>
                    ) : (
                        <Button variant="secondary" onClick={() => setAdding(true)}>
                            <Plus aria-hidden="true" className="size-5" />
                            {t('medicinas.add')}
                        </Button>
                    )}
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-[19px] font-bold">{t('medicinas.history')}</h2>
                    {history.length === 0 ? (
                        <p className="text-[15px] text-ink-soft">{t('medicinas.no_history')}</p>
                    ) : (
                        <ul className="flex flex-col gap-2">
                            {history.map((h) => (
                                <li key={h.id} className="flex items-center justify-between gap-3 rounded-2xl bg-surface p-3 shadow-card">
                                    <span className="min-w-0">
                                        <span className="block truncate text-base font-semibold">{h.name}</span>
                                        <span className="text-[13px] text-ink-soft">
                                            {num(h.dose)}
                                            {h.unit ? ` ${h.unit}` : ''}
                                            {h.by ? ` · ${h.by}` : ''}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-sm font-semibold">
                                        {format.date(h.at)} {format.time(h.at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Link href="/bitacora?tipo=medicina" className="flex min-h-12 items-center text-[15px] font-semibold text-primary">
                        {t('medicinas.see_all')}
                    </Link>
                </section>

                <p className="text-[13px] text-ink-soft">{t('medicinas.disclaimer')}</p>
            </div>
        </AppLayout>
    );
}
