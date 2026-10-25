import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Button from '../../components/Button';
import Chips from '../../components/Chips';
import Field from '../../components/Field';
import Segmented from '../../components/Segmented';
import Switch from '../../components/Switch';
import AppLayout from '../../layouts/AppLayout';
import { useT } from '../../lib/i18n';

function Section({ title, help, error, children }) {
    return (
        <section className="flex flex-col gap-3 rounded-3xl bg-surface p-4 shadow-sm">
            <div>
                <h2 className="text-base font-semibold">{title}</h2>
                {help && <p className="text-sm text-ink-soft">{help}</p>}
            </div>
            {children}
            {error && (
                <p role="alert" className="text-sm font-medium text-red-700">
                    {error}
                </p>
            )}
        </section>
    );
}

export default function Form({ reminder, defaults, caretakers, babies, options }) {
    const t = useT();
    const editing = Boolean(reminder);

    const form = useForm({
        title: reminder?.title ?? '',
        body: reminder?.body ?? '',
        event_at: reminder?.event_at ?? defaults.event_at,
        baby_id: reminder?.baby_id ?? '',
        offsets_minutes: reminder?.offsets_minutes ?? [0],
        repeat_every_minutes: reminder?.repeat_every_minutes ?? '',
        max_repeats: reminder?.max_repeats ?? '',
        requires_ack: reminder?.requires_ack ?? false,
        ack_scope: reminder?.ack_scope ?? 'any',
        snooze_options: reminder?.snooze_options ?? [5, 10, 15, 30],
        quiet_start: reminder?.quiet_start ?? '',
        quiet_end: reminder?.quiet_end ?? '',
        respect_quiet_hours: reminder?.respect_quiet_hours ?? true,
        recipients: reminder?.recipients ?? [defaults.recipient],
    });
    const { data, setData, errors } = form;

    // Modos de repetición: una vez / repetir N veces. "Insistir" es un interruptor aparte.
    const repeating = data.repeat_every_minutes !== '' && data.repeat_every_minutes !== null;
    const repeatMode = repeating && !data.requires_ack ? 'n' : 'none';
    const hasQuiet = data.quiet_start !== '' || data.quiet_end !== '';

    const setRepeatMode = (mode) => {
        if (mode === 'none') {
            setData((d) => ({ ...d, repeat_every_minutes: '', max_repeats: '' }));
        } else {
            setData((d) => ({ ...d, repeat_every_minutes: d.repeat_every_minutes || 10, max_repeats: d.max_repeats || 3 }));
        }
    };

    const setInsist = (on) =>
        setData((d) => ({
            ...d,
            requires_ack: on,
            repeat_every_minutes: on ? d.repeat_every_minutes || 5 : d.repeat_every_minutes,
            max_repeats: on ? '' : d.max_repeats,
        }));

    const setQuiet = (on) =>
        setData((d) => ({ ...d, quiet_start: on ? '22:00' : '', quiet_end: on ? '07:00' : '' }));

    const submit = (e) => {
        e.preventDefault();
        const payload = (d) => ({
            ...d,
            baby_id: d.baby_id === '' ? null : d.baby_id,
            repeat_every_minutes: d.repeat_every_minutes === '' ? null : Number(d.repeat_every_minutes),
            max_repeats: d.max_repeats === '' || d.requires_ack ? null : Number(d.max_repeats),
            quiet_start: d.quiet_start || null,
            quiet_end: d.quiet_end || null,
        });

        form.transform(payload);
        editing ? form.put(`/recordatorios/${reminder.id}`) : form.post('/recordatorios');
    };

    const offsetOptions = options.offsets.map((m) => ({ value: m, label: t(`reminders.offsets.${Math.abs(m)}`) }));
    const snoozeOptions = options.snooze.map((m) => ({ value: m, label: t('reminders.form.snooze_minutes', { minutes: m }) }));
    const recipientOptions = caretakers.map((c) => ({ value: c.id, label: c.name }));

    return (
        <AppLayout title={editing ? t('reminders.edit_title') : t('reminders.new')}>
            <Link href="/recordatorios" className="inline-flex min-h-12 items-center gap-2 text-base font-medium text-primary">
                <ArrowLeft aria-hidden="true" className="size-4" />
                {t('reminders.back')}
            </Link>
            <h1 className="mb-4 text-2xl font-semibold">{editing ? t('reminders.edit_title') : t('reminders.new')}</h1>

            <form onSubmit={submit} className="mx-auto flex max-w-2xl flex-col gap-4 pb-28">
                <Section title={t('reminders.form.what')}>
                    <Field
                        label={t('reminders.form.what')}
                        placeholder={t('reminders.form.what_placeholder')}
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        error={errors.title}
                        maxLength={120}
                    />
                    <Field
                        label={t('reminders.form.detail')}
                        optional={t('common.optional')}
                        value={data.body ?? ''}
                        onChange={(e) => setData('body', e.target.value)}
                        error={errors.body}
                        maxLength={300}
                    />
                    <Field
                        label={t('reminders.form.when')}
                        type="datetime-local"
                        value={data.event_at}
                        onChange={(e) => setData('event_at', e.target.value)}
                        error={errors.event_at}
                    />
                    {babies.length > 1 && (
                        <Field label={t('reminders.form.baby')} optional={t('common.optional')} error={errors.baby_id}>
                            {() => (
                                <Segmented
                                    label={t('reminders.form.baby')}
                                    options={[{ value: '', label: t('reminders.form.no_baby') }, ...babies.map((b) => ({ value: b.id, label: b.first_name }))]}
                                    value={data.baby_id}
                                    onChange={(v) => setData('baby_id', v)}
                                />
                            )}
                        </Field>
                    )}
                </Section>

                <Section title={t('reminders.form.offsets')} help={t('reminders.form.offsets_help')} error={errors.offsets_minutes}>
                    <Chips
                        label={t('reminders.form.offsets')}
                        options={offsetOptions}
                        value={data.offsets_minutes}
                        onChange={(v) => setData('offsets_minutes', v)}
                    />
                </Section>

                <Section title={t('reminders.form.repeat')}>
                    <Switch
                        label={t('reminders.form.insist')}
                        help={t('reminders.form.insist_help')}
                        checked={data.requires_ack}
                        onChange={setInsist}
                    />
                    {!data.requires_ack && (
                        <Segmented
                            label={t('reminders.form.repeat')}
                            options={[
                                { value: 'none', label: t('reminders.form.repeat_none') },
                                { value: 'n', label: t('reminders.form.repeat_n') },
                            ]}
                            value={repeatMode}
                            onChange={setRepeatMode}
                        />
                    )}
                    {(data.requires_ack || repeatMode === 'n') && (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field
                                label={t('reminders.form.repeat_every')}
                                type="number"
                                inputMode="numeric"
                                min="1"
                                max="1440"
                                value={data.repeat_every_minutes}
                                onChange={(e) => setData('repeat_every_minutes', e.target.value)}
                                error={errors.repeat_every_minutes}
                            />
                            {!data.requires_ack && (
                                <Field
                                    label={t('reminders.form.repeat_count')}
                                    type="number"
                                    inputMode="numeric"
                                    min="1"
                                    max="50"
                                    value={data.max_repeats}
                                    onChange={(e) => setData('max_repeats', e.target.value)}
                                    error={errors.max_repeats}
                                />
                            )}
                        </div>
                    )}
                </Section>

                <Section title={t('reminders.form.recipients')} error={errors.recipients ?? errors['recipients.0']}>
                    <Chips
                        label={t('reminders.form.recipients')}
                        options={recipientOptions}
                        value={data.recipients}
                        onChange={(v) => setData('recipients', v)}
                    />
                    {data.recipients.length > 1 && (
                        <Segmented
                            label={t('reminders.form.ack_scope')}
                            options={[
                                { value: 'any', label: t('reminders.form.ack_any') },
                                { value: 'each', label: t('reminders.form.ack_each') },
                            ]}
                            value={data.ack_scope}
                            onChange={(v) => setData('ack_scope', v)}
                        />
                    )}
                </Section>

                <Section title={t('reminders.form.snooze')} error={errors.snooze_options}>
                    <Chips
                        label={t('reminders.form.snooze')}
                        options={snoozeOptions}
                        value={data.snooze_options}
                        onChange={(v) => setData('snooze_options', v)}
                    />
                </Section>

                <Section title={t('reminders.form.quiet')} help={t('reminders.form.quiet_help')}>
                    <Switch label={t('reminders.form.quiet')} checked={hasQuiet} onChange={setQuiet} />
                    {hasQuiet && (
                        <>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field
                                    label={t('reminders.form.quiet_from')}
                                    type="time"
                                    value={data.quiet_start}
                                    onChange={(e) => setData('quiet_start', e.target.value)}
                                    error={errors.quiet_start}
                                />
                                <Field
                                    label={t('reminders.form.quiet_to')}
                                    type="time"
                                    value={data.quiet_end}
                                    onChange={(e) => setData('quiet_end', e.target.value)}
                                    error={errors.quiet_end}
                                />
                            </div>
                            <Switch
                                label={t('reminders.form.quiet_ignore')}
                                checked={!data.respect_quiet_hours}
                                onChange={(on) => setData('respect_quiet_hours', !on)}
                            />
                        </>
                    )}
                </Section>

                <div className="fixed inset-x-0 bottom-0 z-10 border-t border-ink/10 bg-surface px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <div className="mx-auto max-w-2xl">
                        <Button type="submit" className="w-full" disabled={form.processing || !data.title.trim()}>
                            {t('reminders.form.save')}
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
