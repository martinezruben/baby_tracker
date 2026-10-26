import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Button from '../components/Button';
import Field from '../components/Field';
import GuestLayout from '../layouts/GuestLayout';
import { useT } from '../lib/i18n';

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
            <h1 className="text-2xl font-semibold">{family.name}</h1>

            {caretakers.length === 0 ? (
                <p className="mt-4 text-base text-ink-soft">{t('auth.no_caretakers')}</p>
            ) : (
                <form onSubmit={submit} className="mt-4 flex flex-col gap-5">
                    <fieldset>
                        <legend className="mb-2 text-sm font-medium">{t('auth.who')}</legend>
                        <div role="radiogroup" className="flex flex-wrap gap-2">
                            {caretakers.map((c) => {
                                const selected = form.data.caretaker_id === c.id;
                                return (
                                    <button
                                        key={c.id}
                                        type="button"
                                        role="radio"
                                        aria-checked={selected}
                                        onClick={() => form.setData('caretaker_id', c.id)}
                                        className={`inline-flex min-h-12 items-center gap-2 rounded-full border px-4 text-base font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary ${
                                            selected ? 'border-primary bg-primary text-on-primary' : 'border-ink/20 bg-surface'
                                        }`}
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="size-3 rounded-full ring-2 ring-white"
                                            style={{ backgroundColor: c.badge_color ?? '#8B6340' }}
                                        />
                                        {c.name}
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>

                    <Field
                        label={t('auth.pin')}
                        type="password"
                        inputMode="numeric"
                        pattern="[0-9]*"
                        autoComplete="current-password"
                        value={form.data.pin}
                        onChange={(e) => form.setData('pin', e.target.value)}
                        error={form.errors.pin}
                    />

                    <Button type="submit" disabled={form.processing || !form.data.pin}>
                        {t('auth.enter')}
                    </Button>
                </form>
            )}
        </GuestLayout>
    );
}
