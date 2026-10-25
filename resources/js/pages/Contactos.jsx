import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Mail, MapPin, Pencil, Phone, Plus, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import Button from '../components/Button';
import Field from '../components/Field';
import Section from '../components/form/Section';
import AppLayout from '../layouts/AppLayout';
import { useT } from '../lib/i18n';

const ROLES = ['pediatrician', 'emergency', 'family', 'nanny', 'other'];

function ContactForm({ contact, onDone }) {
    const t = useT();
    const editing = Boolean(contact);
    const form = useForm({
        name: contact?.name ?? '',
        role: contact?.role ?? '',
        phone: contact?.phone ?? '',
        email: contact?.email ?? '',
        address: contact?.address ?? '',
        notes: contact?.notes ?? '',
    });
    const { data, setData, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) form.reset();
                onDone?.();
            },
        };
        editing ? form.put(`/contactos/${contact.id}`, options) : form.post('/contactos', options);
    };

    const bind = (name) => ({ value: data[name], onChange: (e) => setData(name, e.target.value), error: errors[name] });

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
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
            <Field label={t('contactos.email')} optional={t('common.optional')} type="email" inputMode="email" autoComplete="off" maxLength={120} {...bind('email')} />
            <Field label={t('contactos.address')} optional={t('common.optional')} maxLength={200} {...bind('address')} />
            <Field label={t('contactos.notes')} optional={t('common.optional')} maxLength={1000} {...bind('notes')} />
            <Button type="submit" disabled={form.processing || !data.name.trim()}>
                {t('common.save')}
            </Button>
        </form>
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
                <span aria-hidden="true" className="flex size-[46px] shrink-0 items-center justify-center rounded-full bg-sleep text-lg font-bold text-sleep-ink">
                    {contact.name.slice(0, 1).toUpperCase()}
                </span>
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
                            <div className="flex flex-wrap gap-2">
                                {contact.phone && (
                                    <a href={`tel:${contact.phone.replace(/[^\d+]/g, '')}`} className={action}>
                                        <Phone aria-hidden="true" className="size-5" strokeWidth={1.75} />
                                        {contact.phone}
                                    </a>
                                )}
                                {contact.email && (
                                    <a href={`mailto:${contact.email}`} className={`${action} break-all`}>
                                        <Mail aria-hidden="true" className="size-5 shrink-0" strokeWidth={1.75} />
                                        {contact.email}
                                    </a>
                                )}
                                {contact.address && (
                                    <a href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(contact.address)}`} target="_blank" rel="noopener noreferrer" className={action}>
                                        <MapPin aria-hidden="true" className="size-5 shrink-0" strokeWidth={1.75} />
                                        {contact.address}
                                    </a>
                                )}
                            </div>
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
