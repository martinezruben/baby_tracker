import { Link, usePage } from '@inertiajs/react';
import { ArchiveRestore, ArrowLeft, Baby, ChevronRight, SlidersHorizontal, Syringe, Users } from 'lucide-react';
import AppLayout from '../../layouts/AppLayout';
import { KINDS } from '../../lib/activities';
import { useT } from '../../lib/i18n';

const row = 'flex min-h-[72px] items-center gap-3 rounded-[22px] bg-surface p-3 text-left shadow-card focus-visible:outline-2 focus-visible:outline-primary';

function Item({ href, icon: Icon, kind, title, help }) {
    return (
        <li>
            <Link href={href} className={row}>
                <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>
                    <Icon aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </span>
                <span className="flex min-w-0 grow flex-col">
                    <span className="text-base font-bold">{title}</span>
                    <span className="text-[13px] text-ink-soft">{help}</span>
                </span>
                <ChevronRight aria-hidden="true" className="size-5 shrink-0 text-ink-soft" strokeWidth={2} />
            </Link>
        </li>
    );
}

export default function Index({ family_name, counts }) {
    const t = useT();

    return (
        <AppLayout title={t('ajustes.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/mas" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav.more')}
                </Link>
                <header>
                    <h1 className="text-2xl leading-tight font-bold">{t('ajustes.title')}</h1>
                    <p className="text-sm text-ink-soft">{family_name}</p>
                </header>

                <ul className="flex flex-col gap-3">
                    <Item href="/ajustes/cuidadores" icon={Users} kind="sleep" title={t('ajustes.hub.caretakers')} help={`${t.choice('ajustes.hub.active', counts.caretakers)} · ${t('ajustes.hub.caretakers_help')}`} />
                    <Item href="/ajustes/bebes" icon={Baby} kind="diaper" title={t('ajustes.hub.babies')} help={`${t.choice('ajustes.hub.count_babies', counts.babies)} · ${t('ajustes.hub.babies_help')}`} />
                    <Item href="/ajustes/preferencias" icon={SlidersHorizontal} kind="growth" title={t('ajustes.hub.preferences')} help={t('ajustes.hub.preferences_help')} />
                    <Item href="/ajustes/respaldos" icon={ArchiveRestore} kind="appointment" title={t('ajustes.hub.backups')} help={t('ajustes.hub.backups_help')} />
                    <Item href="/vacunas/esquema" icon={Syringe} kind="vaccine" title={t('ajustes.hub.vaccines')} help={t('ajustes.hub.vaccines_help')} />
                </ul>
            </div>
        </AppLayout>
    );
}
