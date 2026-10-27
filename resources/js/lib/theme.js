import { useSyncExternalStore } from 'react';

/**
 * Tema de la app: "auto" sigue al sistema (claro u oscuro); "nursery" se elige a mano.
 * Es una preferencia del dispositivo (localStorage); resources/views/app.blade.php aplica el mismo
 * resultado antes de pintar para evitar el parpadeo.
 */
export const THEMES = ['auto', 'light', 'dark', 'nursery'];

const KEYS = { theme: 'kidu-theme', before: 'kidu-theme-before', brightness: 'kidu-brightness' };
const META = { light: '#f3f4fa', dark: '#12162a', nursery: '#0b0907' };
const listeners = new Set();
let snapshot = null;

const read = (key) => {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
};

const write = (key, value) => {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Sin almacenamiento (modo privado): el cambio vale solo para esta sesión.
    }
};

const systemDark = () => window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
const resolve = (pref) => (pref === 'auto' ? (systemDark() ? 'dark' : 'light') : pref);

function compute() {
    const stored = read(KEYS.theme);
    const pref = THEMES.includes(stored) ? stored : 'auto';
    const brightness = Math.min(100, Math.max(5, Number(read(KEYS.brightness)) || 20));

    return { pref, resolved: resolve(pref), brightness };
}

function emit() {
    snapshot = compute();
    const root = document.documentElement;
    root.dataset.theme = snapshot.resolved;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', META[snapshot.resolved]);
    listeners.forEach((fn) => fn());
}

export function applyTheme() {
    emit();
}

export function setTheme(pref) {
    if (!THEMES.includes(pref)) return;
    write(KEYS.theme, pref);
    emit();
}

export function setBrightness(value) {
    write(KEYS.brightness, String(Math.round(value)));
    emit();
}

/** Entra al modo nursery recordando el tema anterior para poder volver. */
export function enterNursery() {
    const current = compute().pref;
    if (current !== 'nursery') write(KEYS.before, current);
    setTheme('nursery');
}

export function exitNursery() {
    const before = read(KEYS.before);
    setTheme(THEMES.includes(before) && before !== 'nursery' ? before : 'auto');
}

function subscribe(fn) {
    listeners.add(fn);
    const media = window.matchMedia?.('(prefers-color-scheme: dark)');
    const onChange = () => emit();
    media?.addEventListener?.('change', onChange);

    return () => {
        listeners.delete(fn);
        media?.removeEventListener?.('change', onChange);
    };
}

const getSnapshot = () => (snapshot ??= compute());
const getServerSnapshot = () => ({ pref: 'auto', resolved: 'light', brightness: 20 });

export function useTheme() {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
