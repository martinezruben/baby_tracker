import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Camera, ChevronDown, Mail, MapPin, Pencil, Phone, Plus, Trash2, Users } from 'lucide-react';
import { useRef, useState } from 'react';
import { InstagramIcon, WazeIcon, WhatsAppIcon } from '../components/BrandIcons';
import Button from '../components/Button';
import Field from '../components/Field';
import Section from '../components/form/Section';
import AppLayout from '../layouts/AppLayout';
import { instagramUrl, mapsUrl, telUrl, wazeUrl, whatsappUrl } from '../lib/contactLinks';
import { useT } from '../lib/i18n';

const ROLES = ['pediatrician', 'emergency', 'family', 'nanny', 'other'];

function ContactForm({ contact, onDone }) {
    const t = useT();
    const editing = Boolean(contact);
    const input = useRef(null);
    const form = useForm({
        name: contact?.name ?? '',
        role: contact?.role ?? '',
        phone: contact?.phone ?? '',
        work_phone: contact?.work_phone ?? '',
        email: contact?.email ?? '',
        instagram: contact?.instagram ?? '',
        address: contact?.address ?? '',
        notes: contact?.notes ?? '',
        photo: null,
        remove_photo: false,
    });
    const { data, setData, errors } = form;
    const [preview, setPreview] = useState(null);
    const shownPhoto = preview ?? (data.remove_photo ? null : contact?.photo_url);

    const pick = (file) => {
        if (!file) return;
        setData((d) => ({ ...d, photo: file, remove_photo: false }));
        setPreview(URL.createObjectURL(file));
    };

    const submit = (e) => {
        e.preventDefault();
        const options = {
            forceFormData: true, // la foto viaja como archivo
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) form.reset();
                setPreview(null);
                onDone?.();
            },
        };
        // Un PUT con archivos no llega como multipart: se envía por POST con el método simulado.
        form.transform((d) => ({ ...d, remove_photo: d.remove_photo ? 1 : 0, ...(editing ? { _method: 'put' } : {}) }));
        form.post(editing ? `/contactos/${contact.id}` : '/contactos', options);
    };

    const bind = (name) => ({ value: data[name], onChange: (e) => setData(name, e.target.value), error: errors[name] });

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <div className="flex items-center gap-4">
                <Avatar name={data.name || '?'} url={shownPhoto} size="size-20 text-3xl" />
                <div className="flex flex-col gap-1">
                    <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" aria-label={t('contactos.photo_add')} onChange={(e) => pick(e.target.files?.[0])} />
                    <Button variant="secondary" onClick={() => input.current?.click()}>
                        <Camera aria-hidden="true" className="size-5" />
                        {shownPhoto ? t('contactos.photo_change') : t('contactos.photo_add')}
                    </Button>
                    {shownPhoto && (
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setPreview(null);
                                setData((d) => ({ ...d, photo: null, remove_photo: true }));
                            }}
                        >
                            {t('contactos.photo_remove')}
                        </Button>
                    )}
                </div>
            </div>
            {errors.photo && (
                <p role="alert" className="-mt-2 text-sm font-medium text-danger-ink">
                    {errors.photo}
                </p>
            )}
            <Field label={t('contactos.name')} maxLength={80} autoComplete="off" {...bind('name')} />
            <Field label={t('contactos.role')} optional={t('common.optional')} maxLength={60} {...bind('role')} />
            <div role="group" aria-label={t('contactos.role_suggestions')} className="-mt-2 flex flex-wrap gap-2">
                {ROLES.map((r) => (
                    <button
                        key={r}
                        type="button"
                        onClick={() => setData('role', t(`contactos.role_${r}`))}
                        className="min-h-12 rounded-full border border-line bg-surface px-4 text-[15px] font-medium text-ink focus-visible:outline-2 focus-visible:outline-primary"
                    >
                        {t(`contactos.role_${r}`)}
                    </button>
                ))}
            </div>
            <Field label={t('contactos.phone')} optional={t('common.optional')} type="tel" inputMode="tel" autoComplete="off" maxLength={30} {...bind('phone')} />
            <Field label={t('contactos.work_phone')} optional={t('common.optional')} hint={t('contactos.phone_help')} type="tel" inputMode="tel" autoComplete="off" maxLength={30} {...bind('work_phone')} />
            <Field label={t('contactos.email')} optional={t('common.optional')} type="email" inputMode="email" autoComplete="off" maxLength={120} {...bind('email')} />
            <Field label={t('contactos.instagram')} optional={t('common.optional')} hint={t('contactos.instagram_help')} placeholder="@usuario" autoCapitalize="none" autoCorrect="off" spellCheck={false} maxLength={80} {...bind('instagram')} />
            <Field label={t('contactos.address')} optional={t('common.optional')} maxLength={200} {...bind('address')} />
            <Field label={t('contactos.notes')} optional={t('common.optional')} maxLength={1000} {...bind('notes')} />
            <Button type="submit" disabled={form.processing || !data.name.trim()}>
                {t('common.save')}
            </Button>
        </form>
    );
}

function Avatar({ name, url, size = 'size-[46px] text-lg' }) {
    return url ? (
        <img src={url} alt="" loading="lazy" className={`${size.split(' ')[0]} ${size.split(' ')[1]} shrink-0 rounded-full object-cover`} />
    ) : (
        <span aria-hidden="true" className={`flex ${size} shrink-0 items-center justify-center rounded-full bg-sleep font-bold text-sleep-ink`}>
            {name.slice(0, 1).toUpperCase()}
        </span>
    );
}

const iconButton = 'flex size-12 shrink-0 items-center justify-center rounded-full bg-canvas text-ink focus-visible:outline-2 focus-visible:outline-primary';

