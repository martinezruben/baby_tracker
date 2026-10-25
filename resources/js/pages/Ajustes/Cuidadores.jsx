import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, ChevronDown, Plus } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import Field from '../../components/Field';
import ChoiceChips from '../../components/form/Chips';
import Section from '../../components/form/Section';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';

function Avatar({ name, color, size = 'size-[46px]' }) {
    return (
        <span aria-hidden="true" className={`flex ${size} shrink-0 items-center justify-center rounded-full text-lg font-bold text-white`} style={{ backgroundColor: color ?? '#8B6340' }}>
            {name.slice(0, 1).toUpperCase()}
        </span>
    );
}

function Swatches({ colors, value, onChange, label }) {
    return (
        <div className="flex flex-col gap-2.5">
            <div className="text-[15px] font-semibold text-ink-soft">{label}</div>
            <div role="radiogroup" aria-label={label} className="flex flex-wrap gap-2">
                {colors.map((c) => (
                    <button
                        key={c}
                        type="button"
                        role="radio"
                        aria-checked={value === c}
                        aria-label={c}
                        onClick={() => onChange(c)}
                        className="flex size-12 items-center justify-center rounded-full text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        style={{ backgroundColor: c }}
                    >
                        {value === c && <Check aria-hidden="true" className="size-5" strokeWidth={3} />}
                    </button>
                ))}
            </div>
        </div>
    );
}

function CaretakerForm({ caretaker, colors, languages, onDone }) {
    const t = useT();
    const editing = Boolean(caretaker);
    const form = useForm({
        name: caretaker?.name ?? '',
        type: caretaker?.type ?? '',
        role: caretaker?.role ?? 'user',
        badge_color: caretaker?.badge_color ?? colors[0],
        language: caretaker?.language ?? '',
        pin: '',
        pin_confirmation: '',
    });
    const { data, setData, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, type: d.type || null, language: d.language || null, pin: d.pin || null, pin_confirmation: d.pin_confirmation || null }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) form.reset();
                onDone?.();
            },
        };
        editing ? form.put(`/ajustes/cuidadores/${caretaker.id}`, options) : form.post('/ajustes/cuidadores', options);
    };

    const pin = (name, label) => ({
        label,
        type: 'password',
        inputMode: 'numeric',
        pattern: '[0-9]*',
        autoComplete: 'new-password',
        value: data[name],
        onChange: (e) => setData(name, e.target.value),
        error: errors[name],
    });

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <Field label={t('ajustes.caretakers.name')} value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} maxLength={60} autoComplete="off" />
            <Field label={t('ajustes.caretakers.type')} optional={t('common.optional')} placeholder={t('ajustes.caretakers.type_ph')} value={data.type} onChange={(e) => setData('type', e.target.value)} error={errors.type} maxLength={40} />
            <ChoiceChips
                label={t('ajustes.caretakers.role')}
                value={data.role}
                onChange={(v) => setData('role', v)}
                options={[{ value: 'user', label: t('ajustes.caretakers.role_user') }, { value: 'admin', label: t('ajustes.caretakers.role_admin') }]}
            />
            <p className="-mt-2 text-sm text-ink-soft">{t('ajustes.caretakers.role_help')}</p>
            {errors.role && <p role="alert" className="text-sm font-medium text-red-700">{errors.role}</p>}
            <Swatches colors={colors} value={data.badge_color} onChange={(v) => setData('badge_color', v)} label={t('ajustes.caretakers.color')} />
            <ChoiceChips
                label={t('ajustes.caretakers.language')}
                value={data.language}
                onChange={(v) => setData('language', v)}
                options={[{ value: '', label: t('ajustes.caretakers.language_family') }, ...languages.map((l) => ({ value: l, label: l === 'es' ? 'Español' : 'English' }))]}
            />
            <Field {...pin('pin', editing ? t('ajustes.caretakers.pin_new') : t('ajustes.caretakers.pin'))} />
            {(!editing || data.pin) && <Field {...pin('pin_confirmation', t('ajustes.caretakers.pin_confirm'))} />}
            <Button type="submit" disabled={form.processing || !data.name.trim() || (!editing && !data.pin)}>
                {editing ? t('ajustes.caretakers.save') : t('ajustes.caretakers.create')}
            </Button>
        </form>
    );
}

