/** Tarjeta de sección de un formulario largo (recordatorios, citas). */
export default function Section({ title, help, error, children }) {
    return (
        <section className="flex flex-col gap-3 rounded-3xl bg-surface p-4 shadow-card">
            {(title || help) && (
                <div>
                    {title && <h2 className="text-base font-semibold">{title}</h2>}
                    {help && <p className="text-sm text-ink-soft">{help}</p>}
                </div>
            )}
            {children}
            {error && (
                <p role="alert" className="text-sm font-medium text-red-700">
                    {error}
                </p>
            )}
        </section>
    );
}
