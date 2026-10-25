import { usePage } from '@inertiajs/react';
import { shortDate, shortTime, splitLocalTime } from './time';

/** Formateadores de fecha y hora con el idioma, la zona horaria y el formato 12/24 h de la familia. */
export default function useFormat() {
    const { locale, timezone, timeFormat } = usePage().props;

    return {
        time: (iso) => shortTime(iso, locale, timezone, timeFormat),
        date: (iso) => shortDate(iso, locale, timezone),
        localTime: (local) => splitLocalTime(local, locale, timeFormat),
        locale,
        timezone,
    };
}
