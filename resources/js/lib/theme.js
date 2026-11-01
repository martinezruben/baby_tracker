import { useSyncExternalStore } from 'react';

/**
 * Tema de la app: "auto" sigue al sistema (claro u oscuro); "nursery" se elige a mano.
 * Es una preferencia del dispositivo (localStorage); resources/views/app.blade.php aplica el mismo
 * resultado antes de pintar para evitar el parpadeo.
 */
export const THEMES = ['auto', 'light', 'dark', 'nursery'];

const KEYS = { theme: 'kidu-theme', before: 'kidu-theme-before', brightness: 'kidu-brightness', auto: 'kidu-nursery-auto' };
const META = { light: '#f5f2ea', dark: '#0d1815', nursery: '#0b0907' };
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
    write(KEYS.auto, '0'); // elegido a mano: no se quita solo al salir de /nursery
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
    write(KEYS.auto, '1'); // activado por la pantalla /nursery: se quita al salir de ella
}

export function exitNursery() {
    const before = read(KEYS.before);
    setTheme(THEMES.includes(before) && before !== 'nursery' ? before : 'auto');
}

/** Pantallas que forman parte del modo nursery: /nursery y los formularios abiertos con ?nursery=1. */
const inNursery = (url) => {
    const { pathname, searchParams } = new URL(url, window.location.origin);
    return pathname.startsWith('/nursery') || searchParams.get('nursery') === '1';
};

/**
 * Si el modo nursery se activó entrando a /nursery y se llega a otra pantalla por cualquier camino (menú, botón
 * atrás, reabrir la app), se vuelve al tema anterior. Si se eligió a mano en Ajustes, se respeta.
 */
export function leaveNurseryIfAuto(url) {
    const { pref } = compute();
    if (pref === 'nursery' && read(KEYS.auto) === '1' && !inNursery(url)) exitNursery();
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
