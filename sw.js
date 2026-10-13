const CACHE_NAME   = 'dewoc-v12';
const STATIC_CACHE = 'dewoc-static-v12';

// Base dinámica: funciona en dev (/subdir/) y producción (/)
const BASE = self.location.pathname.replace(/sw\.js$/, '');

const PRECACHE_URLS = [
    BASE + 'assets/css/theme.bundle.css',
    BASE + 'assets/css/app.css',
    BASE + 'assets/js/theme.bundle.js',
    BASE + 'assets/js/app.js',
    BASE + 'assets/js/pos-offline.js',
];

// Instalar: precachear assets propios con tolerancia a fallos individuales
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(STATIC_CACHE).then(cache =>
            Promise.allSettled(
                PRECACHE_URLS.map(url => cache.add(url).catch(() => null))
            )
        )
    );
});

// Activar: limpiar caches viejas
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(k => k !== CACHE_NAME && k !== STATIC_CACHE).map(k => caches.delete(k))
            )
        )
    );
    self.clients.claim();
});

// Fetch: SOLO interceptar requests al mismo origen (nunca CDNs externos)
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Dejar pasar requests cross-origin sin tocarlas
    if (url.origin !== self.location.origin) return;

    // Assets estáticos propios → cache first
    if (url.pathname.includes('/assets/')) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(STATIC_CACHE).then(c => c.put(event.request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // POS: además del comportamiento general (network first), guardamos una
    // copia del shell HTML para poder abrir /ventas/pos aunque no haya conexión
    // (el catálogo y las ventas pendientes reales viven en IndexedDB, esto solo
    // evita la pantalla "Sin conexión" en blanco si se recarga estando offline).
    if (event.request.method === 'GET' && url.pathname.endsWith('/ventas/pos')) {
        event.respondWith(
            fetch(event.request).then(response => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(STATIC_CACHE).then(c => c.put(event.request, clone));
                }
                return response;
            }).catch(() =>
                caches.match(event.request).then(r => r || new Response('Sin conexión', { status: 503 }))
            )
        );
        return;
    }

    // Páginas PHP → network first, sin cachear respuestas dinámicas
    if (event.request.method === 'GET') {
        event.respondWith(
            fetch(event.request).catch(() =>
                caches.match(event.request).then(r => r || new Response('Sin conexión', { status: 503 }))
            )
        );
    }
});

// ── Web Push: notificaciones de solicitudes para administradores ───────────
self.addEventListener('push', event => {
    let data = { title: 'Dewoc', body: 'Tenés una notificación nueva.' };
    if (event.data) {
        try { data = event.data.json(); } catch { data.body = event.data.text(); }
    }
    event.waitUntil(
        self.registration.showNotification(data.title || 'Dewoc', {
            body: data.body || '',
            icon: data.icon || (BASE + 'assets/favicon/favicon.png'),
            badge: data.icon || (BASE + 'assets/favicon/favicon.png'),
            data: { url: data.url || BASE },
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = event.notification.data?.url || BASE;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientsList => {
            for (const client of clientsList) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            if (self.clients.openWindow) return self.clients.openWindow(url);
        })
    );
});
