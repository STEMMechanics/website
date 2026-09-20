const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const script = fs.readFileSync('public/script.js', 'utf8');
const handler = script.slice(script.indexOf('    confirmDelete:'), script.indexOf('    confirmAccountDelete:'));

for (const client of ['axios', 'fetch']) {
    for (const status of [200, 422, 500]) {
        test(`${client} delete ${status} redirects on success or shows a visible error`, async () => {
            const alerts = [], redirects = [];
            let reloads = 0;
            const data = status === 200 ? {success: true, redirect: '/admin/invoices'} : {message: status === 422 ? 'Invoice is no longer a draft.' : 'Sensitive database error'};
            const context = {
                Swal: {fire: async () => ({isConfirmed: true})}, HTMLFormElement: class {},
                window: {location: {reload: () => reloads++}},
                fetch: async () => ({ok: status === 200, status, json: async () => data}),
            };
            if (client === 'axios') context.window.axios = {delete: async () => {
                if (status !== 200) throw {response: {status, data}};
                return {data};
            }};
            vm.runInNewContext(`const SM = {${handler}}; globalThis.SM = SM;`, context);
            context.SM.alert = (...args) => alerts.push(args);
            context.SM.redirectIfSafe = url => redirects.push(url);
            context.SM.confirmDelete('csrf', 'Delete?', 'Confirm', '/admin/invoices/1');
            await new Promise(resolve => setImmediate(resolve));
            assert.equal(reloads, 0);
            if (status === 200) {assert.deepEqual(redirects, ['/admin/invoices']); assert.equal(alerts.length, 0);}
            else {assert.equal(alerts.length, 1); assert.equal(alerts[0][0], 'Unable to delete'); assert(!alerts[0][1].includes('Sensitive')); if (status === 422) assert.equal(alerts[0][1], data.message);}
        });
    }
}
