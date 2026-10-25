import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, ClipboardList, Plus, Syringe } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import AppLayout from '../../layouts/AppLayout';
import { KINDS } from '../../lib/activities';
import { doseAgeLabel, useT } from '../../lib/i18n';
import { dayLabel } from '../../lib/lifeEvents';
import useFormat from '../../lib/useFormat';

const STATUS_STYLES = {
    applied: 'bg-vaccine text-vaccine-ink',
    overdue: 'bg-danger-soft text-danger-ink',
    soon: 'bg-appointment text-appointment-ink',
    later: 'bg-ink/10 text-ink-soft',
};

function dueText(t, locale, dose) {
    if (dose.days_left === 0) return t('vacunas.due.today');
    if (dose.days_left === 1) return t('vacunas.due.tomorrow');
    if (dose.days_left > 1 && dose.days_left <= 60) return t('vacunas.due.in_days', { n: dose.days_left });
    if (dose.days_left < 0 && dose.days_left >= -60) return t('vacunas.due.days_ago', { n: -dose.days_left });
    return dayLabel(dose.due_on, locale, true);
}

function Details({ v }) {
    const t = useT();
    const parts = [v.lot && t('vacunas.detail.lot', { lot: v.lot }), v.place && t('vacunas.detail.place', { place: v.place }), v.administered_by && t('vacunas.detail.by', { name: v.administered_by }), v.notes].filter(Boolean);
    return parts.length > 0 ? <span className="text-[13px] break-words text-ink-soft">{parts.join(' · ')}</span> : null;
}

function DeleteButton({ vaccination }) {
    const t = useT();
    const [confirming, setConfirming] = useState(false);
    return confirming ? (
        <Button variant="secondary" className="border-danger-line text-danger-ink" onClick={() => router.delete(`/vacunas/${vaccination.id}`, { preserveScroll: true })}>
            {t('vacunas.delete_confirm')}
        </Button>
    ) : (
        <Button variant="ghost" className="text-danger-ink" onClick={() => setConfirming(true)}>
            {t('vacunas.delete')}
        </Button>
    );
}

