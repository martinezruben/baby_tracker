import { Head, Link, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import Snackbar from '../components/Snackbar';
import { KINDS } from '../lib/activities';
import { useT } from '../lib/i18n';

/**
 * Hoja de formulario (docs/mockup/Panal.dc.html): fondo atenuado, hoja blanca con esquinas
 * superiores de 28 px, cabecera con icono del tipo, contenido con scroll y botón principal fijo abajo.
 */
export default function SheetLayout({ title, subtitle, kind, icon: Icon, closeHref, actionLabel, editing = false, onSubmit, disabled, processing, children, footer }) {
    const t = useT();
    const { babies, activeBabyId } = usePage().props;
    const babyName = babies?.find((b) => b.id === activeBabyId)?.first_name ?? '';
    const colors = KINDS[kind];

    return (
        <>
            <Head title={title} />
            <div className="fixed inset-0 bg-[rgba(18,32,48,0.45)]" aria-hidden="true" />
            <form
                role="dialog"
                aria-label={title}
                onSubmit={onSubmit}
                className="fixed inset-x-0 top-16 bottom-0 z-10 mx-auto flex max-w-xl flex-col rounded-t-[28px] bg-surface"
            >
                <div className="flex flex-1 flex-col gap-5 overflow-y-auto px-5 pt-2.5 pb-4">
                    <div aria-hidden="true" className="h-[5px] w-10 shrink-0 self-center rounded-[3px] bg-line" />

                    <div className="flex items-center justify-between gap-3">
                        <div className="flex min-w-0 items-center gap-3">
                            <div className={`flex size-[50px] shrink-0 items-center justify-center rounded-full ${colors.bg} ${colors.ink}`}>
                                <Icon aria-hidden="true" className="size-[26px]" strokeWidth={1.75} />
                            </div>
                            <div className="min-w-0">
                                <h1 className="text-[22px] leading-tight font-bold">{title}</h1>
                                {(editing || subtitle) && <p className="text-sm text-ink-soft">{editing ? t('registrar.editing', { name: babyName }) : subtitle}</p>}
                            </div>
                        </div>
                        <Link
                            href={closeHref ?? (editing ? '/bitacora' : '/registrar')}
                            aria-label={t('common.close')}
                            className="flex size-12 shrink-0 items-center justify-center rounded-full bg-canvas text-ink focus-visible:outline-2 focus-visible:outline-primary"
                        >
                            <X aria-hidden="true" className="size-[22px]" strokeWidth={2} />
                        </Link>
                    </div>

                    {children}
                </div>

                <div className="flex flex-col items-center gap-2 px-5 pt-3 pb-[max(1.75rem,env(safe-area-inset-bottom))]">
                    {footer}
                    <button
                        type="submit"
                        disabled={disabled || processing}
                        className="min-h-[58px] w-full rounded-full bg-primary text-lg font-bold text-white transition-colors hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:opacity-50"
                    >
                        {editing ? t('registrar.save_changes') : actionLabel}
                    </button>
                    {!editing && <p className="text-sm text-ink-soft">{t('registrar.undo_hint')}</p>}
                </div>
            </form>
            <Snackbar />
        </>
    );
}
