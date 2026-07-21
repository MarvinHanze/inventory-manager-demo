const CACHE_NAME = 'inventory-manager-v1';
const urlsToCache = [
    '/',
    '/login.php',
    '/assets/css/style.css',
    'https://cdn.jsdelivr.net/npm/chart.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                if (response) return response;
                return fetch(event.request);
            })
    );
});
// ponytail: cache-first for all requests including API calls — acceptable for demo, skip stale-while-revalidate
