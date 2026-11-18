let SM = {
    // Reconnect page controls after a list replacement; abort old global listeners.
    onDynamicList: (name, initialise) => {
        let lifecycle;
        const run = () => {
            lifecycle?.abort();
            lifecycle = new AbortController();
            initialise(lifecycle.signal);
        };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, { once: true });
        else run();
        document.addEventListener('sm:list-updated', (event) => {
            if (event.detail.root.dataset.dynamicList === name) run();
        });
    },
    redirectIfSafe: (target) => {
        if (typeof target !== 'string' || target === '') {
            window.location.assign('/');
            return;
        }

        let url;
        try {
            url = new URL(target, window.location.origin);
        } catch (error) {
            window.location.assign('/');
            return;
        }

        if (url.origin !== window.location.origin) {
            window.location.assign('/');
            return;
        }

        window.location.assign(url.href);
    },

    setFormProcessing: (form, isProcessing, options = {}) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const submitLabel = typeof options.submitLabel === 'string' && options.submitLabel.trim() !== ''
            ? options.submitLabel.trim()
            : 'Processing...';
        const controls = form.querySelectorAll('input:not([type="hidden"]), select, textarea, button');

        const canUseReadonly = (control, typeAttr) => {
            if (control.tagName === 'TEXTAREA') {
                return true;
            }
            if (control.tagName !== 'INPUT') {
                return false;
            }

            return !['checkbox', 'radio', 'file', 'submit', 'button', 'reset', 'image', 'range', 'color'].includes(typeAttr);
        };

        controls.forEach((control) => {
            const typeAttr = (control.getAttribute('type') || '').toLowerCase();
            const isSubmitControl = (control.tagName === 'BUTTON' && (typeAttr === '' || typeAttr === 'submit'))
                || (control.tagName === 'INPUT' && typeAttr === 'submit');

            if (isProcessing) {
                if (isSubmitControl) {
                    if (!control.disabled) {
                        control.disabled = true;
                        control.dataset.smFormTemporarilyDisabled = '1';
                    }

                    control.setAttribute('aria-busy', 'true');

                    if (control.dataset.smFormTemporarilyDisabled === '1') {
                        if (control.tagName === 'BUTTON' && !control.dataset.smFormOriginalHtml) {
                            control.dataset.smFormOriginalHtml = control.innerHTML;
                            control.innerHTML = `<span class="altcha-processing-content"><span class="altcha-inline-spinner" aria-hidden="true"></span><span>${submitLabel}</span></span>`;
                        } else if (control.tagName === 'INPUT' && !control.dataset.smFormOriginalValue) {
                            control.dataset.smFormOriginalValue = control.value;
                            control.value = submitLabel;
                        }
                    }
                } else if (control.dataset.smFormSoftDisabled !== '1') {
                    control.dataset.smFormSoftDisabled = '1';
                    control.classList.add('sm-form-processing-control');
                    control.setAttribute('aria-disabled', 'true');

                    if (control === document.activeElement && typeof control.blur === 'function') {
                        control.blur();
                    }

                    const hasTabindex = control.hasAttribute('tabindex');
                    control.dataset.smFormOriginalTabindex = hasTabindex ? control.getAttribute('tabindex') || '' : '__none__';
                    control.setAttribute('tabindex', '-1');

                    if (canUseReadonly(control, typeAttr) && !control.readOnly) {
                        control.readOnly = true;
                        control.dataset.smFormTemporarilyReadonly = '1';
                    }
                }

                return;
            }

            if (control.dataset.smFormTemporarilyDisabled === '1') {
                control.disabled = false;
                delete control.dataset.smFormTemporarilyDisabled;
            }

            if (isSubmitControl) {
                control.removeAttribute('aria-busy');

                if (control.tagName === 'BUTTON' && control.dataset.smFormOriginalHtml) {
                    control.innerHTML = control.dataset.smFormOriginalHtml;
                    delete control.dataset.smFormOriginalHtml;
                }

                if (control.tagName === 'INPUT' && control.dataset.smFormOriginalValue) {
                    control.value = control.dataset.smFormOriginalValue;
                    delete control.dataset.smFormOriginalValue;
                }
            } else if (control.dataset.smFormSoftDisabled === '1') {
                delete control.dataset.smFormSoftDisabled;
                control.classList.remove('sm-form-processing-control');
                control.removeAttribute('aria-disabled');

                const originalTabindex = control.dataset.smFormOriginalTabindex;
                if (typeof originalTabindex === 'undefined' || originalTabindex === '__none__') {
                    control.removeAttribute('tabindex');
                } else {
                    control.setAttribute('tabindex', originalTabindex);
                }
                delete control.dataset.smFormOriginalTabindex;

                if (control.dataset.smFormTemporarilyReadonly === '1') {
                    control.readOnly = false;
                    delete control.dataset.smFormTemporarilyReadonly;
                }
            }
        });
    },

    bindFormProcessingOnSubmit: (formOrSelector, options = {}) => {
        const forms = typeof formOrSelector === 'string'
            ? Array.from(document.querySelectorAll(formOrSelector))
            : (formOrSelector instanceof HTMLFormElement ? [formOrSelector] : []);

        forms.forEach((form) => {
            if (!(form instanceof HTMLFormElement) || form.dataset.smFormProcessingBound === '1') {
                return;
            }

            form.dataset.smFormProcessingBound = '1';
            form.addEventListener('submit', () => {
                SM.setFormProcessing(form, true, options);
            });
        });
    },

    bindSingleSubmit: (formOrSelector, options = {}) => {
        const forms = typeof formOrSelector === 'string'
            ? Array.from(document.querySelectorAll(formOrSelector))
            : (formOrSelector instanceof HTMLFormElement ? [formOrSelector] : []);

        forms.forEach((form) => {
            if (!(form instanceof HTMLFormElement) || form.dataset.smSingleSubmitBound === '1') {
                return;
            }

            form.dataset.smSingleSubmitBound = '1';
            form.addEventListener('submit', (event) => {
                if (form.dataset.smSingleSubmitAllowNext === '1') {
                    delete form.dataset.smSingleSubmitAllowNext;
                    return;
                }

                if (form.dataset.smSingleSubmitLocked === '1') {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    return;
                }

                form.dataset.smSingleSubmitLocked = '1';
            }, true);
        });
    },

    clearTimer: (timerId) => {
        if (timerId !== null && typeof timerId !== 'undefined') {
            window.clearTimeout(timerId);
        }

        return null;
    },

    scheduleDebounce: (timerId, callback, delayMs = 300) => {
        SM.clearTimer(timerId);

        return window.setTimeout(() => {
            callback();
        }, Math.max(0, Number.parseInt(String(delayMs), 10) || 0));
    },

    clearInterval: (timerId) => {
        if (timerId !== null && typeof timerId !== 'undefined') {
            window.clearInterval(timerId);
        }

        return null;
    },

    replaceHtmlPreservingState: (container, nextHtml) => {
        if (!(container instanceof HTMLElement)) {
            return false;
        }

        const html = typeof nextHtml === 'string' ? nextHtml : '';
        if (container.innerHTML.trim() === html.trim()) {
            return false;
        }

        const activeElement = document.activeElement;
        const isEditingWithinContainer = activeElement instanceof HTMLElement
            && container.contains(activeElement)
            && (
                ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeElement.tagName)
                || activeElement.isContentEditable
            );

        if (isEditingWithinContainer) {
            return false;
        }

        const openDetailKeys = Array.from(container.querySelectorAll('details[data-refresh-key][open]'))
            .map((detail) => detail.dataset.refreshKey || '')
            .filter((key) => key !== '');

        container.innerHTML = html;

        if (openDetailKeys.length === 0) {
            return true;
        }

        const openKeySet = new Set(openDetailKeys);
        container.querySelectorAll('details[data-refresh-key]').forEach((detail) => {
            const key = detail.dataset.refreshKey || '';
            if (openKeySet.has(key)) {
                detail.open = true;
            }
        });

        return true;
    },

    toBoundedInt: (value, options = {}) => {
        const min = Number.parseInt(String(options.min ?? Number.MIN_SAFE_INTEGER), 10);
        const max = Number.parseInt(String(options.max ?? Number.MAX_SAFE_INTEGER), 10);
        const allowNull = options.allowNull !== false;
        const parsed = Number.parseInt(String(value ?? '').trim(), 10);

        if (!Number.isFinite(parsed)) {
            return allowNull ? null : min;
        }

        if (parsed < min) {
            return allowNull ? null : min;
        }

        return Math.min(parsed, max);
    },

    escapeHtml: (value) => {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    pluralize: (word, count) => {
        const safeWord = String(word ?? '').trim();
        if (safeWord === '') {
            return '';
        }

        if (Number.parseInt(String(count ?? 0), 10) === 1) {
            return safeWord;
        }

        const trailingParentheticalMatch = safeWord.match(/^(.*?)(\s*\([^)]*\))$/);
        if (trailingParentheticalMatch) {
            const baseWord = String(trailingParentheticalMatch[1] ?? '').trim();
            const suffix = String(trailingParentheticalMatch[2] ?? '');

            if (baseWord !== '') {
                return `${SM.pluralize(baseWord, count)}${suffix}`;
            }
        }

        const words = safeWord.split(/\s+/);
        const lastWord = words[words.length - 1] || '';
        const lowerLastWord = lastWord.toLowerCase();
        const alreadyPlural = /(ies|ves|ches|shes|sses|xes|zes)$/.test(lowerLastWord)
            || (lowerLastWord.endsWith('s') && !/(ss|us|is)$/.test(lowerLastWord));

        if (alreadyPlural) {
            return safeWord;
        }

        if (/(s|x|z|ch|sh)$/i.test(lastWord)) {
            words[words.length - 1] = `${lastWord}es`;
            return words.join(' ');
        }

        if (/[^aeiou]y$/i.test(lastWord)) {
            words[words.length - 1] = `${lastWord.slice(0, -1)}ies`;
            return words.join(' ');
        }

        words[words.length - 1] = `${lastWord}s`;
        return words.join(' ');
    },

    autosaveJson: async (url, csrfToken, payload, options = {}) => {
        const response = await fetch(url, {
            method: options.method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
            credentials: options.credentials || 'same-origin',
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            throw new Error(`Autosave failed with status ${response.status}`);
        }

        return response.json();
    },

    relativeTimeFromIso: (isoString) => {
        if (!isoString) {
            return '';
        }

        const saved = new Date(isoString);
        if (Number.isNaN(saved.getTime())) {
            return '';
        }

        const diffSeconds = Math.max(0, Math.floor((Date.now() - saved.getTime()) / 1000));
        if (diffSeconds < 10) {
            return 'just now';
        }
        if (diffSeconds < 60) {
            return `${diffSeconds} seconds ago`;
        }

        const diffMinutes = Math.floor(diffSeconds / 60);
        if (diffMinutes < 60) {
            return `${diffMinutes} minute${diffMinutes === 1 ? '' : 's'} ago`;
        }

        const diffHours = Math.floor(diffMinutes / 60);
        if (diffHours < 24) {
            return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
        }

        const diffDays = Math.floor(diffHours / 24);
        return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
    },

    startRelativeTimeTicker: (onTick, intervalMs = 30000) => {
        if (typeof onTick !== 'function') {
            return null;
        }

        onTick();
        return window.setInterval(() => {
            onTick();
        }, Math.max(1000, Number.parseInt(String(intervalMs), 10) || 30000));
    },

    feedbackIcon: (type) => {
        const mark = type === 'success' ? '<path d="m8 12 3 3 5-6"/>'
            : ['danger', 'error', 'warning'].includes(type) ? '<path d="M12 7v6m0 4h.01"/>'
            : '<path d="m12 5 2 5 5 2-5 2-2 5-2-5-5-2 5-2Z"/>';
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${mark}</svg>`;
    },

    decorateFeedback: (element) => {
        if (!element?.style) return;
        for (const dot of ['a', 'b']) {
            element.style.setProperty(`--feedback-${dot}-duration`, `${3 + Math.random() * 3}s`);
            element.style.setProperty(`--feedback-${dot}-delay`, `${-Math.random() * 6}s`);
            element.style.setProperty(`--feedback-${dot}-x`, `${Math.round(Math.random() * 32 - 16)}px`);
            element.style.setProperty(`--feedback-${dot}-y`, `${Math.round(Math.random() * 20 - 10)}px`);
        }
    },

    feedback: (region, title, message, type = 'success') => {
        if (!region) return SM.alert(title, message, type);
        region.dataset.tone = ['success', 'info', 'warning', 'danger', 'error'].includes(type) ? type : 'info';
        region.querySelector('[data-feedback-title]').textContent = title || '';
        region.querySelector('[data-feedback-message]').textContent = message || '';
        region.querySelector('[data-feedback-icon]').innerHTML = SM.feedbackIcon(type);
        region.querySelector('[data-feedback-dismiss]').onclick = () => { region.hidden = true; };
        SM.decorateFeedback(region);
        region.hidden = false;
    },

    feedbackCountdown: (popup, duration) => {
        if (!(duration > 0) || typeof Swal.getTimerLeft !== 'function') return () => {};
        popup.classList.add('sm-feedback-timed');
        popup.style.setProperty('--feedback-remaining', '100%');
        let frame, disposed = false;
        const update = () => {
            if (disposed) return;
            const remaining = Swal.getTimerLeft();
            // SweetAlert can initialise its timer after the open callback.
            if (typeof remaining === 'number') popup.style.setProperty('--feedback-remaining', `${Math.max(0, Math.min(1, remaining / duration)) * 100}%`);
            frame = window.requestAnimationFrame(update);
        };
        update();
        return () => {
            disposed = true;
            window.cancelAnimationFrame(frame);
            popup.classList.remove('sm-feedback-timed');
            popup.style.removeProperty('--feedback-remaining');
        };
    },

    banner: (title, content, type = 'info', options = {}) => {
        const tone = ['success', 'info', 'warning', 'danger', 'error'].includes(type) ? type : 'info';
        let observer, clearCountdown;
        const duration = options.timer ?? (['danger', 'error', 'warning'].includes(tone) ? 0 : 7000);
        const position = () => {
            const host = Swal.getContainer();
            const nav = document.querySelector('[data-site-navbar]');
            const bottom = nav ? nav.getBoundingClientRect().bottom : 0;
            if (host) host.style.setProperty('--sm-feedback-top', `${Math.max(12, bottom + 12)}px`);
        };
        return Swal.fire({
            titleText: title, ...(options.html ? { html: content } : { text: content }),
            ...(options.target ? { target: options.target } : {}),
            position: 'top', toast: true, width: 'min(36rem, calc(100vw - 2rem))',
            icon: tone === 'danger' ? 'error' : tone,
            iconHtml: SM.feedbackIcon(tone),
            showConfirmButton: false, showCloseButton: true, closeButtonAriaLabel: 'Dismiss notification',
            timer: duration,
            timerProgressBar: false,
            customClass: { container: `sm-feedback-host ${tone}`, popup: 'sm-feedback-card' },
            showClass: { popup: 'sm-feedback-enter' },
            hideClass: { popup: 'sm-feedback-leave' },
            didOpen: popup => {
                SM.decorateFeedback(popup);
                clearCountdown = SM.feedbackCountdown(popup, duration);
                position();
                window.addEventListener('scroll', position, { passive: true });
                window.addEventListener('resize', position);
                const nav = document.querySelector('[data-site-navbar]');
                if (nav && typeof ResizeObserver !== 'undefined') {
                    observer = new ResizeObserver(position);
                    observer.observe(nav);
                }
                popup.addEventListener('mouseenter', () => Swal.stopTimer());
                popup.addEventListener('mouseleave', () => { if (!popup.contains(document.activeElement)) Swal.resumeTimer(); });
                popup.addEventListener('focusin', () => Swal.stopTimer());
                popup.addEventListener('focusout', event => { if (!popup.contains(event.relatedTarget)) Swal.resumeTimer(); });
            },
            didDestroy: () => {
                clearCountdown?.();
                observer?.disconnect();
                window.removeEventListener('scroll', position);
                window.removeEventListener('resize', position);
            },
        });
    },

    alert: (title, text, type = 'info') => SM.banner(title, text, type),

    confirm: (title, content, button, callback) => {
        if (typeof Swal === 'undefined' || !Swal || typeof Swal.fire !== 'function') {
            const fallbackResult = Promise.resolve({ isConfirmed: false, isDismissed: true });

            if (typeof callback === 'function') {
                fallbackResult.then((result) => {
                    callback(result.isConfirmed);
                });
            }

            return fallbackResult;
        }

        return Swal.fire({
            position: 'center',
            icon: 'warning',
            iconColor: '#b91c1c',
            title: title,
            html: content,
            showCancelButton: true,
            confirmButtonText: button,
            confirmButtonColor: '#b91c1c',
            cancelButtonText: 'Cancel',
            reverseButtons: true
        }).then((result) => {
            if (typeof callback === 'function') {
                callback(result.isConfirmed);
            }

            return result;
        });
    },

    notice: (title, content = '', type = 'info', options = {}) => {
        if (typeof content === 'object' && content !== null) {
            options = content;
            content = options.content ?? options.html ?? options.text ?? '';
            type = options.type ?? 'info';
        } else if (typeof type === 'object' && type !== null) {
            options = type;
            type = options.type ?? 'info';
        }

        const styles = {
            info: { icon: 'info', iconColor: '#2563eb', confirmButtonColor: '#2563eb' },
            success: { icon: 'success', iconColor: '#15803d', confirmButtonColor: '#15803d' },
            warning: { icon: 'warning', iconColor: '#b45309', confirmButtonColor: '#b45309' },
            danger: { icon: 'warning', iconColor: '#b91c1c', confirmButtonColor: '#b91c1c' },
            error: { icon: 'error', iconColor: '#b91c1c', confirmButtonColor: '#b91c1c' },
            question: { icon: 'question', iconColor: '#374151', confirmButtonColor: '#374151' }
        };

        const styleKey = String(type || 'info').toLowerCase();
        const style = styles[styleKey] || styles.info;
        const toast = options.toast === true;
        if (toast && !options.showConfirmButton && !options.showCancelButton) {
            return SM.banner(title, content, styleKey, { html: true, timer: options.timer }).then(result => {
                if (result.isDismissed && typeof options.onDismiss === 'function') options.onDismiss(result);
                if (typeof options.onClose === 'function') options.onClose(result);
                return result;
            });
        }
        const customClass = typeof options.customClass === 'object' && options.customClass !== null
            ? {
                ...options.customClass,
                container: [options.customClass.container, styleKey].filter(Boolean).join(' '),
            }
            : {
                container: styleKey,
                ...(typeof options.customClass === 'string' && options.customClass !== ''
                    ? { popup: options.customClass }
                    : {}),
            };
        const config = {
            position: options.position || (toast ? 'bottom-end' : 'center'),
            title: title,
            html: content,
            icon: options.icon === false ? undefined : (options.icon || style.icon),
            iconColor: options.iconColor || style.iconColor,
            showConfirmButton: options.showConfirmButton ?? !toast,
            confirmButtonText: options.confirmButtonText || 'OK',
            confirmButtonColor: options.confirmButtonColor || style.confirmButtonColor,
            showCancelButton: options.showCancelButton === true,
            cancelButtonText: options.cancelButtonText || 'Cancel',
            reverseButtons: options.reverseButtons ?? true,
            allowOutsideClick: options.allowOutsideClick ?? true,
            allowEscapeKey: options.allowEscapeKey ?? true,
            timer: options.timer,
            timerProgressBar: options.timerProgressBar ?? false,
            toast: toast,
            showCloseButton: options.showCloseButton ?? toast,
            customClass,
            width: options.width,
            footer: options.footer,
        };

        return Swal.fire(config).then((result) => {
            if (result.isConfirmed && typeof options.onConfirm === 'function') {
                options.onConfirm(result);
            }

            if (result.isDismissed && typeof options.onDismiss === 'function') {
                options.onDismiss(result);
            }

            if (typeof options.onClose === 'function') {
                options.onClose(result);
            }

            return result;
        });
    },

    copyToClipboard: (text) => {
        const copyContent = async () => {
            try {
                await navigator.clipboard.writeText(text);
                SM.alert('Link copied', 'The link has been copied to the clipboard.', 'success');
            } catch (err) {
                SM.alert('Copy failed', 'Could not copy the link to the clipboard. It may not have permission in your browser.', 'danger');
            }
        }

        copyContent().then(() => { /* empty */});
    },

    updateShippingAddress: () => {
        const checkboxElement = document.querySelector('input[name="shipping_same_billing"]');

        if (checkboxElement) {
            const itemNames = ['address', 'address2', 'city', 'state', 'postcode', 'country'];

            if (checkboxElement.checked) {
                itemNames.forEach((itemName) => {
                    const element = document.querySelector(`input[name="shipping_${itemName}"]`);
                    element.value = document.querySelector(`input[name="billing_${itemName}"]`).value;
                    element.setAttribute('readonly', 'true');
                });
            } else {
                itemNames.forEach((itemName) => {
                    const element = document.querySelector(`input[name="shipping_${itemName}"]`);
                    element.removeAttribute('readonly');
                });
            }
        }
    },

    initTicketCancelModal: (reasonDefault = 'The following ticket has been cancelled.') => {
        if (!window.Alpine?.store) {
            return;
        }

        const store = Alpine.store('ticketCancelModal');
        if (!store) {
            return;
        }

        const normalizedReason = String(reasonDefault || '').trim() || 'The following ticket has been cancelled.';
        store.reasonDefault = normalizedReason;
        store.reason = normalizedReason;
        store.emailCustomer = true;
        store.showSquareRefund = false;
        store.processSquareRefund = false;
        store.open = false;
    },

    openTicketCancelModal: (action, label, message, submitLabel = 'Cancel Ticket', showSquareRefund = false, processSquareRefund = false) => {
        if (!window.Alpine?.store) {
            return;
        }

        const store = Alpine.store('ticketCancelModal');
        if (!store) {
            return;
        }

        store.formAction = action;
        store.ticketLabel = label;
        store.confirmationMessage = message;
        store.submitLabel = submitLabel;
        store.showSquareRefund = Boolean(showSquareRefund);
        store.processSquareRefund = Boolean(processSquareRefund);
        store.emailCustomer = true;
        store.reason = store.reasonDefault || 'The following ticket has been cancelled.';
        store.open = true;
    },

    closeTicketCancelModal: () => {
        if (!window.Alpine?.store) {
            return;
        }

        const store = Alpine.store('ticketCancelModal');
        if (!store) {
            return;
        }

        store.open = false;
        store.formAction = '';
        store.ticketLabel = '';
        store.confirmationMessage = '';
        store.submitLabel = 'Cancel Ticket';
        store.processSquareRefund = false;
        store.showSquareRefund = false;
        store.emailCustomer = true;
        store.reason = store.reasonDefault || 'The following ticket has been cancelled.';
    },

    confirmDelete: (token, title, content, urlOrForm, confirmButtonText = 'Delete', cancelButtonText = 'Cancel') => {
        Swal.fire({
            position: 'top',
            icon: 'warning',
            iconColor: '#b91c1c',
            title: title,
            html: content,
            showCancelButton: true,
            confirmButtonText: confirmButtonText,
            confirmButtonColor: '#b91c1c',
            cancelButtonText: cancelButtonText,
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                if (urlOrForm instanceof HTMLFormElement) {
                    urlOrForm.submit();
                    return;
                }

                const deleteUrl = typeof urlOrForm === 'string'
                    ? urlOrForm
                    : '';

                if (deleteUrl === '') {
                    return;
                }

                const showDeleteError = (error) => {
                    const status = Number(error?.response?.status || 0);
                    const data = error?.response?.data;
                    const message = status >= 400 && status < 500
                        ? Object.values(data?.errors || {}).flat()[0] || data?.message
                        : null;
                    SM.alert('Unable to delete', message || 'The request could not be completed. Please try again. If it keeps failing, contact support.', 'danger');
                };
                const completeDelete = (data) => {
                    if (data?.success) {
                        SM.redirectIfSafe(data.redirect);
                    } else {
                        showDeleteError();
                    }
                };
                const axiosClient = window.axios;
                if (axiosClient && typeof axiosClient.delete === 'function') {
                    axiosClient.delete(deleteUrl)
                        .then(response => completeDelete(response.data))
                        .catch(showDeleteError);
                    return;
                }

                fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                })
                .then(async response => {
                    const data = await response.json().catch(() => null);
                    if (!response.ok) throw {response: {status: response.status, data}};
                    completeDelete(data);
                })
                .catch(showDeleteError);
            }
        });
    },

    confirmAccountDelete: (form) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const title = String(form.dataset.deleteTitle || 'Delete account?').trim() || 'Delete account?';
        const introText = String(form.dataset.deleteMessage || 'Are you sure you want to delete your account? This action cannot be undone.').trim()
            || 'Are you sure you want to delete your account? This action cannot be undone.';
        const secondaryText = String(form.dataset.deleteSecondaryMessage || 'Any workshop tickets will remain valid.').trim()
            || 'Any workshop tickets will remain valid.';

        const deleteThreadsInput = form.querySelector('input[name="delete_discussion_threads"]');
        if (deleteThreadsInput instanceof HTMLInputElement) {
            deleteThreadsInput.value = '0';
        }

        Swal.fire({
            position: 'top',
            icon: 'warning',
            iconColor: '#b91c1c',
            title: title,
            html: `
                <p>${introText}</p>
                <p class="mt-3">${secondaryText}</p>
                <label class="mt-5 flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-left text-sm text-gray-700">
                    <input type="checkbox" id="sm-delete-discussion-threads" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500">
                    <span>Also delete my discussion threads and posts</span>
                </label>
            `,
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#b91c1c',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            preConfirm: () => {
                const checkbox = document.getElementById('sm-delete-discussion-threads');

                return {
                    deleteDiscussionThreads: checkbox instanceof HTMLInputElement && checkbox.checked,
                };
            },
        }).then((result) => {
            if (!result.isConfirmed) {
                if (deleteThreadsInput instanceof HTMLInputElement) {
                    deleteThreadsInput.value = '0';
                }

                return;
            }

            if (deleteThreadsInput instanceof HTMLInputElement) {
                deleteThreadsInput.value = result.value?.deleteDiscussionThreads ? '1' : '0';
            }

            form.submit();
        });
    },

    upload: (files, callback, titles = [], options = {}) => {
        let uploadedFiles = [];
        const showModal = options.showModal !== false;
        const successDelayMs = Number.isFinite(Number(options.successDelayMs))
            ? Math.max(0, Number(options.successDelayMs))
            : (showModal ? 3000 : 0);
        const onStart = typeof options.onStart === 'function' ? options.onStart : null;
        const onProgress = typeof options.onProgress === 'function' ? options.onProgress : null;
        const onSuccess = typeof options.onSuccess === 'function' ? options.onSuccess : null;
        const onError = typeof options.onError === 'function' ? options.onError : null;
        const fields = options.fields && typeof options.fields === 'object' ? options.fields : {};
        const deferFinalization = options.deferFinalization === true;

        if(files.length === 0) {
            return;
        }

        if (onStart) {
            onStart({
                files,
                count: files.length,
            });
        }

        if (showModal) {
            const data = {
                title: "Checking...",
                text: "Please wait",
                imageUrl: "/loading.gif",
                imageHeight: 100,
                showConfirmButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
            };
            Swal.fire(data);
        }

        const showError = (message) => {
            if (onError) {
                onError(message);
            }

            if (!showModal) {
                if(callback) {
                    callback({ success: false, message });
                }

                return;
            }

            Swal.fire({
                position: 'top',
                icon: 'error',
                title: 'An error occurred',
                html: message,
                showConfirmButton: true,
                confirmButtonColor: '#b91c1c',
            }).then(() => {
                if(callback) {
                    callback({ success: false, message });
                }
            });
        }

        for(const file of files) {
            if (file.size > SM.maxUploadSize()) {
                const size = SM.bytesToString(file.size);
                const maxSize = SM.bytesToString(SM.maxUploadSize());
                showError('The file size is too large (' + size + '). Please upload a file less than ' + maxSize + '.');
                return;
            }
        }

        const uploadFile = (file, start, title, idx, count, uploadToken = null) => {
            const showPercentDecimals = (file.size > (1024 * 1024 * 40));
            const chunkSize = 1024 * 1024 * 2;
            const end = Math.min(file.size, start + chunkSize);
            const chunk = file.slice(start, end);

            const formData = new FormData();
            formData.append('file', chunk);
            formData.append('filename', file.name);
            formData.append('filesize', file.size);
            Object.entries(fields).forEach(([key, value]) => {
                if (value !== null && typeof value !== 'undefined') {
                    formData.append(key, value);
                }
            });
            if (uploadToken) {
                formData.append('upload_token', uploadToken);
            }

            if (start === 0) {
                formData.append('filestart', 'true');
            } else {
                formData.append('fileappend', 'true');
            }

            if (title !== '' && !deferFinalization) {
                formData.append('title', title);
            }

            const mediaUploadUrl = document.querySelector('meta[name="media-upload-url"]')?.getAttribute('content') || '/media';

            axios.post(mediaUploadUrl, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'Accept': 'application/json'
                },
                timeout: 90000,
                onUploadProgress: (progressEvent) => {
                    let percent = ((start + progressEvent.loaded) / file.size) * 100;

                    if(showPercentDecimals) {
                        percent = percent.toFixed(1);
                    } else {
                        percent = Math.round(percent);
                    }

                    let progressTitle = 'Uploading';
                    if(count > 1) {
                        progressTitle += ' ' + (idx + 1) + ' of ' + count;
                    }

                    if (showModal) {
                        Swal.update({
                            title: progressTitle + '...',
                            html: `${file.name} - ${percent}%`,
                        });
                    }

                    if (onProgress) {
                        onProgress({
                            file,
                            title,
                            index: idx,
                            count,
                            percent: Number(percent),
                            loaded: start + progressEvent.loaded,
                            total: file.size,
                        });
                    }
                }
            }).then((response) => {
                if (response.status === 200) {
                    if (response.data && response.data.upload_token) {
                        uploadToken = response.data.upload_token;
                    }

                    if (end >= file.size) {
                        uploadedFiles.push({ file: file, title: title, data: response.data });

                        if (idx === count - 1) {
                            const successPayload = { success: true, files: uploadedFiles };

                            if (showModal) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    html: count > 1 ? `Uploaded ${count} files successfully` : `${response.data.name || file.name} uploaded successfully`,
                                    showConfirmButton: false,
                                    timer: successDelayMs || 3000
                                });
                            }

                            window.setTimeout(() => {
                                if (onSuccess) {
                                    onSuccess(successPayload);
                                }

                                if (callback) {
                                    callback(successPayload);
                                }
                            }, successDelayMs);

                            return;
                        } else {
                            start = 0;
                            idx += 1;
                            uploadToken = null;
                        }
                    } else {
                        start = end;
                    }

                    uploadFile(files[idx], start, titles[idx] || '', idx, files.length, uploadToken);
                } else {
                    showError(response.data.message);
                }
            }).catch((error) => {
                const message = error?.response?.status === 413
                    ? 'The server or proxy rejected an upload chunk as too large. Please contact an administrator with the time of this upload.'
                    : error?.code === 'ECONNABORTED'
                    ? 'The upload stopped responding for 90 seconds. You can retry without creating a duplicate media record.'
                    : error?.response?.data?.message
                    || error?.response?.data?.errors?.file
                    || error?.message
                    || 'An error occurred while uploading the file.';
                showError(message);
            });
        }

        uploadFile(files[0], 0, titles[0] || '', 0, files.length);
    },

    bytesToString: (bytes) => {
        bytes = parseInt(bytes);
        if (isNaN(bytes)) {
            bytes = 0;
        }

        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const fixed = [0, 0, 2, 2, 2];
        if (bytes === 0) return '0 Bytes';
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const size = parseFloat((bytes / Math.pow(1024, i)).toFixed(fixed[i]));
        return size + ' ' + sizes[i];
    },

    maxUploadSize: () => {
        try {
            return parseInt(document.querySelector('meta[name="max-upload-size"]').getAttribute('content'));
        } catch (error) {
            /* Do nothing */
        }

        return 0;
    },

    /**
     * Transforms a string to title case.
     * @param {string} str The string to transform.
     * @returns {string} A string transformed to title case.
     */
    toTitleCase: (str) => {
        // Remove leading and trailing spaces
        str = str.trim();

        // Remove file extension
        str = str.replace(/\.[a-zA-Z0-9]{1,4}$/, "");

        // Replace underscores and hyphens with spaces
        str = str.replace(/[_-]+/g, " ");

        // Capitalize the first letter of each word and make the rest lowercase.
        // Apostrophes stay inside the word so names like "don't" become "Don't".
        str = str.toLowerCase().replace(/(^|[^A-Za-z0-9'’])([A-Za-z])/g, (match, prefix, letter) => {
            return prefix + letter.toUpperCase();
        });

        // Replace "cdn" with "CDN"
        str = str.replace(/\bCdn\b/gi, "CDN");

        return str;
    },

    mediaDetails: (name, callback) => {
        const url = '/media/' + encodeURIComponent(name);
        const request = window.axios
            ? window.axios.get(url, {
                headers: {
                    'Accept': 'application/json'
                }
            }).then((response) => response.data)
            : fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('Unable to load media details.');
                }

                return response.json();
            });

        request.then((data) => {
            callback(data);
        }).catch((error) => {
            console.error(error);
            callback(null);
        });
    },

    mimeMatches: (fileMime, matchMimeList) => {
        for(const matchMime of matchMimeList.split(',')) {
            if (matchMime === '*' || matchMime === '*/*') {
                return true;
            }

            const matchMimeArray = matchMime.split('/');
            const fileMimeArray = fileMime.split('/');

            if (matchMimeArray[1] === '*' && matchMimeArray[0] === fileMimeArray[0]) {
                return true;
            } else if(fileMime === matchMime) {
                return true;
            }
        }

        return false;
    },

    arrayToString: (array, separator = ',') => {
        return array.map(item => {
            if (item.includes(separator)) {
                // If the item contains the separator, wrap it in quotes and escape any quotes within the string
                return `"${item.replace(/"/g, '\\"')}"`;
            } else {
                return item;
            }
        }).join(separator);
    },

    stringToArray: (string, separator = ',') => {
        return string.split(separator).map(item => {
            // Remove quotes and unescape any escaped quotes within the string
            return item.replace(/^"|"$/g, '').replace(/\\"/g, '"');
        });
    },

    decodeHtml: (html) => {
        const ta = document.createElement("textarea");
        ta.innerHTML = html;
        return ta.value;
    },

    toLocalISOString: (date) => {
        return date.getFullYear() + '-' + (date.getMonth() + 1).toString().padStart(2, '0') + '-' + date.getDate().toString().padStart(2, '0') + 'T' + date.getHours().toString().padStart(2, '0') + ':' + date.getMinutes().toString().padStart(2, '0');
    },

    updateThumbnail: (name, element) => {
        axios.get('/media/' + name)
            .then(response => {
                if(response.data.status === 'ready') {
                    if(element instanceof HTMLImageElement) {
                        element.src = response.data.thumbnail;
                    } else if(typeof element === 'string') {
                        const imgElement = document.querySelector(element);
                        if(imgElement instanceof HTMLImageElement) {
                            imgElement.src = response.data.thumbnail;
                        }
                    }
                } else if(response.data.status === 'processing') {
                    setTimeout(() => {
                        SM.updateThumbnail(name, element);
                    }, 5000);
                }
            })
            .catch(error => {
                console.error(error);
            });
    },

    updateAllThumbnails: () => {
        const elements = document.querySelectorAll('img[data-thumbnail]');
        elements.forEach(element => {
            SM.updateThumbnail(element.getAttribute('data-thumbnail'), element);
        });
    },

    shopCart: {
        config: {
            showUrl: '/shop/cart',
            updateUrl: '/shop/cart/update',
            removeUrl: '/shop/cart/remove',
            preferencesUrl: '/shop/cart/preferences',
            couponApplyUrl: '/shop/cart/coupon',
            couponRemoveUrl: '/shop/cart/coupon/remove',
        },
        state: {
            shipping_country: 'Australia',
            coupon_code: null,
            is_empty: true,
            cart_url: '/shop/cart',
            checkout_url: '/shop/checkout',
            lines: [],
            summary: {
                item_count: 0,
                subtotal: 0,
                shipping: 0,
                discount: 0,
                gst: 0,
                total: 0,
                can_checkout: false,
                coupon_code: null,
                shipping_quote: {
                    boxed_shipping_required: false,
                    method: '',
                    reason: null,
                    package_summary: null,
                    known_weight_grams: 0,
                },
            },
        },
        subscribers: [],

        configure(config = {}) {
            this.config = {
                ...this.config,
                ...(config || {}),
            };

            if (config.initialState) {
                this.setState(config.initialState);
            }
        },

        getState() {
            return this.state;
        },

        subscribe(callback) {
            if (typeof callback !== 'function') {
                return () => {};
            }

            this.subscribers.push(callback);
            callback(this.state);

            return () => {
                this.subscribers = this.subscribers.filter((item) => item !== callback);
            };
        },

        notify() {
            this.subscribers.forEach((callback) => {
                try {
                    callback(this.state);
                } catch (_error) {
                }
            });

            window.dispatchEvent(new CustomEvent('shop-cart-updated', {
                detail: { cart: this.state },
            }));
        },

        setState(state = {}) {
            const nextState = state && typeof state === 'object' ? state : {};
            this.state = {
                ...this.state,
                ...nextState,
                lines: Array.isArray(nextState.lines) ? nextState.lines : [],
                summary: {
                    ...this.state.summary,
                    ...(nextState.summary || {}),
                    shipping_quote: {
                        ...this.state.summary.shipping_quote,
                        ...((nextState.summary || {}).shipping_quote || {}),
                    },
                },
            };
            this.notify();
            return this.state;
        },

        csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },

        errorMessageFromPayload(payload, fallback = 'Unable to update the cart right now.') {
            if (!payload || typeof payload !== 'object') {
                return fallback;
            }

            if (typeof payload.message === 'string' && payload.message.trim() !== '') {
                return payload.message.trim();
            }

            if (payload.errors && typeof payload.errors === 'object') {
                for (const value of Object.values(payload.errors)) {
                    if (Array.isArray(value) && value.length > 0) {
                        return String(value[0]);
                    }
                    if (typeof value === 'string' && value.trim() !== '') {
                        return value.trim();
                    }
                }
            }

            return fallback;
        },

        setFormInput(form, name, value) {
            if (!(form instanceof HTMLFormElement)) {
                return null;
            }

            const resolvedName = typeof name === 'string' ? name.trim() : '';
            if (resolvedName === '') {
                return null;
            }

            const existingInput = Array.from(form.elements || []).find((field) => (
                field instanceof HTMLInputElement
                && field.type === 'hidden'
                && field.name === resolvedName
            ));

            const input = existingInput instanceof HTMLInputElement
                ? existingInput
                : document.createElement('input');

            if (!existingInput) {
                input.type = 'hidden';
                input.name = resolvedName;
                form.appendChild(input);
            }

            input.value = String(value ?? '');

            return input;
        },

        lineByKey(lineKey, state = null) {
            const resolvedState = state && typeof state === 'object' ? state : this.state;
            if (!lineKey || !Array.isArray(resolvedState?.lines)) {
                return null;
            }

            return resolvedState.lines.find((line) => String(line?.key || '') === String(lineKey)) || null;
        },

        async request(url, options = {}) {
            const response = await fetch(url, {
                method: options.method || 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken(),
                    ...(options.headers || {}),
                },
                credentials: 'same-origin',
                body: options.body || null,
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false) {
                throw new Error(this.errorMessageFromPayload(payload));
            }

            return payload;
        },

        showSuccess(message) {
            if (!message || !window.SM) {
                return;
            }

            if (typeof window.SM.notice === 'function') {
                window.SM.notice('Cart updated', message, 'success', { toast: true });
                return;
            }

            if (typeof window.SM.alert === 'function') {
                window.SM.alert('Cart updated', message, 'success');
            }
        },

        showError(message) {
            if (!window.SM) {
                return;
            }

            if (typeof window.SM.notice === 'function') {
                window.SM.notice('Cart update failed', message, 'danger');
                return;
            }

            if (typeof window.SM.alert === 'function') {
                window.SM.alert('Cart update failed', message, 'danger');
            }
        },

        ensureAddSheetStyles() {
            if (document.getElementById('sm-shop-add-sheet-styles')) {
                return;
            }

            const style = document.createElement('style');
            style.id = 'sm-shop-add-sheet-styles';
            style.textContent = `
                .swal2-container.sm-shop-add-sheet-container {
                    padding: 0 0.5rem 0.75rem !important;
                }

                .swal2-popup.sm-shop-add-sheet-popup {
                    margin: 0 !important;
                    transform: translateY(calc(100% + 1.5rem));
                    opacity: 0;
                    border-radius: .5rem !important;
                }

                .swal2-popup.sm-shop-add-sheet-popup.sm-shop-add-sheet-popup-show {
                    animation: sm-shop-add-sheet-slide-up 220ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
                }

                .swal2-popup.sm-shop-add-sheet-popup.sm-shop-add-sheet-popup-hide {
                    animation: sm-shop-add-sheet-slide-down 180ms ease-in forwards;
                }

                @keyframes sm-shop-add-sheet-slide-up {
                    from {
                        transform: translateY(calc(100% + 1.5rem));
                        opacity: 0;
                    }

                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }

                @keyframes sm-shop-add-sheet-slide-down {
                    from {
                        transform: translateY(0);
                        opacity: 1;
                    }

                    to {
                        transform: translateY(calc(100% + 1.5rem));
                        opacity: 0;
                    }
                }

                @media (prefers-reduced-motion: reduce) {
                    .swal2-popup.sm-shop-add-sheet-popup,
                    .swal2-popup.sm-shop-add-sheet-popup.sm-shop-add-sheet-popup-show,
                    .swal2-popup.sm-shop-add-sheet-popup.sm-shop-add-sheet-popup-hide {
                        animation: none !important;
                        transform: none !important;
                        opacity: 1 !important;
                    }
                }
            `;
            document.head.appendChild(style);
        },

        async confirmPreorder(options = {}) {
            const itemTitle = String(options.itemTitle || 'This item').trim() || 'This item';
            const shippingEstimate = String(options.shippingEstimate || '').trim();
            const confirmText = String(options.confirmText || 'Add to cart').trim() || 'Add to cart';
            const cancelText = String(options.cancelText || 'Cancel').trim() || 'Cancel';
            const acknowledgementText = String(options.acknowledgementText || 'I understand this item is a pre-order and my order will ship when it becomes available.').trim()
                || 'I understand this item is a pre-order and my order will ship when it becomes available.';

            if (typeof Swal === 'undefined') {
                return false;
            }

            const checkboxId = `sm-preorder-ack-${Date.now()}-${Math.random().toString(16).slice(2)}`;
            const shippingHtml = shippingEstimate !== ''
                ? `
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.9rem;border-radius:1rem;border:1px solid #cbd5e1;background:#f8fafc;padding:0.95rem 1rem;">
                        <div style="font-size:0.85rem;font-weight:700;color:#0f172a;">Estimated shipping date</div>
                        <div style="font-size:0.95rem;color:#334155;">${SM.escapeHtml(shippingEstimate)}</div>
                    </div>
                `
                : '';

            const result = await Swal.fire({
                title: itemTitle,
                html: `
                    <div style="text-align:left;">
                        <div style="margin-top:0.75rem;font-size:0.97rem;line-height:1.6;color:#374151;">
                            <span class="font-bold">Pre-order notice:</span> This item is not yet instock or available.
                        </div>
                        ${shippingHtml}
                        <label for="${checkboxId}" style="margin-top:1rem;display:flex;align-items:flex-start;gap:0.85rem;border-radius:1rem;border:1px solid #d1d5db;background:#f9fafb;padding:1rem;cursor:pointer;">
                            <input id="${checkboxId}" type="checkbox" data-preorder-ack style="margin-top:0.15rem;height:1.1rem;width:1.1rem;border-radius:0.25rem;">
                            <span style="font-size:0.95rem;line-height:1.5;color:#374151;">${SM.escapeHtml(acknowledgementText)}</span>
                        </label>
                    </div>
                `,
                showCancelButton: true,
                focusConfirm: false,
                allowOutsideClick: true,
                confirmButtonText: confirmText,
                cancelButtonText: cancelText,
                reverseButtons: true,
                buttonsStyling: false,
                customClass: {
                    popup: 'w-full! max-w-xl! rounded-3xl! p-6! text-left',
                    actions: 'flex w-full flex-col gap-3 sm:flex-row sm:justify-end',
                    confirmButton: 'inline-flex items-center justify-center rounded-md bg-orange-500 px-8 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm transition hover:bg-orange-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500 disabled:cursor-not-allowed disabled:bg-orange-300 disabled:opacity-60 disabled:shadow-none',
                    cancelButton: 'inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-8 py-1.5 text-sm font-semibold leading-6 text-slate-900! shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-300',
                    validationMessage: '!mt-3 !rounded-xl !border !border-red-200 !bg-red-50 !px-4 !py-3 !text-left !text-sm !text-red-700',
                },
                didOpen: (popup) => {
                    const checkbox = popup.querySelector('[data-preorder-ack]');
                    const confirmButton = Swal.getConfirmButton();
                    const syncConfirmState = () => {
                        if (checkbox instanceof HTMLInputElement && checkbox.checked && typeof Swal.resetValidationMessage === 'function') {
                            Swal.resetValidationMessage();
                        }

                        if (confirmButton instanceof HTMLButtonElement) {
                            const isEnabled = checkbox instanceof HTMLInputElement && checkbox.checked;
                            confirmButton.disabled = !isEnabled;
                            confirmButton.setAttribute('aria-disabled', isEnabled ? 'false' : 'true');
                        }
                    };

                    syncConfirmState();
                    checkbox?.addEventListener('change', syncConfirmState);
                },
                preConfirm: () => {
                    const checkbox = Swal.getPopup()?.querySelector('[data-preorder-ack]');

                    if (!(checkbox instanceof HTMLInputElement) || !checkbox.checked) {
                        Swal.showValidationMessage('Please confirm you understand the pre-order shipping timing before continuing.');
                        return false;
                    }

                    return true;
                },
            });

            return Boolean(result.isConfirmed);
        },

        showAddSheet(line, options = {}) {
            if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
                window.dispatchEvent(new CustomEvent('shop-cart-open'));
                return Promise.resolve(null);
            }

            this.showSuccess(options.fallbackMessage || 'Added to cart.');
            return Promise.resolve(null);
        },

        stripNonNumericQuantityInput(input) {
            if (!(input instanceof HTMLInputElement)) {
                return '';
            }

            const sanitizedValue = String(input.value ?? '').replace(/\D+/g, '');
            if (input.value !== sanitizedValue) {
                input.value = sanitizedValue;
            }

            return sanitizedValue;
        },

        prepareQuantityUpdate(quantity, options = {}) {
            const max = Number.parseInt(String(options.max ?? 99), 10) || 99;
            const fallbackQuantity = SM.toBoundedInt(options.fallbackQuantity ?? 1, {
                min: 1,
                max,
                allowNull: false,
            });
            const input = options.input instanceof HTMLInputElement ? options.input : null;

            if (input) {
                const sanitizedValue = this.stripNonNumericQuantityInput(input);
                if (sanitizedValue === '') {
                    input.value = String(fallbackQuantity);

                    return {
                        quantity: fallbackQuantity,
                        shouldSubmit: false,
                        value: String(fallbackQuantity),
                    };
                }

                const nextQuantity = SM.toBoundedInt(sanitizedValue, {
                    min: 0,
                    max,
                    allowNull: false,
                });
                const normalizedValue = String(nextQuantity);
                input.value = normalizedValue;

                return {
                    quantity: nextQuantity,
                    shouldSubmit: nextQuantity !== fallbackQuantity,
                    value: normalizedValue,
                };
            }

            const sourceQuantity = typeof quantity === 'string'
                ? quantity.replace(/\D+/g, '')
                : quantity;
            const nextQuantity = SM.toBoundedInt(sourceQuantity, {
                min: 0,
                max,
                allowNull: false,
            });

            return {
                quantity: nextQuantity,
                shouldSubmit: nextQuantity !== fallbackQuantity,
                value: String(nextQuantity),
            };
        },

        quantitiesFormData(overrides = {}, shippingCountry = null) {
            const data = new FormData();
            const resolvedCountry = shippingCountry || this.state.shipping_country || 'Australia';

            (this.state.lines || []).forEach((line) => {
                const nextQuantity = Object.prototype.hasOwnProperty.call(overrides, line.key)
                    ? overrides[line.key]
                    : line.quantity;

                data.append(`quantities[${line.key}]`, String(nextQuantity));
            });
            data.append('shipping_country', resolvedCountry);

            return data;
        },

        async submitAddForm(form, options = {}) {
            if (!(form instanceof HTMLFormElement)) {
                return null;
            }

            try {
                const addedLineKey = typeof options.addedLineKey === 'string' ? options.addedLineKey : '';
                const previousLine = addedLineKey !== '' ? this.lineByKey(addedLineKey) : null;
                const previousQuantity = Number.parseInt(String(previousLine?.quantity ?? 0), 10) || 0;
                const payload = await this.request(form.action, {
                    method: form.method || 'POST',
                    body: new FormData(form),
                });
                this.setState(payload.cart || {});
                if (options.showAddSheet === true && previousQuantity <= 0) {
                    this.showAddSheet(this.lineByKey(addedLineKey), {
                        reviewUrl: this.state.cart_url || payload?.cart?.cart_url || '/shop/cart',
                        fallbackMessage: payload.message || 'Added to cart.',
                    });
                } else if (options.showNotice === true) {
                    this.showSuccess(payload.message || 'Added to cart.');
                }
                return payload;
            } catch (error) {
                this.showError(error.message || 'Unable to add this item to the cart.');
                throw error;
            }
        },

        async updateQuantity(lineKey, quantity, options = {}) {
            try {
                const nextQuantity = SM.toBoundedInt(quantity, {
                    min: 0,
                    max: Number.parseInt(String(options.max ?? 99), 10) || 99,
                    allowNull: false,
                });
                const payload = await this.request(this.config.updateUrl, {
                    method: 'POST',
                    body: this.quantitiesFormData({ [lineKey]: nextQuantity }, options.shippingCountry || null),
                });
                this.setState(payload.cart || {});
                if (options.showNotice !== false) {
                    this.showSuccess(payload.message || 'Your cart has been updated.');
                }
                return payload;
            } catch (error) {
                if (options.showError !== false) {
                    this.showError(error.message || 'Unable to update the item quantity.');
                }
                throw error;
            }
        },

        async removeLine(lineKey, options = {}) {
            try {
                const data = new FormData();
                data.append('line_key', lineKey);
                data.append('shipping_country', options.shippingCountry || this.state.shipping_country || 'Australia');

                const payload = await this.request(this.config.removeUrl, {
                    method: 'POST',
                    body: data,
                });
                this.setState(payload.cart || {});
                if (options.showNotice !== false) {
                    this.showSuccess(payload.message || 'Removed that item from your cart.');
                }
                return payload;
            } catch (error) {
                if (options.showError !== false) {
                    this.showError(error.message || 'Unable to remove that item right now.');
                }
                throw error;
            }
        },

        async updatePreferences(options = {}) {
            try {
                const data = new FormData();
                const shippingMethodCode = String(options.shippingMethodCode ?? this.state?.summary?.shipping_method_code ?? '').trim();
                const shippingCountry = String(options.shippingCountry || this.state.shipping_country || 'Australia').trim() || 'Australia';

                if (shippingMethodCode !== '') {
                    data.append('shipping_method_code', shippingMethodCode);
                }
                data.append('consolidate_shipments', options.consolidateShipments ? '1' : '0');
                data.append('shipping_country', shippingCountry);

                const payload = await this.request(this.config.preferencesUrl, {
                    method: 'POST',
                    body: data,
                });
                this.setState(payload.cart || {});
                if (options.showNotice !== false) {
                    this.showSuccess(payload.message || 'Delivery options updated.');
                }
                return payload;
            } catch (error) {
                if (options.showError !== false) {
                    this.showError(error.message || 'Unable to update delivery options right now.');
                }
                throw error;
            }
        },

        async applyCoupon(options = {}) {
            try {
                const data = new FormData();
                const couponCode = String(options.couponCode || '').trim();
                const shippingCountry = String(options.shippingCountry || this.state.shipping_country || 'Australia').trim() || 'Australia';
                const returnTo = String(options.returnTo || '').trim();

                data.append('coupon_code', couponCode);
                data.append('shipping_country', shippingCountry);
                if (returnTo !== '') {
                    data.append('return_to', returnTo);
                }

                const payload = await this.request(this.config.couponApplyUrl, {
                    method: 'POST',
                    body: data,
                });
                this.setState(payload.cart || {});
                if (options.showNotice !== false) {
                    this.showSuccess(payload.message || 'Voucher applied successfully.');
                }
                return payload;
            } catch (error) {
                if (options.showError !== false) {
                    this.showError(error.message || 'Unable to update the voucher right now.');
                }
                throw error;
            }
        },

        async removeCoupon(options = {}) {
            try {
                const data = new FormData();
                const shippingCountry = String(options.shippingCountry || this.state.shipping_country || 'Australia').trim() || 'Australia';
                const returnTo = String(options.returnTo || '').trim();

                data.append('shipping_country', shippingCountry);
                if (returnTo !== '') {
                    data.append('return_to', returnTo);
                }

                const payload = await this.request(this.config.couponRemoveUrl, {
                    method: 'POST',
                    body: data,
                });
                this.setState(payload.cart || {});
                if (options.showNotice !== false) {
                    this.showSuccess(payload.message || 'Voucher removed.');
                }
                return payload;
            } catch (error) {
                if (options.showError !== false) {
                    this.showError(error.message || 'Unable to remove the voucher right now.');
                }
                throw error;
            }
        },

        async refresh(shippingCountry = null, options = {}) {
            try {
                const url = new URL(this.config.showUrl, window.location.origin);
                url.searchParams.set('shipping_country', shippingCountry || this.state.shipping_country || 'Australia');
                const payload = await this.request(url.toString(), {
                    method: 'GET',
                });
                this.setState(payload.cart || {});
                return payload;
            } catch (error) {
                if (options.showError !== false) {
                    this.showError(error.message || 'Unable to refresh the cart.');
                }
                throw error;
            }
        },
    }
};

