const APP_PREFIX = 'si-jadwal';
const BASE_PATH = '/si-jadwal';
const SHELL_CACHE = `${APP_PREFIX}-shell-v1`;
const PAGE_CACHE = `${APP_PREFIX}-pages-v1`;
const DATA_CACHE = `${APP_PREFIX}-data-v1`;

const SHELL_FILES = [
  `${BASE_PATH}/`,
  `${BASE_PATH}/index.php`,
  `${BASE_PATH}/offline.html`,
  `${BASE_PATH}/manifest.webmanifest`,
  `${BASE_PATH}/assets/icons/icon-192.png`,
  `${BASE_PATH}/assets/icons/icon-512.png`,
  `${BASE_PATH}/assets/js/pwa-core.js`,
  `${BASE_PATH}/globals.css`,
  `${BASE_PATH}/style.css`,
  `${BASE_PATH}/login/index.php`
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_FILES)).catch(() => null)
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(
      keys
        .filter((key) => key.startsWith(APP_PREFIX) && ![SHELL_CACHE, PAGE_CACHE, DATA_CACHE].includes(key))
        .map((key) => caches.delete(key))
    );
    await self.clients.claim();
  })());
});

async function networkFirst(request, cacheName, fallbackPath = null) {
  const cache = await caches.open(cacheName);
  try {
    const response = await fetch(request);
    if (response && response.status === 200) {
      cache.put(request, response.clone()).catch(() => null);
    }
    return response;
  } catch (err) {
    const cached = await cache.match(request);
    if (cached) {
      return cached;
    }
    if (fallbackPath) {
      const fallback = await caches.match(fallbackPath);
      if (fallback) {
        return fallback;
      }
    }
    throw err;
  }
}

async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request)
    .then((response) => {
      if (response && response.status === 200) {
        cache.put(request, response.clone()).catch(() => null);
      }
      return response;
    })
    .catch(() => null);

  return cached || networkPromise || fetch(request);
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin || !url.pathname.startsWith(`${BASE_PATH}/`)) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirst(request, PAGE_CACHE, `${BASE_PATH}/offline.html`));
    return;
  }

  if (url.pathname.startsWith(`${BASE_PATH}/backend/`)) {
    if (url.pathname === `${BASE_PATH}/backend/api/pwa_snapshot.php`) {
      event.respondWith(networkFirst(request, DATA_CACHE));
    }
    return;
  }

  const isStatic = /\.(?:css|js|png|jpg|jpeg|svg|gif|webp|ico|woff2?)$/i.test(url.pathname);
  if (isStatic) {
    event.respondWith(staleWhileRevalidate(request, SHELL_CACHE));
    return;
  }

  event.respondWith(networkFirst(request, PAGE_CACHE, `${BASE_PATH}/offline.html`));
});

self.addEventListener('sync', (event) => {
  if (event.tag !== 'si-jadwal-sync') {
    return;
  }

  event.waitUntil(
    self.clients.matchAll({ includeUncontrolled: true, type: 'window' }).then((clients) => {
      clients.forEach((client) => {
        client.postMessage({ type: 'SI_JADWAL_TRIGGER_SYNC' });
      });
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  event.waitUntil((async () => {
    const allClients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
    for (const client of allClients) {
      if ('focus' in client) {
        client.focus();
        client.postMessage({ type: 'SI_JADWAL_OPEN_FROM_NOTIFICATION', payload: event.notification.data || null });
        return;
      }
    }

    if (self.clients.openWindow) {
      await self.clients.openWindow(`${BASE_PATH}/beranda/index.php`);
    }
  })());
});
