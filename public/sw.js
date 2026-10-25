/* Service worker de Kidu Baby Tracker: avisos push con botones Confirmar / Posponer. */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'Kidu Baby Tracker', body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'Kidu Baby Tracker', {
            body: payload.body || '',
            tag: payload.tag,
            renotify: Boolean(payload.tag),
            requireInteraction: Boolean(payload.requireInteraction),
            actions: payload.actions || [],
            data: payload.data || {},
        }),
    );
});

async function sendAction(url) {
    const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
}

async function openApp(url) {
    const target = new URL(url || '/', self.location.origin).href;
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const existing = windows.find((w) => 'focus' in w);

    if (existing) {
        await existing.focus();
        if ('navigate' in existing) await existing.navigate(target);
        return;
    }
    await self.clients.openWindow(target);
}

self.addEventListener('notificationclick', (event) => {
    const data = event.notification.data || {};
    event.notification.close();

    const url = event.action === 'ack' ? data.ack_url : event.action === 'snooze' ? data.snooze_url : null;

    event.waitUntil(
        url
            ? sendAction(url).catch(() => openApp(data.open_url)) // si falla, abrir la app para resolverlo a mano
            : openApp(data.open_url),
    );
});
