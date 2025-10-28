/* public/notification-sw.js */

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) {}

  const title = typeof data.title === 'string' && data.title.trim()
    ? data.title
    : 'GlitchaHitch';

  const timestamp = Date.now();
  const rawCategory = typeof data.category === 'string' ? data.category.trim().toLowerCase() : '';
  const category = rawCategory && /^[a-z0-9_-]{3,32}$/.test(rawCategory) ? rawCategory : 'social';

  const baseData = data.data && typeof data.data === 'object' ? { ...data.data } : {};
  const targetUrl = typeof data.url === 'string' && data.url.trim()
    ? data.url.trim()
    : (typeof baseData.url === 'string' && baseData.url ? baseData.url : '/');

  const meta = {
    ...baseData,
    url: targetUrl,
    ts: baseData.ts || timestamp,
    category,
  };

  const options = {
    body: typeof data.body === 'string' ? data.body : '',
    icon: typeof data.icon === 'string' && data.icon ? data.icon : '/assets/img/icon-192.png',
    badge: typeof data.badge === 'string' && data.badge ? data.badge : '/assets/img/badge-72.png',
    data: meta,
    actions: Array.isArray(data.actions) ? data.actions : [],
    tag: typeof data.tag === 'string' && data.tag.trim() ? data.tag.trim() : category,
  };

  if (Array.isArray(data.vibrate)) {
    options.vibrate = data.vibrate;
  }

  if (typeof data.requireInteraction === 'boolean') {
    options.requireInteraction = data.requireInteraction;
  }

  if (typeof data.silent === 'boolean') {
    options.silent = data.silent;
  }

  if (typeof data.image === 'string' && data.image) {
    options.image = data.image;
  }

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification && event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) return client.focus();
      }
      if (clients.openWindow) return clients.openWindow(target);
    })
  );
});
