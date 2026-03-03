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

    pluralize: (word, count) => {
        const safeWord = String(word ?? '').trim();
        if (safeWord === '') {
            return '';
        }

        return Number.parseInt(String(count ?? 0), 10) === 1 ? safeWord : `${safeWord}s`;
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

    confirmDelete: (token, title, content, urlOrForm) => {
        Swal.fire({
            position: 'top',
            icon: 'warning',
            iconColor: '#b91c1c',
            title: title,
            html: content,
            showCancelButton: true,
            confirmButtonText: 'Delete',
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
    }
};

window.SM = SM;

document.addEventListener('DOMContentLoaded', () => {
    SM.updateShippingAddress();
    SM.updateAllThumbnails();
});
