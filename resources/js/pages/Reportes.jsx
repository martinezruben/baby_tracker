import { Link } from '@inertiajs/react';
import { ChevronLeft, Download, Printer } from 'lucide-react';
import AppLayout from '../layouts/AppLayout';
import { useT } from '../lib/i18n';
import useFormat from '../lib/useFormat';

const W = 360;
const H = 170;
const M = { left: 34, right: 6, top: 10, bottom: 24 };

const num = (v, digits = 1) => String(Math.round(v * 10 ** digits) / 10 ** digits).replace('.', ',');

function niceMax(max) {
    if (max <= 0) return 4;
    const pow = 10 ** Math.floor(Math.log10(max));
    return [1, 2, 2.5, 4, 5, 10].map((m) => m * pow).find((v) => v >= max);
}

/** Barras por día de una sola métrica: 2 px de separación, extremo superior redondeado de 4 px. */
function BarChart({ title, series, value, format, colorClass, textFor }) {
    const values = series.map(value);
    const top = niceMax(Math.max(...values));
    const ticks = [0, top / 2, top];
    const innerW = W - M.left - M.right;
    const innerH = H - M.top - M.bottom;
    const slot = innerW / series.length;
    const bar = Math.max(2, slot - 2);
    const y = (v) => M.top + innerH - (v / top) * innerH;
    const every = series.length > 14 ? 5 : series.length > 7 ? 2 : 1;

    return (
        <figure className="rounded-3xl bg-surface p-4 shadow-card break-inside-avoid">
            <figcaption className="mb-1 text-[17px] font-bold">{title}</figcaption>
            <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label={title} className="w-full">
                {ticks.map((v) => (
                    <g key={v}>
                        <line x1={M.left} x2={W - M.right} y1={y(v)} y2={y(v)} className="stroke-line" strokeWidth="1" />
                        <text x={M.left - 6} y={y(v) + 4} textAnchor="end" className="fill-ink-soft" fontSize="12">
                            {num(v, top < 10 ? 1 : 0)}
                        </text>
                    </g>
                ))}
                {series.map((row, i) => {
                    const v = values[i];
                    const x = M.left + i * slot + (slot - bar) / 2;
                    const h = (v / top) * innerH;
                    const r = Math.min(4, bar / 2, h);
                    return (
                        <g key={row.date}>
                            <title>{textFor(row)}</title>
                            {v > 0 && <path d={`M${x},${y(0)} V${y(v) + r} Q${x},${y(v)} ${x + r},${y(v)} H${x + bar - r} Q${x + bar},${y(v)} ${x + bar},${y(v) + r} V${y(0)} Z`} className={colorClass} />}
                            {i % every === 0 && (
                                <text x={x + bar / 2} y={H - 6} textAnchor="middle" className="fill-ink-soft" fontSize="12">
                                    {Number(row.date.slice(8))}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>
        </figure>
    );
}

export default function Reportes({ baby, ranges, report }) {
    const t = useT();
    const format = useFormat();
    const { series, averages } = report;

    const dayText = (row) => new Intl.DateTimeFormat(format.locale, { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(`${row.date}T12:00:00Z`));

    const cards = [
        { label: t('reportes.avg_feeds'), value: num(averages.feeds), hint: averages.ml ? `${averages.ml} ml` : null },
        { label: t('reportes.avg_sleep'), value: `${num(averages.sleep_hours)} h` },
        { label: t('reportes.avg_diapers'), value: num(averages.diapers) },
    ];

    return (
        <AppLayout title={t('reportes.title')}>
            <div className="flex flex-col gap-5 print:gap-3">
                <header className="flex min-h-14 items-center gap-3">
                    <Link href="/" aria-label={t('registrar.back_home')} className="flex size-12 items-center justify-center rounded-full bg-surface text-ink shadow-soft focus-visible:outline-2 focus-visible:outline-primary print:hidden">
                        <ChevronLeft aria-hidden="true" className="size-[22px]" strokeWidth={2} />
                    </Link>
                    <div>
                        <h1 className="text-2xl leading-tight font-bold">{t('reportes.title')}</h1>
                        <div className="text-sm text-ink-soft">{t('reportes.subtitle', { name: baby.first_name, n: report.days })}</div>
                    </div>
                </header>

                <nav aria-label={t('reportes.range')} className="flex gap-2 print:hidden">
                    {ranges.map((n) => (
                        <Link
                            key={n}
                            href={`/reportes?dias=${n}`}
                            preserveScroll
                            aria-current={report.days === n ? 'true' : undefined}
                            className={`flex min-h-11 items-center rounded-full px-5 text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${report.days === n ? 'bg-primary font-semibold text-on-primary' : 'bg-surface font-medium text-ink'}`}
                        >
                            {t('reportes.days', { n })}
                        </Link>
                    ))}
                </nav>

                {report.active_days === 0 ? (
                    <p className="rounded-3xl bg-surface p-8 text-center text-base text-ink-soft shadow-card">{t('reportes.empty')}</p>
                ) : (
                    <>
                        <section aria-label={t('reportes.averages')} className="grid grid-cols-3 gap-2.5">
                            {cards.map((c) => (
                                <div key={c.label} className="flex flex-col gap-1 rounded-[22px] bg-surface p-3 shadow-card">
                                    <span className="text-[13px] leading-tight text-ink-soft">{c.label}</span>
                                    <span className="text-2xl leading-none font-bold">{c.value}</span>
                                    {c.hint && <span className="text-[13px] text-ink-soft">{c.hint}</span>}
                                </div>
                            ))}
                        </section>
                        <p className="-mt-3 text-[13px] text-ink-soft">{t('reportes.avg_note', { n: report.active_days })}</p>

                        <BarChart title={t('reportes.feeds')} series={series} value={(r) => r.feeds} colorClass="fill-chart-food" textFor={(r) => `${dayText(r)}: ${r.feeds} · ${r.ml} ml`} />
                        <BarChart title={t('reportes.sleep')} series={series} value={(r) => Math.round((r.sleep_minutes / 60) * 10) / 10} colorClass="fill-chart-sleep" textFor={(r) => `${dayText(r)}: ${num(r.sleep_minutes / 60)} h`} />
                        <BarChart title={t('reportes.diapers')} series={series} value={(r) => r.diapers} colorClass="fill-chart-diaper" textFor={(r) => `${dayText(r)}: ${r.diapers} (${r.wet} ${t('reportes.wet')}, ${r.dirty} ${t('reportes.dirty')})`} />

                        <details className="rounded-3xl bg-surface p-4 shadow-card print:open">
                            <summary className="flex min-h-12 cursor-pointer items-center text-base font-semibold">{t('reportes.table')}</summary>
                            <div className="overflow-x-auto">
                                <table className="mt-2 w-full text-left text-[15px]">
                                    <thead>
                                        <tr className="text-ink-soft">
                                            <th className="py-2 pr-3 font-medium">{t('reportes.day')}</th>
                                            <th className="px-2 font-medium">{t('reportes.feeds')}</th>
                                            <th className="px-2 font-medium">{t('reportes.sleep')}</th>
                                            <th className="px-2 font-medium">{t('reportes.diapers')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {[...series].reverse().map((r) => (
                                            <tr key={r.date} className="border-t border-line">
                                                <td className="py-2 pr-3">{dayText(r)}</td>
                                                <td className="px-2">{r.feeds}{r.ml ? ` · ${r.ml} ml` : ''}</td>
                                                <td className="px-2">{num(r.sleep_minutes / 60)} h</td>
                                                <td className="px-2">{r.diapers}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </details>
                    </>
                )}

                <section className="flex flex-col gap-3 print:hidden">
                    <Link href="/crecimiento" className="flex min-h-14 items-center justify-center rounded-full bg-surface text-base font-semibold shadow-soft focus-visible:outline-2 focus-visible:outline-primary">
                        {t('reportes.growth_link')}
                    </Link>
                    <a href="/reportes/exportar" download className="flex min-h-14 items-center justify-center gap-2 rounded-full bg-primary text-lg font-bold text-on-primary hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        <Download aria-hidden="true" className="size-5" />
                        {t('reportes.export')}
                    </a>
                    <button type="button" onClick={() => window.print()} className="flex min-h-14 items-center justify-center gap-2 rounded-full bg-surface text-base font-semibold shadow-soft focus-visible:outline-2 focus-visible:outline-primary">
                        <Printer aria-hidden="true" className="size-5" />
                        {t('reportes.print')}
                    </button>
                    <p className="text-[13px] text-ink-soft">{t('reportes.export_help')}</p>
                </section>
            </div>
        </AppLayout>
    );
}
