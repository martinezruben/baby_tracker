import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, Home, List, LayoutGrid, Plus } from 'lucide-react';
import { useT } from '../lib/i18n';

/** Barra inferior estilo Aire: cuatro destinos con etiqueta y el botón + elevado en el centro. */
export default function FloatingNav() {
    const { url } = usePage();
    const t = useT();
    const path = url.split('?')[0];

    const active = path === '/' ? 'home' : path.startsWith('/bitacora') ? 'log' : path.startsWith('/calendario') ? 'calendar' : path.startsWith('/mas') || path.startsWith('/recordatorios') ? 'more' : null;

    const item = (key, href, Icon, label) => {
        const on = active === key;
        return (
            <Link
                key={key}
                href={href}
                aria-current={on ? 'page' : undefined}
                className={`flex min-h-12 min-w-12 flex-1 flex-col items-center justify-center gap-0.5 rounded-2xl text-[12px] leading-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                    on ? 'font-bold text-primary' : 'font-medium text-ink-soft'
                }`}
            >
                <Icon aria-hidden="true" className="size-[22px]" strokeWidth={on ? 2.25 : 1.75} />
                {label}
            </Link>
        );
    };

    return (
        <nav
            aria-label={t('nav.main')}
            className="fixed print:hidden inset-x-4 bottom-[max(1rem,env(safe-area-inset-bottom))] z-20 mx-auto flex h-[68px] max-w-[430px] items-center rounded-[30px] bg-surface px-2 shadow-nav"
        >
            {item('home', '/', Home, t('nav.home'))}
            {item('calendar', '/calendario', CalendarDays, t('nav.calendar'))}
            <div className="relative flex flex-1 justify-center">
                <Link
                    href="/registrar"
                    aria-label={t('nav.register')}
                    className="-mt-7 flex size-14 items-center justify-center rounded-full border-4 border-canvas bg-primary text-on-primary shadow-fab focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                >
                    <Plus aria-hidden="true" className="size-7" strokeWidth={2.25} />
                </Link>
            </div>
            {item('log', '/bitacora', List, t('nav.log'))}
            {item('more', '/mas', LayoutGrid, t('nav.more'))}
        </nav>
    );
}
