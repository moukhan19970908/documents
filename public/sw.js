// Web Push service worker — shows OS notifications even when the site is closed.

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'Уведомление', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'Уведомление';
    const options = {
        body: data.body || '',
        tag: data.type || undefined,
        renotify: true,
        data: { url: data.url || '/' },
        // OS plays its default notification sound (silent defaults to false).
    };

    event.waitUntil((async () => {
        // If a tab is already open AND focused, let the in-page toast handle it
        // (avoids a duplicate OS banner). Otherwise show the OS notification.
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const focused = windows.some((c) => c.focused || c.visibilityState === 'visible');
        if (focused) return;
        await self.registration.showNotification(title, options);
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url;
    if (!url) return;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (client.url === url && 'focus' in client) return client.focus();
        }
        if (self.clients.openWindow) return self.clients.openWindow(url);
    })());
});
