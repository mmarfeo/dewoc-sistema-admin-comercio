const CACHE_NAME   = 'dewoc-v2';
const STATIC_CACHE = 'dewoc-static-v2';

// Assets que se precachean al instalar el SW
const PRECACHE_URLS = [
    '/01-Dewoc-Sist-Admin-Comercio/',
    '/01-Dewoc-Sist-Admin-Comercio/assets/css/theme.bundle.css',
    '/01-Dewoc-Sist-Admin-Comercio/assets/css/app.css',
    '/01-Dewoc-Sist-Admin-Comercio/assets/js/theme.bundle.js',
    '/01-Dewoc-Sist-Admin-Comercio/assets/js/app.js',
    '/01-Dewoc-Sist-Admin-Comercio/offline.html',
    // Páginas clave de la app (shell precacheado para uso offline)
    '/01-Dewoc-Sist-Admin-Comercio/?url=auth/login',
    '/01-Dewoc-Sist-Admin-Comercio/?url=dashboard',
    '/01-Dewoc-Sist-Admin-Comercio/?url=ventas/pos',
    '/01-Dewoc-Sist-Admin-Comercio/?url=stock',
];

// Instalar: precachear assets estáticos
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache => cache.addAll(PRECACHE_URLS))
    );
});

// Activar: limpiar caches viejas
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME && k !== STATIC_CACHE).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Fetch: estrategia mixta
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Assets estáticos → cache first
    if (url.pathname.includes('/assets/')) {
        event.respondWith(
            caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
                const clone = response.clone();
                caches.open(STATIC_CACHE).then(c => c.put(event.request, clone));
                return response;
            }))
        );
        return;
    }

    // Llamadas PHP (controladores/API) → network first, fallback offline
    if (event.request.method === 'GET') {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
                    return response;
                })
                .catch(() => caches.match(event.request).then(cached => cached || caches.match('/01-Dewoc-Sist-Admin-Comercio/offline.html')))
        );
    }
});
