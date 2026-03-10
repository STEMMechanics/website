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
        Swal.fire({
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
            callback(result.isConfirmed);
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

    confirmDelete: (token, title, content, urlOrForm, confirmButtonText = 'Delete') => {
        Swal.fire({
            position: 'top',
            icon: 'warning',
            iconColor: '#b91c1c',
            title: title,
            html: content,
            showCancelButton: true,
            confirmButtonText: confirmButtonText,
            confirmButtonColor: '#b91c1c',
            cancelButtonText: 'Cancel',
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

    upload: (files, callback, titles = []) => {
        let uploadedFiles = [];

        if(files.length === 0) {
            return;
        }

        const data = {
            title: "Checking...",
            text: "Please wait",
            imageUrl: "/loading.gif",
            imageHeight: 100,
            showConfirmButton: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
        }
        Swal.fire(data);

        const showError = (message) => {
            Swal.fire({
                position: 'top',
                icon: 'error',
                title: 'An error occurred',
                html: message,
                showConfirmButton: true,
                confirmButtonColor: '#b91c1c',
            }).then(() => {
                if(callback) {
                    callback({success: false});
                }
            });
        }

        for(const file of files) {
            if (file.size > SM.maxUploadSize()) {
                const size = SM.bytesToString(file.size);
                const maxSize = SM.bytesToString(SM.maxUploadSize());
                showError('The file size is too large (' + size + ').<br />Please upload a file less than ' + maxSize + '.');
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

                    let title = 'Uploading';
                    if(count > 1) {
                        title += ' ' + (idx + 1) + ' of ' + count;
                    }

                    Swal.update({
                        title: title + '...',
                        html: `${file.name} - ${percent}%`,
                    });
                }
            }).then((response) => {
                if (response.status === 200) {
                    if (response.data && response.data.upload_token) {
                        uploadToken = response.data.upload_token;
                    }

                    if (end >= file.size) {
                        uploadedFiles.push({ file: file, title: title, data: response.data });

                        if (idx === count - 1) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                html: count > 1 ? `Uploaded ${count} files successfully` : `${response.data.name || file.name} uploaded successfully`,
                                showConfirmButton: false,
                                timer: 3000
                            });

                            if (callback) {
                                window.setTimeout(() => {
                                    callback({ success: true, files: uploadedFiles });
                                }, 3000);
                            }

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

        // Capitalize the first letter of each word and make the rest lowercase
        str = str.replace(/\b\w+\b/g, (txt) => {
            return txt.charAt(0).toUpperCase() + txt.slice(1).toLowerCase();
        });

        // Replace "cdn" with "CDN"
        str = str.replace(/\bCdn\b/gi, "CDN");

        return str;
    },

    mediaDetails: (name, callback) => {
        axios.get('/media/' + name, {
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

        showAddSheet(line, options = {}) {
            const reviewUrl = options.reviewUrl || this.state.cart_url || '/shop/cart';
            const itemTitle = options.itemTitle
                || line?.display_title
                || line?.product?.title
                || 'Item';
            const itemImageUrl = options.imageUrl
                || line?.product?.image_url
                || '';

            if (typeof Swal === 'undefined') {
                this.showSuccess(options.fallbackMessage || 'Added to cart.');
                return Promise.resolve(null);
            }

            this.ensureAddSheetStyles();

            const imageHtml = itemImageUrl !== ''
                ? `<div style="display:flex;height:96px;width:96px;align-items:center;justify-content:center;overflow:hidden;border-radius:1.25rem;background:#ffffff;"><img src="${SM.escapeHtml(itemImageUrl)}" alt="${SM.escapeHtml(itemTitle)}" style="max-height:100%;max-width:100%;object-fit:cover;"></div>`
                : '';
            let outsidePointerHandler = null;
            let bindOutsidePointerTimer = null;

            return Swal.fire({
                position: 'bottom',
                toast: true,
                showConfirmButton: false,
                allowOutsideClick: true,
                allowEscapeKey: true,
                backdrop: false,
                width: 'min(72rem, calc(100vw - 1rem))',
                padding: 0,
                customClass: {
                    container: 'sm-shop-add-sheet-container',
                    popup: 'sm-shop-add-sheet-popup',
                },
                showClass: {
                    popup: 'sm-shop-add-sheet-popup-show',
                },
                hideClass: {
                    popup: 'sm-shop-add-sheet-popup-hide',
                },
                html: `
                    <div style="position:relative;padding:.5rem;text-align:left;">
                        <button
                            type="button"
                            aria-label="Close"
                            data-shop-add-sheet-close
                            x-data="{ hover:false }"
                            @mouseenter="hover=true"
                            @mouseleave="hover=false"
                            :style="'position:absolute;top:.5rem;right:.5rem;display:inline-flex;font-size:1rem;color:' + (hover ? '#EF4444' : '#1f2937')"
                        >
                            <span aria-hidden="true"><i class="fa-solid fa-close"></i></span>
                        </button>
                        <div style="display:flex;align-items:center;gap:1rem;">
                            ${imageHtml}
                            <div style="min-width:0;flex:1;">
                                <div style="display:flex;align-items:center;gap:0.75rem;font-size:1.1rem;font-weight:700;color:#1f2937;">
                                    <span aria-hidden="true" style="display:inline-flex;height:1.9rem;width:1.9rem;align-items:center;justify-content:center;border-radius:999px;background:rgba(2, 132, 199, 0.12);color:#0f766e;font-size:1.1rem;">✓</span>
                                    <span>Item added to your cart</span>
                                </div>
                                <div style="margin-top:0.5rem;font-size:1.05rem;color:#1f2937;">${SM.escapeHtml(itemTitle)}</div>
                            </div>
                        </div>
                        <div style="margin-top:1rem;display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1rem;">
                            <button type="button" data-shop-add-sheet-continue style="height:2.5rem;border-radius:.25rem;border:1px solid #1f2937;background:#ffffff;font-weight:700;color:#1f2937;">Continue shopping</button>
                            <button type="button" data-shop-add-sheet-review style="height:2.5rem;border-radius:.25rem;border:0;background:#0284c7;font-weight:700;color:#ffffff;">Review cart</button>
                        </div>
                    </div>
                `,
                didOpen: (popup) => {
                    popup.style.borderRadius = '2rem';
                    popup.style.background = '#f3f4f6';
                    popup.style.boxShadow = '0 -18px 48px rgba(15, 23, 42, 0.22)';
                    popup.style.pointerEvents = 'auto';

                    popup.querySelector('[data-shop-add-sheet-close]')?.addEventListener('click', () => {
                        Swal.close();
                    });

                    popup.querySelector('[data-shop-add-sheet-continue]')?.addEventListener('click', () => {
                        Swal.close();
                    });

                    popup.querySelector('[data-shop-add-sheet-review]')?.addEventListener('click', () => {
                        Swal.close();
                        SM.redirectIfSafe(reviewUrl);
                    });

                    bindOutsidePointerTimer = window.setTimeout(() => {
                        outsidePointerHandler = (event) => {
                            const target = event?.target;
                            if (!(target instanceof Node)) {
                                return;
                            }
                            if (popup.contains(target)) {
                                return;
                            }

                            Swal.close();
                        };

                        document.addEventListener('pointerdown', outsidePointerHandler, true);
                    }, 0);
                },
                willClose: () => {
                    if (bindOutsidePointerTimer !== null) {
                        window.clearTimeout(bindOutsidePointerTimer);
                        bindOutsidePointerTimer = null;
                    }

                    if (typeof outsidePointerHandler === 'function') {
                        document.removeEventListener('pointerdown', outsidePointerHandler, true);
                        outsidePointerHandler = null;
                    }
                },
            });
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
                this.showError(error.message || 'Unable to update the item quantity.');
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
                this.showError(error.message || 'Unable to remove that item right now.');
                throw error;
            }
        },

        async refresh(shippingCountry = null) {
            try {
                const url = new URL(this.config.showUrl, window.location.origin);
                url.searchParams.set('shipping_country', shippingCountry || this.state.shipping_country || 'Australia');
                const payload = await this.request(url.toString(), {
                    method: 'GET',
                });
                this.setState(payload.cart || {});
                return payload;
            } catch (error) {
                this.showError(error.message || 'Unable to refresh the cart.');
                throw error;
            }
        },
    }
};

window.SM = SM;

document.addEventListener('DOMContentLoaded', () => {
    SM.updateShippingAddress();
    SM.updateAllThumbnails();
});
