// Las fechas que se escriben y se muestran van en hora de la familia como texto naive
// "YYYY-MM-DDTHH:mm" (el servidor las convierte a UTC). Aquí se opera sobre ese texto con
// Date en UTC solo como calculadora, sin depender de la zona horaria del navegador.

const pad = (n) => String(n).padStart(2, '0');

export function parseLocal(local) {
    const [date, time] = local.split('T');
    const [y, m, d] = date.split('-').map(Number);
    const [h, min] = time.split(':').map(Number);
    return new Date(Date.UTC(y, m - 1, d, h, min));
}

export function formatLocal(date) {
    return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}T${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}`;
}

export const shiftLocal = (local, minutes) => formatLocal(new Date(parseLocal(local).getTime() + minutes * 60000));
export const timeOf = (local) => local.split('T')[1];
export const dateOf = (local) => local.split('T')[0];

/** Minutos transcurridos desde un instante ISO (absoluto, no depende de la zona). */
export const minutesSince = (iso, nowMs = Date.now()) => Math.max(0, Math.floor((nowMs - Date.parse(iso)) / 60000));

export function formatClock(seconds) {
    const s = Math.max(0, Math.floor(seconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${pad(m)}:${pad(sec)}`;
}

/** Fecha corta en la zona de la familia: "25 oct". */
export function shortDate(iso, locale, timeZone) {
    return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', timeZone }).format(new Date(iso));
}

/** Hora de un instante ISO en la zona de la familia, en 12 o 24 h según sus ajustes: "4:06 p. m." / "16:06". */
export function shortTime(iso, locale, timeZone, timeFormat = '12') {
    return new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: '2-digit', hour12: timeFormat === '12', timeZone }).format(new Date(iso));
}

/** Hora de un texto local "YYYY-MM-DDTHH:mm" separada en cifra grande y sufijo (a. m./p. m.). */
export function splitLocalTime(local, locale, timeFormat = '12') {
    const parts = new Intl.DateTimeFormat(locale, {
        hour: timeFormat === '12' ? 'numeric' : '2-digit',
        minute: '2-digit',
        hour12: timeFormat === '12',
        timeZone: 'UTC',
    }).formatToParts(parseLocal(local));

    const suffix = parts.find((p) => p.type === 'dayPeriod')?.value ?? '';
    const main = parts.filter((p) => p.type !== 'dayPeriod').map((p) => p.value).join('').trim();
    return { main, suffix };
}

/** Fecha larga con la primera letra en mayúscula: "Domingo, 25 oct". */
export function longDate(date, locale, timeZone) {
    const text = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'short', timeZone }).format(date);
    return text.charAt(0).toUpperCase() + text.slice(1);
}

/** Días de calendario (en la zona de la familia) entre hoy y un instante: 0 = hoy, 1 = mañana. */
export function calendarDaysFrom(iso, nowLocal, timeZone) {
    const at = new Intl.DateTimeFormat('en-CA', { timeZone }).format(new Date(iso)); // YYYY-MM-DD
    const today = nowLocal.split('T')[0];
    return Math.round((Date.parse(`${at}T00:00:00Z`) - Date.parse(`${today}T00:00:00Z`)) / 86400000);
}

/** "jueves, 5 de noviembre": fecha completa de un instante en la zona de la familia. */
export function fullDate(iso, locale, timeZone) {
    const text = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long', timeZone }).format(new Date(iso));
    return text.charAt(0).toUpperCase() + text.slice(1);
}
