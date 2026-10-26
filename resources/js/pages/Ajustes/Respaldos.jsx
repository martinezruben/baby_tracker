import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import Section from '../../components/form/Section';
import Switch from '../../components/Switch';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';
import { fileSize } from '../../components/AppointmentFiles';
import useFormat from '../../lib/useFormat';

export default function Respaldos({ backups, keep, time, auto_files: autoFiles }) {
    const t = useT();
    const format = useFormat();
    const { errors } = usePage().props;
    const [withFiles, setWithFiles] = useState(false);
    const create = useForm({ files: false });

    return (
        <AppLayout title={t('respaldos.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/ajustes" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('ajustes.back')}
                </Link>
                <header>
                    <h1 className="text-2xl leading-tight font-bold">{t('respaldos.title')}</h1>
                    <p className="text-sm text-ink-soft">{t('respaldos.subtitle')}</p>
                </header>

                {errors?.backup && (
                    <p role="alert" className="rounded-2xl bg-danger-soft p-4 text-base font-medium text-danger-ink">
                        {errors.backup}
                    </p>
                )}

                <Section title={t('respaldos.new')}>
                    <Switch label={t('respaldos.include_files')} checked={withFiles} onChange={setWithFiles} />
                    <p className="text-sm text-ink-soft">{t('respaldos.include_files_help')}</p>
                    <a
                        href={`/ajustes/respaldos/exportar${withFiles ? '?files=1' : ''}`}
                        className="flex min-h-14 items-center justify-center gap-2 rounded-full bg-primary text-lg font-bold text-on-primary hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >
                        <Download aria-hidden="true" className="size-5" />
                        {t('respaldos.download')}
                    </a>
                    <Button
                        variant="secondary"
                        disabled={create.processing}
                        onClick={() => {
                            create.transform(() => ({ files: withFiles }));
                            create.post('/ajustes/respaldos', { preserveScroll: true });
                        }}
                    >
                        {create.processing ? t('respaldos.creating') : t('respaldos.save_here')}
                    </Button>
                    <p className="text-sm text-ink-soft">{t('respaldos.pins_note')}</p>
                </Section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-[19px] font-bold">{t('respaldos.stored')}</h2>
                    <p className="text-sm text-ink-soft">{t('respaldos.auto_info', { time, keep })}{autoFiles ? ` ${t('respaldos.auto_files_on')}` : ''}</p>
                    {backups.length === 0 ? (
                        <p className="text-[15px] text-ink-soft">{t('respaldos.none')}</p>
                    ) : (
                        <ul className="flex flex-col gap-2.5">
                            {backups.map((b) => {
                                const iso = new Date(b.at * 1000).toISOString();
                                return (
                                    <li key={b.name} className="flex items-center gap-2 rounded-[22px] bg-surface p-3 shadow-card">
                                        <span className="min-w-0 grow">
                                            <span className="block truncate text-base font-semibold">
                                                {format.date(iso)} {format.time(iso)}
                                            </span>
                                            <span className="text-[13px] text-ink-soft">
                                                {fileSize(b.size)} · {b.files ? t('respaldos.with_files') : t('respaldos.data_only')}
                                            </span>
                                        </span>
                                        <a href={`/ajustes/respaldos/${b.name}`} aria-label={`${t('respaldos.download_one')}: ${b.name}`} className="flex size-12 shrink-0 items-center justify-center rounded-full bg-canvas text-ink focus-visible:outline-2 focus-visible:outline-primary">
                                            <Download aria-hidden="true" className="size-5" strokeWidth={1.75} />
                                        </a>
                                        <button
                                            type="button"
                                            aria-label={`${t('common.delete')}: ${b.name}`}
                                            onClick={() => router.delete(`/ajustes/respaldos/${b.name}`, { preserveScroll: true })}
                                            className="flex size-12 shrink-0 items-center justify-center rounded-full bg-canvas text-danger-ink focus-visible:outline-2 focus-visible:outline-primary"
                                        >
                                            <Trash2 aria-hidden="true" className="size-5" strokeWidth={1.75} />
                                        </button>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>

                <section className="rounded-3xl bg-surface p-4 shadow-card">
                    <h2 className="text-base font-bold">{t('respaldos.restore_title')}</h2>
                    <p className="mt-1 text-sm text-ink-soft">{t('respaldos.restore_help')}</p>
                    <code className="mt-2 block rounded-xl bg-canvas p-3 text-sm break-all">php artisan backup:restore ruta/al/respaldo.zip</code>
                </section>

                <p className="text-[13px] text-ink-soft">{t('respaldos.same_server_warning')}</p>
            </div>
        </AppLayout>
    );
}
