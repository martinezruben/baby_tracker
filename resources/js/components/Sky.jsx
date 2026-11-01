/** Cielo decorativo de las tarjetas principales: sol (o luna), nubes y, de noche, estrellas. Solo usa tokens del tema. */
export default function Sky({ className = '' }) {
    const star = { opacity: 'var(--star-opacity)' };

    return (
        <svg aria-hidden="true" viewBox="0 0 350 150" className={`pointer-events-none absolute top-0 left-0 w-full ${className}`}>
            <circle cx="300" cy="46" r="52" className="fill-sun opacity-15" />
            <circle cx="300" cy="46" r="34" className="fill-sun opacity-90" />
            {[[42, 26, 1.8], [86, 58, 1.2], [140, 20, 1.5], [214, 40, 1.2], [250, 18, 1.8], [24, 86, 1.2]].map(([x, y, r]) => (
                <circle key={`${x}-${y}`} cx={x} cy={y} r={r} className="fill-star" style={star} />
            ))}
            <g className="fill-cloud opacity-95">
                <rect x="236" y="62" width="86" height="24" rx="12" />
                <circle cx="266" cy="62" r="15" />
                <circle cx="292" cy="58" r="19" />
                <rect x="20" y="98" width="60" height="18" rx="9" className="opacity-70" />
                <circle cx="40" cy="98" r="11" className="opacity-70" />
            </g>
        </svg>
    );
}
