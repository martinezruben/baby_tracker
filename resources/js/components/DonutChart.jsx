const R = 40;
const C = 2 * Math.PI * R; // 251.33

const COLORS = {
    feed: 'var(--color-chart-food)',
    sleep: 'var(--color-chart-sleep)',
    diaper: 'var(--color-chart-diaper)',
    medicine: 'var(--color-chart-medicine)',
    play: 'var(--color-chart-play)',
};

/** Dona de 112 px con el total en el centro (docs/mockup/Main.dc.html). Cada tramo = nº de registros. */
export default function DonutChart({ segments, total, label, caption }) {
    const visible = segments.filter((s) => s.count > 0);
    const gap = visible.length > 1 ? 3 : 0;
    let offset = 0;

    return (
        <div className="relative size-28 shrink-0">
            <svg width="112" height="112" viewBox="0 0 112 112" role="img" aria-label={label}>
                <circle cx="56" cy="56" r={R} fill="none" stroke="var(--color-track)" strokeWidth="16" />
                <g transform="rotate(-90 56 56)" fill="none" strokeWidth="16">
                    {visible.map((segment) => {
                        const length = (segment.count / total) * C;
                        const circle = (
                            <circle
                                key={segment.key}
                                cx="56"
                                cy="56"
                                r={R}
                                stroke={COLORS[segment.key]}
                                strokeDasharray={`${Math.max(0, length - gap)} ${C}`}
                                strokeDashoffset={-offset}
                            />
                        );
                        offset += length;
                        return circle;
                    })}
                </g>
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <div className="text-2xl leading-none font-bold">{total}</div>
                <div className="text-xs text-ink-soft">{caption}</div>
            </div>
        </div>
    );
}
