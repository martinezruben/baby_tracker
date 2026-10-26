/* Service worker de Kidu Baby Tracker: avisos push con botones Confirmar / Posponer. */

const ASSETS = 'kidu-assets-v1';
const MAX_ASSETS = 150;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) =>
    event.waitUntil(
        (async () => {
            // Borra cachés de versiones anteriores del propio service worker.
            for (const key of await caches.keys()) if (key.startsWith('kidu-assets-') && key !== ASSETS) await caches.delete(key);
            await self.clients.claim();
        })(),
    ),
);

// Los archivos de /build/assets/ llevan el hash en el nombre (nunca cambian): primero la copia local, así las
// visitas siguientes no esperan a la red. Los datos (páginas, API) siempre van al servidor.
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin || !url.pathname.startsWith('/build/assets/')) return;

    event.respondWith(
        caches.open(ASSETS).then(async (cache) => {
            const hit = await cache.match(request);
            if (hit) return hit;

            const response = await fetch(request);
            if (response.ok) {
                cache.put(request, response.clone());
                // Cada despliegue trae archivos nuevos: se conservan los más recientes.
                cache.keys().then((keys) => keys.length > MAX_ASSETS && Promise.all(keys.slice(0, keys.length - MAX_ASSETS).map((k) => cache.delete(k))));
            }

            return response;
        }),
    );
});

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
