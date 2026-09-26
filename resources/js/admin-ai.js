const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const applyCsrfToken = (token) => {
    if (typeof token !== 'string' || token === '') return;
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.content = token;
    document.querySelectorAll('input[name="_token"]').forEach((field) => { field.value = token; });
    document.querySelectorAll('[data-ai-token]').forEach((element) => { element.dataset.aiToken = token; });
};

const valueFor = (value) => {
    if (Array.isArray(value)) {
        return value.map((item) => {
            if (typeof item === 'string') return `• ${item}`;
            if (item && typeof item === 'object') {
                const description = item.task || item.item || item.title || '';
                const timing = item.when ? `${item.when}: ` : '';
                const quantity = item.quantity ? ` — ${item.quantity}` : '';
                const reason = item.reason ? ` (${item.reason})` : '';
                return `• ${timing}${description}${quantity}${reason}`.trim();
            }
            return `• ${String(item)}`;
        }).join('\n');
    }

    if (value && typeof value === 'object') {
        return Object.entries(value).map(([key, item]) => `${key}: ${typeof item === 'string' ? item : JSON.stringify(item)}`).join('\n');
    }

    return String(value ?? '');
};

const toParagraphHtml = (value) => String(value || '')
    .split(/\n\s*\n/)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean)
    .map((paragraph) => `<p>${paragraph.split('\n').map((line) => line.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;')).join('<br>')}</p>`)
    .join('');

