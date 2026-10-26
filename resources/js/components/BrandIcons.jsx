/** Iconos sencillos (trazo como Lucide) para WhatsApp, Waze e Instagram, que Lucide no incluye. */
const base = { viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.75, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true };

export function WhatsAppIcon({ className = 'size-5' }) {
    return (
        <svg {...base} className={className}>
            <path d="M3 21l1.65-4.9A9 9 0 1 1 8.1 19.4L3 21z" />
            <path d="M9 8.5c0 3.5 3 6.5 6.5 6.5l1-1.5-2-1-.8.8c-1-.4-2.1-1.5-2.5-2.5l.8-.8-1-2L9 8.5z" />
        </svg>
    );
}

export function WazeIcon({ className = 'size-5' }) {
    return (
        <svg {...base} className={className}>
            <path d="M12 3a8 8 0 0 0-7.4 11c-.3 1.4-.9 2.3-1.6 3 1.5.1 2.7-.1 3.7-.7A8 8 0 1 0 12 3z" />
            <path d="M9.5 10h.01M14.5 10h.01" />
            <path d="M9 13.5c.9 1 2 1.5 3 1.5s2.1-.5 3-1.5" />
        </svg>
    );
}

export function InstagramIcon({ className = 'size-5' }) {
    return (
        <svg {...base} className={className}>
            <rect x="3" y="3" width="18" height="18" rx="5" />
            <circle cx="12" cy="12" r="4" />
            <path d="M17.5 6.5h.01" />
        </svg>
    );
}
