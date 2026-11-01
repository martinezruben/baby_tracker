import { Link, router, usePage } from '@inertiajs/react';
import { ChevronRight, LogOut, Users } from 'lucide-react';
import Sky from '../components/Sky';
import AppLayout from '../layouts/AppLayout';
import { KINDS, MODULES } from '../lib/activities';
import { useT } from '../lib/i18n';

const row = 'flex min-h-16 items-center gap-3 rounded-[22px] bg-surface p-3 text-left shadow-card focus-visible:outline-2 focus-visible:outline-primary';

function Icon({ kind, children }) {
    return <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>{children}</span>;
}

function Modules({ name, admin, badges }) {
    const t = useT();

    return (
        <section className="flex flex-col gap-3.5">
            <h2 className="text-[19px] font-bold">{t('home.everything', { name })}</h2>
            <ul className="grid grid-cols-4 gap-x-2 gap-y-4">
                {MODULES.map(({ key, icon: ModuleIcon, kind, href }) => (
                    <li key={key} className="flex justify-center">
                        <Link href={key === 'settings' && !admin ? '/ajustes/dispositivo' : href} className="flex flex-col items-center gap-2 focus-visible:outline-2 focus-visible:outline-primary">
                            <span className={`relative flex size-[66px] items-center justify-center rounded-3xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                                <ModuleIcon aria-hidden="true" className="size-7" strokeWidth={1.75} />
                                {badges?.[key] > 0 && <span className="absolute -top-1 -right-1 flex h-[22px] min-w-[22px] items-center justify-center rounded-full bg-danger-ink px-1.5 text-xs font-bold text-canvas">{badges[key]}</span>}
                            </span>
                            <span className="text-[13px] font-medium">{t(`home.modules.${key}`)}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Mas({ caretakers, slug, badges }) {
    const t = useT();
    const { auth, babies, activeBabyId, family } = usePage().props;
    const name = auth.caretaker.name;
    const babyName = babies.find((b) => b.id === activeBabyId)?.first_name ?? '';

    return (
        <AppLayout title={t('nav.more')}>
            <div className="flex flex-col gap-5">
                <header className="relative flex items-center gap-3.5 overflow-hidden rounded-[32px] border border-line bg-gradient-to-br from-sky-1 to-sky-2 p-5">
                    <Sky className="opacity-70" />
                    <div className="relative flex size-16 items-center justify-center rounded-full bg-primary text-[28px] font-semibold text-on-primary">{name.slice(0, 1).toUpperCase()}</div>
                    <div className="relative">
                        <h1 className="text-[26px] leading-tight font-semibold">{name}</h1>
                        <div className="text-sm text-ink-soft">{t(`more.role_${auth.caretaker.role}`)} · {family.name}</div>
                    </div>
                </header>

                <Modules name={babyName} admin={auth.caretaker.role === 'admin'} badges={badges} />

                <section className="flex flex-col gap-3">
                    {caretakers > 1 && (
                        <Link href={`/entrar/${slug}`} className={row}>
                            <Icon kind="sleep">
                                <Users aria-hidden="true" className="size-6" strokeWidth={1.75} />
                            </Icon>
                            <span className="grow text-base font-bold">{t('more.switch_caretaker')}</span>
                            <ChevronRight aria-hidden="true" className="size-5 text-ink-soft" strokeWidth={2} />
                        </Link>
                    )}
                    <button type="button" onClick={() => router.post('/salir')} className={row}>
                        <Icon kind="neutral">
                            <LogOut aria-hidden="true" className="size-6" strokeWidth={1.75} />
                        </Icon>
                        <span className="grow text-base font-bold">{t('common.logout')}</span>
                    </button>
                </section>
            </div>
        </AppLayout>
    );
}