window.SM = SM;
Object.assign(window.SM, {
    number: value => Number(value),
    string: value => String(value),
    boolean: value => Boolean(value),
    isArray: value => Array.isArray(value),
    objectKeys: value => Object.keys(value || {}),
    jsonStringify: value => JSON.stringify(value),
    numberIsFinite: value => Number.isFinite(value),
    numberIsInteger: value => Number.isInteger(value),
    numberParseInt: (value, radix) => Number.parseInt(value, radix),
    numberParseFloat: value => Number.parseFloat(value),
    parseFloat: value => parseFloat(value),
    parseInt: (value, radix) => parseInt(value, radix),
    abs: value => Math.abs(value),
    max: (...values) => Math.max(...values),
    min: (...values) => Math.min(...values),
    combineData: (base, ...sources) => Object.assign(base || {}, ...sources),
    get: (value, path, fallback = null) => {
        const result = String(path).split('.').reduce((current, key) => current == null ? undefined : current[key], value);
        return result == null ? fallback : result;
    },
    findById: (items, id) => (Array.isArray(items) ? items : []).find((item) => String(item?.id ?? '') === String(id ?? '')) || null,
    querySelector: (element, selector) => element instanceof Element ? element.querySelector(selector) : null,
    focus: (element) => { if (element && typeof element.focus === 'function') element.focus(); },
    closestDataValue: (target, selector, key) => target instanceof Element ? target.closest(selector)?.dataset?.[key] || null : null,
    setDataValues: (element, values) => {
        const data = Alpine.$data(element);
        for (const [path, value] of Object.entries(values || {})) {
            const parts = path.split('.');
            const key = parts.pop();
            const target = parts.reduce((current, part) => current?.[part], data);
            if (target && key) target[key] = value;
        }
    },
    setDataValueWhen: (element, path, value, condition) => {
        if (condition) window.SM.setDataValues(element, { [path]: value });
    },
    incrementDataValue: (element, path, amount) => {
        const data = Alpine.$data(element);
        const current = Number(window.SM.get(data, path, 0)) || 0;
        window.SM.setDataValues(element, { [path]: current + (Number(amount) || 0) });
    },
    setDataValueAndCall: (element, path, value, method, args = []) => {
        window.SM.setDataValues(element, { [path]: value });
        return window.SM.callAlpineMethod(element, method, args);
    },
    setDataValueThenCallSM: (element, path, value, method, args = []) => {
        window.SM.setDataValues(element, { [path]: value });
        if (typeof window.SM[method] === 'function') return window.SM[method](...args);
    },
    callDataMethodAndDispatch: (element, method, args, eventName, detail = {}) => {
        const result = window.SM.callAlpineMethod(element, method, args || []);
        element.dispatchEvent(new CustomEvent(eventName, { bubbles: true, detail }));
        return result;
    },
    callDataMethodAndSubmit: (element, method) => {
        window.SM.callAlpineMethod(element, method, []);
        if (element instanceof HTMLFormElement) HTMLFormElement.prototype.submit.call(element);
    },
    updateShippingAddressAndSubmit: (form) => {
        window.SM.updateShippingAddress();
        if (form instanceof HTMLFormElement) HTMLFormElement.prototype.submit.call(form);
    },
    clearSelectionIfButton: (element, event, prefix, property, value = '') => {
        const button = event.target instanceof Element ? event.target.closest('button') : null;
        if (button?.id.startsWith(prefix)) window.SM.setDataValues(element, { [property]: value });
    },
    updateDataFromEvent: (element, event, acceptedName, property, detailKey) => {
        if (event.detail?.name === acceptedName) window.SM.setDataValues(element, { [property]: window.SM.get(event.detail, detailKey, '') || '' });
    },
    updateDateRange: (from, to, start, end) => {
        if (from) from.value = start;
        if (to) to.value = end;
    },
    formatMoneyInput: (element) => {
        if (!(element instanceof HTMLInputElement) || element.value.trim() === '') return;
        const amount = Number(element.value);
        if (Number.isFinite(amount) && amount >= 0) element.value = amount.toFixed(2);
    },
    openUrl: (url) => window.open(url, '_blank', 'noopener,noreferrer'),
    preventIf: (event, condition) => { if (condition) event.preventDefault(); },
    trackAndCall: (element, paths, method, args = []) => {
        const data = Alpine.$data(element);
        (paths || []).forEach((path) => window.SM.get(data, path));
        return window.SM.callAlpineMethod(element, method, args);
    },
    trackAndCallNextTick: (element, paths, method, args = []) => {
        const data = Alpine.$data(element);
        (paths || []).forEach((path) => window.SM.get(data, path));
        return Alpine.nextTick(() => window.SM.callAlpineMethod(element, method, args));
    },
    trackAndCallPreviewCanvas: (canvas, imageUrl) => {
        const data = Alpine.$data(canvas);
        const edits = {
            rotation: data.editRotation,
            top: data.editCropTop,
            right: data.editCropRight,
            bottom: data.editCropBottom,
            left: data.editCropLeft,
        };
        return window.SM.callAlpineMethod(canvas, 'renderPreviewCanvas', [canvas, imageUrl, edits]);
    },
    trackAndCallEditorCanvas: (canvas) => {
        const data = Alpine.$data(canvas);
        const draft = data.editorDraft || {};
        ['rotation', 'top', 'right', 'bottom', 'left'].forEach((key) => window.SM.get(draft, key));
        return window.SM.callAlpineMethod(canvas, 'renderEditorCanvas', [canvas]);
    },
    nextTickMethod: (element, method, args = []) => Alpine.nextTick(() => window.SM.callAlpineMethod(element, method, args)),
    nextFrameMethod: (element, method, args = []) => requestAnimationFrame(() => window.SM.callAlpineMethod(element, method, args)),
    initializeDataMethods: (element, methods) => (methods || []).forEach((method) => window.SM.callAlpineMethod(element, method, [])),
    callDataMethodWhen: (element, condition, method, args = []) => condition ? window.SM.callAlpineMethod(element, method, args) : undefined,
    applyExpenseItemsAndExpand: (element, event) => {
        const data = Alpine.$data(element);
        event.detail.appliedCount = data.applyAiItems(event.detail.items);
        if (event.detail.appliedCount > 0) data.expanded = true;
    },
    applyInvoiceAllocationCalculation: (form, detail) => {
        const data = Alpine.$data(form);
        data.applyCalculatorValues(detail);
        form.dataset.allocationChanged = '1';
    },
    syncAllocationPlan: (form, changed, values, total) => {
        if (changed) form.dataset.allocationChanged = '1';
        form.dispatchEvent(new CustomEvent('allocation-plan-updated', {
            bubbles: true,
            detail: { key: 'invoice', values: { ...values }, funding: total },
        }));
    },
    callDataMethods: (element, calls) => (calls || []).forEach(([method, args = []]) => window.SM.callAlpineMethod(element, method, args)),
    toggleDataAndCall: (element, key, method) => {
        const data = Alpine.$data(element);
        data[key] = !data[key];
        if (data[key]) window.SM.callAlpineMethod(element, method, []);
    },
    dispatchAndSetData: (element, eventName, key, value) => {
        window.SM.setDataValues(element, { [key]: value });
        element.dispatchEvent(new CustomEvent(eventName, { bubbles: true }));
    },
    submitOnce: (form) => {
        if (!(form instanceof HTMLFormElement)) return;
        const data = Alpine.$data(form);
        if (data.isSubmitting) return;
        data.isSubmitting = true;
        HTMLFormElement.prototype.submit.call(form);
    },
    syncShippingAddressModes: (element) => {
        const data = Alpine.$data(element);
        if (data.useOrganisationShippingAddress) data.shippingSameBilling = false;
        if (data.shippingSameBilling) data.useOrganisationShippingAddress = false;
        window.SM.updateShippingAddress();
    },
    hasChangedObjectValues: (values, initial) => Object.keys(values || {}).some((key) => values[key] !== (initial || {})[key]),
    hasShippingFieldError: (errors, index, section) => Object.keys(errors || {}).some((key) => {
        const prefix = `shipping_methods.${index}.${section}`;
        return section === 'weight_tiers'
            ? key.startsWith(`${prefix}.`) || key === `shipping_methods.${index}.cubic_divisor` || key === `shipping_methods.${index}.calculated_packaging_cost`
            : key.startsWith(prefix);
    }),
    activePackageCount: (packages) => (Array.isArray(packages) ? packages : []).filter((item) => item?.is_active).length,
    hasActivePackages: (packages) => window.SM.activePackageCount(packages) > 0,
    previousGstRatesLabel: (rates) => (Array.isArray(rates) ? rates : []).map((rate) => (Number(rate) * 100).toFixed(2) + '%').join(', '),
    matchingNewsletterLinks: (links, search) => (Array.isArray(links) ? links : []).filter((link) => (String(link.title || '') + ' ' + String(link.type || '')).toLowerCase().includes(String(search || '').toLowerCase())).slice(0, 30),
    hasMatchingNewsletterLinks: (links, search) => window.SM.matchingNewsletterLinks(links, search).length > 0,
    fileTypeLabel: (value) => String(value || '').replace(/\(.*?\)/g, '').trim(),
    splitParagraphs: (value) => String(value || '').replace(/\r\n?/g, '\n').split(/\n\s*\n/).filter((text) => text.trim()),
    parseMoney: (value) => Number.parseFloat(String(value || '').replace(/[$,]/g, '')) || 0,
    aiMode: (element, fieldName) => {
        const field = element.closest('form')?.querySelector(`[name="${fieldName}"]`);
        return field && String(field.value || '').trim() ? 'improve' : 'write';
    },
    shouldShowPayAtDoor: (registration, price, type, format, locations, selectedLocationId) => registration === 'tickets'
        && window.SM.parseMoney(price) > 0
        && (type === 'physical' || format === 'course')
        && (Array.isArray(locations) ? locations : []).some((location) => String(location.id) === String(selectedLocationId)
            && String(location.name || '').trim().toLowerCase() !== 'online'),
    syncAiAction: (element, fieldName, contextMethod) => {
        const data = Alpine.$data(element);
        element.dataset.aiContext = JSON.stringify(data[contextMethod]());
        element.dataset.aiMode = window.SM.aiMode(element, fieldName);
    },
    slug: (value) => String(value || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/-+/g, '-').replace(/^[-_]+|[-_]+$/g, ''),
    setFilenameFromFile: (element, event) => {
        const data = Alpine.$data(element);
        if (!data.filename) data.filename = event.target.files?.[0]?.name || '';
    },
    setNewsletterNoteFromAi: (element, event) => {
        const data = Alpine.$data(element);
        const html = event.detail?.html || '';
        if (!html) return;
        data.noteHtml = event.detail?.mode === 'append' && data.noteHtml.trim()
            ? data.noteHtml + '<p><br></p>' + html
            : html;
    },
    selectNewsletterLink: (link) => {
        const url = document.getElementById('newsletter-note-link-url');
        const label = document.getElementById('newsletter-note-link-label');
        if (url) url.value = link.url;
        if (label && !label.value) label.value = link.title;
    },
    clearFieldAndRun: (fieldId, method, args = []) => {
        const field = document.getElementById(fieldId);
        if (field) field.value = '';
        if (typeof window[method] === 'function') window[method](...args);
    },
    resetNewsletterImage: (fieldId, mode) => window.SM.clearFieldAndRun(fieldId, 'SMNewsletterPhotoPreview', [mode]),
    nextTickShowModal: (element) => Alpine.nextTick(() => element.showModal()),
    addAttachmentFiles: (element, event) => {
        element.classList.remove('border-primary-color', 'bg-sky-50');
        window.SM.callAlpineMethod(element, 'addAttachmentFiles', [event.dataTransfer.files]);
    },
    positionProductSuggestionsIfOpen: (element, item, activeId, inputId) => {
        const data = Alpine.$data(element);
        const state = data.productSearchState(item);
        if (state.open && state.activeId === activeId) data.positionProductSuggestions(item, document.getElementById(inputId));
    },
    closeProductSuggestionsOnOutside: (element, event, item, activeId, menuId, inputId) => {
        const data = Alpine.$data(element);
        const state = data.productSearchState(item);
        if (!state.open || state.activeId !== activeId) return;
        const target = event.target;
        const menu = document.getElementById(menuId);
        const input = document.getElementById(inputId);
        if (!(target instanceof Node) || (!(input instanceof Node) || !input.contains(target)) && (!(menu instanceof Node) || !menu.contains(target))) {
            data.closeProductSuggestions(item, inputId);
        }
    },
    runProductAiAction: (element, fieldName, contextMethod) => {
        const data = Alpine.$data(element);
        const field = element.closest('form')?.querySelector(`[name="${fieldName}"]`);
        element.dataset.aiContext = JSON.stringify(data[contextMethod]());
        element.dataset.aiMode = field && String(field.value || '').trim() ? 'improve' : 'write';
    },
    dataHasValue: (element, fieldName) => {
        const field = element.closest('form')?.querySelector(`[name="${fieldName}"]`);
        return Boolean(field && String(field.value || '').trim());
    },
    formTotal: (element) => {
        const form = element.closest('form');
        return form ? Alpine.$data(form).total : 0;
    },
    limitNumericInput: (element, length) => { element.value = String(element.value || '').replace(/\D+/g, '').slice(0, length); },
    updateViewportWidth: (element) => window.SM.setDataValues(element, { pickListViewportWidth: window.innerWidth }),
    formatStockTotalCost: (element) => {
        if (element.value === '' || !Number.isFinite(Number(element.value))) return;
        const value = Number(element.value).toFixed(2);
        element.value = value;
        window.SM.setDataValues(element, { totalCost: value });
    },
    closeDetailsAndConfirmDelete: (button, ...args) => {
        const details = button.closest('details');
        if (details) details.open = false;
        return window.SM.confirmDelete(...args);
    },
    closeDialogAndCallGlobal: (element, method) => {
        element.closest('dialog')?.close();
        return window.SM.callGlobal(method, []);
    },
    openAncestorDetails: (target) => {
        let section = target instanceof Element ? target.closest('details') : null;
        while (section) {
            section.open = true;
            section = section.parentElement?.closest('details') || null;
        }
    },
    callAndOpenDetails: (element, method) => {
        window.SM.callAlpineMethod(element, method, []);
        const details = element.closest('details');
        if (details) details.open = true;
    },
    initializeDialog: (dialog, shouldOpen, focusSelector = '') => Alpine.nextTick(() => {
        if (!shouldOpen || !(dialog instanceof HTMLDialogElement)) return;
        dialog.showModal();
        if (focusSelector) dialog.querySelector(focusSelector)?.focus();
    }),
    confirmSubmitButton: (button, title, content, label) => window.SM.confirm(title, content, label).then((result) => {
        if (result?.isConfirmed && button.form instanceof HTMLFormElement) button.form.requestSubmit(button);
    }),
    submitOrphanScan: (form, event) => {
        event.preventDefault();
        const data = Alpine.$data(form);
        if (data.confirmedSubmit) {
            data.confirmedSubmit = false;
            HTMLFormElement.prototype.submit.call(form);
            return;
        }
        window.SM.confirm('Confirm action', 'Run orphaned file scan now?', 'Run Scan').then((result) => {
            if (!result?.isConfirmed) return;
            data.confirmedSubmit = true;
            form.requestSubmit();
        });
    },
    copyInvoicePaymentLink: async (url, csrfToken) => {
        try {
            const response = await window.fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
            });
            const data = await response.json();
            if (!response.ok || !data?.url) throw new Error('Unable to generate payment link.');
            await window.SM.copyToClipboard(data.url);
            window.SM.alert('Payment Link Copied', 'Invoice payment link copied to clipboard.', 'success');
        } catch (error) {
            window.SM.alert('Copy Failed', error?.message || 'Unable to generate payment link.', 'danger');
        }
    },
    setDataValuesThenCall: (element, values, method, args = []) => {
        window.SM.setDataValues(element, values);
        return window.SM.callAlpineMethod(element, method, args);
    },
    preventAndCallWhen: (element, event, condition, method, args = []) => {
        if (!condition) return;
        event.preventDefault();
        return window.SM.callAlpineMethod(element, method, args);
    },
    scrollIntoViewWhen: (element, condition) => { if (condition) element.scrollIntoView({ block: 'nearest' }); },
    handleAllocationAttention: (element, needsAttention) => {
        if (!needsAttention) return;
        const data = Alpine.$data(element);
        data.allocationOpen = true;
        if (!data.allocationTouched) data.enabled = true;
    },
    refreshDefaultsWhenDisabled: (element, enabled) => { if (!enabled) window.SM.callAlpineMethod(element, 'refreshDefaults', []); },
    replaceWorkshopPlaceholder: (select) => {
        if (!select.value) return;
        window.SM.callAlpineMethod(select, 'insertText', [select.value]);
        select.value = '';
    },
    dispatchWindowEvent: (name, detail = {}) => window.dispatchEvent(new CustomEvent(name, { detail })),
    clearPhotoSelection: () => {
        window.dispatchEvent(new CustomEvent('select-all-photo-media', { detail: { checked: false } }));
        window.dispatchEvent(new CustomEvent('photo-existing-selection-changed'));
    },
    saveWorkshopPhotos: (form) => {
        const data = Alpine.$data(form);
        data.saving = true;
        axios.post(form.action, new FormData(form), { headers: { Accept: 'application/json' } })
            .then(() => window.SM.notice('Photos updated', 'Selected media changes were saved.', 'success', { toast: true }))
            .catch((error) => window.SM.notice('Save failed', error.response?.data?.message || 'Could not save selected media.', 'danger', { toast: true }))
            .finally(() => { data.saving = false; });
    },
    applyInvoiceEmailTemplate: (element) => {
        const data = Alpine.$data(element);
        if (data.$refs?.emailTemplatePending) data.$refs.emailTemplatePending.value = '1';
        return window.SM.callAlpineMethod(element, 'closeInvoiceEmailModal', []);
    },
    closeAgeHelp: (element) => {
        const data = Alpine.$data(element);
        if (!data.ageHelpOpen) return;
        data.ageHelpOpen = false;
        window.SM.focus(data.$refs?.ageHelpButton);
    },
    initializeTicketPayment: (element) => {
        const data = Alpine.$data(element);
        window.SM.callAlpineMethod(element, 'startHoldTimer', []);
        if (data.voucherDialogOpen) Alpine.nextTick(() => window.SM.focus(data.$refs?.voucherInput));
    },
    openOrderItemActions: (element, itemId, actions) => {
        if (actions?.cancel) window.SM.callAlpineMethod(element, 'openCancel', [itemId]);
        if (Array.isArray(actions?.tracking)) window.SM.callAlpineMethod(element, 'openTracking', [itemId, ...actions.tracking]);
        if (Array.isArray(actions?.collection)) window.SM.callAlpineMethod(element, 'openCollection', [itemId, ...actions.collection]);
    },
    allocateRemainingFromButton: (element, categoryId, editableDefaults) => {
        if (editableDefaults) Alpine.$data(element).enabled = true;
        return window.SM.callAlpineMethod(element, 'allocateRemaining', [categoryId]);
    },
    updateWorkshopAllocationInputs: (element, detail, workshopId) => {
        if (String(detail?.id) !== String(workshopId)) return;
        window.SM.setDataValues(element, { participants: detail.participants, hours: detail.hours });
    },
    syncWorkshopAllocationPlan: (form, changed, values, total, key, initialChanged) => {
        form.dataset.allocationChanged = changed || initialChanged ? '1' : '0';
        form.dispatchEvent(new CustomEvent('allocation-plan-updated', {
            bubbles: true,
            detail: { key, values: { ...values }, funding: total, inspect: false },
        }));
    },
    updatePhotoCounts: (element, event) => {
        const detail = event.detail || {};
        window.SM.setDataValuesThenCall(element, {
            selectedCount: Number(detail.count || 0),
            totalCount: Number(detail.total || 0),
        }, 'refreshExistingNames');
    },
    validateSearchScopes: (form, event) => {
        const data = Alpine.$data(form);
        data.searchScopeError = !data.searchProducts && !data.searchWorkshops;
        if (data.searchScopeError) event.preventDefault();
    },
    validateSearchScopeChange: (element, autoSubmit) => {
        const data = Alpine.$data(element);
        data.searchScopeError = !data.searchProducts && !data.searchWorkshops;
        if (autoSubmit && !data.searchScopeError) Alpine.nextTick(() => data.$refs?.searchForm?.requestSubmit());
    },
    validateSearchAndOpenFilters: (form, event) => {
        const data = Alpine.$data(form);
        data.searchScopeError = !data.searchProducts && !data.searchWorkshops;
        if (data.searchScopeError) {
            event.preventDefault();
            form.querySelector('[data-open-dialog=search-filters]')?.click();
            return;
        }
        form.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    },
    clearSearchFilters: (form) => form.querySelectorAll('[data-search-filter]').forEach((input) => { input.value = ''; }),
    clearUiSearch: (button, advancedActive, hadValue) => {
        const form = button.closest('form');
        const data = Alpine.$data(button);
        data.search = '';
        if (advancedActive) {
            form?.querySelectorAll('[data-advanced-search-param]').forEach((input) => input.remove());
            data.advancedActive = false;
            button.dispatchEvent(new CustomEvent('clear-advanced-search', { bubbles: true }));
            Alpine.nextTick(() => form?.requestSubmit());
        } else if (hadValue) {
            Alpine.nextTick(() => form?.requestSubmit());
        }
    },
    uploadFilesFromInput: (input, method, clearAfterChange) => {
        window.SM.callAlpineMethod(input, method, [input.files]);
        if (clearAfterChange) input.value = '';
    },
    formatLineQuantityHandler: (element, item) => {
        if (item.kind === 'travel') {
            item.travel_hours = item.quantity;
            window.SM.updateWorkshopLine(item);
        }
        window.SM.callAlpineMethod(element, 'serializeLineItems', []);
    },
    searchProductVariantChanged: (element, item, value, index) => {
        item.source_variant_id = value;
        window.SM.callAlpineMethod(element, 'applyProductSelection', [index]);
    },
    callGlobal: (name, args = []) => typeof window[name] === 'function' ? window[name](...args) : undefined,
    toggleBodyScroll: (element, condition) => document.body.classList.toggle('overflow-hidden', Boolean(condition)),
    setBodyScrollLocks: (locked) => {
        document.documentElement.classList.toggle('overflow-hidden', Boolean(locked));
        document.body.classList.toggle('overflow-hidden', Boolean(locked));
    },
    shopCartPage: (config = null) => window.shopCartPage(config || window.shopCartPageConfig || {}),
    shopAdminOrderEditor: (config) => window.shopAdminOrderEditor(config),
    navigateTo: (url) => { window.location.href = url; },
    submitFormById: (id) => {
        const form = document.getElementById(id);
        if (form instanceof HTMLFormElement) HTMLFormElement.prototype.submit.call(form);
    },
    refreshLineAndSerialize: (element, item, price) => {
        if (price) window.SM.refreshLinePrice(item);
        else window.SM.refreshLineQuantity(item);
        window.SM.callAlpineMethod(element, 'serializeLineItems', []);
    },
    handleLineQuantityInput: (element, item) => {
        if (item.kind === 'travel') {
            item.travel_hours = item.quantity;
            window.SM.updateWorkshopLine(item);
        }
        window.SM.callAlpineMethod(element, 'serializeLineItems', []);
    },
    updateUnitPriceInput: (element, item) => {
        item.auto_pricing = false;
        delete item.details_json.inclusive_unit_price;
        delete item.details_json.price_rounding;
        window.SM.callAlpineMethod(element, 'serializeLineItems', []);
    },
});

