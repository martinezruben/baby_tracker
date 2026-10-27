import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Bell, ChevronRight, KeyRound, MoonStar } from 'lucide-react';
import { useState } from 'react';
import Button from '../../components/Button';
import Field from '../../components/Field';
import InstallHint from '../../components/InstallHint';
import ChoiceChips from '../../components/form/Chips';
import PushStatus from '../../components/PushStatus';
import PushToggle from '../../components/PushToggle';
import AppLayout from '../../layouts/AppLayout';
import { KINDS } from '../../lib/activities';
import { useT } from '../../lib/i18n';
import { enterNursery, setTheme, THEMES, useTheme } from '../../lib/theme';

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

function Appearance() {
    const t = useT();
    const { pref } = useTheme();

    return (
        <div className="flex flex-col gap-3 rounded-3xl bg-surface p-4 shadow-card">
            <ChoiceChips label={t('more.appearance')} value={pref} onChange={setTheme} options={THEMES.map((k) => ({ value: k, label: t(`more.theme_${k}`) }))} />
            <p className="text-sm text-ink-soft">{t('more.theme_help')}</p>
            <Link href="/nursery" onClick={enterNursery} className="flex min-h-12 items-center justify-center gap-2 rounded-full bg-ink/10 text-base font-semibold text-ink focus-visible:outline-2 focus-visible:outline-primary">
                <MoonStar aria-hidden="true" className="size-5" strokeWidth={1.75} />
                {t('more.open_nursery')}
            </Link>
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

export default function Dispositivo({ language, languages }) {
    const t = useT();
    const { auth } = usePage().props;
    const admin = auth.caretaker.role === 'admin';

    return (
        <AppLayout title={t('ajustes.device.title')}>
            <div className="flex flex-col gap-5">
                <Link href={admin ? '/ajustes' : '/mas'} className="inline-flex min-h-12 items-center gap-2 self-start text-base font-medium text-primary">
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {admin ? t('ajustes.title') : t('nav.more')}
                </Link>
                <header>
                    <h1 className="text-2xl leading-tight font-bold">{t('ajustes.device.title')}</h1>
                    <p className="text-sm text-ink-soft">{auth.caretaker.name}</p>
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
                    {admin && <PushStatus />}
                    <InstallHint />
                </section>

                <section className="flex flex-col gap-3">
                    <h2 className="text-[19px] font-bold">{t('more.account')}</h2>
                    <Appearance />
                    <MyLanguage language={language} languages={languages} />
                    <ChangePin />
                </section>
            </div>
        </AppLayout>
    );
}
