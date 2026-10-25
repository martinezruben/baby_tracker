import { useForm } from '@inertiajs/react';
import { agoLabel } from './i18n';
import { minutesSince } from './time';

/** "Sofía · último hace 45 min" para la cabecera de un formulario. */
export function lastSubtitle(t, babyName, lastAt) {
    return lastAt
        ? t('registrar.baby_last', { name: babyName, ago: agoLabel(t, minutesSince(lastAt)).toLowerCase() })
        : t('registrar.baby_first', { name: babyName });
}

/** El formulario se abrió desde el modo nursery: al guardar o cerrar se vuelve ahí. */
export const fromNursery = () => typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('nursery') === '1';

/**
 * Formulario de una actividad: crea (POST /registrar/{tipo}) o, con `record`, corrige un registro
 * ya guardado (PUT /registros/{tipo}/{id}). `defaults` son los valores de un registro nuevo.
 */
export function useActivityForm(type, defaults, record) {
    const { id, ...values } = record ?? {};
    const form = useForm(record ? { ...defaults, ...values } : defaults);
    const save = () => (record ? form.put(`/registros/${type}/${id}`) : form.post(`/registrar/${type}${fromNursery() ? '?nursery=1' : ''}`));

    return [form, save];
}

const plain = (text) => String(text ?? '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase().trim();

/** Alergia registrada que coincide con un nombre (alimento o medicina), o null. Compara sin acentos ni mayúsculas. */
export function allergyHit(allergies, name) {
    const target = plain(name);
    if (target.length < 3) return null;

    return allergies.find((a) => {
        const known = plain(a);
        return known.length >= 3 && (target.includes(known) || known.includes(target));
    }) ?? null;
}
