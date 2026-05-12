/**
 * TK Danışmanlık Service Worker
 *
 * Strateji:
 *  - /api/* istekleri: network-only (asla cache)
 *  - Statik varliklar (css/js/font/image): stale-while-revalidate
 *  - HTML sayfalar: network-first, offline'da cache fallback
 */
const VERSION = 'tk-v1';
const STATIC_CACHE = 'tk-static-' + VERSION;
const PAGE_CACHE   = 'tk-pages-' + VERSION;

const PRECACHE = [
  '/',
  '/index.html',
  '/blog.html',
  '/admin.html',
  '/css/style.css',
  '/js/main.js',
  '/icons/icon.svg',
  '/icons/icon-maskable.svg',
  '/icons/icon-admin.svg',
  '/manifest.json',
  '/manifest-admin.json',
  '/odeme-basarili.html',
  '/odeme-basarisiz.html'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) =>
      Promise.all(PRECACHE.map((url) => cache.add(url).catch(() => null)))
    ).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) =>
      Promise.all(names.filter((n) => !n.endsWith(VERSION)).map((n) => caches.delete(n)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Asla cache'leme: API, iyzico, PHP
  if (url.pathname.startsWith('/api/') ||
      url.pathname.endsWith('.php') ||
      url.hostname.includes('iyzipay') ||
      url.hostname.includes('iyzico')) {
    return; // Default browser fetch
  }

  // HTML navigasyon: network-first
  if (req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(PAGE_CACHE).then((c) => c.put(req, copy));
          return res;
        })
        .catch(() =>
          caches.match(req).then((cached) => cached || caches.match('/index.html'))
        )
    );
    return;
  }

  // Statik varliklar: stale-while-revalidate
  event.respondWith(
    caches.match(req).then((cached) => {
      const network = fetch(req).then((res) => {
        if (res && res.status === 200 && res.type === 'basic') {
          const copy = res.clone();
          caches.open(STATIC_CACHE).then((c) => c.put(req, copy));
        }
        return res;
      }).catch(() => cached);
      return cached || network;
    })
  );
});

// Admin panelden cache clear sinyali alindiginda
self.addEventListener('message', (event) => {
  if (event.data === 'clear-cache') {
    caches.keys().then((names) => names.forEach((n) => caches.delete(n)));
  }
});
