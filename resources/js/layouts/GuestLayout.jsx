import { Head } from '@inertiajs/react';
import { Baby } from 'lucide-react';

export default function GuestLayout({ title, children }) {
    return (
        <>
            <Head title={title} />
            <main className="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center gap-6 px-4 py-8">
                <div className="flex items-center gap-3 text-primary">
                    <Baby aria-hidden="true" className="size-8" />
                    <span className="text-xl font-bold">Baby Tracker</span>
                </div>
                <div className="rounded-3xl bg-surface p-6 shadow-sm">{children}</div>
            </main>
        </>
    );
}
