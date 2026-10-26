import { Link, router } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, FolderSearch, Search, X } from 'lucide-react';
import { useState } from 'react';
import { CategoryBadge, fileSize, FileThumb } from '../components/AppointmentFiles';
import AppLayout from '../layouts/AppLayout';
import { useT } from '../lib/i18n';
import useFormat from '../lib/useFormat';

export default function Documentos({ baby, q, tipo, categories, total, files }) {
    const t = useT();
    const format = useFormat();
    const [text, setText] = useState(q);

    const go = (params) =>
        router.get('/documentos', Object.fromEntries(Object.entries(params).filter(([, v]) => v)), { preserveState: true, preserveScroll: true, replace: true, only: ['q', 'tipo', 'total', 'files'] });

    const chip = (value, label) => {
        const selected = (tipo ?? null) === value;
        return (
            <button
                key={label}
                type="button"
                aria-pressed={selected}
                onClick={() => go({ q: text, tipo: value })}
                className={`flex min-h-11 shrink-0 items-center rounded-full px-5 text-[15px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${selected ? 'bg-primary font-semibold text-on-primary' : 'bg-surface font-medium text-ink'}`}
            >
                {label}
            </button>
        );
    };

    return (
        <AppLayout title={t('documentos.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/citas" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('citas.title')}
                </Link>
                <header>
                    <h1 className="text-2xl leading-tight font-bold">{t('documentos.title')}</h1>
                    <p className="text-sm text-ink-soft">{t('documentos.subtitle', { name: baby.first_name })}</p>
                </header>

                <form
                    role="search"
                    onSubmit={(e) => {
                        e.preventDefault();
                        go({ q: text.trim(), tipo });
                    }}
                    className="flex gap-2"
                >
                    <div className="relative grow">
                        <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-ink-soft" strokeWidth={1.75} />
                        <input
                            type="search"
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            aria-label={t('documentos.search')}
                            placeholder={t('documentos.search_ph')}
                            className="min-h-12 w-full rounded-full border border-ink/20 bg-surface pr-4 pl-12 text-base text-ink focus-visible:outline-2 focus-visible:outline-primary"
                        />
                    </div>
                    <button type="submit" className="min-h-12 shrink-0 rounded-full bg-primary px-5 text-base font-semibold text-on-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                        {t('documentos.go')}
                    </button>
                </form>

                <nav aria-label={t('citas.files.category')} className="-mr-5 flex gap-2 overflow-x-auto pr-5 [scrollbar-width:none]">
                    {chip(null, t('documentos.all'))}
                    {categories.map((c) => chip(c, t(`citas.files.cat_${c}`)))}
                </nav>

                {(q || tipo) && (
                    <button
                        type="button"
                        onClick={() => {
                            setText('');
                            go({});
                        }}
                        className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary"
                    >
                        <X aria-hidden="true" className="size-4" />
                        {t('documentos.clear')}
                    </button>
                )}

                <p role="status" className="text-sm text-ink-soft">
                    {t.choice('documentos.count', total)}
                </p>

                {files.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center shadow-card">
                        <FolderSearch aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                        <p className="text-base text-ink-soft">{q || tipo ? t('documentos.no_results') : t('documentos.empty')}</p>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-2.5">
                        {files.map((f) => (
                            <li key={f.id} className="flex flex-col gap-2 rounded-[22px] bg-surface p-3 shadow-card">
                                <div className="flex items-center gap-3">
                                    <FileThumb file={f} />
                                    <span className="min-w-0 grow">
                                        <CategoryBadge category={f.category} />
                                        <span className="block truncate text-[15px] font-semibold">{f.name}</span>
                                        <span className="text-xs text-ink-soft">{fileSize(f.size)}</span>
                                    </span>
                                    <a href={f.url} target="_blank" rel="noreferrer" aria-label={`${t('citas.files.open')}: ${f.name}`} className="flex size-12 shrink-0 items-center justify-center rounded-full bg-canvas text-ink focus-visible:outline-2 focus-visible:outline-primary">
                                        <ExternalLink aria-hidden="true" className="size-5" strokeWidth={1.75} />
                                    </a>
                                </div>
                                {f.description && <p className="text-base whitespace-pre-line text-ink-mid">{f.description}</p>}
                                <Link href={`/citas/${f.appointment.id}`} className="flex min-h-12 items-center text-[13px] text-primary focus-visible:outline-2 focus-visible:outline-primary">
                                    {f.appointment.reason} · {format.fullDate(f.appointment.at)}
                                    {f.appointment.doctor && ` · ${f.appointment.doctor}`}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </AppLayout>
    );
}
