export const ML_PER_OZ = 29.5735295625;

/** Convierte un volumen entre ml y oz y lo redondea para mostrarlo (ml enteros, oz con un decimal; 0,5 oz en el paso). */
export function convertVolume(value, from, to) {
    const n = Number(value);
    if (!Number.isFinite(n) || from === to) return n;
    const converted = to === 'oz' ? n / ML_PER_OZ : n * ML_PER_OZ;
    return to === 'oz' ? Math.round(converted * 2) / 2 : Math.round(converted / 5) * 5;
}
