import { usePage } from '@inertiajs/react';
import { Bell, BellOff, Send } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useT } from '../lib/i18n';
import { currentSubscription, disablePush, enablePush, pushSupported, sendTestPush } from '../lib/push';
import Button from './Button';

export default function PushToggle() {
    const { vapidPublicKey } = usePage().props;
    const t = useT();
    const [state, setState] = useState('loading'); // loading | unsupported | unconfigured | denied | off | on | error
    const [busy, setBusy] = useState(false);
    const [testMessage, setTestMessage] = useState(null);

    useEffect(() => {
        if (!pushSupported()) return setState('unsupported');
        if (!vapidPublicKey) return setState('unconfigured');
        if (Notification.permission === 'denied') return setState('denied');

        currentSubscription()
            .then((subscription) => setState(subscription ? 'on' : 'off'))
            .catch(() => setState('off'));
    }, [vapidPublicKey]);

    const toggle = async () => {
        setBusy(true);
        try {
            if (state === 'on') {
                await disablePush();
                setState('off');
            } else {
                setState(await enablePush(vapidPublicKey));
            }
        } catch (e) {
            setState('error');
        } finally {
            setBusy(false);
        }
    };

    const sendTest = async () => {
        setBusy(true);
        try {
            setTestMessage(await sendTestPush());
        } finally {
            setBusy(false);
        }
    };

    if (state === 'loading') return null;

    const messages = {
        unsupported: t('push.unsupported'),
        unconfigured: t('push.unconfigured'),
        denied: t('push.denied'),
        error: t('push.error'),
        on: t('push.enabled'),
    };
    const canToggle = ['off', 'on', 'error'].includes(state);

    return (
        <section className="rounded-3xl bg-surface p-4 shadow-sm">
            {messages[state] && (
                <p role="status" className="text-base text-ink-soft">
                    {messages[state]}
                </p>
            )}
            {canToggle && (
                <Button
                    variant={state === 'on' ? 'secondary' : 'primary'}
                    className="mt-3 w-full sm:w-auto"
                    onClick={toggle}
                    disabled={busy}
                >
                    {state === 'on' ? <BellOff aria-hidden="true" className="size-5" /> : <Bell aria-hidden="true" className="size-5" />}
                    {state === 'on' ? t('push.disable') : t('push.enable')}
                </Button>
            )}
            {state === 'on' && (
                <>
                    <Button variant="ghost" className="mt-2 w-full sm:ml-2 sm:w-auto" onClick={sendTest} disabled={busy}>
                        <Send aria-hidden="true" className="size-5" />
                        {t('push.test_button')}
                    </Button>
                    {testMessage && (
                        <p role="status" className="mt-2 text-sm text-ink-soft">
                            {testMessage}
                        </p>
                    )}
                </>
            )}
        </section>
    );
}