window.SM.productAllocationFields = (fixed, percent) => ({
    fixed,
    percent,
    total(values) {
        return Object.values(values).reduce((sum, value) => sum + (Number(value) || 0), 0);
    },
});
document.addEventListener('alpine:init', () => {
    Alpine.magic('SM', () => window.SM);
    Alpine.magic('SMMediaPicker', () => window.SMMediaPicker);
});

window.SM.confirmAndSubmit = (form, title, content, button) => window.SM.confirm(
    title,
    content,
    button,
    (confirmed) => {
        if (confirmed && form instanceof HTMLFormElement) {
            HTMLFormElement.prototype.submit.call(form);
        }
    },
);

window.SM.nextTickMethod = (element, method, ...args) => Alpine.nextTick(() => {
    const data = Alpine.$data(element);
    if (typeof data?.[method] === 'function') {
        data[method].apply(data, args);
    }
});

window.SM.nextTickFocus = (element) => Alpine.nextTick(() => element?.focus());

window.SM.nextFrameMethod = (element, method, ...args) => requestAnimationFrame(() => {
    const data = Alpine.$data(element);
    if (typeof data?.[method] === 'function') {
        data[method].apply(data, args);
    }
});

window.SM.delaySet = (element, key, value, delay) => setTimeout(() => {
    Alpine.$data(element)[key] = value;
}, delay);

