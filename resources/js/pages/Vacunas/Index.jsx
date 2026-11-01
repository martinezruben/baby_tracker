import { Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, ClipboardList, Clock, Plus, Syringe } from 'lucide-react';
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

/** Anillo de avance: dosis aplicadas sobre el total del esquema. */
function ProgressRing({ applied, total }) {
    const t = useT();
    const r = 38;
    const c = 2 * Math.PI * r;
    const pct = total ? applied / total : 0;

    return (
        <div role="progressbar" aria-label={t('vacunas.progress', { applied, total })} aria-valuemin={0} aria-valuemax={total} aria-valuenow={applied} className="relative size-[92px] shrink-0">
            <svg viewBox="0 0 92 92" className="size-full -rotate-90" aria-hidden="true">
                <circle cx="46" cy="46" r={r} fill="none" strokeWidth="9" className="stroke-track" />
                <circle cx="46" cy="46" r={r} fill="none" strokeWidth="9" strokeLinecap="round" strokeDasharray={c} strokeDashoffset={c * (1 - pct)} className="stroke-vaccine-ink" />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center leading-none">
                <span className="text-2xl font-bold">{applied}</span>
                <span className="text-xs text-ink-soft">/ {total}</span>
            </div>
        </div>
    );
}

function Stat({ value, label, tone }) {
    return (
        <div className={`flex min-h-14 flex-col justify-center rounded-2xl px-3 ${tone}`}>
            <span className="text-xl leading-tight font-bold">{value}</span>
            <span className="text-xs leading-tight">{label}</span>
        </div>
    );
}

/** La dosis que más urge: la vencida más antigua o, si no hay, la próxima. */
function NextDose({ dose }) {
    const t = useT();
    const format = useFormat();
    const overdue = dose.status === 'overdue';

    return (
        <section aria-label={t('vacunas.next')} className={`flex flex-col gap-3 rounded-3xl p-4 ${overdue ? 'bg-danger-soft' : 'bg-vaccine'}`}>
            <div className={`text-xs font-semibold ${overdue ? 'text-danger-ink' : 'text-vaccine-ink'}`}>{overdue ? t('vacunas.next_overdue') : t('vacunas.next')}</div>
            <div>
                <div className="text-xl leading-tight font-bold break-words">{[dose.name, dose.label].filter(Boolean).join(' · ')}</div>
                <div className="text-sm text-ink-mid">
                    {doseAgeLabel(t, dose.age_days)} · {dueText(t, format.locale, dose)}
                </div>
            </div>
            <Link
                href={`/vacunas/aplicar?dosis=${dose.dose_id}`}
                className="inline-flex min-h-12 items-center justify-center gap-2 self-start rounded-full bg-primary px-5 text-[15px] font-semibold text-on-primary hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                <Plus aria-hidden="true" className="size-4" />
                {t('vacunas.apply')}
            </Link>
        </section>
    );
}

