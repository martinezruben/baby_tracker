const variants = {
    primary: 'bg-primary text-white hover:bg-primary/90',
    secondary: 'bg-surface text-ink border border-ink/15 hover:bg-ink/5',
    ghost: 'bg-transparent text-primary hover:bg-primary/10',
};

export default function Button({ variant = 'primary', className = '', type = 'button', ...props }) {
    return (
        <button
            type={type}
            className={`inline-flex min-h-12 items-center justify-center gap-2 rounded-full px-6 text-base font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50 ${variants[variant]} ${className}`}
            {...props}
        />
    );
}