window.SM.registerImagePaste = (element, enabled, method) => window.SMImagePaste.register(element, (files) => {
    if (enabled) {
        window.SM.callAlpineMethod(element, method, [files]);
    }
});

window.SM.callAlpineMethod = (element, method, args = []) => {
    const data = Alpine.$data(element);
    if (typeof data?.[method] === 'function') {
        return data[method].apply(data, args);
    }
};

window.SM.handlePageUploadDrop = (element, event, action, enabled, method) => {
    const types = Array.from(event.dataTransfer?.types || []);
    if (!types.includes('Files')) return;

    const data = Alpine.$data(element);
    if (action === 'dragenter') {
        event.preventDefault();
        if (enabled) data.pageUploadDragDepth++;
    } else if (action === 'dragover') {
        event.preventDefault();
        event.dataTransfer.dropEffect = enabled ? 'copy' : 'none';
    } else if (action === 'drop') {
        const alreadyHandled = event.defaultPrevented;
        event.preventDefault();
        data.pageUploadDragDepth = 0;
        if (!alreadyHandled && enabled) window.SM.callAlpineMethod(element, method, [event.dataTransfer.files]);
    } else {
        data.pageUploadDragDepth = 0;
    }
};

window.SM.resetPageUpload = (element) => {
    Alpine.$data(element).pageUploadDragDepth = 0;
};

