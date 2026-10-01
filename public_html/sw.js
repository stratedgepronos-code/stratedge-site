// ============================================================
// STRATEDGE — Service Worker v3
// Fichier : /public_html/sw.js
// Compatible iOS 16.4+ (PWA) et Android (Chrome/Firefox/Samsung)
// ============================================================

const CACHE_VERSION = 'stratedge-v3-2026-04';

// ── Installation : activation immédiate du nouveau SW ─────
self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

// ── Activation : prendre le contrôle immédiatement ────────
self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();
        await Promise.all(
            cacheNames
                .filter(name => name !== CACHE_VERSION)
                .map(name => caches.delete(name))
        );
        await self.clients.claim();
    })());
});

// ── Réception d'une notification push ─────────────────────
self.addEventListener('push', (event) => {
    let data = { title: 'StratEdge Pronos', body: '', url: '/dashboard.php' };

    if (event.data) {
        try {
            data = event.data.json();
        } catch (e) {
            try {
                data = { title: 'StratEdge Pronos', body: event.data.text(), url: '/dashboard.php' };
            } catch (e2) {
                data = { title: 'StratEdge Pronos', body: 'Nouvelle notification', url: '/dashboard.php' };
            }
        }
    }

    const isIOS = /iPhone|iPad|iPod/.test(self.navigator ? self.navigator.userAgent : '');

    const options = {
        body: data.body || '',
        icon: data.icon || '/assets/images/mascotte.png',
        badge: data.badge || '/assets/images/mascotte.png',
        data: {
            url: data.url || '/dashboard.php',
            timestamp: Date.now()
        },
        vibrate: [200, 100, 200],
        requireInteraction: false,
        tag: data.tag || 'stratedge-' + Date.now(),
        renotify: true,
        silent: false
    };

    // iOS ne supporte pas bien image/actions
    if (data.image && !isIOS) {
        options.image = data.image;
    }
    if (data.actions && Array.isArray(data.actions) && !isIOS) {
        options.actions = data.actions;
    }

    event.waitUntil(
        self.registration.showNotification(
            data.title || 'StratEdge Pronos',
            options
        ).catch(err => {
            console.error('[SW] showNotification failed:', err);
            return self.registration.showNotification(
                data.title || 'StratEdge Pronos',
                { body: data.body || '', icon: '/assets/images/mascotte.png' }
            );
        })
    );
});

// ── Clic sur la notification → ouvre l'URL ────────────────
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/dashboard.php';
    const fullUrl = new URL(targetUrl, self.location.origin).href;

    event.waitUntil((async () => {
        const windowClients = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        });

        for (const client of windowClients) {
            if (client.url.startsWith(self.location.origin)) {
                try {
                    await client.focus();
                    if ('navigate' in client) {
                        await client.navigate(fullUrl);
                    }
                    return;
                } catch (e) {
                    console.error('[SW] focus/navigate failed:', e);
                }
            }
        }

        if (self.clients.openWindow) {
            return self.clients.openWindow(fullUrl);
        }
    })());
});

// ── Gestion renouvellement de subscription (iOS/Android) ──
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        try {
            const oldKey = event.oldSubscription ? event.oldSubscription.options.applicationServerKey : null;
            if (!oldKey) return;
            const subscription = await self.registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: oldKey
            });
            await fetch('/push-subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(subscription)
            });
        } catch (e) {
            console.error('[SW] subscription renewal failed:', e);
        }
    })());
});
