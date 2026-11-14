/* BoostRwanda service worker: caches static assets + an offline page.
   Dynamic pages (anything with a login/session) are always fetched from the network. */
const CACHE = 'boostrwanda-static-v1';
const BASE = '/BoostRwanda/';
const STATIC = [
  BASE + 'offline.html',
  BASE + 'assets/css/style.css', BASE + 'assets/css/dashboard.css', BASE + 'assets/css/responsive.css',
  BASE + 'assets/js/app.js', BASE + 'assets/images/logo.svg'
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(STATIC)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;                       // never touch form posts / API writes
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  if (url.pathname.startsWith(BASE + 'assets/')) {       // static: cache first
    e.respondWith(caches.match(req).then(hit => hit || fetch(req).then(res => {
      const copy = res.clone(); caches.open(CACHE).then(c => c.put(req, copy)); return res;
    })));
    return;
  }
  if (req.mode === 'navigate') {                          // pages: network only, offline fallback
    e.respondWith(fetch(req).catch(() => caches.match(BASE + 'offline.html')));
  }
});
