import { Head } from '@inertiajs/react';
import { useT } from '../lib/i18n';

export default function GuestLayout({ title, children }) {
    const t = useT();

    return (
        <>
            <Head title={title} />
            <main className="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-6 px-4 py-8">
                <div className="flex items-center gap-3">
                    <img src="/brand/kidu-mark.png" alt="" width="48" height="47" className="size-12 object-contain" />
                    <span className="text-xl font-bold">{t('app.name')}</span>
                </div>
                <div className="rounded-3xl bg-surface p-6 shadow-sm">{children}</div>
            </main>
        </>
    );
}
