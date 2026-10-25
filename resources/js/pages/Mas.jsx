import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Bell, ChevronRight, KeyRound, LogOut, Settings, Users } from 'lucide-react';
import { useState } from 'react';
import Button from '../components/Button';
import Field from '../components/Field';
import ChoiceChips from '../components/form/Chips';
import PushToggle from '../components/PushToggle';
import AppLayout from '../layouts/AppLayout';
import { KINDS } from '../lib/activities';
import { useT } from '../lib/i18n';

const row = 'flex min-h-16 items-center gap-3 rounded-[22px] bg-surface p-3 text-left shadow-card focus-visible:outline-2 focus-visible:outline-primary';

function Icon({ kind, children }) {
    return <span className={`flex size-[46px] shrink-0 items-center justify-center rounded-2xl ${KINDS[kind].bg} ${KINDS[kind].ink}`}>{children}</span>;
}

function ChangePin() {
    const t = useT();
    const [open, setOpen] = useState(false);
    const form = useForm({ current_pin: '', pin: '', pin_confirmation: '' });

    const submit = (e) => {
        e.preventDefault();
        form.put('/cuidador/pin', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    const pinProps = (name, label) => ({
        label,
        type: 'password',
        inputMode: 'numeric',
        pattern: '[0-9]*',
        autoComplete: name === 'current_pin' ? 'current-password' : 'new-password',
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        error: form.errors[name],
    });

    return (
        <div className="flex flex-col gap-3">
            <button type="button" aria-expanded={open} onClick={() => setOpen(!open)} className={row}>
                <Icon kind="neutral">
                    <KeyRound aria-hidden="true" className="size-6" strokeWidth={1.75} />
                </Icon>
                <span className="grow text-base font-bold">{t('auth.change_pin')}</span>
                <ChevronRight aria-hidden="true" className={`size-5 text-ink-soft transition-transform ${open ? 'rotate-90' : ''}`} strokeWidth={2} />
            </button>
            {open && (
                <form onSubmit={submit} className="flex flex-col gap-4 rounded-3xl bg-surface p-4 shadow-card">
                    <Field {...pinProps('current_pin', t('auth.current_pin'))} />
                    <Field {...pinProps('pin', t('auth.new_pin'))} />
                    <Field {...pinProps('pin_confirmation', t('setup.security.pin_confirmation'))} />
                    <Button type="submit" disabled={form.processing || !form.data.pin}>
                        {t('common.save')}
                    </Button>
                </form>
            )}
        </div>
    );
}

function MyLanguage({ language, languages }) {
    const t = useT();
    const [value, setValue] = useState(language ?? '');

    const change = (v) => {
        setValue(v);
        router.patch('/cuidador/idioma', { language: v || null }, { preserveScroll: true });
    };

    return (
        <div className="rounded-3xl bg-surface p-4 shadow-card">
            <ChoiceChips
                label={t('more.my_language')}
                value={value}
                onChange={change}
                options={[{ value: '', label: t('more.language_family') }, ...languages.map((l) => ({ value: l, label: l === 'es' ? 'Español' : 'English' }))]}
            />
        </div>
    );
}

export default function Mas({ caretakers, slug, language, languages }) {
    const t = useT();
    const { auth } = usePage().props;
    const name = auth.caretaker.name;

    return (
        <AppLayout title={t('nav.more')}>
            <div className="flex flex-col gap-5">
                <header className="flex min-h-14 items-center gap-3">
                    <div className="flex size-[52px] items-center justify-center rounded-full bg-sleep text-xl font-bold text-sleep-ink">{name.slice(0, 1).toUpperCase()}</div>
                    <div>
                        <h1 className="text-2xl leading-tight font-bold">{name}</h1>
                        <div className="text-sm text-ink-soft">{t(`more.role_${auth.caretaker.role}`)}</div>
                    </div>
                </header>

                <section className="flex flex-col gap-3">
                    <Link href="/recordatorios" className={row}>
                        <Icon kind="appointment">
                            <Bell aria-hidden="true" className="size-6" strokeWidth={1.75} />
                        </Icon>
                        <span className="grow text-base font-bold">{t('home.reminders')}</span>
                        <ChevronRight aria-hidden="true" className="size-5 text-ink-soft" strokeWidth={2} />
                    </Link>
                    <PushToggle />
                    {auth.caretaker.role === 'admin' && (
                        <Link href="/ajustes" className={row}>
                            <Icon kind="growth">
                                <Settings aria-hidden="true" className="size-6" strokeWidth={1.75} />
                            </Icon>
                            <span className="flex grow flex-col">
                                <span className="text-base font-bold">{t('more.family_settings')}</span>
                                <span className="text-[13px] text-ink-soft">{t('more.family_settings_help')}</span>
                            </span>
                            <ChevronRight aria-hidden="true" className="size-5 text-ink-soft" strokeWidth={2} />
                        </Link>
                    )}
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-[19px] font-bold">{t('more.account')}</h2>
                    <MyLanguage language={language} languages={languages} />
                    <ChangePin />
                    {caretakers > 1 && (
                        <Link href={`/entrar/${slug}`} className={row}>
                            <Icon kind="sleep">
                                <Users aria-hidden="true" className="size-6" strokeWidth={1.75} />
                            </Icon>
                            <span className="grow text-base font-bold">{t('more.switch_caretaker')}</span>
                            <ChevronRight aria-hidden="true" className="size-5 text-ink-soft" strokeWidth={2} />
                        </Link>
                    )}
                    <button type="button" onClick={() => router.post('/salir')} className={row}>
                        <Icon kind="neutral">
                            <LogOut aria-hidden="true" className="size-6" strokeWidth={1.75} />
                        </Icon>
                        <span className="grow text-base font-bold">{t('common.logout')}</span>
                    </button>
                </section>
            </div>
        </AppLayout>
    );
}
