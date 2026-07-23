const CACHE_NAME = 'inventory-manager-v3';
// BUGFIX (v2 -> v3): index.php/login.php stonden hier eerder in de precache-lijst. Dat zijn
// dynamische, sessie-afhankelijke PHP-pagina's (login.php bevat een CSRF-token gebonden aan de
// PHP-sessie van het moment van cachen) — cache-first serveren van login.php betekende dat
// terugkerende bezoekers een verouderd, niet meer geldig CSRF-token in het loginformulier konden
// krijgen, waardoor elke inlogpoging faalde met een CSRF-mismatch en de app leek "onbereikbaar".
// Alleen nog écht statische assets precachen; alle .php-pagina's altijd vers van het netwerk.
const urlsToCache = [
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

// Cache-first is bewust eenvoudig gehouden voor deze demo (i.p.v. stale-while-revalidate) — maar
// nooit voor .php-requests: die zijn altijd dynamisch/sessie-afhankelijk (login-status,
// CSRF-tokens, live voorraaddata) en mogen nooit uit de cache komen, ook niet als er ooit
// per ongeluk iets van dat type in de cache terechtkomt.
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (url.pathname.endsWith('.php')) {
        event.respondWith(fetch(event.request));
        return;
    }
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                if (response) return response;
                return fetch(event.request);
            })
    );
});