function Row({ caretaker, colors, languages }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const { post, processing } = useForm({});

    return (
        <li className={`rounded-[22px] bg-surface shadow-card ${caretaker.is_active ? '' : 'opacity-75'}`}>
            <button
                type="button"
                aria-expanded={open}
                onClick={() => setOpen(!open)}
                className="flex min-h-[72px] w-full items-center gap-3 p-3 text-left focus-visible:outline-2 focus-visible:outline-primary"
            >
                <Avatar name={caretaker.name} color={caretaker.badge_color} />
                <span className="flex min-w-0 grow flex-col">
                    <span className="text-base font-bold break-words">
                        {caretaker.name}
                        {caretaker.is_me && <span className="ml-2 text-xs font-semibold text-primary">{t('ajustes.caretakers.you')}</span>}
                    </span>
                    <span className="text-[13px] text-ink-soft">{[caretaker.type, caretaker.role === 'admin' ? t('ajustes.caretakers.role_admin') : null].filter(Boolean).join(' · ')}</span>
                </span>
                {!caretaker.is_active && <span className="shrink-0 rounded-full bg-ink/10 px-3 py-1 text-xs font-medium text-ink-soft">{t('ajustes.caretakers.inactive')}</span>}
                <ChevronDown aria-hidden="true" className={`size-5 shrink-0 text-ink-soft transition-transform ${open ? 'rotate-180' : ''}`} />
            </button>

            {open && (
                <div className="flex flex-col gap-4 border-t border-line p-4">
                    <CaretakerForm caretaker={caretaker} colors={colors} languages={languages} onDone={() => setOpen(false)} />
                    <p className="text-sm text-ink-soft">{t('ajustes.caretakers.hint')}</p>
                    {caretaker.is_active ? (
                        !caretaker.is_me &&
                        (confirming ? (
                            <Button variant="secondary" className="border-red-300 text-red-700" disabled={processing} onClick={() => post(`/ajustes/cuidadores/${caretaker.id}/baja`, { preserveScroll: true })}>
                                {t('ajustes.caretakers.deactivate_confirm')}
                            </Button>
                        ) : (
                            <Button variant="ghost" className="text-red-700" onClick={() => setConfirming(true)}>
                                {t('ajustes.caretakers.deactivate')}
                            </Button>
                        ))
                    ) : (
                        <Button variant="secondary" disabled={processing} onClick={() => post(`/ajustes/cuidadores/${caretaker.id}/alta`, { preserveScroll: true })}>
                            {t('ajustes.caretakers.reactivate')}
                        </Button>
                    )}
                </div>
            )}
        </li>
    );
}

export default function Cuidadores({ caretakers, colors, languages }) {
    const t = useT();
    const [adding, setAdding] = useState(false);

    return (
        <AppLayout title={t('ajustes.caretakers.title')}>
            <div className="flex flex-col gap-4">
                <Link href="/ajustes" className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {t('ajustes.back')}
                </Link>
                <h1 className="text-2xl leading-tight font-bold">{t('ajustes.caretakers.title')}</h1>

                <ul className="flex flex-col gap-3">
                    {caretakers.map((c) => (
                        <Row key={c.id} caretaker={c} colors={colors} languages={languages} />
                    ))}
                </ul>

                {adding ? (
                    <Section title={t('ajustes.caretakers.add')}>
                        <CaretakerForm colors={colors} languages={languages} onDone={() => setAdding(false)} />
                        <Button variant="ghost" onClick={() => setAdding(false)}>
                            {t('ajustes.caretakers.cancel')}
                        </Button>
                    </Section>
                ) : (
                    <Button onClick={() => setAdding(true)}>
                        <Plus aria-hidden="true" className="size-5" />
                        {t('ajustes.caretakers.add')}
                    </Button>
                )}
            </div>
        </AppLayout>
    );
}
