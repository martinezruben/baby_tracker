const onlyDigits = (value) => String(value ?? '').replace(/\D/g, '');

/** `tel:` con solo números y el + inicial. */
export const telUrl = (phone) => `tel:${String(phone ?? '').replace(/[^\d+]/g, '')}`;

/**
 * WhatsApp necesita el número con código de país. Si viene con "+" o "00" se respeta; un número de 10 cifras
 * se toma como del plan +1 (República Dominicana, EE. UU., Puerto Rico); otro tamaño se usa tal cual.
 */
export function whatsappUrl(phone) {
    const raw = String(phone ?? '').trim();
    let digits = onlyDigits(raw);
    if (raw.startsWith('00')) digits = digits.slice(2);
    else if (!raw.startsWith('+') && digits.length === 10) digits = `1${digits}`;

    return `https://wa.me/${digits}`;
}

export const wazeUrl = (address) => `https://waze.com/ul?q=${encodeURIComponent(address)}&navigate=yes`;

export const mapsUrl = (address) => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;

export const instagramUrl = (handle) => `https://www.instagram.com/${encodeURIComponent(handle)}/`;
