// sw.js — langkah 4
const APP_CACHE = 'mafatihul-akhyar-app-v5';
const RUNTIME   = 'mafatihul-akhyar-rt-v5';

// SESUAIKAN nama file kalau beda di situsmu
const PRECACHE_URLS = [
  '/',            // halaman utama (kalau index.php/html)
  '/index.html',  // kalau pakai index.html
  '/styles.css',
  '/app.js',
  '/logo.png',
  '/offline.html'
];

// Install: simpan app shell
self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(APP_CACHE).then(c => c.addAll(PRECACHE_URLS)));
  self.skipWaiting();
});

// Activate: bersihkan cache lama
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => ![APP_CACHE, RUNTIME].includes(k)).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch handler
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // 1) Navigasi (alamat bar / klik link) → network-first, fallback ke halaman cache
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).then(r => {
        // simpan versi terbaru untuk kunjungan berikutnya
        caches.open(RUNTIME).then(c => c.put(req, r.clone()));
        return r;
      }).catch(async () =>
        // urutan fallback: cache root → cache index.html → offline.html
        (await caches.match('/')) ||
        (await caches.match('/index.html')) ||
        (await caches.match('/offline.html'))
      )
    );
    return;
  }

// Aset statis (CSS/JS/gambar/font) — cache-first, tapi abaikan query (?v=...)
if (/\.(css|js|png|jpe?g|webp|svg|ico|woff2?)$/i.test(new URL(event.request.url).pathname)) {
  event.respondWith((async () => {
    // cari di cache dengan ignoreSearch agar /app.js match /app.js?v=123
    const cached = await caches.match(event.request, { ignoreSearch: true });
    if (cached) return cached;

    try {
      const net = await fetch(event.request);
      const c = await caches.open(RUNTIME);
      c.put(event.request, net.clone());
      return net;
    } catch (e) {
      // offline & tidak ada di cache: biarkan gagal (atau kembalikan offline.html untuk HTML)
      return new Response('', { status: 504 });
    }
  })());
  return;
}


  // selain itu biarkan default (network)
});
