import { Link } from '@inertiajs/react';
import { ChevronLeft, Pencil, Plus, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '../layouts/AppLayout';
import { dayLabel } from '../lib/lifeEvents';
import { useT } from '../lib/i18n';
import useFormat from '../lib/useFormat';

const W = 360;
const H = 300;
const M = { left: 44, right: 40, top: 14, bottom: 30 };
const DAYS_PER_MONTH = 30.4375;

const num = (v, digits = 1) => String(Math.round(v * 10 ** digits) / 10 ** digits).replace('.', ',');

function niceTicks(min, max, count = 5) {
    const raw = (max - min) / count;
    const pow = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((s) => s >= raw);
    const start = Math.floor(min / step) * step;
    const ticks = [];
    for (let v = start; v <= max + step; v += step) ticks.push(Math.round(v * 100) / 100);
    return ticks;
}

function ageText(t, days) {
    const months = days / DAYS_PER_MONTH;
    if (months < 1) return t('crecimiento.age_days', { n: Math.round(days) });
    return t('crecimiento.age_months', { n: num(months, months < 24 ? 1 : 0) });
}

function Chart({ report, selected, onSelect }) {
    const t = useT();
    const { bands, points, x_max: xMax, unit } = report;

    const all = [...Object.values(bands).flat().map((p) => p[1]), ...points.map((p) => p.value)];
    const ticksY = niceTicks(Math.min(...all), Math.max(...all));
    const yMin = ticksY[0];
    const yMax = ticksY[ticksY.length - 1];

    const x = (d) => M.left + (d / xMax) * (W - M.left - M.right);
    const y = (v) => H - M.bottom - ((v - yMin) / (yMax - yMin)) * (H - M.top - M.bottom);

    const line = (curve) => curve.map(([d, v], i) => `${i ? 'L' : 'M'}${x(d).toFixed(1)},${y(v).toFixed(1)}`).join(' ');
    const area = (upper, lower) => `${line(upper)} ${[...lower].reverse().map(([d, v]) => `L${x(d).toFixed(1)},${y(v).toFixed(1)}`).join(' ')} Z`;

    const monthStep = xMax / DAYS_PER_MONTH > 30 ? 12 : xMax / DAYS_PER_MONTH > 12 ? 3 : 1;
    const ticksX = [];
    for (let m = 0; m * DAYS_PER_MONTH <= xMax + 1; m += monthStep) ticksX.push(m);

    const baby = points.filter((p) => p.day >= 0 && p.day <= xMax);
    const labelled = Object.keys(bands);

    return (
        <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={t('crecimiento.chart_label')} className="w-full">
            {ticksY.map((v) => (
                <g key={v}>
                    <line x1={M.left} x2={W - M.right} y1={y(v)} y2={y(v)} className="stroke-line" strokeWidth="1" />
                    <text x={M.left - 6} y={y(v) + 4} textAnchor="end" className="fill-ink-soft" fontSize="12">
                        {num(v)}
                    </text>
                </g>
            ))}
            {ticksX.map((m) => (
                <text key={m} x={x(m * DAYS_PER_MONTH)} y={H - 8} textAnchor="middle" className="fill-ink-soft" fontSize="12">
                    {m}
                </text>
            ))}

            <path d={area(bands[97], bands[3])} className="fill-growth" />
            <path d={area(bands[85], bands[15])} className="fill-growth-deco" />
            <path d={line(bands[50])} fill="none" className="stroke-ink-soft" strokeWidth="1.5" strokeDasharray="4 3" />

            {labelled.map((p) => {
                const last = bands[p].at(-1);
                return (
                    <text key={p} x={W - M.right + 5} y={y(last[1]) + 4} className="fill-ink-soft" fontSize="12">
                        P{p}
                    </text>
                );
            })}

            {baby.length > 1 && <path d={line(baby.map((p) => [p.day, p.value]))} fill="none" className="stroke-primary" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />}
            {baby.map((p) => (
                <g key={p.id}>
                    <circle cx={x(p.day)} cy={y(p.value)} r="24" fill="transparent" tabIndex={0} role="button" aria-label={`${ageText(t, p.day)}: ${num(p.value, 2)} ${unit}`} onClick={() => onSelect(p.id)} onKeyDown={(e) => (e.key === 'Enter' || e.key === ' ') && onSelect(p.id)} className="cursor-pointer outline-none" />
                    <circle cx={x(p.day)} cy={y(p.value)} r={selected === p.id ? 7 : 5} className="pointer-events-none fill-primary stroke-surface" strokeWidth="2" />
                </g>
            ))}
            <text x={M.left} y={10} className="fill-ink-soft" fontSize="12">
                {unit}
            </text>
            <text x={M.left - 12} y={H - 8} textAnchor="end" className="fill-ink-soft" fontSize="12">
                {t('crecimiento.months_axis')}
            </text>
        </svg>
    );
}

function Verdict({ t, percentile }) {
    if (percentile === null) return null;
    if (percentile < 3) return <p className="text-sm text-ink-soft">{t('crecimiento.below')}</p>;
    if (percentile > 97) return <p className="text-sm text-ink-soft">{t('crecimiento.above')}</p>;
    return <p className="text-sm text-ink-soft">{t('crecimiento.within')}</p>;
}

export default function Crecimiento({ baby, measures, report }) {
    const t = useT();
    const format = useFormat();
    const rows = [...report.points].reverse();
    const [selectedId, setSelectedId] = useState(rows[0]?.id ?? null);
    const selected = report.points.find((p) => p.id === selectedId) ?? rows[0] ?? null;
    const standardName = t(`crecimiento.standard_${report.standard}`);

    return (
        <AppLayout title={t('crecimiento.title')}>
            <div className="flex flex-col gap-5">
                <header className="flex min-h-14 items-center gap-3">
                    <Link href="/" aria-label={t('registrar.back_home')} className="flex size-12 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary">
                        <ChevronLeft aria-hidden="true" className="size-[22px]" strokeWidth={2} />
                    </Link>
                    <div>
                        <h1 className="text-2xl leading-tight font-bold">{t('crecimiento.title')}</h1>
                        <div className="text-sm text-ink-soft">{t('crecimiento.subtitle', { name: baby.first_name, standard: standardName })}</div>
                    </div>
                </header>

                <nav aria-label={t('crecimiento.measures')} className="flex gap-2 overflow-x-auto [scrollbar-width:none]">
                    {measures.map((m) => (
                        <Link
                            key={m}
                            href={`/crecimiento?medida=${m}`}
                            preserveScroll
                            aria-current={report.measure === m ? 'true' : undefined}
                            className={`flex min-h-11 shrink-0 items-center rounded-full px-5 text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${report.measure === m ? 'bg-primary font-semibold text-white' : 'bg-surface font-medium text-ink'}`}
                        >
                            {t(`crecimiento.measure_${m}`)}
                        </Link>
                    ))}
                </nav>

                {!report.sex_known && (
                    <div role="status" className="rounded-3xl bg-moment p-4 text-base text-moment-ink">
                        {t('crecimiento.need_sex')}{' '}
                        <Link href="/ajustes/bebes" className="font-semibold underline">
                            {t('crecimiento.set_sex')}
                        </Link>
                    </div>
                )}

                {report.bands && (
                    <section className="rounded-3xl bg-surface p-4 shadow-card">
                        <h2 className="mb-2 text-[17px] font-bold">{t(`crecimiento.measure_${report.measure}`)}</h2>
                        <Chart report={report} selected={selected?.id} onSelect={setSelectedId} />
                        <p className="mt-2 text-[13px] text-ink-soft">{t('crecimiento.legend')}</p>
                    </section>
                )}

                {selected && (
                    <section aria-live="polite" className="flex flex-col gap-1 rounded-3xl bg-night p-5 text-white shadow-card">
                        <div className="text-sm opacity-85">
                            {dayLabel(selected.date, format.locale, true)} · {ageText(t, selected.day)}
                        </div>
                        <div className="text-[32px] leading-tight font-bold">
                            {num(selected.value, 2)} {report.unit}
                        </div>
                        {selected.percentile !== null ? (
                            <div className="text-lg font-semibold">{t('crecimiento.percentile', { n: num(selected.percentile, selected.percentile < 3 || selected.percentile > 97 ? 1 : 0) })}</div>
                        ) : (
                            report.sex_known && <div className="text-sm opacity-85">{t('crecimiento.no_reference')}</div>
                        )}
                    </section>
                )}
                {selected && selected.percentile !== null && <Verdict t={t} percentile={selected.percentile} />}

                <Link href="/registrar/medida" className="flex min-h-14 items-center justify-center gap-2 rounded-full bg-primary text-lg font-bold text-white hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    <Plus aria-hidden="true" className="size-5" />
                    {t('crecimiento.add')}
                </Link>

                <section className="flex flex-col gap-2.5">
                    <h2 className="text-[19px] font-bold">{t('crecimiento.history')}</h2>
                    {rows.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center shadow-card">
                            <TrendingUp aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                            <p className="text-base text-ink-soft">{t('crecimiento.empty')}</p>
                        </div>
                    ) : (
                        <ul className="flex flex-col gap-2.5">
                            {rows.map((p) => (
                                <li key={p.id} className="flex items-center gap-3 rounded-[22px] bg-surface p-3 shadow-card">
                                    <button type="button" onClick={() => setSelectedId(p.id)} className="flex min-h-12 grow flex-col text-left focus-visible:outline-2 focus-visible:outline-primary">
                                        <span className="text-base font-bold">
                                            {num(p.value, 2)} {report.unit}
                                            {p.percentile !== null && <span className="ml-2 text-sm font-semibold text-ink-soft">{t('crecimiento.percentile', { n: num(p.percentile, 0) })}</span>}
                                        </span>
                                        <span className="text-[13px] text-ink-soft">
                                            {dayLabel(p.date, format.locale, true)} · {ageText(t, p.day)}
                                        </span>
                                    </button>
                                    <Link href={`/registros/medida/${p.id}/editar`} aria-label={t('log.edit')} className="flex size-12 shrink-0 items-center justify-center rounded-full bg-canvas text-ink focus-visible:outline-2 focus-visible:outline-primary">
                                        <Pencil aria-hidden="true" className="size-5" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <p className="text-[13px] text-ink-soft">{t('crecimiento.disclaimer', { standard: standardName })}</p>
            </div>
        </AppLayout>
    );
}
