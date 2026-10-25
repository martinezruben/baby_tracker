import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import Chips from '../../components/Chips';
import Field from '../../components/Field';
import Section from '../../components/form/Section';
import Segmented from '../../components/Segmented';
import Switch from '../../components/Switch';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';

const VITALS = ['weight', 'height', 'head_circumference', 'temperature'];

function Area({ label, value, onChange, error, rows = 3 }) {
    return (
        <Field label={label} error={error} optional={null}>
            {({ id, describedBy }) => (
                <textarea
                    id={id}
                    aria-describedby={describedBy}
                    aria-invalid={error ? 'true' : undefined}
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    rows={rows}
                    maxLength={5000}
                    className="min-h-24 rounded-2xl border border-ink/20 bg-surface p-4 text-base focus-visible:outline-2 focus-visible:outline-primary"
                />
            )}
        </Field>
    );
}

export default function Form({ appointment, baby, defaults, units, options }) {
    const t = useT();
    const editing = Boolean(appointment);
    const [confirmDelete, setConfirmDelete] = useState(false);

    const form = useForm({
        reason: appointment?.reason ?? '',
        doctor_name: appointment?.doctor_name ?? '',
        specialty: appointment?.specialty ?? '',
        location: appointment?.location ?? '',
        scheduled_at: appointment?.scheduled_at ?? defaults.scheduled_at,
        status: appointment?.status ?? 'scheduled',
        symptoms: appointment?.symptoms ?? '',
        diagnosis: appointment?.diagnosis ?? '',
        notes: appointment?.notes ?? '',
        follow_up_at: appointment?.follow_up_at ?? '',
        remind: appointment?.remind ?? true,
        offsets_minutes: appointment?.offsets_minutes ?? [-1440, -60],
        vitals: Object.fromEntries(VITALS.map((k) => [k, appointment?.vitals?.[k] ?? ''])),
    });
    const { data, setData, errors } = form;

    const hasFollowUp = data.follow_up_at !== '';
    const unitOf = { weight: units.weight_unit, height: units.height_unit, head_circumference: units.height_unit, temperature: `°${units.temperature_unit}` };
    const offsetOptions = options.offsets.map((m) => ({ value: m, label: t(`reminders.offsets.${Math.abs(m)}`) }));

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            follow_up_at: d.follow_up_at || null,
            // Solo las citas programadas avisan.
            remind: d.status === 'scheduled' ? d.remind : false,
            vitals: Object.fromEntries(Object.entries(d.vitals).map(([k, v]) => [k, v === '' ? null : Number(v)])),
        }));
        editing ? form.put(`/citas/${appointment.id}`) : form.post('/citas');
    };

    const bind = (name) => ({
        value: data[name] ?? '',
        onChange: (e) => setData(name, e.target.value),
        error: errors[name],
    });

    return (
        <AppLayout nav={false} title={editing ? t('citas.edit_title') : t('citas.new')}>
            <Link href={editing ? `/citas/${appointment.id}` : '/citas'} className="inline-flex min-h-12 items-center gap-2 text-base font-medium text-primary">
                <ArrowLeft aria-hidden="true" className="size-4" />
                {t('citas.back')}
            </Link>
            <h1 className="text-2xl font-bold">{editing ? t('citas.edit_title') : t('citas.new')}</h1>
            <p className="mb-4 text-sm text-ink-soft">{baby.first_name}</p>

            <form onSubmit={submit} className="flex flex-col gap-4 pb-28">
                <Section>
                    <Field label={t('citas.form.reason')} placeholder={t('citas.form.reason_ph')} maxLength={160} {...bind('reason')} />
                    <Field label={t('citas.form.when')} type="datetime-local" {...bind('scheduled_at')} />
                    <Field label={t('citas.form.doctor')} optional={t('common.optional')} maxLength={120} autoComplete="off" {...bind('doctor_name')} />
                    <Field label={t('citas.form.specialty')} optional={t('common.optional')} maxLength={80} {...bind('specialty')} />
                    <Field label={t('citas.form.location')} optional={t('common.optional')} maxLength={160} {...bind('location')} />
                </Section>

                <Section title={t('citas.form.status')} error={errors.status}>
                    <Segmented
                        label={t('citas.form.status')}
                        value={data.status}
                        onChange={(v) => setData('status', v)}
                        options={['scheduled', 'completed', 'cancelled'].map((s) => ({ value: s, label: t(`citas.states.${s}`) }))}
                    />
                </Section>

                {data.status === 'scheduled' && (
                    <Section title={t('citas.form.remind')} help={t('citas.form.remind_help')} error={errors.offsets_minutes ?? errors['offsets_minutes.0']}>
                        <Switch label={t('citas.form.remind')} checked={data.remind} onChange={(v) => setData('remind', v)} />
                        {data.remind && (
                            <Chips label={t('citas.form.offsets')} options={offsetOptions} value={data.offsets_minutes} onChange={(v) => setData('offsets_minutes', v)} />
                        )}
                    </Section>
                )}

                {data.status === 'completed' && (
                    <>
                        <Section title={t('citas.form.result')}>
                            <Area label={t('citas.form.symptoms')} value={data.symptoms} onChange={(v) => setData('symptoms', v)} error={errors.symptoms} />
                            <Area label={t('citas.form.diagnosis')} value={data.diagnosis} onChange={(v) => setData('diagnosis', v)} error={errors.diagnosis} />
                            <Area label={t('citas.form.notes')} value={data.notes} onChange={(v) => setData('notes', v)} error={errors.notes} rows={4} />
                        </Section>

                        <Section title={t('citas.form.vitals')} help={t('citas.form.vitals_help')}>
                            <div className="grid grid-cols-2 gap-3">
                                {VITALS.map((k) => (
                                    <Field
                                        key={k}
                                        label={`${t(`registrar.measure.${k}`)} (${unitOf[k]})`}
                                        type="number"
                                        step="0.1"
                                        inputMode="decimal"
                                        value={data.vitals[k]}
                                        onChange={(e) => setData('vitals', { ...data.vitals, [k]: e.target.value })}
                                        error={errors[`vitals.${k}`]}
                                    />
                                ))}
                            </div>
                        </Section>
                    </>
                )}

                <Section title={t('citas.form.follow_up')} help={t('citas.form.follow_up_help')}>
                    <Switch
                        label={t('citas.form.follow_up')}
                        checked={hasFollowUp}
                        onChange={(on) => setData('follow_up_at', on ? data.scheduled_at.slice(0, 10) + 'T' + data.scheduled_at.slice(11) : '')}
                    />
                    {hasFollowUp && <Field label={t('citas.form.when')} type="datetime-local" {...bind('follow_up_at')} />}
                </Section>

                {editing && (
                    <div className="flex justify-center">
                        {confirmDelete ? (
                            <Button variant="secondary" className="border-danger-line text-danger-ink" onClick={() => form.delete(`/citas/${appointment.id}`)}>
                                {t('citas.form.delete_confirm')}
                            </Button>
                        ) : (
                            <Button variant="ghost" className="text-danger-ink" onClick={() => setConfirmDelete(true)}>
                                {t('citas.form.delete')}
                            </Button>
                        )}
                    </div>
                )}

                <div className="fixed inset-x-0 bottom-0 z-10 border-t border-ink/10 bg-surface px-5 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <div className="mx-auto max-w-xl">
                        <Button type="submit" className="w-full" disabled={form.processing || !data.reason.trim()}>
                            {t('citas.form.save')}
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
