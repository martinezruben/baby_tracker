import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useT } from '../lib/i18n';

/** Aviso breve tras guardar, con "Deshacer" (en vez de diálogos de confirmación). Se oculta a los 6 s. */
export default function Snackbar() {
    const { flash } = usePage().props;
    const t = useT();
    const [message, setMessage] = useState(null);

    const key = JSON.stringify([flash?.undo, flash?.status]);

    useEffect(() => {
        if (!flash?.undo && !flash?.status) return;

        setMessage({ undo: flash.undo ?? null, text: flash.undo?.message ?? flash.status });
        const id = setTimeout(() => setMessage(null), flash.undo ? 6000 : 4000);
        return () => clearTimeout(id);
    }, [key]);

    if (!message) return null;

    const undo = () => {
        router.visit(message.undo.url, { method: message.undo.method ?? 'delete', preserveScroll: true });
        setMessage(null);
    };

    return (
        <div
            role="status"
            aria-live="polite"
            className="fixed inset-x-5 bottom-[calc(max(1.5rem,env(safe-area-inset-bottom))+88px)] z-30 mx-auto flex min-h-12 max-w-[430px] items-center justify-between gap-3 rounded-full bg-ink py-1 pr-1 pl-5 text-[15px] text-white shadow-nav"
        >
            <span>{message.text}</span>
            {message.undo && (
                <button
                    type="button"
                    onClick={undo}
                    className="min-h-12 rounded-full px-4 text-[15px] font-bold text-accent focus-visible:outline-2 focus-visible:outline-accent"
                >
                    {t('common.undo')}
                </button>
            )}
        </div>
    );
}
