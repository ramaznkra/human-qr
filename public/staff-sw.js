const CACHE_NAME = 'human-staff-v7';
const STATIC_ASSETS = [
    '/manifest-waiter.json',
    '/icons/apple-touch-icon.png',
    '/icons/icon-192.png',
    '/icons/icon-256.png',
    '/icons/icon-512.png',
    '/icons/icon-512-maskable.png',
    '/icons/favicon-32.png',
    '/images/human-logo.png',
];

function isStaffApiPath(pathname) {
    return pathname.startsWith('/admin/api/')
        || pathname.startsWith('/api/waiter/')
        || pathname.startsWith('/api/admin/');
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Admin/garson API — önbellek yok, doğrudan ağ
    if (isStaffApiPath(url.pathname)) {
        event.respondWith(
            fetch(request).catch(() =>
                new Response(JSON.stringify({ message: 'Ağ bağlantısı kurulamadı.' }), {
                    status: 503,
                    headers: { 'Content-Type': 'application/json' },
                })
            )
        );
        return;
    }

    if (request.method !== 'GET') return;

    const isStaffArea = url.pathname.startsWith('/admin') || url.pathname.startsWith('/waiter');
    const isStaticAsset = url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/');
    const isLogoAsset = url.pathname.startsWith('/images/human-logo');
    const isManifest = url.pathname === '/manifest-waiter.json';

    if (!isStaffArea && !isStaticAsset && !isLogoAsset && !isManifest) return;

    const isBuildAsset = url.pathname.startsWith('/build/');
    const isDocumentRequest =
        request.mode === 'navigate' ||
        request.destination === 'document' ||
        (request.headers.get('accept') || '').includes('text/html');

    if (isStaffArea && isDocumentRequest) {
        event.respondWith(fetch(request));
        return;
    }

    if (isBuildAsset) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    if (isLogoAsset || isManifest) {
        event.respondWith(
            fetch(request).catch(() => caches.match(request))
        );
        return;
    }

    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;

            return fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() => cached || Response.error());
        })
    );
});
