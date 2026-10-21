// Earner-Pro service worker: makes the tool installable and quick to reopen on a weak signal.
// Page and machine data are network-first so updates show straight away; everything else is cache-first.
// Bump CACHE when shipping changes to the cached files.
const CACHE = "earner-pro-v1";
const SHELL = ["./", "./machines.json", "./manifest.webmanifest", "./icons/icon-192.png", "./icons/icon-512.png"];

self.addEventListener("install", e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", e => {
  e.waitUntil(caches.keys()
    .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
    .then(() => self.clients.claim()));
});

async function networkFirst(req, cacheKey) {
  const cache = await caches.open(CACHE);
  try {
    const res = await fetch(req);
    if (res.ok) cache.put(cacheKey, res.clone());
    return res;
  } catch (err) {
    const hit = await cache.match(cacheKey);
    if (hit) return hit;
    throw err;
  }
}

async function cacheFirst(req) {
  const cache = await caches.open(CACHE);
  const hit = await cache.match(req);
  if (hit) return hit;
  const res = await fetch(req);
  if (res.ok || res.type === "opaque") cache.put(req, res.clone());
  return res;
}

self.addEventListener("fetch", e => {
  const req = e.request;
  if (req.method !== "GET") return;   // lead POSTs go straight to the network
  const url = new URL(req.url);

  if (url.origin === location.origin) {
    if (req.mode === "navigate") return e.respondWith(networkFirst(req, "./"));
    if (url.pathname.endsWith("/machines.json")) return e.respondWith(networkFirst(req, "./machines.json"));
    return e.respondWith(cacheFirst(req));
  }
  if (url.hostname === "fonts.googleapis.com" || url.hostname === "fonts.gstatic.com") {
    return e.respondWith(cacheFirst(req));
  }
  // anything else (analytics, lead endpoint) is left to the browser
});
