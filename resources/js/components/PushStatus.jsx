import { CheckCircle2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { agoLabel, useT } from '../lib/i18n';
import { fetchPushStatus } from '../lib/push';
import { minutesSince } from '../lib/time';

/** Para administradores: qué falta en el servidor para que los avisos lleguen. */
export default function PushStatus() {
    const t = useT();
    const [status, setStatus] = useState(null);

    useEffect(() => {
        fetchPushStatus().then(setStatus);
    }, []);

    if (!status) return null;

    const rows = [
        { ok: status.vapid_configured && status.vapid_valid, label: t('push.status.vapid'), fix: t('push.status.vapid_fix') },
        { ok: status.secure, label: t('push.status.https'), fix: t('push.status.https_fix') },
        { ok: Object.values(status.extensions).every(Boolean), label: t('push.status.extensions'), fix: t('push.status.extensions_fix', { names: Object.entries(status.extensions).filter(([, v]) => !v).map(([k]) => k).join(', ') }) },
        {
            ok: Boolean(status.dispatcher_ok),
            label: status.dispatcher_last_run ? t('push.status.cron_last', { ago: agoLabel(t, minutesSince(status.dispatcher_last_run)).toLowerCase() }) : t('push.status.cron_never'),
            fix: t('push.status.cron_fix'),
        },
        { ok: status.overdue === 0, label: t('push.status.overdue', { n: status.overdue }), fix: t('push.status.overdue_fix') },
        { ok: status.devices_mine > 0, label: t('push.status.devices', { mine: status.devices_mine, total: status.devices_total }), fix: t('push.status.devices_fix') },
    ];

    return (
        <section aria-label={t('push.status.title')} className="flex flex-col gap-3 rounded-3xl bg-surface p-4 shadow-card">
            <h2 className="text-base font-bold">{t('push.status.title')}</h2>
            <ul className="flex flex-col gap-2.5">
                {rows.map((row) => (
                    <li key={row.label} className="flex items-start gap-2.5">
                        {row.ok ? <CheckCircle2 aria-label={t('push.status.ok')} className="mt-0.5 size-5 shrink-0 text-vaccine-ink" strokeWidth={1.75} /> : <XCircle aria-label={t('push.status.fail')} className="mt-0.5 size-5 shrink-0 text-danger-ink" strokeWidth={1.75} />}
                        <span className="text-[15px]">
                            {row.label}
                            {!row.ok && <span className="block text-sm text-ink-soft">{row.fix}</span>}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
