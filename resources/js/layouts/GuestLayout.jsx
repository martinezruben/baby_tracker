import { Head } from '@inertiajs/react';
import Sky from '../components/Sky';
import { useT } from '../lib/i18n';

export default function GuestLayout({ title, children }) {
    const t = useT();

    return (
        <>
            <Head title={title} />
            <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-0 h-64 overflow-hidden bg-gradient-to-b from-sky-1 to-transparent">
                <Sky className="mx-auto max-w-md" />
            </div>
            <main className="relative mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-6 px-4 py-8">
                <div className="flex items-center gap-3">
                    <img src="/brand/kidu-mark.png" alt="" width="48" height="47" className="size-12 object-contain" />
                    <span className="text-xl font-semibold">{t('app.name')}</span>
                </div>
                <div className="rounded-3xl bg-surface p-6 shadow-sm">{children}</div>
            </main>
        </>
    );
}
