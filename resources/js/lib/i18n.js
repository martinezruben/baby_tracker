import { usePage } from '@inertiajs/react';

function lookup(tree, key) {
    return key.split('.').reduce((node, part) => (node == null ? node : node[part]), tree);
}

function fill(text, vars) {
    return Object.entries(vars).reduce((out, [name, value]) => out.replaceAll(`:${name}`, value), text);
}

/**
 * t('grupo.clave', { nombre: 'x' }) con los textos que manda el servidor.
 * t.choice('grupo.clave', n) usa "singular|plural" y rellena :n.
 */
export function useT() {
    const { t: messages } = usePage().props;

    const t = (key, vars = {}) => {
        const text = lookup(messages, key);
        return typeof text === 'string' ? fill(text, vars) : key;
    };

    t.choice = (key, n, vars = {}) => {
        const text = lookup(messages, key);
        if (typeof text !== 'string') return key;
        const [one, many = one] = text.split('|');
        return fill(n === 1 ? one : many, { n, ...vars });
    };

    return t;
}

/** "Hace 2 h 10 min" a partir de minutos transcurridos. */
export function agoLabel(t, minutes) {
    if (minutes < 1) return t('time.just_now');
    if (minutes < 60) return t('time.ago_min', { m: minutes });
    if (minutes < 1440) {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return m === 0 ? t('time.ago_h', { h }) : t('time.ago_hm', { h, m });
    }
    return t('time.ago_d', { d: Math.floor(minutes / 1440) });
}

/** "11 h 20" / "45 min" para duraciones. */
export function durationLabel(t, minutes) {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) return t('time.dur_min', { m });
    return m === 0 ? t('time.dur_h', { h }) : t('time.dur_hm', { h, m });
}

/** "3 meses", "5 semanas", "12 días" desde la fecha de nacimiento. */
export function ageLabel(t, birthDate, now = new Date()) {
    const birth = new Date(`${birthDate.slice(0, 10)}T00:00:00Z`);
    const today = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
    const days = Math.max(0, Math.floor((today - birth) / 86400000));

    let months = (today.getUTCFullYear() - birth.getUTCFullYear()) * 12 + today.getUTCMonth() - birth.getUTCMonth();
    if (today.getUTCDate() < birth.getUTCDate()) months -= 1;

    if (months >= 24) return t.choice('age.years', Math.floor(months / 12));
    if (months >= 1) return t.choice('age.months', months);
    if (days >= 14) return t.choice('age.weeks', Math.floor(days / 7));
    return t.choice('age.days', days);
}

/** Edad recomendada de una dosis (en días): "Al nacer", "12 días", "6 semanas", "4 meses", "1 año". */
export function doseAgeLabel(t, days) {
    if (days === 0) return t('vacunas.due.at_birth');
    if (days < 14) return t.choice('age.days', days);
    if (days < 56) return t.choice('age.weeks', Math.round(days / 7));

    const months = days / 30.4375;
    if (months >= 23) return t.choice('age.years', Math.round(months / 12));
    return t.choice('age.months', Math.round(months));
}