const toProductDescriptionHtml = (value) => {
    const escape = (text) => String(text || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    const output = [];
    let paragraph = [];
    let features = [];
    const flushParagraph = () => {
        if (paragraph.length) output.push(`<p>${paragraph.map(escape).join('<br>')}</p>`);
        paragraph = [];
    };
    const flushFeatures = () => {
        if (features.length) output.push(`<ul data-list-style="ticks">${features.map((feature) => `<li><p>${escape(feature)}</p></li>`).join('')}</ul>`);
        features = [];
    };

    String(value || '').replaceAll('\r', '').split('\n').forEach((line) => {
        const text = line.trim();
        const bullet = text.match(/^[-*]\s+(.+)$/);
        if (!text) {
            flushParagraph();
            flushFeatures();
        } else if (bullet) {
            flushParagraph();
            features.push(bullet[1]);
        } else {
            flushFeatures();
            paragraph.push(text);
        }
    });

    flushParagraph();
    flushFeatures();

    return output.join('');
};

const toWorkshopDescriptionHtml = (value) => {
    const escapeInline = (text) => String(text || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>');
    const lines = String(value || '').replaceAll('\r', '').split('\n');
    const output = [];
    let paragraph = [];
    let listType = null;
    let listItems = [];

    const flushParagraph = () => {
        if (paragraph.length) output.push(`<p>${paragraph.map(escapeInline).join('<br>')}</p>`);
        paragraph = [];
    };
    const flushList = () => {
        if (listType && listItems.length) {
            output.push(`<${listType}>${listItems.map((item) => `<li><p>${escapeInline(item)}</p></li>`).join('')}</${listType}>`);
        }
        listType = null;
        listItems = [];
    };

    lines.forEach((line) => {
        const trimmed = line.trim();
        const heading = trimmed.match(/^(#{1,3})\s*(.+)$/);
        const bullet = trimmed.match(/^[-*]\s+(.+)$/);
        const numbered = trimmed.match(/^\d+[.)]\s+(.+)$/);
        if (!trimmed) {
            flushParagraph();
            flushList();
            return;
        }
        if (heading) {
            flushParagraph();
            flushList();
            const level = Math.min(3, Math.max(2, heading[1].length));
            output.push(`<h${level}>${escapeInline(heading[2])}</h${level}>`);
            return;
        }
        if (bullet || numbered) {
            flushParagraph();
            const nextType = bullet ? 'ul' : 'ol';
            if (listType && listType !== nextType) flushList();
            listType = nextType;
            listItems.push((bullet || numbered)[1]);
            return;
        }
        flushList();
        paragraph.push(trimmed);
    });

    flushParagraph();
    flushList();
    return output.join('');
};

const positionAiToast = (root) => {
    const navbar = document.querySelector('[data-site-navbar]');
    const navbarBottom = navbar?.getBoundingClientRect().bottom ?? 0;
    root.style.top = `${Math.ceil(Math.max(16, navbarBottom + 12))}px`;
};

let aiToastPositionUpdatePending = false;
const scheduleAiToastPositionUpdate = () => {
    if (aiToastPositionUpdatePending) return;
    aiToastPositionUpdatePending = true;
    window.requestAnimationFrame(() => {
        aiToastPositionUpdatePending = false;
        document.querySelectorAll('[data-ai-auto-file][aria-hidden="false"], [data-ai-toast][aria-hidden="false"]').forEach(positionAiToast);
    });
};

window.addEventListener('scroll', scheduleAiToastPositionUpdate, { passive: true });
window.addEventListener('resize', scheduleAiToastPositionUpdate);

const setAiToastPopoverVisibility = (root, visible) => {
    if (!root.hasAttribute('popover') || typeof root.showPopover !== 'function') return;
    window.clearTimeout(root._adminAiPopoverTimer);

    if (visible) {
        try {
            if (!root.matches(':popover-open')) {
                positionAiToast(root);
                root.showPopover();
                root.getBoundingClientRect();
            }
        } catch {
            // Keep the inline toast available if the browser cannot promote the popover.
        }
        return;
    }

    root._adminAiPopoverTimer = window.setTimeout(() => {
        if (root.matches(':popover-open')) root.hidePopover();
        root._adminAiPopoverTimer = null;
    }, 320);
};

const randomiseAiStarPosition = (star) => {
    star.style.left = `${Math.round(4 + Math.random() * 92)}%`;
    star.style.top = `${Math.round(10 + Math.random() * 76)}%`;
};

const initialiseAiStars = () => {
    document.querySelectorAll('[data-ai-star]').forEach((star) => {
        if (star.dataset.aiPositionInitialised === 'true') return;
        star.dataset.aiPositionInitialised = 'true';
        randomiseAiStarPosition(star);
        star.addEventListener('animationiteration', () => {
            if (star.closest('[data-ai-widget]')?.classList.contains('is-processing')) {
                randomiseAiStarPosition(star);
            }
        });
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseAiStars, { once: true });
} else {
    initialiseAiStars();
}

const markAiDefaultAsUserModified = (event) => {
    const field = event.target;
    if (!(field instanceof HTMLInputElement) || !field.hasAttribute('data-ai-replace-default') || field._smAiUpdate) return;
    field.dataset.aiUserModified = 'true';
};

document.addEventListener('input', markAiDefaultAsUserModified, true);
document.addEventListener('change', markAiDefaultAsUserModified, true);

const setStatus = (root, message, isError = false, isProcessing = false) => {
    const status = root?.querySelector('[data-ai-status]');
    if (!status) return;
    const isAutomaticWidget = Boolean(root.dataset.aiAutoFile);
    const isToastWidget = isAutomaticWidget || root.hasAttribute('data-ai-toast');
    const statusText = status.querySelector('[data-ai-status-text]');
    const statusDetail = status.querySelector('[data-ai-status-detail]');
    if (statusText) statusText.textContent = message;
    else status.textContent = message;
    if (statusDetail) statusDetail.hidden = !isProcessing;
    status.classList.toggle('text-red-700', isError);
    status.classList.toggle('text-sky-700', isProcessing);
    status.classList.toggle('text-emerald-700', !isError && !isProcessing);

    if (isToastWidget) {
        status.hidden = false;
        const visible = message !== '';
        const wasHidden = root.getAttribute('aria-hidden') !== 'false';
        if (visible && root.hasAttribute('popover')) setAiToastPopoverVisibility(root, true);
        else if (visible && wasHidden) positionAiToast(root);
        root.classList.toggle('-translate-y-full', !visible);
        root.classList.toggle('translate-y-0', visible);
        root.classList.toggle('opacity-0', !visible);
        root.classList.toggle('opacity-100', visible);
        root.classList.toggle('is-processing', isProcessing);
        root.classList.toggle('is-complete', visible && !isProcessing && !isError);
        root.classList.toggle('is-error', visible && isError);
        root.setAttribute('aria-hidden', String(!visible));
        if (!visible && root.hasAttribute('popover')) setAiToastPopoverVisibility(root, false);

        window.clearTimeout(root._adminAiDismissTimer);
        root._adminAiDismissTimer = message && !isProcessing
            ? window.setTimeout(() => setStatus(root, ''), 8000)
            : null;
    } else {
        status.hidden = message === '';
    }
};

const readFormField = (form, name) => {
    const field = form?.elements?.namedItem(name);
    if (!field) return '';
    if (typeof RadioNodeList !== 'undefined' && field instanceof RadioNodeList) return field.value;
    return field.value ?? '';
};

const fillField = (form, name, value, button, onlyIfBlank = false) => {
    if (typeof value !== 'string' || value.trim() === '') return false;
    if (onlyIfBlank && String(readFormField(form, name)).trim() !== '') {
        const field = form?.elements?.namedItem(name);
        const untouchedDefaultDate = name === 'paid_on'
            && field instanceof HTMLInputElement
            && field.hasAttribute('data-ai-replace-default')
            && value.trim() !== ''
            && field.dataset.aiUserModified !== 'true'
            && field.value === field.dataset.aiDefaultValue;
        if (!untouchedDefaultDate) return false;
    }

    if (name === button.dataset.aiEditorField) {
        window.dispatchEvent(new CustomEvent('sm-editor-set-content', {
            detail: {
                name,
                html: button.dataset.aiEditorFormat === 'product-description' ? toProductDescriptionHtml(value) : toParagraphHtml(value),
                focusEnd: false,
            },
        }));
        return true;
    }

    const field = form?.elements?.namedItem(name);
    if (!field || (typeof RadioNodeList !== 'undefined' && field instanceof RadioNodeList)) return false;
    field.value = ['total_amount', 'gst_amount'].includes(name) ? value.replace(/[^0-9.-]/g, '') : value;
    field._smAiUpdate = true;
    try {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    } finally {
        field._smAiUpdate = false;
    }
    return true;
};

const populateResults = (root, result) => {
    root._adminAiResult = result;
    root.querySelectorAll('[data-ai-result]').forEach((field) => {
        const key = field.dataset.aiResult;
        if (!Object.prototype.hasOwnProperty.call(result, key)) return;
        const value = valueFor(result[key]);
        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
        } else {
            field.textContent = value;
        }
    });

    const panel = root.querySelector('[data-ai-results]');
    if (panel) panel.hidden = false;
};

const applyNewsletterAiAction = (root, trigger, result) => {
    if (trigger.dataset.aiAction === 'newsletter-header') {
        const fields = [['subject', 'newsletter-subject'], ['hero_header', 'newsletter-hero_header'], ['hero_cta', 'newsletter-hero_cta']];
        if (fields.some(([key]) => typeof result[key] !== 'string' || result[key].trim() === '')) {
            throw new Error('The AI returned an incomplete newsletter header. Please try again.');
        }
        fields.forEach(([key, id]) => {
            const field = document.getElementById(id);
            if (!field) return;
            field.value = result[key];
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
        setStatus(root, 'Header replaced. Review it before saving.');
        return true;
    }

    if (trigger.dataset.aiAction === 'newsletter-message') {
        if (typeof result.message !== 'string' || result.message.trim() === '') {
            throw new Error('The AI returned an empty newsletter message. Please try again.');
        }
        window.dispatchEvent(new CustomEvent('sm-newsletter-ai-draft', {
            detail: { html: toParagraphHtml(result.message), mode: trigger.dataset.aiMode || 'replace' },
        }));
        setStatus(root, trigger.dataset.aiMode === 'append' ? 'New paragraphs added. Review before saving.' : 'Message replaced. Review before saving.');
        return true;
    }

    return false;
};

const applyProductAiAction = (root, trigger, result, form) => {
    if (trigger.dataset.aiAction === 'product-warning') {
        if (typeof result.warning !== 'string') {
            throw new Error('The AI returned an unreadable product warning. Please try again.');
        }
        if (result.warning.trim() === '') {
            setStatus(root, 'No additional warning was supported. Existing text was left unchanged.');
            return true;
        }

        const field = form?.elements?.namedItem('caution_message');
        if (!field || typeof field.value !== 'string') {
            throw new Error('The Product Warning field could not be found. Please reload and try again.');
        }
        field.value = result.warning.trim();
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        setStatus(root, 'Warning drafted. Review it before saving.');
        return true;
    }

    if (trigger.dataset.aiAction === 'product-specifications') {
        if (!Array.isArray(result.product_details) || result.product_details.some((detail) => !detail || typeof detail.key !== 'string' || typeof detail.value !== 'string')) {
            throw new Error('The AI returned unreadable product specifications. Please try again.');
        }

        const specifications = result.product_details.filter((detail) => String(detail.key || '').trim().toLowerCase() !== 'sku');
        const existingSku = result.product_details.find((detail) => String(detail.key || '').trim().toLowerCase() === 'sku');
        const skuRow = {
            key: 'SKU',
            value: String(existingSku?.value || '').trim() || '{sku}',
        };

        window.dispatchEvent(new CustomEvent('sm-product-specifications-ai', {
            detail: { details: [...specifications, skuRow] },
        }));
        setStatus(root, specifications.length > 0
            ? 'Specifications drafted. Review them before saving.'
            : 'No new specifications were found. SKU is kept as the final detail.');
        return true;
    }

    return false;
};

const makePayload = async (button, form) => {
    const payload = new FormData();
    const token = csrfToken() || button.dataset.aiToken || readFormField(form, '_token');
    if (token) payload.append('_token', token);

    const fileSelector = button.dataset.aiFile;
    if (fileSelector) {
        const input = document.querySelector(fileSelector);
        const file = input instanceof HTMLInputElement ? input.files?.[0] : null;
        if (!file) throw new Error('Choose a PDF first.');
        let fileBytes;
        try {
            fileBytes = await file.arrayBuffer();
        } catch {
            throw new Error('The receipt could not be read. Please attach it again.');
        }
        const materializedName = /^cid:/i.test(file.name || '')
            ? file.name.replace(/^cid:/i, '').replace(/[<>]/g, '') || 'receipt.pdf'
            : file.name;
        const materializedFile = new File([fileBytes], materializedName, {
            type: file.type || 'application/pdf',
            lastModified: file.lastModified || Date.now(),
        });
        payload.append(button.dataset.aiFileName || 'receipt_pdf', materializedFile, materializedName);
    }

    const scope = button.dataset.aiScope ? document.querySelector(button.dataset.aiScope) : form;
    const fields = (button.dataset.aiFields || '').split(',').map((field) => field.trim()).filter(Boolean);
    fields.forEach((name) => payload.append(name, readFormField(scope, name)));
    if (button.dataset.aiMode) payload.append('mode', button.dataset.aiMode);
    if (button.dataset.aiKind) payload.append('kind', button.dataset.aiKind);
    if (button.hasAttribute?.('data-ai-context') || button.dataset.aiContext) {
        payload.append('context', button.dataset.aiContext || '{}');
    }

    return payload;
};

const responseFailureMessage = (response, body) => {
    const firstError = body.errors ? Object.values(body.errors).flat()[0] : null;
    if (typeof firstError === 'string' && firstError.trim() !== '') {
        const sizeLimit = firstError.match(/must not be greater than\s+([\d,.]+)\s+kilobytes/i);
        if (sizeLimit) {
            const limitMb = Math.round(Number(sizeLimit[1].replaceAll(',', '')) / 1024);
            return `PDF is too large. The processing limit is ${limitMb} MB.`;
        }
        return firstError;
    }

    if (response.status === 413) return 'PDF is too large to upload. Try a smaller file.';
    if (response.status === 419) return 'Your session expired. Reload the page and try again.';
    if (response.status === 429) return 'AI is busy. Wait a moment, then try again.';
    if (typeof body.message === 'string' && body.message.trim() !== '') return body.message;
    return `PDF processing failed (HTTP ${response.status}). Try again.`;
};

const refreshCsrfToken = async (url, signal) => {
    const response = await fetch(url, {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
        signal,
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok || typeof body.token !== 'string' || body.token === '') {
        throw new Error(response.redirected || response.status === 401
            ? 'Your session has expired. Reload the page and try again.'
            : 'Could not refresh your session. Reload the page and try again.');
    }

    applyCsrfToken(body.token);
    return body.token;
};

const readAiEventStream = async (response, onProgress) => {
    if (!response.body) throw new Error('The AI progress stream could not be read. Please try again.');

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';
    let result = null;
    const consumeBlock = (block) => {
        let eventName = 'message';
        const dataLines = [];
        block.split(/\r?\n/).forEach((line) => {
            if (line.startsWith('event:')) eventName = line.slice(6).trim();
            if (line.startsWith('data:')) dataLines.push(line.slice(5).trimStart());
        });
        if (!dataLines.length) return;

        let data;
        try {
            data = JSON.parse(dataLines.join('\n'));
        } catch {
            throw new Error('The AI returned an unreadable progress update. Please try again.');
        }

        if (eventName === 'progress' && typeof data.message === 'string') onProgress(data.message);
        if (eventName === 'error') throw new Error(data.message || 'The AI service could not complete this request.');
        if (eventName === 'result') result = data.result || null;
    };

    while (true) {
        const { value, done } = await reader.read();
        buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
        let boundary;
        while ((boundary = buffer.search(/\r?\n\r?\n/)) !== -1) {
            const delimiter = buffer.match(/\r?\n\r?\n/);
            const block = buffer.slice(0, boundary);
            buffer = buffer.slice(boundary + delimiter[0].length);
            consumeBlock(block);
        }
        if (done) break;
    }

    if (buffer.trim()) consumeBlock(buffer);
    if (!result || typeof result !== 'object') throw new Error('The AI stream ended before the receipt was processed. Please try again.');

    return result;
};

const clearResults = (root) => {
    root._adminAiResult = null;
    const panel = root.querySelector('[data-ai-results]');
    if (panel) panel.hidden = true;
};

const lockAiFields = (trigger, form) => {
    const names = (trigger.dataset.aiLockFields || '').split(',').map((name) => name.trim()).filter(Boolean);
    const originals = names.map((name) => form?.elements?.namedItem(name)).filter((field) => field && typeof field.readOnly === 'boolean').map((field) => {
        const original = {
            field,
            readOnly: field.readOnly,
            ariaBusy: field.getAttribute('aria-busy'),
            hadAnimationClass: field.classList.contains('sm-ai-field-processing'),
        };
        field.readOnly = true;
        field.setAttribute('aria-busy', 'true');
        field.classList.add('sm-ai-field-processing');
        return original;
    });
    const editableContent = trigger.dataset.aiLockContent
        ? [...document.querySelectorAll(trigger.dataset.aiLockContent)].map((element) => {
            const original = {
                element,
                contentEditable: element.getAttribute('contenteditable'),
                ariaBusy: element.getAttribute('aria-busy'),
                hadAnimationClass: element.classList.contains('sm-ai-field-processing'),
            };
            element.setAttribute('contenteditable', 'false');
            element.setAttribute('aria-busy', 'true');
            element.classList.add('sm-ai-field-processing');
            return original;
        })
        : [];
    const controls = trigger.dataset.aiLockControls
        ? [...document.querySelectorAll(trigger.dataset.aiLockControls)].filter((control) => control !== trigger).map((control) => {
            const original = {
                control,
                disabled: control.disabled,
                ariaBusy: control.getAttribute('aria-busy'),
            };
            control.disabled = true;
            control.setAttribute('aria-busy', 'true');
            return original;
        })
        : [];

    return () => {
        originals.forEach(({ field, readOnly, ariaBusy, hadAnimationClass }) => {
            field.readOnly = readOnly;
            if (ariaBusy === null) field.removeAttribute('aria-busy');
            else field.setAttribute('aria-busy', ariaBusy);
            field.classList.toggle('sm-ai-field-processing', hadAnimationClass);
        });
        editableContent.forEach(({ element, contentEditable, ariaBusy, hadAnimationClass }) => {
            if (contentEditable === null) element.removeAttribute('contenteditable');
            else element.setAttribute('contenteditable', contentEditable);
            if (ariaBusy === null) element.removeAttribute('aria-busy');
            else element.setAttribute('aria-busy', ariaBusy);
            element.classList.toggle('sm-ai-field-processing', hadAnimationClass);
        });
        controls.forEach(({ control, disabled, ariaBusy }) => {
            control.disabled = disabled;
            if (ariaBusy === null) control.removeAttribute('aria-busy');
            else control.setAttribute('aria-busy', ariaBusy);
        });
    };
};

const requestDraft = async (trigger, { automatic = false } = {}) => {
    const targetWidget = trigger.dataset.aiWidgetTarget ? document.querySelector(trigger.dataset.aiWidgetTarget) : null;
    const root = targetWidget || (trigger.matches('[data-ai-widget]') ? trigger : trigger.closest('[data-ai-widget]'));
    if (!root) return;

    root._adminAiAbortController?.abort();
    root._adminAiUnlockFields?.();
    root._adminAiUnlockFields = null;
    const controller = new AbortController();
    root._adminAiAbortController = controller;
    root._adminAiProcessing = true;
    root.setAttribute('aria-busy', 'true');

    if (automatic) clearResults(root);

    const form = trigger.dataset.aiScope ? document.querySelector(trigger.dataset.aiScope) : trigger.closest('form');
    root._adminAiUnlockFields = lockAiFields(trigger, form);
    const isButton = trigger instanceof HTMLButtonElement;
    const originalDisabled = isButton ? trigger.disabled : false;
    if (!automatic && isButton) {
        trigger.disabled = true;
        trigger.setAttribute('aria-busy', 'true');
        trigger.classList.add('opacity-70', 'cursor-wait');
    }
    const streamProgress = automatic && trigger.dataset.aiStream === 'true';
    const requestHeaders = {
        Accept: streamProgress ? 'text/event-stream, application/json' : 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const initialToken = csrfToken() || trigger.dataset.aiToken || readFormField(form, '_token');
    if (initialToken) requestHeaders['X-CSRF-TOKEN'] = initialToken;
    const initialStatus = automatic ? 'Reading receipt PDF…' : (trigger.dataset.aiProcessingMessage || 'Creating a draft…');
    setStatus(root, initialStatus, false, automatic || root.hasAttribute('data-ai-toast'));

    try {
        let payload = await makePayload(trigger, form);
        let response = await fetch(trigger.dataset.aiUrl, {
            method: 'POST',
            headers: requestHeaders,
            body: payload,
            credentials: 'same-origin',
            signal: controller.signal,
        });
        if (response.status === 419 && trigger.dataset.aiCsrfUrl) {
            setStatus(root, automatic ? 'Refreshing your session…' : 'Refreshing session…', false, automatic || root.hasAttribute('data-ai-toast'));
            const refreshedToken = await refreshCsrfToken(trigger.dataset.aiCsrfUrl, controller.signal);
            payload.set('_token', refreshedToken);
            requestHeaders['X-CSRF-TOKEN'] = refreshedToken;
            response = await fetch(trigger.dataset.aiUrl, {
                method: 'POST',
                headers: requestHeaders,
                body: payload,
                credentials: 'same-origin',
                signal: controller.signal,
            });
        }
        if (!response.ok) {
            const body = await response.json().catch(() => ({}));
            throw new Error(responseFailureMessage(response, body));
        }

        if (controller.signal.aborted) return;

        const result = streamProgress
            ? await readAiEventStream(response, (message) => setStatus(root, message, false, true))
            : await response.json().then((body) => body.result || body);
        if (controller.signal.aborted) return;
        populateResults(root, result);
        if (applyNewsletterAiAction(root, trigger, result)) return;
        if (applyProductAiAction(root, trigger, result, form)) return;

        const resultKey = trigger.dataset.aiResultKey || 'content';
        const resultContent = result?.[resultKey];
        if (trigger.dataset.aiKind === 'blueprint_description_amend' && typeof resultContent === 'string' && resultContent.trim() === 'NO_CHANGES') {
            setStatus(root, 'No missing supported learning outcomes found. Your description was kept unchanged.');
            return;
        }
        if (trigger.dataset.aiKind && (trigger.dataset.aiResultEvent || trigger.dataset.aiEditorField || trigger.dataset.aiFillTarget)) {
            if (typeof resultContent !== 'string' || resultContent.trim() === '') {
                throw new Error('The AI returned an empty workshop draft. Please try again.');
            }
        }

        if (trigger.dataset.aiKind && trigger.dataset.aiResultEvent) {
            let context = {};
            try {
                context = trigger.dataset.aiContext ? JSON.parse(trigger.dataset.aiContext) : {};
            } catch {
                throw new Error('The workshop task details could not be read. Please reload and try again.');
            }
            const htmlByKey = Object.fromEntries(Object.entries(result)
                .filter(([, value]) => typeof value === 'string')
                .map(([key, value]) => [key, toParagraphHtml(value)]));
            window.dispatchEvent(new CustomEvent(trigger.dataset.aiResultEvent, {
                detail: { content: resultContent, html: toParagraphHtml(resultContent), context, result, htmlByKey },
            }));
            setStatus(root, trigger.dataset.aiCompleteMessage || 'Draft ready. Review and edit it before saving.');
            return;
        }

        if (trigger.dataset.aiKind && trigger.dataset.aiEditorField) {
            const append = trigger.dataset.aiKind === 'blueprint_description_amend';
            window.dispatchEvent(new CustomEvent('sm-editor-set-content', {
                detail: {
                    name: trigger.dataset.aiEditorField,
                    html: trigger.dataset.aiEditorFormat === 'workshop-description' ? toWorkshopDescriptionHtml(resultContent) : toParagraphHtml(resultContent),
                    append,
                    focusEnd: false,
                },
            }));
            setStatus(root, append ? 'Missing learning outcomes added. Your existing description was kept.' : 'Draft ready. Review and edit it before saving.');
            return;
        }

        if (trigger.dataset.aiKind && trigger.dataset.aiFillTarget) {
            const fillScope = trigger.dataset.aiFillScope ? document.querySelector(trigger.dataset.aiFillScope) : form;
            if (!fillField(fillScope, trigger.dataset.aiFillTarget, resultContent, trigger)) {
                throw new Error('The AI summary could not be added to the field. Please try again.');
            }
            setStatus(root, 'Summary ready. Review and edit it before saving.');
            return;
        }

        if (automatic) setStatus(root, 'Filling blank fields…', false, true);

        const fillScope = trigger.dataset.aiFillScope ? document.querySelector(trigger.dataset.aiFillScope) : form;
        const fields = (trigger.dataset.aiFillFields || '').split(',').map((field) => field.trim()).filter(Boolean);
        const fillOnlyBlanks = automatic || trigger.dataset.aiFillBlanks === 'true';
        const filledCount = fields.reduce((count, name) => count + Number(fillField(fillScope, name, result[name], trigger, fillOnlyBlanks)), 0);

        if (automatic) {
            const documentType = typeof result.document_type === 'string' && result.document_type.trim() !== ''
                ? result.document_type.trim().slice(0, 48)
                : 'Receipt processed';
            const pages = [...new Set(Object.values(result.evidence || {})
                .map((evidence) => Number(evidence?.page))
                .filter((page) => Number.isInteger(page) && page > 0))];
            const pageSummary = pages.length ? `${pages.length} ${pages.length === 1 ? 'page' : 'pages'}` : 'receipt read';
            const fieldSummary = filledCount > 0
                ? `${filledCount} ${filledCount === 1 ? 'field' : 'fields'} filled`
                : 'no blank fields to fill';
            const reviewCount = Array.isArray(result.needs_review) ? result.needs_review.length : 0;
            const reviewSummary = reviewCount > 0 ? ` · ${reviewCount} to review` : '';
            setStatus(root, `${documentType} · ${pageSummary} · ${fieldSummary}${reviewSummary}`);
        } else {
            setStatus(root, 'Draft ready. Review and edit it before saving or sending.');
        }
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') return;
        const errorMessage = error instanceof Error ? error.message : 'The draft could not be created.';
        const networkFailure = /failed to fetch|network error|load failed/i.test(errorMessage);
        setStatus(root, automatic && networkFailure
            ? 'Could not reach the AI service. Check your connection and try again.'
            : errorMessage, true);
    } finally {
        if (root._adminAiAbortController === controller) {
            root._adminAiAbortController = null;
            root._adminAiProcessing = false;
            root.removeAttribute('aria-busy');
            root._adminAiUnlockFields?.();
            root._adminAiUnlockFields = null;
            if (isButton) {
                trigger.disabled = originalDisabled;
                trigger.removeAttribute('aria-busy');
                trigger.classList.remove('opacity-70', 'cursor-wait');
            }
        }
    }
};

document.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;

    const widget = [...document.querySelectorAll('[data-ai-auto-file]')].find((candidate) => {
        return candidate.dataset.aiAutoFile && document.querySelector(candidate.dataset.aiAutoFile) === input;
    });
    if (!widget) return;

    const file = input.files?.[0];
    if (!file) {
        widget._adminAiAbortController?.abort();
        clearResults(widget);
        setStatus(widget, '');
        return;
    }

    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    if (!isPdf) {
        widget._adminAiAbortController?.abort();
        clearResults(widget);
        setStatus(widget, 'PDF only.', true);
        return;
    }

    if (widget.dataset.aiConfigured !== 'true') {
        widget._adminAiAbortController?.abort();
        clearResults(widget);
        setStatus(widget, 'AI unavailable.', true);
        return;
    }

    requestDraft(widget, { automatic: true });
});

document.addEventListener('click', async (event) => {
    const trigger = event.target instanceof Element ? event.target.closest('[data-admin-ai]') : null;
    if (!trigger || trigger.disabled) return;
    requestDraft(trigger);
});
