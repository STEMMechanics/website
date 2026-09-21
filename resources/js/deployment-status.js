// Keep monitoring independent of the deployed application's ability to return JSON.
export function deploymentStatus({ element, notify, watch = false, check = () => fetch('/?_deployment_check=' + Date.now(), { cache: 'no-store', credentials: 'omit', signal: AbortSignal.timeout(15000) }) }) {
    let lastNotice = '', checking = false, healthy = '', watching = watch, maintenanceSince = null;
    const show = (message, tone = 'info', announce = false) => {
        element.textContent = message;
        element.dataset.tone = tone;
        if (announce && lastNotice !== message) {
            lastNotice = message;
            notify(tone === 'success' ? 'Deployment complete' : 'Deployment status', message, tone);
        }
    };
    const error = (failure) => {
        healthy = '';
        if (failure.status === 503) {
            watching = true;
            maintenanceSince ??= Date.now();
            if (Date.now() - maintenanceSince > 600000) {
                show('The site has remained in maintenance mode (HTTP 503) for over 10 minutes. Check the deployment log on the server.', 'warning', true);
                return;
            }
            show('Maintenance mode (HTTP 503). Waiting for the site to return…');
        } else if (failure.status >= 500) {
            watching = true;
            show(`The site is returning HTTP ${failure.status}. Deployment may have failed; monitoring will continue.`, 'danger', true);
        } else if (failure.status === 401 || failure.status === 403 || failure.status === 419) {
            show('Your session needs refreshing. Sign in again to check deployment status.', 'warning', true);
        } else {
            show('Cannot reach deployment status. Retrying…', 'warning');
        }
    };
    const update = async (content) => {
        maintenanceSince = null;
        const terminal = content.match(/Deployment process exited with code (\d+)/);
        const finished = content.match(/Deployment finished at (.+)/);
        if (terminal && Number(terminal[1]) !== 0) {
            show(`Deployment failed (exit ${terminal[1]}). Check the deployment log.`, 'danger', true);
            return;
        }
        if (finished || terminal) {
            const key = finished?.[0] || terminal[0];
            if (healthy === key) { show('Deployment finished. The site is responding successfully.', 'success'); return; }
            if (checking) return;
            checking = true;
            show('Deployment command finished. Checking the site…');
            try {
                const response = await check();
                if (response.status === 503) {
                    show('Deployment command finished, but the site is still in maintenance mode (HTTP 503).', 'warning', true);
                } else if (!response.ok) {
                    show(`Deployment command finished, but the site health check returned HTTP ${response.status}.`, 'danger', true);
                } else {
                    healthy = key;
                    show(finished ? 'Deployment finished. The site is responding successfully.' : 'Deployment command finished. The site is responding successfully (check the log for skipped updates).', 'success', watching);
                }
            } catch {
                show('Deployment command finished, but the site health check could not connect. Retrying…', 'warning', true);
            } finally { checking = false; }
            return;
        }
        if (!content.trim()) { show('No deployment recorded.'); return; }
        watching = true;
        healthy = '';
        const stages = [...content.matchAll(/Deployment status: ([^\r\n]+)/g)];
        show(stages.at(-1)?.[1] || 'Deployment in progress. Waiting for the next update…');
    };
    return { update, error };
}
window.SM = window.SM || {};
window.SM.deploymentStatus = deploymentStatus;
