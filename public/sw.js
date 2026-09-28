const CACHE = 'app-v2';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    // ponytail: no precache manifest, hashed build assets are cache-first once seen.
    if (new URL(request.url).pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ??
                    fetch(request).then((response) => {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(request, copy));
                        return response;
                    }),
            ),
        );
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
    }
});

// Web Push dari server (App\Notifications\IzinDiajukan). Payload berbentuk
// WebPushMessage::toArray(): { title, body, icon, data: { url } }.
self.addEventListener('push', (event) => {
    const pesan = event.data?.json() ?? {};

    event.waitUntil(
        self.registration.showNotification(pesan.title ?? 'Notifikasi', {
            body: pesan.body,
            icon: pesan.icon,
            badge: '/pwa-192.png',
            data: pesan.data,
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((tab) => {
            const terbuka = tab.find((client) => new URL(client.url).origin === self.location.origin);

            return terbuka ? terbuka.navigate(url).then((client) => client?.focus()) : self.clients.openWindow(url);
        }),
    );
});
