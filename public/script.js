let SM = {
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

    alert: (title, text, type = 'info') =>{
        const data = {
            position: 'top-end',
            timer: 7000,
            toast: true,
            title: title,
            text: text,
            showConfirmButton: false,
            showCloseButton: true,
            customClass: {
                container: type,
            }
        }

        Swal.fire(data);
    },

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
            position: 'top',
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
        const config = {
            position: options.position || (toast ? 'top-end' : 'top'),
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
            customClass: options.customClass,
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

                axios.delete(deleteUrl)
                .then((response) => {
                    if(response.data.success){
                        SM.redirectIfSafe(response.data.redirect);
                    }
                })
                .catch(() => {
                    window.location.reload();
                });
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
            if (uploadToken) {
                formData.append('upload_token', uploadToken);
            }

            if (start === 0) {
                formData.append('filestart', 'true');
            } else {
                formData.append('fileappend', 'true');
            }

            if (title !== '') {
                formData.append('title', title);
            }

            const mediaUploadUrl = document.querySelector('meta[name="media-upload-url"]')?.getAttribute('content') || '/media';

            axios.post(mediaUploadUrl, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                    'Accept': 'application/json'
                },
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
            }).catch(() => {
                showError('An error occurred while uploading the file.');
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
        axios.get('/media/' + encodeURIComponent(name), {
            headers: {
                'Accept': 'application/json'
            }
        }).then((response) => {
            callback(response.data);
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
