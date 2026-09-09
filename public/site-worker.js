self.addEventListener('push', event => {
    const message = event.data?.json() || {};
    event.waitUntil(self.registration.showNotification(message.title || 'STEMMechanics', {
        body: message.body || 'Open STEMMechanics to view the details.',
        icon: '/apple-touch-icon.png',
        tag: message.tag,
        data: { url: message.url || '/admin/dashboard' },
    }));
});
self.addEventListener('notificationclick', event => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/admin/dashboard', self.location.origin);
    if (target.origin !== self.location.origin) return;
    event.waitUntil(clients.openWindow(target.href));
});