window.SM.handleImageDrop = (element, event, action, enabled, method) => {
    const types = Array.from(event.dataTransfer?.types || []);
    if (!types.includes('Files')) return;

    event.preventDefault();
    if (action === 'dragover') {
        if (enabled) element.classList.add('ring-2', 'ring-primary-color', 'border-primary-color');
    } else if (action === 'drop') {
        element.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color');
        if (enabled) window.SM.callAlpineMethod(element, method, [event.dataTransfer.files]);
    } else if (action === 'dragleave') {
        element.classList.remove('ring-2', 'ring-primary-color', 'border-primary-color');
    }
};

window.SM.normalizeGroupList = (value) => value
    .split(/[\s,]+/)
    .map((group) => group.toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/-+/g, '-').replace(/^[-_]+|[-_]+$/g, ''))
    .filter(Boolean)
    .join(', ');

window.SM.nextTickToolbar = (element) => Alpine.nextTick(() => window.SM.initToolbar(element));
window.SM.setElementValueAfterTick = (element, value) => Alpine.nextTick(() => { element.value = value; });
window.SM.requestSubmitAfterTick = (form) => Alpine.nextTick(() => form.requestSubmit());
window.SM.openDialogAfterTick = (dialog, shouldOpen, focusSelector = null) => {
    if (!shouldOpen) return;
    Alpine.nextTick(() => {
        dialog.showModal();
        if (focusSelector) dialog.querySelector(focusSelector)?.focus();
    });
};
window.SM.closeOpenDialogs = (element) => element.closest('form')?.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
window.SM.openInNewTab = (url) => window.open(url, '_blank', 'noopener,noreferrer');
window.SM.navigateTo = (url) => { window.location.href = url; };
window.SM.selectedOptionLabels = (options, selected) => options
    .filter((option) => selected.includes(option.value))
    .map((option) => option.label)
    .join(', ');
