import { router } from '@inertiajs/react';
import { ExternalLink, FileText, Pencil, Trash2, Upload, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { useT } from '../lib/i18n';
import Button from './Button';
import Field from './Field';
import ChoiceChips from './form/Chips';
import Section from './form/Section';

export const FILE_CATEGORIES = ['prescription', 'exam', 'report', 'other'];

export const fileSize = (bytes) => (bytes / 1024 / 1024 >= 1 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

export function FileThumb({ file }) {
    return file.is_image ? (
        <img src={file.url} alt="" loading="lazy" className="size-14 shrink-0 rounded-xl object-cover" />
    ) : (
        <span className="flex size-14 shrink-0 items-center justify-center rounded-xl bg-appointment text-appointment-ink">
            <FileText aria-hidden="true" className="size-6" strokeWidth={1.75} />
        </span>
    );
}

export function CategoryBadge({ category }) {
    const t = useT();
    if (!category) return null;
    return <span className="inline-flex rounded-full bg-appointment px-2.5 py-0.5 text-xs font-semibold text-appointment-ink">{t(`citas.files.cat_${category}`)}</span>;
}

function CategoryChips({ value, onChange }) {
    const t = useT();
    return (
        <ChoiceChips
            label={t('citas.files.category')}
            value={value}
            onChange={(v) => onChange(value === v ? '' : v)}
            options={FILE_CATEGORIES.map((c) => ({ value: c, label: t(`citas.files.cat_${c}`) }))}
        />
    );
}

/** Archivos elegidos y aún sin subir: cada uno con su tipo y su descripción. */
function Staged({ appointment, items, setItems, onDone }) {
    const t = useT();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const patch = (index, change) => setItems(items.map((item, i) => (i === index ? { ...item, ...change } : item)));

    const send = () => {
        setBusy(true);
        setError(null);
        router.post(
            `/citas/${appointment.id}/archivos`,
            { files: items.map((i) => i.file), descriptions: items.map((i) => i.description), categories: items.map((i) => i.category) },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: onDone,
                onError: (errors) => setError(Object.values(errors)[0]),
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-4 rounded-2xl border border-line p-3">
            {items.map((item, i) => (
                <div key={`${item.file.name}-${i}`} className="flex flex-col gap-3 border-b border-line pb-4 last:border-b-0 last:pb-0">
                    <div className="flex items-center justify-between gap-2">
                        <span className="min-w-0 truncate text-[15px] font-semibold">{item.file.name}</span>
                        <button
                            type="button"
                            aria-label={`${t('citas.files.remove_pending')}: ${item.file.name}`}
                            onClick={() => setItems(items.filter((_, j) => j !== i))}
                            className="flex size-12 shrink-0 items-center justify-center rounded-full bg-canvas text-ink focus-visible:outline-2 focus-visible:outline-primary"
                        >
                            <X aria-hidden="true" className="size-5" strokeWidth={1.75} />
                        </button>
                    </div>
                    <CategoryChips value={item.category} onChange={(category) => patch(i, { category })} />
                    <Field
                        label={t('citas.files.description')}
                        optional={t('common.optional')}
                        placeholder={t('citas.files.description_ph')}
                        value={item.description}
                        onChange={(e) => patch(i, { description: e.target.value })}
                        maxLength={500}
                    />
                </div>
            ))}
            {error && (
                <p role="alert" className="text-sm font-medium text-danger-ink">
                    {error}
                </p>
            )}
            <Button disabled={busy || items.length === 0} onClick={send}>
                <Upload aria-hidden="true" className="size-5" />
                {busy ? t('citas.files.uploading') : t('citas.files.upload', { n: items.length })}
            </Button>
            <Button variant="ghost" onClick={onDone}>
                {t('common.cancel')}
            </Button>
        </div>
    );
}

function FileRow({ appointment, file }) {
    const t = useT();
    const [editing, setEditing] = useState(false);
    const [description, setDescription] = useState(file.description ?? '');
    const [category, setCategory] = useState(file.category ?? '');

    const save = () =>
        router.patch(`/citas/${appointment.id}/archivos/${file.id}`, { description, category: category || null }, { preserveScroll: true, onSuccess: () => setEditing(false) });

    return (
        <li className="flex flex-col gap-3 rounded-2xl bg-canvas p-2">
            <div className="flex items-center gap-3">
                <FileThumb file={file} />
                <span className="min-w-0 grow">
                    <CategoryBadge category={file.category} />
                    <span className="block truncate text-[15px] font-semibold">{file.name}</span>
                    <span className="text-xs text-ink-soft">{fileSize(file.size)}</span>
                </span>
                <a
                    href={file.url}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={`${t('citas.files.open')}: ${file.name}`}
                    className="flex size-12 shrink-0 items-center justify-center rounded-full bg-surface text-ink focus-visible:outline-2 focus-visible:outline-primary"
                >
                    <ExternalLink aria-hidden="true" className="size-5" strokeWidth={1.75} />
                </a>
            </div>
            {file.description && !editing && <p className="px-1 text-base whitespace-pre-line text-ink-mid">{file.description}</p>}
            {editing ? (
                <div className="flex flex-col gap-3 px-1">
                    <CategoryChips value={category} onChange={setCategory} />
                    <Field label={t('citas.files.description')} placeholder={t('citas.files.description_ph')} value={description} onChange={(e) => setDescription(e.target.value)} maxLength={500} />
                    <div className="flex gap-2">
                        <Button onClick={save}>{t('common.save')}</Button>
                        <Button variant="ghost" onClick={() => setEditing(false)}>
                            {t('common.cancel')}
                        </Button>
                    </div>
                </div>
            ) : (
                <div className="flex gap-1">
                    <Button variant="ghost" onClick={() => setEditing(true)}>
                        <Pencil aria-hidden="true" className="size-5" />
                        {t('citas.files.edit')}
                    </Button>
                    <Button
                        variant="ghost"
                        className="text-danger-ink hover:bg-danger-soft"
                        aria-label={`${t('citas.files.delete')}: ${file.name}`}
                        onClick={() => router.delete(`/citas/${appointment.id}/archivos/${file.id}`, { preserveScroll: true })}
                    >
                        <Trash2 aria-hidden="true" className="size-5" />
                        {t('common.delete')}
                    </Button>
                </div>
            )}
        </li>
    );
}

export default function AppointmentFiles({ appointment }) {
    const t = useT();
    const input = useRef(null);
    const [items, setItems] = useState([]);

    const choose = (fileList) => {
        const picked = Array.from(fileList).map((file) => ({ file, description: '', category: '' }));
        if (picked.length > 0) setItems((current) => [...current, ...picked]);
        if (input.current) input.current.value = '';
    };

    return (
        <Section title={t('citas.files.title')}>
            {appointment.files.length === 0 ? (
                <p className="text-[15px] text-ink-soft">{t('citas.files.empty')}</p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {appointment.files.map((file) => (
                        <FileRow key={file.id} appointment={appointment} file={file} />
                    ))}
                </ul>
            )}

            <input ref={input} type="file" multiple accept="application/pdf,image/jpeg,image/png,image/webp,image/heic,image/heif" className="sr-only" id="appointment-files" onChange={(e) => choose(e.target.files)} />

            {items.length > 0 ? (
                <Staged appointment={appointment} items={items} setItems={setItems} onDone={() => setItems([])} />
            ) : (
                <Button variant="secondary" onClick={() => input.current?.click()}>
                    <Upload aria-hidden="true" className="size-5" />
                    {t('citas.files.add')}
                </Button>
            )}
            {items.length > 0 && (
                <Button variant="secondary" onClick={() => input.current?.click()}>
                    {t('citas.files.add_more')}
                </Button>
            )}
            <p className="text-sm text-ink-soft">{t('citas.files.hint')}</p>
        </Section>
    );
}
