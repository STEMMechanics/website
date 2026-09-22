const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const template = fs.readFileSync('resources/views/components/ui/filelist.blade.php', 'utf8');
const start = template.indexOf('    function sanitizeFileListEntry(');
const end = template.indexOf('    function createFallbackFileListEntry(', start);
const context = {};
vm.runInNewContext(template.slice(start, end), context);
const sanitize = context.sanitizeFileListEntry;

test('serialized workshop media without URLs receives view and download links', () => {
    const stored = { name: 'workshop notes.pdf', title: 'Workshop notes' };
    const file = sanitize(stored);
    assert.equal(file, stored);
    assert.equal(file.url, '/media/download/workshop%20notes.pdf');
    assert.equal(file.download_url, '/media/download/workshop%20notes.pdf?download=1');
    assert.equal(file.title, 'Workshop notes');
});
test('explicit media links are preserved, including restricted download endpoints', () => {
    const file = sanitize({ name: 'notes.pdf', url: '/secure/view?token=abc', download_url: '/secure/download?token=abc' });
    assert.equal(file.url, '/secure/view?token=abc');
    assert.equal(file.download_url, '/secure/download?token=abc');
});
test('download fallback preserves query parameters and fragments', () => {
    assert.equal(sanitize({ name: 'notes.pdf', url: '/media/notes.pdf?token=abc#page=2' }).download_url, '/media/notes.pdf?token=abc&download=1#page=2');
    assert.equal(sanitize({ name: 'notes.pdf', url: null, download_url: '' }).download_url, '/media/download/notes.pdf?download=1');
    assert.equal(sanitize({ title: 'Missing name' }), null);
});