/** Una dosis dentro de la tarjeta de su vacuna. */
function DoseLine({ dose, showName = false }) {
    const t = useT();
    const format = useFormat();
    const applied = dose.status === 'applied';
    const label = showName ? [dose.name, dose.label].filter(Boolean).join(' · ') : dose.label || t('vacunas.single_dose');
    const marker = {
        applied: 'bg-vaccine text-vaccine-ink',
        overdue: 'bg-danger-soft text-danger-ink',
        soon: 'bg-appointment text-appointment-ink',
        later: 'bg-track text-ink-soft',
    }[dose.status];

    return (
        <li className="flex flex-col gap-2 border-t border-line py-3 first:border-t-0 first:pt-0 last:pb-0">
            <div className="flex items-start gap-3">
                <span aria-hidden="true" className={`flex size-9 shrink-0 items-center justify-center rounded-full ${marker}`}>
                    {applied ? <Check className="size-5" strokeWidth={2.5} /> : dose.status === 'overdue' ? <Syringe className="size-[18px]" strokeWidth={2} /> : <Clock className="size-[18px]" strokeWidth={2} />}
                </span>
                <span className="flex min-w-0 grow flex-col gap-0.5">
                    <span className="flex flex-wrap items-center gap-x-2 text-base font-semibold break-words">
                        {label}
                        <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_STYLES[dose.status]}`}>{t(`vacunas.status.${dose.status}`)}</span>
                    </span>
                    <span className="text-[13px] text-ink-soft">
                        {doseAgeLabel(t, dose.age_days)}
                        {' · '}
                        {applied ? t('vacunas.due.applied_on', { date: format.date(dose.vaccination.administered_at) }) : dueText(t, format.locale, dose)}
                    </span>
                    {applied && <Details v={dose.vaccination} />}
                    {!applied && dose.notes && <span className="text-[13px] text-ink-soft">{dose.notes}</span>}
                </span>
            </div>
            {applied ? (
                <div className="flex justify-end">
                    <DeleteButton vaccination={dose.vaccination} />
                </div>
            ) : (
                <Link
                    href={`/vacunas/aplicar?dosis=${dose.dose_id}`}
                    className="ml-12 inline-flex min-h-12 items-center justify-center gap-2 self-start rounded-full border border-primary px-5 text-[15px] font-semibold text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    <Plus aria-hidden="true" className="size-4" />
                    {t('vacunas.apply')}
                </Link>
            )}
        </li>
    );
}

/** Esquema por vacuna: cuántas dosis lleva, una barra de pasos y cada dosis debajo. */
function VaccineCard({ name, doses }) {
    const t = useT();
    const applied = doses.filter((d) => d.status === 'applied').length;
    const overdue = doses.some((d) => d.status === 'overdue');
    const complete = applied === doses.length;
    const step = { applied: 'bg-vaccine-ink', overdue: 'bg-danger', soon: 'bg-appointment-ink', later: 'bg-track' };

    return (
        <li className="flex flex-col gap-3 rounded-3xl bg-surface p-4 shadow-card">
            <div className="flex items-start gap-3">
                <span aria-hidden="true" className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${complete ? 'bg-vaccine text-vaccine-ink' : overdue ? 'bg-danger-soft text-danger-ink' : `${KINDS.vaccine.bg} ${KINDS.vaccine.ink}`}`}>
                    {complete ? <Check className="size-6" strokeWidth={2.5} /> : <Syringe className="size-6" strokeWidth={1.75} />}
                </span>
                <span className="flex min-w-0 grow flex-col">
                    <span className="text-lg leading-tight font-bold break-words">{name}</span>
                    <span className="text-[13px] text-ink-soft">{complete ? t('vacunas.complete') : t('vacunas.doses_of', { applied, total: doses.length })}</span>
                </span>
            </div>
            <div aria-hidden="true" className="flex gap-1">
                {doses.map((d) => (
                    <span key={d.dose_id} className={`h-2 grow rounded-full ${step[d.status]}`} />
                ))}
            </div>
            <ul>
                {doses.map((d) => (
                    <DoseLine key={d.dose_id} dose={d} />
                ))}
            </ul>
        </li>
    );
}

function Section({ id, doses }) {
    const t = useT();
    if (doses.length === 0) return null;
    return (
        <section className="flex flex-col gap-2.5">
            <h2 className="text-[19px] font-bold">
                {t(`vacunas.sections.${id}`)} <span className="text-base font-medium text-ink-soft">· {doses.length}</span>
            </h2>
            <ul className="flex flex-col gap-2.5 rounded-3xl bg-surface p-4 shadow-card">
                {doses.map((d) => (
                    <DoseLine key={d.dose_id} dose={d} showName />
                ))}
            </ul>
        </section>
    );
}

function Extra({ extra }) {
    const t = useT();
    const format = useFormat();
    if (extra.length === 0) return null;

    return (
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
    );
}

const TABS = ['schema', 'pending', 'applied'];

