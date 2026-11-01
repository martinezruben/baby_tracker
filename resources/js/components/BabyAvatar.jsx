/** Cara ilustrada del bebé (sin foto): círculo suave, pelo y mejillas. */
export default function BabyAvatar({ className = 'size-14', label }) {
    return (
        <svg viewBox="0 0 56 56" role="img" aria-label={label} className={className}>
            <circle cx="28" cy="28" r="28" className="fill-vaccine" />
            <circle cx="28" cy="31" r="17" fill="#F2C7A2" />
            <path d="M17 22c2-8 20-9 22 0-5-3-12-4-22 0Z" fill="#5B3A29" />
            <path d="M27 12c1 3 4 3 5 1" fill="none" stroke="#5B3A29" strokeWidth="2.4" strokeLinecap="round" />
            <circle cx="22" cy="31" r="1.9" fill="#3A2418" />
            <circle cx="34" cy="31" r="1.9" fill="#3A2418" />
            <circle cx="19.5" cy="36" r="3" fill="#EFA08A" opacity="0.55" />
            <circle cx="36.5" cy="36" r="3" fill="#EFA08A" opacity="0.55" />
            <path d="M24.5 37c2 2 5 2 7 0" fill="none" stroke="#3A2418" strokeWidth="1.8" strokeLinecap="round" />
        </svg>
    );
}