window.SM.hasValue = (items, property, value) => items.some((item) => item[property] === value);
window.SM.countMatchingValue = (items, property, value) => items.filter((item) => item[property] === value).length;
window.SM.countTruthy = (items, property) => items.filter((item) => item[property]).length;
window.SM.hasPositiveValue = (items) => items.some((value) => Number(value) > 0);
window.SM.countWithAnyProperty = (items, properties) => items.filter((item) => properties.some((property) => item[property])).length;
window.SM.hasKeyPrefix = (values, prefix) => Object.keys(values || {}).some((key) => key.startsWith(prefix));
window.SM.shippingPackageSummary = (method) => {
    const active = window.SM.countTruthy(method.packages || [], 'is_active');
    const total = (method.packages || []).length;
    return `${active} active / ${total} box option${total === 1 ? '' : 's'}`;
};
window.SM.canMoveProductDetail = (details, detail, index) => {
    const movable = (details || []).filter((row) => String(row.key || '').trim().toLowerCase() !== 'sku');
    return String(detail.key || '').trim().toLowerCase() !== 'sku' && index < movable.length - 1;
};
window.SM.productQuantityUnitFor = (catalog, productId) => {
    const product = (catalog || []).find((option) => String(option.id) === String(productId));
    return product ? window.SM.productQuantityUnit(product) : '';
};
window.SM.recipeVariantName = (item, variantId) => {
    const variant = (item?.variants || []).find((option) => String(option.id) === String(variantId));
    return variant?.name || item?.base_option_name || item?.name || '';
};
window.SM.hasPositiveColumnAllocation = (columns, values, categoryId) =>
    columns.some((column) => Number(values(column)?.[categoryId]?.amount || 0) > 0);
