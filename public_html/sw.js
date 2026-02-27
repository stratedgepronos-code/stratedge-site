// ============================================================
// STRATEDGE — Service Worker
// Fichier : /public_html/sw.js
// IMPORTANT : doit être à la racine du site !
// ============================================================

const CACHE_NAME = 'stratedge-v1';

// Installation du SW
self.addEventListener('install', (e) => {
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(clients.claim());
});

// ── Réception d'une notification push ─────────────────────
self.addEventListener('push', (e) => {
    if (!e.data) return;

    let data;
    try {
        data = e.data.json();
    } catch {
        data = { title: 'StratEdge', body: e.data.text(), url: '/' };
    }

    const options = {
        body:    data.body    || '',
        icon:    data.icon    || '/assets/images/mascotte.png',
        badge:   data.badge   || '/assets/images/mascotte.png',
        image:   data.image   || null,
        data:    { url: data.url || '/dashboard.php' },
        vibrate: [200, 100, 200],
        requireInteraction: false,
        actions: data.actions || [],
        tag:     data.tag     || 'stratedge-notif',
        renotify: true,
    };

    e.waitUntil(
        self.registration.showNotification(data.title || 'StratEdge Pronos', options)
    );
});

// ── Clic sur la notification → ouvre l'URL ────────────────
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const url = e.notification.data?.url || '/dashboard.php';

    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            // Si l'onglet est déjà ouvert → focus
            for (const client of list) {
                if (client.url.includes(self.location.origin) && 'focus' in client) {
                    client.focus();
                    client.navigate(url);
                    return;
                }
            }
            // Sinon ouvrir un nouvel onglet
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
