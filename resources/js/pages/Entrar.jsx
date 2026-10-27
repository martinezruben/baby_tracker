import { router, useForm, usePage } from '@inertiajs/react';
import { Check, Delete } from 'lucide-react';
import { useState } from 'react';
import Button from '../components/Button';
import Field from '../components/Field';
import GuestLayout from '../layouts/GuestLayout';
import { useT } from '../lib/i18n';

const KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
const key = 'flex min-h-14 items-center justify-center rounded-2xl bg-canvas text-[26px] font-semibold active:bg-track focus-visible:outline-2 focus-visible:outline-primary';

/** PIN con puntos y teclado numérico grande; el campo oculto deja escribir con teclado y gestor de contraseñas. */
function PinPad({ value, onChange, error }) {
    const t = useT();
    const add = (d) => onChange((value + d).slice(0, 10));
    const slots = Math.max(4, value.length);

    return (
        <div className="flex flex-col items-center gap-4">
            <label className="flex flex-col items-center gap-3 text-sm font-medium">
                {t('auth.enter_pin')}
                <input
                    type="password"
                    inputMode="numeric"
                    pattern="[0-9]*"
                    autoComplete="current-password"
                    aria-label={t('auth.pin')}
                    value={value}
                    onChange={(e) => onChange(e.target.value.replace(/\D/g, '').slice(0, 10))}
                    className="sr-only"
                />
                <span aria-hidden="true" className="flex gap-3">
                    {Array.from({ length: slots }, (_, i) => (
                        <span key={i} className={`size-3.5 rounded-full border-2 ${i < value.length ? 'border-primary bg-primary' : 'border-line bg-transparent'}`} />
                    ))}
                </span>
            </label>
            {error && (
                <p role="alert" className="text-sm font-medium text-danger-ink">
                    {error}
                </p>
            )}
            <div className="grid w-full max-w-[320px] grid-cols-3 gap-3">
                {KEYS.map((d) => (
                    <button key={d} type="button" onClick={() => add(d)} className={key}>
                        {d}
                    </button>
                ))}
                <span aria-hidden="true" />
                <button type="button" onClick={() => add('0')} className={key}>
                    0
                </button>
                <button type="button" aria-label={t('auth.delete_digit')} onClick={() => onChange(value.slice(0, -1))} className={key}>
                    <Delete aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </button>
            </div>
        </div>
    );
}

export default function Entrar({ needsFamily, family, caretakers }) {
    const t = useT();
    const [code, setCode] = useState('');
    const form = useForm({ caretaker_id: caretakers[0]?.id ?? null, pin: '' });
    const { flash } = usePage().props;
    const restored = flash?.restored;

    if (needsFamily) {
        return (
            <GuestLayout title={t('auth.login_title')}>
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.visit(`/entrar/${encodeURIComponent(code.trim())}`);
                    }}
                >
                    <Field
                        label={t('auth.family_code')}
                        hint={t('auth.family_code_help')}
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        autoCapitalize="none"
                        autoCorrect="off"
                    />
                    <Button type="submit" disabled={!code.trim()}>
                        {t('common.continue')}
                    </Button>
                </form>
            </GuestLayout>
        );
    }

    const submit = (e) => {
        e.preventDefault();
        form.post('/entrar', { onFinish: () => form.reset('pin') });
    };

    return (
        <GuestLayout title={t('auth.login_title')}>
            {restored && (
                <div role="status" className="mb-4 rounded-2xl bg-vaccine p-4 text-base text-vaccine-ink">
                    <p className="font-semibold">{t('respaldos.restored_title')}</p>
                    <p>{t('respaldos.restored_body', { rows: restored.rows, files: restored.files })}</p>
                    {restored.reset_pin.length > 0 && <p className="mt-1">{t('respaldos.restored_reset', { names: restored.reset_pin.join(', ') })}</p>}
                </div>
            )}
            <h1 className="text-[32px] leading-tight font-bold">{t('auth.welcome_back')}</h1>
            <p className="text-base text-ink-soft">{family.name}</p>

            {caretakers.length === 0 ? (
                <p className="mt-4 text-base text-ink-soft">{t('auth.no_caretakers')}</p>
            ) : (
                <form onSubmit={submit} className="mt-5 flex flex-col gap-5">
                    <fieldset>
                        <legend className="mb-2 text-sm font-medium">{t('auth.who')}</legend>
                        <div role="radiogroup" className="grid grid-cols-2 gap-3">
                            {caretakers.map((c) => {
                                const selected = form.data.caretaker_id === c.id;
                                return (
                                    <button
                                        key={c.id}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        onClick={() => form.setData('caretaker_id', c.id)}
                                        className={`relative flex min-h-[104px] flex-col items-center justify-center gap-2 rounded-3xl border-2 bg-canvas px-2 py-3 text-base font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                            selected ? 'border-primary' : 'border-transparent'
                                        }`}
                                    >
                                        {selected && (
                                            <span aria-hidden="true" className="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-primary text-on-primary">
                                                <Check className="size-4" strokeWidth={3} />
                                            </span>
                                        )}
                                        <span
                                            aria-hidden="true"
                                            className="flex size-12 items-center justify-center rounded-full text-xl font-bold text-white"
                                            style={{ backgroundColor: c.badge_color ?? '#8B6340' }}
                                        >
                                            {c.name.slice(0, 1).toUpperCase()}
                                        </span>
                                        {c.name}
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>

                    <PinPad value={form.data.pin} onChange={(pin) => form.setData('pin', pin)} error={form.errors.pin} />

                    <Button type="submit" disabled={form.processing || form.data.pin.length < 4}>
                        {t('auth.enter')}
                    </Button>
                    <p className="text-center text-sm text-ink-soft">{t('auth.forgot_pin')}</p>
                </form>
            )}
        </GuestLayout>
    );
}
