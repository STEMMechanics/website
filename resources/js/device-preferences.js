async function initialisePush() {
    const root = document.querySelector('[data-push-root]');
    if (!root) return;
    const prompt = root.querySelector('[data-push-prompt]');
    const panels = [...document.querySelectorAll('[data-push-settings]'), root];
    const status = message => panels.forEach(panel => panel.querySelector('[data-push-status]').textContent = message);
    const showError = error => window.SM.alert('Notification error', error.message, 'danger');
    let deviceId, devices = [], publicKey, busy = false;
    const key = `sm-push-dismissed:${root.dataset.user}`;
    const supported = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const needsInstall = ios && !window.matchMedia('(display-mode: standalone)').matches && !navigator.standalone;
    const permissionHelp = 'Notifications were blocked by your browser. Check your browser permissions and try again.';
    const current = () => devices.find(device => device.device_id === deviceId);
    async function request(method = 'GET', data, path = '') {
        const response = await fetch(root.dataset.endpoint + path, {
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
        const blocked = supported && Notification.permission === 'denied';
        for (const panel of panels) {
            panel.querySelectorAll('[data-push-enable]').forEach(button => {
                button.disabled = busy || !supported || needsInstall || !publicKey || !!current()?.enabled;
                button.textContent = current()?.enabled ? 'Enabled on this device' : 'Enable on this device';
            });
            // The prompt also uses this action to save an explicit opt-out before enabling.
            panel.querySelectorAll('[data-push-disable]').forEach(button => button.disabled = busy || (panel !== root && !current()?.enabled));
        }
        for (const list of document.querySelectorAll('[data-push-devices]')) {
            const panel = list.closest('[data-push-settings]');
            const template = panel.querySelector('[data-push-device-template]');
            const count = panel.querySelector('[data-push-count]');
            count.textContent = `${devices.length} ${devices.length === 1 ? 'device' : 'devices'}`;
            count.hidden = false;
            panel.querySelector('[data-push-empty]').hidden = devices.length > 0;
            list.hidden = devices.length === 0;
            list.replaceChildren();
            for (const device of devices) {
                const li = template.content.firstElementChild.cloneNode(true);
                li.querySelector('[data-push-device-name]').textContent = device.name;
                li.querySelector('[data-push-current]').hidden = device.device_id !== deviceId;
                li.querySelector('[data-push-device-status]').textContent = device.device_id === deviceId && blocked
                    ? 'Blocked by browser'
                    : device.enabled ? 'Notifications on' : 'Notifications off';
                const button = li.querySelector('[data-push-remove]');
                const testButton = li.querySelector('[data-push-test]');
                testButton.disabled = busy || !device.enabled || !device.can_enable || !publicKey;
                testButton.addEventListener('click', () => run(async () => {
                    await request('POST', { device_id: device.device_id }, '/test');
                    window.SM.alert('Test notification sent', 'Check the selected device for your test notification.', 'success');
                }));
                button.disabled = busy;
                button.addEventListener('click', () => {
                    const remove = confirmed => confirmed ? run(() => removeDevice(device)) : undefined;
                    if (window.SM && typeof window.SM.confirm === 'function') {
                        return window.SM.confirm('Remove notification device?', 'This device will no longer receive notifications. Enable it from that device to add it again.', 'Remove', remove);
                    }
                    return remove(window.confirm('Remove this device? It will no longer receive notifications.'));
                });
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
    async function removeDevice(device) {
        await request('DELETE', { device_id: device.device_id });
        devices = devices.filter(saved => saved.device_id !== device.device_id);
        if (device.device_id === deviceId) {
            prompt.hidden = true;
            sessionStorage.setItem(key, '1');
        }
        status(device.device_id === deviceId ? 'Device removed. Notifications are off for this device.' : 'Notification device removed.');
    }
    async function run(action) {
        if (busy) return;
        busy = true; render();
        try { await action(); } catch (error) { showError(error); }
        finally { busy = false; render(); }
    }
    try {
        deviceId = localStorage.getItem('sm-push-device');
        if (!deviceId) { deviceId = crypto.randomUUID(); localStorage.setItem('sm-push-device', deviceId); }
        await refresh();
        if (supported && current()?.enabled) {
            const registration = await navigator.serviceWorker.getRegistration('/site-worker.js');
            if (Notification.permission !== 'granted' || !await registration?.pushManager.getSubscription()) {
                await save(false);
            }
        }
        if (needsInstall) status('On iPhone or iPad, add this site to your Home Screen, then open it there to enable notifications.');
        else if (!supported) status('Push notifications are unavailable in this browser. Use a supported browser over HTTPS.');
        else if (!publicKey) status('Push notifications are awaiting server setup.');
        else status(current()?.enabled ? 'Notifications are on for this device.' : 'Notifications are off for this device.');
        if (supported && publicKey && !needsInstall && Notification.permission !== 'denied' && !current() && !sessionStorage.getItem(key) && root.dataset.prompt === '1') prompt.hidden = false;
        for (const panel of panels) {
            panel.querySelectorAll('[data-push-later]').forEach(button => button.addEventListener('click', () => { prompt.hidden = true; sessionStorage.setItem(key, '1'); }));
            panel.querySelectorAll('[data-push-disable]').forEach(button => button.addEventListener('click', () => run(() => save(false))));
            panel.querySelectorAll('[data-push-enable]').forEach(button => button.addEventListener('click', () => run(async () => {
                // Request permission directly from the click, before any network or worker awaits.
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    if (permission === 'denied' && current()?.enabled) await save(false);
                    throw new Error(permission === 'denied' ? permissionHelp : 'Notification permission was not granted. Click Enable on this device to try again.');
                }
                const registration = await navigator.serviceWorker.register('/site-worker.js');
                await navigator.serviceWorker.ready;
                let subscription = await registration.pushManager.getSubscription();
                if (!subscription) {
                    const padded = publicKey.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - publicKey.length % 4) % 4);
                    subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: Uint8Array.from(atob(padded), character => character.charCodeAt(0)) });
                }
                await save(true, subscription.toJSON());
            })));
        }
    } catch (error) {
        status('');
        showError(error);
    }
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialisePush);
else initialisePush();