function DoseRow({ dose }) {
    const t = useT();
    const format = useFormat();
    const applied = dose.status === 'applied';
    const title = [dose.name, dose.label].filter(Boolean).join(' · ');

    return (
        <li className="flex flex-col gap-2 rounded-[22px] bg-surface p-3 shadow-card">
            <div className="flex items-start gap-3">
                <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS.vaccine.bg} ${KINDS.vaccine.ink}`}>
                    {applied ? <Check aria-hidden="true" className="size-6" strokeWidth={2.5} /> : <Syringe aria-hidden="true" className="size-6" strokeWidth={1.75} />}
                </span>
                <span className="flex min-w-0 grow flex-col gap-0.5">
                    <span className="text-base font-bold break-words">{title}</span>
                    <span className="text-[13px] text-ink-soft">
                        {doseAgeLabel(t, dose.age_days)}
                        {' · '}
                        {applied ? t('vacunas.due.applied_on', { date: format.date(dose.vaccination.administered_at) }) : dueText(t, format.locale, dose)}
                    </span>
                    {applied && <Details v={dose.vaccination} />}
                    {!applied && dose.notes && <span className="text-[13px] text-ink-soft">{dose.notes}</span>}
                </span>
                <span className={`shrink-0 rounded-full px-3 py-1 text-xs font-medium ${STATUS_STYLES[dose.status]}`}>{t(`vacunas.status.${dose.status}`)}</span>
            </div>
            {applied ? (
                <div className="flex justify-end">
                    <DeleteButton vaccination={dose.vaccination} />
                </div>
            ) : (
                <Link
                    href={`/vacunas/aplicar?dosis=${dose.dose_id}`}
                    className="inline-flex min-h-12 items-center justify-center gap-2 self-start rounded-full bg-primary px-5 text-[15px] font-semibold text-on-primary hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    <Plus aria-hidden="true" className="size-4" />
                    {t('vacunas.apply')}
                </Link>
            )}
        </li>
    );
}

function Section({ id, doses }) {
    const t = useT();
    if (doses.length === 0) return null;
    return (
        <section className="flex flex-col gap-2.5">
            <h2 className="text-[19px] font-bold">{t(`vacunas.sections.${id}`)}</h2>
            <ul className="flex flex-col gap-2.5">
                {doses.map((d) => (
                    <DoseRow key={d.dose_id} dose={d} />
                ))}
            </ul>
        </section>
    );
}

export default function Index({ schedule_name, has_schedule, counts, doses, extra }) {
    const t = useT();
    const format = useFormat();
    const { babies, activeBabyId } = usePage().props;
    const baby = babies.find((b) => b.id === activeBabyId);
    const by = (status) => doses.filter((d) => d.status === status);

    return (
        <AppLayout title={t('vacunas.title')}>
            <div className="flex flex-col gap-5">
                <Link href="/" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav.home')}
                </Link>

                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl leading-tight font-bold">{t('vacunas.title')}</h1>
                        {baby && <div className="text-sm text-ink-soft">{t('vacunas.subtitle', { name: baby.first_name, schedule: schedule_name })}</div>}
                    </div>
                    <Link
                        href="/vacunas/aplicar"
                        className="inline-flex min-h-12 items-center gap-2 rounded-full bg-primary px-6 text-base font-semibold text-on-primary hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                        <Plus aria-hidden="true" className="size-5" />
                        {t('vacunas.apply_new')}
                    </Link>
                </header>

                {!has_schedule ? (
                    <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-6 text-center shadow-card">
                        <Syringe aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                        <p className="text-lg font-bold">{t('vacunas.empty_title')}</p>
                        <p className="text-[15px] text-ink-soft">{t('vacunas.empty_text')}</p>
                        <Link
                            href="/vacunas/esquema"
                            className="inline-flex min-h-12 items-center gap-2 rounded-full border border-line bg-surface px-6 text-base font-semibold focus-visible:outline-2 focus-visible:outline-primary"
                        >
                            <ClipboardList aria-hidden="true" className="size-5" />
                            {t('vacunas.setup')}
                        </Link>
                    </div>
                ) : (
                    <>
                        <section className="flex flex-col gap-3 rounded-3xl bg-surface p-4 shadow-card">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-base font-bold">{t('vacunas.progress', { applied: counts.applied, total: counts.total })}</p>
                                {counts.overdue > 0 && (
                                    <span className="rounded-full bg-danger-soft px-3 py-1 text-xs font-semibold text-danger-ink">{t.choice('vacunas.overdue_count', counts.overdue)}</span>
                                )}
                            </div>
                            <div role="progressbar" aria-valuemin={0} aria-valuemax={counts.total} aria-valuenow={counts.applied} className="h-2 overflow-hidden rounded-full bg-track">
                                <div className="h-full rounded-full bg-primary" style={{ width: `${counts.total ? (counts.applied / counts.total) * 100 : 0}%` }} />
                            </div>
                            <Link href="/vacunas/esquema" className="inline-flex min-h-12 items-center gap-2 self-start text-[15px] font-semibold text-primary">
                                <ClipboardList aria-hidden="true" className="size-4" />
                                {t('vacunas.edit_schedule')}
                            </Link>
                        </section>

                        <Section id="overdue" doses={by('overdue')} />
                        <Section id="soon" doses={by('soon')} />
                        <Section id="later" doses={by('later')} />
                        <Section id="applied" doses={by('applied')} />
                    </>
                )}

                {extra.length > 0 && (
                    <section className="flex flex-col gap-2.5">
                        <h2 className="text-[19px] font-bold">{t('vacunas.sections.extra')}</h2>
                        <ul className="flex flex-col gap-2.5">
                            {extra.map((v) => (
                                <li key={v.id} className="flex flex-col gap-2 rounded-[22px] bg-surface p-3 shadow-card">
                                    <div className="flex items-start gap-3">
                                        <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS.vaccine.bg} ${KINDS.vaccine.ink}`}>
                                            <Syringe aria-hidden="true" className="size-6" strokeWidth={1.75} />
                                        </span>
                                        <span className="flex min-w-0 grow flex-col gap-0.5">
                                            <span className="text-base font-bold break-words">{[v.name, v.label].filter(Boolean).join(' · ')}</span>
                                            <span className="text-[13px] text-ink-soft">{t('vacunas.due.applied_on', { date: format.date(v.administered_at) })}</span>
                                            <Details v={v} />
                                        </span>
                                    </div>
                                    <div className="flex justify-end">
                                        <DeleteButton vaccination={v} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