export default function Index({ schedule_name, has_schedule, counts, doses, extra }) {
    const t = useT();
    const { babies, activeBabyId } = usePage().props;
    const baby = babies.find((b) => b.id === activeBabyId);
    const by = (status) => doses.filter((d) => d.status === status);
    const [tab, setTab] = useState('schema');

    const pending = doses.filter((d) => d.status !== 'applied');
    const next = by('overdue')[0] ?? by('soon')[0] ?? by('later')[0];
    const appliedDoses = by('applied').sort((a, b) => Date.parse(b.vaccination.administered_at) - Date.parse(a.vaccination.administered_at));

    const groups = [];
    for (const dose of doses) {
        const group = groups.find((g) => g.name === dose.name);
        if (group) group.doses.push(dose);
        else groups.push({ name: dose.name, doses: [dose] });
    }

    const tabLabel = { schema: t('vacunas.tabs.schema'), pending: `${t('vacunas.tabs.pending')} · ${pending.length}`, applied: `${t('vacunas.tabs.applied')} · ${counts.applied + extra.length}` };

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
                        className="inline-flex min-h-12 items-center gap-2 rounded-full bg-primary px-5 text-[15px] font-semibold text-on-primary hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                        <Plus aria-hidden="true" className="size-5" />
                        {t('vacunas.apply_new')}
                    </Link>
                </header>

                {!has_schedule ? (
                    <>
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
                        <Extra extra={extra} />
                    </>
                ) : (
                    <>
                        <section className="flex flex-col gap-4 rounded-3xl bg-surface p-4 shadow-card">
                            <div className="flex items-center gap-4">
                                <ProgressRing applied={counts.applied} total={counts.total} />
                                <div className="grid min-w-0 grow grid-cols-1 gap-2">
                                    <Stat value={counts.applied} label={t('vacunas.stat.applied')} tone="bg-vaccine text-vaccine-ink" />
                                    <div className="grid grid-cols-2 gap-2">
                                        <Stat value={counts.upcoming} label={t('vacunas.stat.upcoming')} tone="bg-appointment text-appointment-ink" />
                                        <Stat value={counts.overdue} label={t('vacunas.stat.overdue')} tone={counts.overdue > 0 ? 'bg-danger-soft text-danger-ink' : 'bg-track text-ink-soft'} />
                                    </div>
                                </div>
                            </div>
                            <Link href="/vacunas/esquema" className="inline-flex min-h-12 items-center gap-2 self-start text-[15px] font-semibold text-primary">
                                <ClipboardList aria-hidden="true" className="size-4" />
                                {t('vacunas.edit_schedule')}
                            </Link>
                        </section>

                        {next && <NextDose dose={next} />}

                        <div role="tablist" aria-label={t('vacunas.title')} className="flex rounded-full bg-surface p-1 shadow-card">
                            {TABS.map((id) => (
                                <button
                                    key={id}
                                    type="button"
                                    role="tab"
                                    aria-selected={tab === id}
                                    onClick={() => setTab(id)}
                                    className={`min-h-12 min-w-0 grow basis-0 rounded-full px-2 text-[14px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                        tab === id ? 'bg-primary font-bold text-on-primary' : 'font-medium text-ink-soft'
                                    }`}
                                >
                                    {tabLabel[id]}
                                </button>
                            ))}
                        </div>

                        {tab === 'schema' && (
                            <ul className="flex flex-col gap-3">
                                {groups.map((g) => (
                                    <VaccineCard key={g.name} name={g.name} doses={g.doses} />
                                ))}
                            </ul>
                        )}

                        {tab === 'pending' &&
                            (pending.length === 0 ? (
                                <p className="rounded-3xl bg-surface p-6 text-center text-base text-ink-soft shadow-card">{t('vacunas.nothing_pending')}</p>
                            ) : (
                                <>
                                    <Section id="overdue" doses={by('overdue')} />
                                    <Section id="soon" doses={by('soon')} />
                                    <Section id="later" doses={by('later')} />
                                </>
                            ))}

                        {tab === 'applied' &&
                            (appliedDoses.length === 0 && extra.length === 0 ? (
                                <p className="rounded-3xl bg-surface p-6 text-center text-base text-ink-soft shadow-card">{t('vacunas.nothing_applied')}</p>
                            ) : (
                                <>
                                    <Section id="applied" doses={appliedDoses} />
                                    <Extra extra={extra} />
                                </>
                            ))}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
