import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, Home, List, MoreHorizontal, Plus } from 'lucide-react';
import { useT } from '../lib/i18n';

const circle =
    'flex size-[52px] items-center justify-center rounded-full bg-canvas text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

/** Barra flotante en forma de pastilla (docs/mockup/Main.dc.html). El destino activo va en oscuro con su etiqueta. */
export default function FloatingNav() {
    const { url } = usePage();
    const t = useT();
    const path = url.split('?')[0];

    const active = path === '/' ? 'home' : path.startsWith('/bitacora') ? 'log' : path.startsWith('/mas') || path.startsWith('/recordatorios') ? 'more' : null;

    const item = (key, href, Icon, label) =>
        active === key ? (
            <Link
                href={href}
                aria-current="page"
                className="flex h-14 items-center gap-2 rounded-[28px] bg-ink pr-[18px] pl-3.5 text-[15px] font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                <Icon aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
                {label}
            </Link>
        ) : (
            <Link href={href} aria-label={label} className={circle}>
                <Icon aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
            </Link>
        );

    const soon = (Icon, label) => (
        <span role="img" aria-label={`${label} (${t('common.soon')})`} className={`${circle} opacity-40`}>
            <Icon aria-hidden="true" className="size-[22px]" strokeWidth={1.75} />
        </span>
    );

    return (
        <nav
            aria-label={t('nav.main')}
            className="fixed inset-x-5 bottom-[max(1.5rem,env(safe-area-inset-bottom))] z-20 mx-auto flex h-[72px] max-w-[430px] items-center justify-between rounded-[36px] bg-surface p-2 shadow-nav"
        >
            {item('home', '/', Home, t('nav.home'))}
            {soon(CalendarDays, t('nav.calendar'))}
            <Link
                href="/registrar"
                aria-label={t('nav.register')}
                className="flex size-[52px] items-center justify-center rounded-full bg-primary text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                <Plus aria-hidden="true" className="size-[26px]" strokeWidth={2} />
            </Link>
            {item('log', '/bitacora', List, t('nav.log'))}
            {item('more', '/mas', MoreHorizontal, t('nav.more'))}
        </nav>
    );
}
