async function initialisePush() {
    const root = document.querySelector('[data-push-root]');
    if (!root) return;
    const prompt = root.querySelector('[data-push-prompt]');
    const panels = [...document.querySelectorAll('[data-push-settings]'), root];
    const status = message => panels.forEach(panel => panel.querySelector('[data-push-status]').textContent = message);
    let deviceId, devices = [], publicKey, busy = false;
    const key = `sm-push-dismissed:${root.dataset.user}`;
    const supported = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const needsInstall = ios && !window.matchMedia('(display-mode: standalone)').matches && !navigator.standalone;
    const current = () => devices.find(device => device.device_id === deviceId);
    async function request(method = 'GET', data) {
        const response = await fetch(root.dataset.endpoint, {
            method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            ...(data ? { body: JSON.stringify(data) } : {}),
        });
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            throw new Error(Object.values(error.errors || {}).flat()[0] || error.message || 'Could not save notification settings. Please try again.');
        }
        return response.json();
    }
    function render() {
        for (const panel of panels) {
            panel.querySelectorAll('[data-push-enable]').forEach(button => button.disabled = busy || !supported || needsInstall || !publicKey || Notification.permission === 'denied' || !!current()?.enabled);
            panel.querySelectorAll('[data-push-disable]').forEach(button => button.disabled = busy);
        }
        for (const list of document.querySelectorAll('[data-push-devices]')) {
            list.replaceChildren();
            for (const device of devices) {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between gap-3 py-3 text-sm';
                const label = document.createElement('span');
                label.textContent = `${device.name}${device.device_id === deviceId ? ' (this device)' : ''} · ${device.enabled ? 'On' : 'Off'}`;
                li.append(label);
                if (device.enabled || (device.can_enable && device.device_id !== deviceId)) {
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'text-primary-color'; button.textContent = device.enabled ? 'Turn off' : 'Turn on'; button.disabled = busy || (!device.enabled && !publicKey);
                    button.addEventListener('click', () => run(() => save(!device.enabled, null, device)));
                    li.append(button);
                }
                list.append(li);
            }
        }
    }
    async function refresh() {
        const result = await request(); devices = result.devices; publicKey = result.publicKey;
        render();
    }
    async function save(enabled, subscription = null, device = current()) {
        await request('PUT', { device_id: device?.device_id || deviceId, name: device?.name || `${/Firefox/.test(navigator.userAgent) ? 'Firefox' : /Edg/.test(navigator.userAgent) ? 'Edge' : /Chrome/.test(navigator.userAgent) ? 'Chrome' : 'Safari'} on ${ios ? 'iPhone / iPad' : /Android/.test(navigator.userAgent) ? 'Android' : /Mac/.test(navigator.platform) ? 'Mac' : 'computer'}`, enabled, subscription });
        if (!device || device.device_id === deviceId) prompt.hidden = true;
        await refresh();
        status('Notification preferences saved.');
    }
    async function run(action) {
        if (busy) return;
        busy = true; render();
        try { await action(); } catch (error) { status(error.message); }
        finally { busy = false; render(); }
    }
    try {
        deviceId = localStorage.getItem('sm-push-device');
        if (!deviceId) { deviceId = crypto.randomUUID(); localStorage.setItem('sm-push-device', deviceId); }
        await refresh();
        if (supported && current()?.enabled) {
            const registration = await navigator.serviceWorker.getRegistration('/push-sw.js');
            if (Notification.permission !== 'granted' || !await registration?.pushManager.getSubscription()) {
                await save(false);
            }
        }
        if (needsInstall) status('On iPhone or iPad, add this site to your Home Screen, then open it there to enable notifications.');
        else if (!supported) status('Push notifications are unavailable in this browser. Use a supported browser over HTTPS.');
        else if (!publicKey) status('Push notifications are awaiting server setup.');
        else if (Notification.permission === 'denied') status('Notifications are blocked in your browser settings. Allow them there before enabling this device.');
        else status(current()?.enabled ? 'Notifications are on for this device.' : 'Notifications are off for this device.');
        if (supported && publicKey && !needsInstall && Notification.permission !== 'denied' && !current() && !sessionStorage.getItem(key) && root.dataset.prompt === '1') prompt.hidden = false;
        for (const panel of panels) {
            panel.querySelectorAll('[data-push-later]').forEach(button => button.addEventListener('click', () => { prompt.hidden = true; sessionStorage.setItem(key, '1'); }));
            panel.querySelectorAll('[data-push-disable]').forEach(button => button.addEventListener('click', () => run(() => save(false))));
            panel.querySelectorAll('[data-push-enable]').forEach(button => button.addEventListener('click', () => run(async () => {
                // Request permission directly from the click, before any network or worker awaits.
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    if (permission === 'denied') await save(false);
                    throw new Error('Notifications were not enabled. You can change this in your browser settings.');
                }
                const registration = await navigator.serviceWorker.register('/push-sw.js');
                await navigator.serviceWorker.ready;
                let subscription = await registration.pushManager.getSubscription();
                if (!subscription) {
                    const padded = publicKey.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - publicKey.length % 4) % 4);
                    subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: Uint8Array.from(atob(padded), character => character.charCodeAt(0)) });
                }
                await save(true, subscription.toJSON());
            })));
        }
    } catch (error) { status(`Notification settings unavailable: ${error.message}`); }
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialisePush);
else initialisePush();
