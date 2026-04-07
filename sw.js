const APP_CACHE = 'mafatihul-akhyar-app-v7';
const RUNTIME = 'mafatihul-akhyar-rt-v7';

const PRECACHE_URLS = [
  './',
  './index.html',
  './admin.html',
  './privasi.html',
  './tentang.html',
  './contact.html',
  './saran.html',
  './offline.html',
  './logo.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(APP_CACHE);
    await Promise.allSettled(
      PRECACHE_URLS.map((url) => cache.add(new Request(url, { cache: 'reload' })))
    );
  })());
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => ![APP_CACHE, RUNTIME].includes(k)).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET') return;

  if (req.mode === 'navigate') {
    event.respondWith((async () => {
      try {
        const res = await fetch(req);
        const cache = await caches.open(RUNTIME);
        cache.put(req, res.clone());
        return res;
      } catch {
        return (await caches.match(req)) ||
               (await caches.match('./index.html', { ignoreSearch: true })) ||
               (await caches.match('./offline.html', { ignoreSearch: true }));
      }
    })());
    return;
  }

  if (/\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$/i.test(url.pathname)) {
    event.respondWith((async () => {
      const cached = await caches.match(req, { ignoreSearch: true });
      if (cached) return cached;

      try {
        const net = await fetch(req);
        const cache = await caches.open(RUNTIME);
        cache.put(req, net.clone());
        return net;
      } catch {
        return new Response('', { status: 504, statusText: 'Offline' });
      }
    })());
  }
});
