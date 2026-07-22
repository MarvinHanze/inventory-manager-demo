const CACHE_NAME = 'inventory-manager-v2';
// Paden zijn relatief aan de scope van deze service worker (/inventory-manager/, zie de
// registratie in index.php) — de eerdere absolute paden ('/', '/login.php', ...) wezen buiten die
// scope naar de domeinroot en werden dus nooit daadwerkelijk gecached.
const urlsToCache = [
    'index.php',
    'login.php',
    'assets/css/style.css',
    'assets/css/components.css',
    'manifest.json'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(urlsToCache))
    );
    self.skipWaiting();
});

// Oude cache-versies opruimen zodra een nieuwe service worker actief wordt (anders stapelt elke
// CACHE_NAME-bump alleen maar op in de browser-opslag van de gebruiker).
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((names) => Promise.all(
            names.filter((n) => n !== CACHE_NAME).map((n) => caches.delete(n))
        ))
    );
    self.clients.claim();
});

// Cache-first is bewust eenvoudig gehouden voor deze demo (i.p.v. stale-while-revalidate). Let op:
// dit geldt alleen voor de precachede lijst hierboven — api.php-verzoeken staan daar niet in en
// worden dus altijd gewoon van het netwerk gehaald, nooit uit deze cache bediend.
self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                if (response) return response;
                return fetch(event.request);
            })
    );
});
