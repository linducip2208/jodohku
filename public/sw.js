const CACHE = 'jodohku-shell-v1';
const CORE = ['/', '/offline-fallback'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(CORE)).then(() => self.skipWaiting()).catch(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim())
    );
});

// Navigations: network-first, fall back to cached shell (never breaks web).
self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') {
        return;
    }
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((cache) => cache.put('/', copy)).catch(() => {});
                return res;
            }).catch(() => caches.match('/').then((hit) => hit || Response.error()))
        );
        return;
    }
    if (new URL(req.url).origin === self.location.origin && (req.destination === 'style' || req.destination === 'script' || req.destination === 'image')) {
        event.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((cache) => cache.put(req, copy)).catch(() => {});
                return res;
            }))
        );
    }
});