window.SM.hasUnallocatedColumnRemainder = (columns, total) =>
    columns.some((column) => {
        const result = total(column);
        return !result.unknown && result.remaining > 0;
    });

window.SM.openFaqOnInvalid = (element, key, event) => {
    const data = Alpine.$data(element);
    data.openKey = key;
    window.SM.nextTickFocus(event.target);
};

window.SM.faqEditor = (faqs) => ({
    faqs: Array.isArray(faqs) ? faqs : [],
    nextKey: 0,
    openKey: null,
    init() {
        this.faqs = this.faqs.map((faq) => ({
            ...faq,
            _key: this.nextKey++,
            show_on_index: [true, 1, '1'].includes(faq.show_on_index),
        }));
    },
    addFaq() {
        const faq = { _key: this.nextKey++, question: '', answer: '', show_on_index: false };
        this.faqs.push(faq);
        this.openKey = faq._key;
        Alpine.nextTick(() => this.$refs.rows.lastElementChild?.querySelector('input[type=text]')?.focus());
    },
    moveFaq(index, direction) {
        const target = index + direction;
        if (target < 0 || target >= this.faqs.length) return;
        const item = this.faqs.splice(index, 1)[0];
        this.faqs.splice(target, 0, item);
    },
    removeFaq(key) {
        if (this.faqs.length <= 1) return;
        window.SM.confirm('Remove FAQ?', 'This question will be removed when you save the content.', 'Remove', (confirmed) => {
            if (!confirmed) return;
            this.faqs = this.faqs.filter((faq) => faq._key !== key);
            if (this.openKey === key) this.openKey = null;
        });
    },
});

window.SM.galleryViewer = (items, thumbnailLimit, viewerId) => ({
    gallery: Array.isArray(items) ? items : [],
    thumbnailLimit,
    modelIndex: -1,
    openGallery(index = 0) {
        if (this.gallery.length) this.modelIndex = Math.max(0, Math.min(index, this.gallery.length - 1));
    },
    openFromEvent(event) {
        if (event.detail?.id === viewerId) this.openGallery(event.detail?.index ?? 0);
    },
    closeGallery() { this.modelIndex = -1; },
    handleEscape() { if (this.modelIndex !== -1) this.closeGallery(); },
    handlePreviousKey() { if (this.modelIndex !== -1) this.previousImage(); },
    handleNextKey() { if (this.modelIndex !== -1) this.nextImage(); },
    thumbnailItems() {
        const limit = Number(this.thumbnailLimit);
        return Number.isInteger(limit) && limit > 0 ? this.gallery.slice(0, limit) : this.gallery;
    },
    remainingThumbnails() {
        const limit = Number(this.thumbnailLimit);
        return Number.isInteger(limit) && limit > 0 ? Math.max(0, this.gallery.length - limit) : 0;
    },
    previousImage() {
        if (this.gallery.length > 1) this.modelIndex = this.modelIndex <= 0 ? this.gallery.length - 1 : this.modelIndex - 1;
    },
    nextImage() {
        if (this.gallery.length > 1) this.modelIndex = this.modelIndex >= this.gallery.length - 1 ? 0 : this.modelIndex + 1;
    },
});

window.SM.presetViews = () => ({
    overflow: false,
    atStart: true,
    atEnd: false,
    refresh() {
        const nav = this.$refs.views;
        this.overflow = nav.scrollWidth > nav.clientWidth + 2;
        this.atStart = nav.scrollLeft < 2;
        this.atEnd = nav.scrollLeft + nav.clientWidth >= nav.scrollWidth - 2;
    },
    move(direction) {
        this.$refs.views.scrollBy({ left: direction * this.$refs.views.clientWidth * 0.8, behavior: 'smooth' });
    },
});

window.SM.galleryEditor = () => ({
    dragGalleryIndex: -1,
    dragGalleryDropIndex: -1,
    galleryDropActive: false,
    openGalleryItem(index) {
        if (this.dragGalleryIndex === -1) Alpine.store('modelIndex', index);
    },
    startGalleryItemDrag(index, event, fileName) {
        this.dragGalleryIndex = index;
        this.dragGalleryDropIndex = index;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', fileName);
    },
    setGalleryDropIndex(index) { this.dragGalleryDropIndex = index; },
    dropGalleryItem(index) {
        window.SM.reorderGalleryItem(this.dragGalleryIndex, index);
        this.resetGalleryDrag();
    },
    resetGalleryDrag() {
        this.dragGalleryIndex = -1;
        this.dragGalleryDropIndex = -1;
    },
});

