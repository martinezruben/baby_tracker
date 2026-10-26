function urlBase64ToUint8Array(base64) {
    const padded = base64.padEnd(base64.length + ((4 - (base64.length % 4)) % 4), '=');
    const raw = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
}

function csrfHeader() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}

async function call(method, body) {
    const response = await fetch('/push/suscripciones', {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeader() },
        body: JSON.stringify(body),
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
}

export const pushSupported = () =>
    typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

export async function currentSubscription() {
    const registration = await navigator.serviceWorker.ready;
    return registration.pushManager.getSubscription();
}

/** Pide permiso, se suscribe en este dispositivo y registra la suscripción en el servidor. */
export async function enablePush(vapidPublicKey) {
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return 'denied';

    const registration = await navigator.serviceWorker.ready;
    const subscription =
        (await registration.pushManager.getSubscription()) ??
        (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
        }));

    await call('POST', {
        ...subscription.toJSON(),
        contentEncoding: (PushManager.supportedContentEncodings ?? ['aes128gcm'])[0],
    });

    return 'on';
}

export async function disablePush() {
    const subscription = await currentSubscription();
    if (!subscription) return;

    await call('DELETE', { endpoint: subscription.endpoint });
    await subscription.unsubscribe();
}

/** Pide al servidor un aviso de prueba para este cuidador. Devuelve el mensaje a mostrar. */
export async function sendTestPush() {
    const response = await fetch('/push/prueba', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...csrfHeader() },
    });
    const body = await response.json().catch(() => ({}));
    // `detail` (motivo técnico) solo llega a los administradores.
    return [body.message, body.detail].filter(Boolean).join(' · ');
}

/** Recordatorio real que vence en 1 minuto: prueba cron, envío y botones. */
export async function scheduleTestPush() {
    const response = await fetch('/push/prueba-programada', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', ...csrfHeader() },
    });
    const body = await response.json().catch(() => ({}));
    return body.message ?? '';
}

export async function fetchPushStatus() {
    const response = await fetch('/push/estado', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    return response.ok ? response.json() : null;
}
