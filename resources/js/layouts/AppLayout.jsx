import { Head } from '@inertiajs/react';
import FloatingNav from '../components/FloatingNav';
import Snackbar from '../components/Snackbar';

export default function AppLayout({ title, children, nav = true, className = '' }) {
    return (
        <>
            <Head title={title} />
            <main className={`mx-auto w-full px-5 pt-3 ${nav ? 'pb-36' : 'pb-8'} ${className || 'max-w-xl'}`}>{children}</main>
            {nav && <FloatingNav />}
            <Snackbar />
        </>
    );
}