window.SM.autocompleteEditor = (config = {}) => ({
    rawValue: config.value || '',
    options: Array.isArray(config.options) ? config.options : [],
    moneyFormat: Boolean(config.moneyFormat),
    showOnFocus: Boolean(config.showOnFocus),
    modelBinding: config.modelBinding || '',
    inputHandler: config.inputHandler || '',
    hasTyped: false,
    suppressRefresh: false,
    filtered: [],
    open: false,
    selectedIndex: -1,
    dropdownStyle: '',
    dropdownListMaxHeight: 240,
    dropdownOpenUp: false,
    init() {
        const initial = this.readBoundValue();
        if (initial !== undefined && initial !== null && initial !== '') this.rawValue = initial;
        this.$watch('rawValue', (value) => this.writeBoundValue(value));
        this.$watch('open', (value) => {
            if (value) this.$nextTick(() => this.updateDropdownPosition());
            else this.dropdownStyle = '';
        });
        this.refresh();
    },
    readBoundValue() {
        if (!this.modelBinding) return undefined;
        try { return Alpine.evaluate(this.$el, this.modelBinding); } catch (_) { return undefined; }
    },
    writeBoundValue(value) {
        if (!this.modelBinding) return;
        try {
            Alpine.evaluate(this.$el, this.modelBinding + ' = $smValue', { scope: { $smValue: value } });
        } catch (_) {}
    },
    syncBoundValue() {
        const incoming = this.readBoundValue();
        if (!this.hasTyped && incoming !== undefined && String(this.rawValue ?? '') !== String(incoming ?? '')) {
            this.rawValue = incoming ?? '';
        }
    },
    handleInput(event) {
        if (event.target?._smAiUpdate === true) {
            this.rawValue = event.target.value;
            this.hasTyped = false;
            this.suppressRefresh = false;
            this.filtered = [];
            this.selectedIndex = -1;
            this.open = false;
            return;
        }
        this.hasTyped = true;
        this.refresh();
        if (this.inputHandler) {
            try { Alpine.evaluate(this.$el, this.inputHandler, { scope: { $event: event } }); } catch (_) {}
        }
    },
    focus(showSuggestions) {
        if (showSuggestions) {
            this.hasTyped = true;
            this.refresh();
        } else {
            this.open = false;
        }
    },
    blur() {
        this.formatMoney();
        window.SM.delaySet(this.$el, 'open', false, 120);
    },
    positionIfOpen() { if (this.open) this.updateDropdownPosition(); },
    refresh() {
        if (this.suppressRefresh) {
            this.suppressRefresh = false;
            this.filtered = [];
            this.selectedIndex = -1;
            this.open = false;
            this.dropdownStyle = '';
            return;
        }
        if (!this.hasTyped) {
            this.filtered = [];
            this.selectedIndex = -1;
            this.open = false;
            this.dropdownStyle = '';
            return;
        }
        const needle = String(this.rawValue || '').toLowerCase().trim();
        if (needle === '') {
            this.filtered = this.showOnFocus ? this.options.slice(0, 8) : [];
        } else {
            this.filtered = this.options.filter((option) => option.toLowerCase().includes(needle)).slice(0, 8);
        }
        this.selectedIndex = this.filtered.length > 0 ? 0 : -1;
        this.open = this.filtered.length > 0;
    },
    move(step) {
        if (!this.open) this.refresh();
        if (!this.filtered.length) return;
        this.selectedIndex = (this.selectedIndex + step + this.filtered.length) % this.filtered.length;
    },
    choose(value) {
        this.suppressRefresh = true;
        this.rawValue = value;
        this.open = false;
        this.selectedIndex = -1;
        this.dropdownStyle = '';
        this.$nextTick(() => {
            const input = this.$refs.inputEl;
            if (!input) return;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    },
    updateDropdownPosition() {
        const input = this.$refs.inputEl;
        if (!this.open || !input) {
            this.dropdownStyle = '';
            return;
        }
        const rect = input.getBoundingClientRect();
        const viewportPadding = 8;
        const gap = 4;
        const preferredMaxHeight = 240;
        const spaceBelow = window.innerHeight - rect.bottom - viewportPadding - gap;
        const spaceAbove = rect.top - viewportPadding - gap;
        const availableHeight = spaceBelow < preferredMaxHeight && spaceAbove > spaceBelow ? spaceAbove : spaceBelow;
        this.dropdownOpenUp = spaceBelow < preferredMaxHeight && spaceAbove > spaceBelow;
        this.dropdownListMaxHeight = Math.max(40, Math.min(preferredMaxHeight, Math.floor(availableHeight)));
        const left = Math.max(viewportPadding, Math.min(rect.left, window.innerWidth - rect.width - viewportPadding));
        this.dropdownStyle = this.dropdownOpenUp
            ? 'position: fixed; left: ' + left + 'px; width: ' + rect.width + 'px; bottom: ' + Math.max(viewportPadding, window.innerHeight - rect.top + gap) + 'px;'
            : 'position: fixed; left: ' + left + 'px; width: ' + rect.width + 'px; top: ' + (rect.bottom + gap) + 'px;';
    },
    applySelection() {
        if (!this.open) return;
        if (this.selectedIndex < 0 || this.selectedIndex >= this.filtered.length) {
            this.open = false;
            this.selectedIndex = -1;
            return;
        }
        this.choose(this.filtered[this.selectedIndex]);
    },
    formatMoney() {
        if (!this.moneyFormat) return;
        const raw = String(this.rawValue || '').trim();
        if (raw === '') return;
        const amount = parseFloat(raw);
        this.rawValue = Number.isFinite(amount) && amount >= 0 ? amount.toFixed(2) : '';
    },
});

window.SM.autocompleteInput = (element, event) => Alpine.$data(element).handleInput(event);
window.SM.autocompleteFocus = (element, showSuggestions) => Alpine.$data(element).focus(showSuggestions);
window.SM.autocompleteBlur = (element) => Alpine.$data(element).blur();
window.SM.autocompletePosition = (element) => Alpine.$data(element).positionIfOpen();
window.SM.syncAutocompleteValue = (element) => Alpine.$data(element).syncBoundValue();

window.SM.workshopLineFields = (priceInclusive) => ({
    priceInclusive: Boolean(priceInclusive),
    get planPricing() { return this.selectedPricingPlan?.pricing ?? null; },
    init() {
        if (this.item.kind === 'travel') window.SM.hydrateTravelLine(this.item);
        if (this.item.kind === 'multi_travel') window.SM.hydrateMultiTravelLine(this.item);
        this.syncPlanPricing();
        if (['multi_workshop', 'multi_travel'].includes(this.item.kind)) window.SM.updateWorkshopLine(this.item);
    },
    hasPlanCategory(categoryId, kind) {
        return (this.planPricing?.rules || []).some((rule) =>
            Number(rule.category_id) === Number(categoryId)
            && (rule.suppliable || rule.basis === 'venue_hour')
            && (kind === 'travel' ? rule.basis === 'travel' : rule.basis !== 'travel'));
    },
    hasSuppliedCategories(kind) {
        return (this.planPricing?.rules || []).some((rule) =>
            kind === 'travel'
                ? rule.basis === 'travel' && rule.suppliable
                : rule.basis !== 'travel' && (rule.suppliable || rule.basis === 'venue_hour'));
    },
    syncPlanPricing() {
        if (!this.planPricing) return;
        window.SM.initializePricingSupplies(this.item, this.planPricing);
        window.SM.registerLinePlan(this.item, this.planPricing, this.priceInclusive);
    },
    updateAndSerialize() {
        window.SM.updateWorkshopLine(this.item, this.planPricing, this.priceInclusive);
        this.serializeLineItems();
    },
    markTravelHoursChanged() {
        this.item.travel_hours_user_changed = true;
        this.item.travel_hours_defaulted = false;
        this.item.details_json.travel = this.item.details_json.travel || {};
        this.item.details_json.travel.hours_user_changed = true;
        this.item.details_json.travel.hours_defaulted = false;
        this.updateAndSerialize();
    },
});

window.SM.trackWorkshopRecommendationImpression = (url, csrfToken, sourceWorkshopId, workshopIds, placement) => {
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
        },
        body: JSON.stringify({
            source_workshop_id: sourceWorkshopId,
            workshop_ids: workshopIds,
            placement,
        }),
    }).catch(() => {});
};

window.SM.tagEditor = (config = {}) => ({
    tags: Array.isArray(config.tags) ? config.tags : [],
    draft: '',
    cursorIndex: 0,
    mixedTags: [],
    tagsBinding: config.tagsBinding || '',
    draftBinding: config.draftBinding || '',
    mixedBinding: config.mixedBinding || '',
    init() {
        const tags = this.readBinding(this.tagsBinding);
        const draft = this.readBinding(this.draftBinding);
        const mixed = this.readBinding(this.mixedBinding);
        if (Array.isArray(tags)) this.tags = tags;
        if (typeof draft === 'string') this.draft = draft;
        this.mixedTags = Array.isArray(mixed) ? mixed : [];
        this.cursorIndex = this.tags.length;
        this.sync();
        this.$watch('tags', (value) => this.writeBinding(this.tagsBinding, value));
        this.$watch('draft', (value) => this.writeBinding(this.draftBinding, value));
    },
    readBinding(binding) {
        if (!binding) return undefined;
        try { return Alpine.evaluate(this.$el, binding); } catch (_) { return undefined; }
    },
    writeBinding(binding, value) {
        if (!binding) return;
        try { Alpine.evaluate(this.$el, binding + ' = $smValue', { scope: { $smValue: value } }); } catch (_) {}
    },
    syncExternal() {
        const tags = this.readBinding(this.tagsBinding);
        const draft = this.readBinding(this.draftBinding);
        const mixed = this.readBinding(this.mixedBinding);
        if (Array.isArray(tags) && JSON.stringify(this.tags) !== JSON.stringify(tags)) {
            this.tags = tags;
            this.cursorIndex = Math.min(this.cursorIndex, tags.length);
        }
        if (typeof draft === 'string' && this.draft !== draft) this.draft = draft;
        if (Array.isArray(mixed) && JSON.stringify(this.mixedTags) !== JSON.stringify(mixed)) this.mixedTags = mixed;
    },
    isMixed(tag) {
        return this.mixedTags.some((mixed) => String(mixed).toLowerCase() === String(tag).toLowerCase());
    },
    sync() { this.$refs.value.value = this.tags.join(', '); },
    add(value = null) {
        const tag = String(value ?? this.draft).trim().replace(/,$/, '');
        if (tag === '') return;
        if (!this.tags.some((existing) => existing.toLowerCase() === tag.toLowerCase())) {
            this.tags.splice(this.cursorIndex, 0, tag);
            this.cursorIndex++;
        }
        this.draft = '';
        this.sync();
    },
    remove(index) {
        this.tags.splice(index, 1);
        if (this.cursorIndex > index) this.cursorIndex--;
        this.sync();
    },
    clear() {
        this.tags = [];
        this.draft = '';
        this.cursorIndex = 0;
        this.sync();
    },
    focusEntry(event) {
        if (!event.target.closest('button') && !event.target.closest('[data-tag-pill]')) {
            this.cursorIndex = this.tags.length;
            this.$refs.entry.focus();
        }
    },
    activatePill(index) {
        this.cursorIndex = index + 1;
        this.$refs.entry.focus();
    },
    handleEntryKey(event) {
        if (event.key === ',') {
            event.preventDefault();
            this.add();
        } else {
            this.handleKey(event);
        }
    },
    handleKey(event) {
        if (event.key === 'ArrowLeft' && this.draft === '') {
            event.preventDefault();
            this.cursorIndex = Math.max(0, this.cursorIndex - 1);
        } else if (event.key === 'ArrowRight' && this.draft === '') {
            event.preventDefault();
            this.cursorIndex = Math.min(this.tags.length, this.cursorIndex + 1);
        } else if (event.key === 'Backspace' && this.draft === '' && this.cursorIndex > 0) {
            event.preventDefault();
            this.remove(this.cursorIndex - 1);
        } else if (event.key === 'Delete' && this.draft === '' && this.cursorIndex < this.tags.length) {
            event.preventDefault();
            this.remove(this.cursorIndex);
        }
    },
});

window.SM.fileList = (name, deferredUploads, uploadFields = []) => ({
    dragActive: false,
    deferredUploads: Boolean(deferredUploads),
    uploadFiles(fileList) {
        const files = Array.from(fileList || []).filter((file) => file instanceof File);
        if (files.length === 0) return;
        if (this.deferredUploads) {
            window.SM.queuePendingFiles(name, files);
            this.dragActive = false;
            return;
        }
        window.SM.upload(files, (result) => {
            if (!result || result.success !== true || !Array.isArray(result.files)) return;
            const uploaded = result.files.map((item) => {
                const data = item?.data || {};
                const fileName = typeof data.name === 'string' ? data.name.trim() : '';
                if (fileName === '') return null;
                return {
                    name: fileName,
                    title: typeof item?.title === 'string' && item.title.trim() !== '' ? item.title.trim() : fileName,
                    mime_type: typeof data.mime_type === 'string' ? data.mime_type : '',
                    size: Number.isFinite(Number(data.size)) ? Number(data.size) : 0,
                    status: '',
                    url: '/media/download/' + encodeURIComponent(fileName),
                    thumbnail: '/thumbnails/unknown.webp',
                    file_type: 'File',
                    is_private: false,
                    password: null,
                    download_url: '/media/download/' + encodeURIComponent(fileName) + '?download=1',
                    can_delete: false,
                    delete_url: null,
                };
            }).filter((item) => item !== null);
            window.SM.appendFiles(name, uploaded);
            this.dragActive = false;
        }, [], {
            showModal: false,
            fields: uploadFields,
            onStart: () => { this.dragActive = true; },
            onSuccess: ({ files: uploadedFiles }) => {
                const count = Array.isArray(uploadedFiles) ? uploadedFiles.length : files.length;
                window.SM.notice('Upload complete', count + ' file' + (count === 1 ? '' : 's') + ' uploaded successfully.', 'success', {
                    toast: true,
                    timer: 2500,
                });
            },
        });
    },
    handleDrop(event) {
        this.dragActive = false;
        this.uploadFiles(event.dataTransfer?.files || []);
    },
    uploadFilesFromInput(input) {
        this.uploadFiles(input.files);
        input.value = '';
    },
});

window.SM.openFileListPicker = (name, options) => window.SMMediaPicker.open(
    window.SM.currentFileList(name).map((file) => file.name),
    options,
    (result) => window.SM.updateFiles(name, result),
);
window.SM.openSingleMediaPicker = (name, options) => {
    const input = document.getElementById(name);
    if (!input) return;
    window.SMMediaPicker.open(input.value, options, (value) => window.SM.updateMedia(name, value));
};

const showFlashNotifications = () => {
    document.querySelectorAll('[data-sm-flash-notification]').forEach((notification) => {
        const { title = '', message = '', type = 'info' } = notification.dataset;

        notification.remove();
        if (message !== '') {
            SM.alert(title, message, type);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showFlashNotifications, { once: true });
} else {
    showFlashNotifications();
}

document.addEventListener('alpine:init', () => {
    Alpine.store('ticketCancelModal', {
        open: false,
        formAction: '',
        ticketLabel: '',
        confirmationMessage: '',
        submitLabel: 'Cancel Ticket',
        processSquareRefund: false,
        showSquareRefund: false,
        emailCustomer: true,
        reasonDefault: 'The following ticket has been cancelled.',
        reason: 'The following ticket has been cancelled.',
    });
});

document.addEventListener('DOMContentLoaded', () => {
    SM.updateShippingAddress();
    SM.updateAllThumbnails();
});

// Format money fields without native inline event handlers.
document.addEventListener('blur', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.hasAttribute('data-money-format')) return;
    const raw = String(input.value || '').trim();
    if (raw === '') return;
    const amount = parseFloat(raw);
    const formatted = Number.isFinite(amount) && amount >= 0 ? amount.toFixed(2) : '';
    if (input.value === formatted) return;
    input.value = formatted;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}, true);
