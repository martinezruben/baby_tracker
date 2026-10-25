import { Head } from '@inertiajs/react';

export default function Inicio({ appName }) {
    return (
        <>
            <Head title="Inicio" />
            <main className="mx-auto max-w-[1200px] px-4 py-6">
                <h1 className="text-2xl font-semibold text-ink">{appName}</h1>
                <p className="mt-2 text-base text-ink-soft">Proyecto base listo.</p>
            </main>
        </>
    );
}
