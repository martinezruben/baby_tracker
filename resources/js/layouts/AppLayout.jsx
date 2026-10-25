import { Head, router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import { useT } from '../lib/i18n';

export default function AppLayout({ title, children }) {
    const { auth, babies, activeBabyId, flash } = usePage().props;
    const t = useT();

    return (
        <>
            <Head title={title} />
            <header className="sticky top-0 z-10 border-b border-ink/10 bg-surface">
                <div className="mx-auto flex min-h-14 max-w-[1200px] items-center justify-between gap-3 px-4">
                    {babies?.length > 1 ? (
                        <select
                            aria-label={t('common.select_baby')}
                            value={activeBabyId ?? ''}
                            onChange={(e) => router.post(`/bebes/${e.target.value}/seleccionar`)}
                            className="min-h-12 rounded-full bg-surface-base px-4 text-base font-semibold text-ink"
                        >
                            {babies.map((baby) => (
                                <option key={baby.id} value={baby.id}>
                                    {baby.first_name}
                                </option>
                            ))}
                        </select>
                    ) : (
                        <span className="text-base font-semibold">{babies?.[0]?.first_name}</span>
                    )}
                    <button
                        type="button"
                        onClick={() => router.post('/salir')}
                        aria-label={t('common.logout')}
                        className="inline-flex min-h-12 min-w-12 items-center justify-center gap-2 rounded-full px-3 text-base text-ink-soft hover:bg-ink/5"
                    >
                        <span className="hidden sm:inline">{auth.caretaker?.name}</span>
                        <LogOut aria-hidden="true" className="size-5" />
                    </button>
                </div>
            </header>
            {flash?.status && (
                <p role="status" className="mx-auto mt-3 max-w-[1200px] px-4 text-sm font-medium text-primary">
                    {flash.status}
                </p>
            )}
            <main className="mx-auto max-w-[1200px] px-4 py-6">{children}</main>
        </>
    );
}
