import { useEffect } from 'react';
import { applyTheme, useTheme } from '../lib/theme';

/** Efectos globales del tema: aplica el tema, atenúa la pantalla y la mantiene encendida en modo nursery. */
export default function ThemeEffects() {
    const { resolved, brightness } = useTheme();

    useEffect(() => {
        applyTheme();
    }, []);

    useEffect(() => {
        if (resolved !== 'nursery' || !('wakeLock' in navigator)) return undefined;

        let lock = null;
        let cancelled = false;
        const acquire = async () => {
            try {
                lock = await navigator.wakeLock.request('screen');
                if (cancelled) lock.release();
            } catch {
                // El navegador puede negarlo (batería baja, pestaña oculta): no es un error.
            }
        };
        const onVisible = () => document.visibilityState === 'visible' && acquire();

        acquire();
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            cancelled = true;
            document.removeEventListener('visibilitychange', onVisible);
            lock?.release().catch(() => {});
        };
    }, [resolved]);

    if (resolved !== 'nursery') return null;

    return <div aria-hidden="true" className="nursery-dim" style={{ opacity: 1 - brightness / 100 }} />;
}