/** Un teléfono con su etiqueta (personal / trabajo) y los botones de llamar y WhatsApp. */
function PhoneLine({ label, phone, name }) {
    const t = useT();
    if (!phone) return null;

    return (
        <div className="flex items-center gap-2">
            <div className="min-w-0 grow">
                <div className="text-[13px] font-semibold text-ink-soft">{label}</div>
                <div className="text-base break-words">{phone}</div>
            </div>
            <a href={telUrl(phone)} aria-label={`${t('contactos.call')}: ${name} (${label})`} className={iconButton}>
                <Phone aria-hidden="true" className="size-5" strokeWidth={1.75} />
            </a>
            <a href={whatsappUrl(phone)} target="_blank" rel="noopener noreferrer" aria-label={`WhatsApp: ${name} (${label})`} className={iconButton}>
                <WhatsAppIcon />
            </a>
        </div>
    );
}

const action = 'inline-flex min-h-12 items-center gap-2 rounded-full bg-canvas px-4 text-base font-semibold text-ink focus-visible:outline-2 focus-visible:outline-primary';

function Row({ contact }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(false);

    return (
        <li className="rounded-[22px] bg-surface shadow-card">
            <button type="button" aria-expanded={open} onClick={() => setOpen(!open)} className="flex min-h-[72px] w-full items-center gap-3 p-3 text-left focus-visible:outline-2 focus-visible:outline-primary">
                <Avatar name={contact.name} url={contact.photo_url} />
                <span className="flex min-w-0 grow flex-col">
                    <span className="text-base font-bold break-words">{contact.name}</span>
                    {contact.role && <span className="text-[13px] text-ink-soft">{contact.role}</span>}
                </span>
                <ChevronDown aria-hidden="true" className={`size-5 shrink-0 text-ink-soft transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="flex flex-col gap-4 border-t border-line p-4">
                    {editing ? (
                        <ContactForm contact={contact} onDone={() => setEditing(false)} />
                    ) : (
                        <>
                            <PhoneLine label={t('contactos.phone_personal')} phone={contact.phone} name={contact.name} />
                            <PhoneLine label={t('contactos.phone_work')} phone={contact.work_phone} name={contact.name} />
                            {contact.email && (
                                <a href={`mailto:${contact.email}`} className={`${action} break-all`}>
                                    <Mail aria-hidden="true" className="size-5 shrink-0" strokeWidth={1.75} />
                                    {contact.email}
                                </a>
                            )}
                            {contact.instagram && (
                                <div className="flex items-center gap-2">
                                    <div className="min-w-0 grow">
                                        <div className="text-[13px] font-semibold text-ink-soft">Instagram</div>
                                        <div className="text-base break-all">@{contact.instagram}</div>
                                    </div>
                                    <a href={instagramUrl(contact.instagram)} target="_blank" rel="noopener noreferrer" aria-label={`Instagram: @${contact.instagram}`} className={iconButton}>
                                        <InstagramIcon />
                                    </a>
                                </div>
                            )}
                            {contact.address && (
                                <div className="flex items-center gap-2">
                                    <div className="min-w-0 grow">
                                        <div className="text-[13px] font-semibold text-ink-soft">{t('contactos.address')}</div>
                                        <div className="text-base break-words">{contact.address}</div>
                                    </div>
                                    <a href={wazeUrl(contact.address)} target="_blank" rel="noopener noreferrer" aria-label={`${t('contactos.open_waze')}: ${contact.address}`} className={iconButton}>
                                        <WazeIcon />
                                    </a>
                                    <a href={mapsUrl(contact.address)} target="_blank" rel="noopener noreferrer" aria-label={`${t('contactos.open_maps')}: ${contact.address}`} className={iconButton}>
                                        <MapPin aria-hidden="true" className="size-5" strokeWidth={1.75} />
                                    </a>
                                </div>
                            )}
                            {contact.notes && <p className="text-base whitespace-pre-line text-ink-mid">{contact.notes}</p>}
                            <div className="flex flex-wrap gap-1">
                                <Button variant="ghost" onClick={() => setEditing(true)}>
                                    <Pencil aria-hidden="true" className="size-5" />
                                    {t('common.edit')}
                                </Button>
                                <Button variant="ghost" className="text-danger-ink hover:bg-danger-soft" onClick={() => router.delete(`/contactos/${contact.id}`, { preserveScroll: true })}>
                                    <Trash2 aria-hidden="true" className="size-5" />
                                    {t('common.delete')}
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            )}
        </li>
    );
}

export default function Contactos({ contacts }) {
    const t = useT();
    const [adding, setAdding] = useState(false);

    return (
        <AppLayout title={t('contactos.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav.home')}
                </Link>
                <header>
                    <h1 className="text-2xl leading-tight font-bold">{t('contactos.title')}</h1>
                    <p className="text-sm text-ink-soft">{t('contactos.subtitle')}</p>
                </header>

                {contacts.length === 0 && !adding ? (
                    <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center shadow-card">
                        <Users aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                        <p className="text-base text-ink-soft">{t('contactos.empty')}</p>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {contacts.map((c) => (
                            <Row key={c.id} contact={c} />
                        ))}
                    </ul>
                )}

                {adding ? (
                    <Section title={t('contactos.add')}>
                        <ContactForm onDone={() => setAdding(false)} />
                        <Button variant="ghost" onClick={() => setAdding(false)}>
                            {t('common.cancel')}
                        </Button>
                    </Section>
                ) : (
                    <Button onClick={() => setAdding(true)}>
                        <Plus aria-hidden="true" className="size-5" />
                        {t('contactos.add')}
                    </Button>
                )}
            </div>
        </AppLayout>
    );
}
