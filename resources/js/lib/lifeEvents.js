import { Baby, Footprints, MessageCircle, Plane, Smile, Star, Thermometer, Utensils } from 'lucide-react';

/** Tipos de momento: icono y color (de los pasteles por tipo de actividad). */
export const EVENT_TYPES = {
    tooth: { icon: Smile, kind: 'moment' },
    food: { icon: Utensils, kind: 'food' },
    first_steps: { icon: Footprints, kind: 'growth' },
    crawling: { icon: Baby, kind: 'diaper' },
    word: { icon: MessageCircle, kind: 'sleep' },
    trip: { icon: Plane, kind: 'appointment' },
    illness: { icon: Thermometer, kind: 'medicine' },
    other: { icon: Star, kind: 'moment' },
};

/** "Diente · Incisivo central inferior" o el título propio del momento. */
export function eventTitle(t, event) {
    if (event.title) return event.title;
    const base = t(`eventos.types.${event.type}`);
    return event.detail ? `${base} · ${event.detail}` : base;
}

/** "2 de noviembre": fecha de un día sin hora (se formatea en UTC para que no se corra de día). */
export function dayLabel(date, locale, withYear = false) {
    return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', ...(withYear ? { year: 'numeric' } : {}), timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`));
}

export function monthLabel(date, locale) {
    const text = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${date.slice(0, 7)}-15T12:00:00Z`));
    return text.charAt(0).toUpperCase() + text.slice(1);
}
