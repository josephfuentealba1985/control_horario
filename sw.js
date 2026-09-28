'use strict';
const CACHE = 'control-horario-public-v1.4.0';
const PUBLIC = ['assets/style.css','assets/app.js','assets/icon-192.png','assets/icon-512.png','logo.png','offline.html'];
const publicURLs = new Set(PUBLIC.map(path => new URL(path,self.registration.scope).href));
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(PUBLIC)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('control-horario-public-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET' || new URL(req.url).origin !== location.origin) return;
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(new URL('offline.html',self.registration.scope).href)));
  } else if (publicURLs.has(req.url)) {
    event.respondWith(caches.open(CACHE).then(async cache => (await cache.match(req)) || fetch(req)));
  }
});
