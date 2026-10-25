import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Pencil, Plus, ShieldAlert, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Button from '../components/Button';
import Field from '../components/Field';
import ChoiceChips from '../components/form/Chips';
import Section from '../components/form/Section';
import AppLayout from '../layouts/AppLayout';
import { KINDS } from '../lib/activities';
import { useT } from '../lib/i18n';

const TYPE_KIND = { food: 'food', medicine: 'medicine', environment: 'vaccine', other: 'moment' };

function AllergyForm({ allergy, types, onDone }) {
    const t = useT();
    const editing = Boolean(allergy);
    const form = useForm({
        name: allergy?.name ?? '',
        type: allergy?.type ?? 'food',
        reaction_description: allergy?.reaction_description ?? '',
        notes: allergy?.notes ?? '',
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
        editing ? form.put(`/alergias/${allergy.id}`, options) : form.post('/alergias', options);
    };

    const bind = (name) => ({ value: data[name], onChange: (e) => setData(name, e.target.value), error: errors[name] });

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <ChoiceChips label={t('alergias.type')} value={data.type} onChange={(v) => setData('type', v)} options={types.map((k) => ({ value: k, label: t(`alergias.type_${k}`) }))} />
            {errors.type && (
                <p role="alert" className="-mt-3 text-sm font-medium text-danger-ink">
                    {errors.type}
                </p>
            )}
            <Field label={t('alergias.name')} maxLength={80} autoComplete="off" {...bind('name')} />
            <Field label={t('alergias.reaction')} optional={t('common.optional')} maxLength={300} {...bind('reaction_description')} />
            <Field label={t('alergias.notes')} optional={t('common.optional')} maxLength={1000} {...bind('notes')} />
            <Button type="submit" disabled={form.processing || !data.name.trim()}>
                {t('common.save')}
            </Button>
        </form>
    );
}

function Row({ allergy, types }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState(false);
    const colors = KINDS[TYPE_KIND[allergy.type]];

    return (
        <li className="rounded-[22px] bg-surface shadow-card">
            <button type="button" aria-expanded={open} onClick={() => setOpen(!open)} className="flex min-h-[72px] w-full items-center gap-3 p-3 text-left focus-visible:outline-2 focus-visible:outline-primary">
                <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${colors.bg} ${colors.ink}`}>
                    <ShieldAlert aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </span>
                <span className="flex min-w-0 grow flex-col">
                    <span className="text-base font-bold break-words">{allergy.name}</span>
                    <span className="text-[13px] text-ink-soft">{[t(`alergias.type_${allergy.type}`), allergy.reaction_description].filter(Boolean).join(' · ')}</span>
                </span>
                <ChevronDown aria-hidden="true" className={`size-5 shrink-0 text-ink-soft transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="flex flex-col gap-4 border-t border-line p-4">
                    {editing ? (
                        <AllergyForm allergy={allergy} types={types} onDone={() => setEditing(false)} />
                    ) : (
                        <>
                            {allergy.notes && <p className="text-base whitespace-pre-line text-ink-mid">{allergy.notes}</p>}
                            <div className="flex flex-wrap gap-1">
                                <Button variant="ghost" onClick={() => setEditing(true)}>
                                    <Pencil aria-hidden="true" className="size-5" />
                                    {t('common.edit')}
                                </Button>
                                <Button variant="ghost" className="text-danger-ink hover:bg-danger-soft" onClick={() => router.delete(`/alergias/${allergy.id}`, { preserveScroll: true })}>
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

export default function Alergias({ baby, types, allergies }) {
    const t = useT();
    const [adding, setAdding] = useState(false);

    return (
        <AppLayout title={t('alergias.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('nav.home')}
                </Link>
                <header>
                    <h1 className="text-2xl leading-tight font-bold">{t('alergias.title')}</h1>
                    <p className="text-sm text-ink-soft">{t('alergias.subtitle', { name: baby.first_name })}</p>
                </header>

                {allergies.length === 0 && !adding ? (
                    <div className="flex flex-col items-center gap-3 rounded-3xl bg-surface p-8 text-center shadow-card">
                        <ShieldCheck aria-hidden="true" className="size-10 text-primary" strokeWidth={1.75} />
                        <p className="text-base text-ink-soft">{t('alergias.empty', { name: baby.first_name })}</p>
                    </div>
                ) : (
                    <ul className="flex flex-col gap-3">
                        {allergies.map((a) => (
                            <Row key={a.id} allergy={a} types={types} />
                        ))}
                    </ul>
                )}

                {adding ? (
                    <Section title={t('alergias.add')}>
                        <AllergyForm types={types} onDone={() => setAdding(false)} />
                        <Button variant="ghost" onClick={() => setAdding(false)}>
                            {t('common.cancel')}
                        </Button>
                    </Section>
                ) : (
                    <Button onClick={() => setAdding(true)}>
                        <Plus aria-hidden="true" className="size-5" />
                        {t('alergias.add')}
                    </Button>
                )}

                <p className="text-[13px] text-ink-soft">{t('alergias.help')}</p>
            </div>
        </AppLayout>
    );
}
