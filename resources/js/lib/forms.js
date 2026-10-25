import { agoLabel } from './i18n';
import { minutesSince } from './time';

/** "Sofía · último hace 45 min" para la cabecera de un formulario. */
export function lastSubtitle(t, babyName, lastAt) {
    return lastAt
        ? t('registrar.baby_last', { name: babyName, ago: agoLabel(t, minutesSince(lastAt)).toLowerCase() })
        : t('registrar.baby_first', { name: babyName });
}
