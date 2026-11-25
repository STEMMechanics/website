const SMMediaPicker = {
    cameraStream: null,

    uploadProgressStyle: (progress) => {
        const value = Number(progress);
        const percentage = Number.isFinite(value) ? Math.max(0, Math.min(100, value)) : 0;

        return `width: ${percentage}%`;
    },

    uploadProgressLabel: (progress) => {
        const value = Number(progress);
        const percentage = Number.isFinite(value) ? Math.max(0, Math.min(100, value)) : 0;

        return `${Math.round(percentage)}% complete`;
    },

    resultSummary: (currentPage, perPage, total) => {
        const page = Math.max(1, Number(currentPage) || 1);
        const pageSize = Math.max(0, Number(perPage) || 0);
        const resultCount = Math.max(0, Number(total) || 0);

        if (resultCount === 0 || pageSize === 0) {
            return '';
        }

        const first = ((page - 1) * pageSize) + 1;
        const last = Math.min(page * pageSize, resultCount);

        return `Showing ${first} to ${last} of ${resultCount} results`;
    },

    escapeHtml: (value) => {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    },

    normalizeCustomTabId: (value, index = 0) => {
        const normalized = String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '_')
            .replace(/^_+|_+$/g, '');

        return normalized || `custom_tab_${index + 1}`;
    },

    normalizeCustomTabs: (tabs) => {
        if (!Array.isArray(tabs)) {
            return [];
        }

        return tabs.reduce((normalizedTabs, tab, index) => {
            if (!tab || typeof tab !== 'object') {
                return normalizedTabs;
            }

            const id = SMMediaPicker.normalizeCustomTabId(tab.id, index);
            if (normalizedTabs.some((existingTab) => existingTab.id === id)) {
                return normalizedTabs;
            }

            normalizedTabs.push({
                id,
                label: String(tab.label || `Tab ${index + 1}`),
                state: typeof tab.state === 'object' && tab.state !== null ? tab.state : {},
                panel_html: String(tab.panel_html || ''),
                panel_class: String(tab.panel_class || 'h-full overflow-y-auto px-4 py-4'),
                onOpen: typeof tab.onOpen === 'function' ? tab.onOpen : null,
                onConfirm: typeof tab.onConfirm === 'function' ? tab.onConfirm : null,
            });

            return normalizedTabs;
        }, []);
    },

    findCustomTab: (tabId) => {
        const store = Alpine.store('media');

        return store?.custom_tabs?.find((tab) => tab.id === tabId) || null;
    },

    activateTab: (tabId) => {
        const store = Alpine.store('media');
        if (!store || store.uploading) {
            return;
        }

        store.active_tab = tabId;
        SMMediaPicker.syncActiveTabDom();

        if (tabId === 'camera') {
            Alpine.nextTick(() => {
                SMMediaPicker.startCamera();
            }).then(() => {
                /* empty */
            });
        } else {
            SMMediaPicker.stopCamera();
        }

        const customTab = SMMediaPicker.findCustomTab(tabId);
        if (customTab?.onOpen) {
            customTab.onOpen(store, tabId);
        }
    },

    syncActiveTabDom: () => {
        const store = Alpine.store('media');
        const htmlContainer = Swal.getHtmlContainer?.();

        if (!store || !htmlContainer) {
            return;
        }

        htmlContainer.querySelectorAll('[data-media-tab-button]').forEach((button) => {
            const isActive = button.getAttribute('data-media-tab-button') === store.active_tab;

            button.classList.toggle('border-gray-300', isActive);
            button.classList.toggle('border-b-white', isActive);
            button.classList.toggle('bg-white', isActive);
            button.classList.toggle('border-transparent', !isActive);
            button.classList.toggle('bg-transparent', !isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        htmlContainer.querySelectorAll('[data-media-tab-panel]').forEach((panel) => {
            panel.style.display = panel.getAttribute('data-media-tab-panel') === store.active_tab ? '' : 'none';
        });
    },

    renderCustomTabButtons: () => {
        const store = Alpine.store('media');
        if (!store?.custom_tabs?.length) {
            return '';
        }

        return store.custom_tabs.map((tab) => {
            const tabId = SMMediaPicker.escapeHtml(tab.id);

            return `
                <li
                    class="cursor-pointer border border-transparent bg-transparent px-3 py-2 rounded-t-lg hover:bg-white hover:border-t-gray-300 hover:border-x-gray-300"
                    data-media-tab-button="${tabId}"
                    x-on:click.prevent="$SMMediaPicker.activateTab('${tabId}')"
                >${SMMediaPicker.escapeHtml(tab.label)}</li>
            `;
        }).join('');
    },

    renderCustomTabPanels: () => {
        const store = Alpine.store('media');
        if (!store?.custom_tabs?.length) {
            return '';
        }

        return store.custom_tabs.map((tab) => {
            const tabId = SMMediaPicker.escapeHtml(tab.id);

            return `
                <div
                    id="content-${tabId}"
                    class="${SMMediaPicker.escapeHtml(tab.panel_class)}"
                    data-media-tab-panel="${tabId}"
                    style="display:none"
                >
                    ${tab.panel_html}
                </div>
            `;
        }).join('');
    },

    cameraSupported: () => {
        return typeof navigator !== 'undefined'
            && !!navigator.mediaDevices
            && typeof navigator.mediaDevices.getUserMedia === 'function';
    },

    stopCamera: () => {
        if (SMMediaPicker.cameraStream) {
            SMMediaPicker.cameraStream.getTracks().forEach((track) => track.stop());
            SMMediaPicker.cameraStream = null;
        }

        const video = document.getElementById('media_camera_preview');
        if (video) {
            video.pause?.();
            video.srcObject = null;
        }

        if (typeof Alpine !== 'undefined' && Alpine.store('media')) {
            Alpine.store('media').camera_ready = false;
            Alpine.store('media').camera_starting = false;
            Alpine.store('media').camera_countdown_active = false;
            Alpine.store('media').camera_countdown = 0;
        }
    },

    startCamera: async () => {
        const store = Alpine.store('media');
        if (!store?.camera_supported) {
            return;
        }

        const video = document.getElementById('media_camera_preview');
        if (!video) {
            return;
        }

        store.camera_error = null;
        store.camera_starting = true;

        try {
            if (!SMMediaPicker.cameraStream) {
                SMMediaPicker.cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                    },
                    audio: false,
                });
            }

            video.srcObject = SMMediaPicker.cameraStream;
            await video.play();
            store.camera_ready = true;
        } catch (_error) {
            store.camera_error = 'Camera access is not available in this browser or has been denied.';
            store.camera_ready = false;
            SMMediaPicker.stopCamera();
        } finally {
            store.camera_starting = false;
        }
    },

    restartCamera: () => {
        SMMediaPicker.stopCamera();
        Alpine.nextTick(() => SMMediaPicker.startCamera());
    },

    captureCameraPhoto: () => {
        const store = Alpine.store('media');
        const video = document.getElementById('media_camera_preview');

        if (!video || !store?.camera_ready) {
            store.camera_error = 'Camera preview is not ready yet.';
            return;
        }

        const width = video.videoWidth || 1280;
        const height = video.videoHeight || 720;
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');
        if (!context) {
            store.camera_error = 'Could not capture the photo.';
            return;
        }

        context.save();
        context.translate(store.camera_flip_x ? width : 0, store.camera_flip_y ? height : 0);
        context.scale(store.camera_flip_x ? -1 : 1, store.camera_flip_y ? -1 : 1);
        context.drawImage(video, 0, 0, width, height);
        context.restore();

        canvas.toBlob((blob) => {
            if (!blob) {
                store.camera_error = 'Could not capture the photo.';
                return;
            }

            const file = new File([blob], `camera-${Date.now()}.jpg`, { type: 'image/jpeg' });
            SMMediaPicker.stopCamera();
            SMMediaPicker.upload([file]);
        }, 'image/jpeg', 0.92);
    },

    startCameraCountdown: () => {
        const store = Alpine.store('media');
        if (!store?.camera_ready || store.camera_countdown_active) {
            return;
        }

        store.camera_error = null;
        store.camera_countdown_active = true;
        store.camera_countdown = 3;

        const tick = () => {
            if (!store.camera_countdown_active) {
                return;
            }

            if (store.camera_countdown <= 1) {
                store.camera_countdown_active = false;
                store.camera_countdown = 0;
                SMMediaPicker.captureCameraPhoto();
                return;
            }

            store.camera_countdown -= 1;
            window.setTimeout(tick, 1000);
        };

        window.setTimeout(tick, 1000);
    },

    toggleCameraFlipX: () => {
        const store = Alpine.store('media');
        if (!store) {
            return;
        }

        store.camera_flip_x = !store.camera_flip_x;
    },

    toggleCameraFlipY: () => {
        const store = Alpine.store('media');
        if (!store) {
            return;
        }

        store.camera_flip_y = !store.camera_flip_y;
    },

    syncDialogInteractivity: () => {
        const store = Alpine.store('media');
        const uploading = !!store?.uploading;
        const confirmButton = Swal.getConfirmButton?.();
        const cancelButton = Swal.getCancelButton?.();

        [confirmButton, cancelButton].forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.disabled = uploading;
            button.setAttribute('aria-disabled', uploading ? 'true' : 'false');
        });
    },

    setUploadingState: (uploading, options = {}) => {
        const store = Alpine.store('media');
        if (!store) {
            return;
        }

        store.uploading = uploading;

        if (Object.prototype.hasOwnProperty.call(options, 'progress')) {
            store.upload_progress = Math.max(0, Math.min(100, Number(options.progress) || 0));
        }

        if (Object.prototype.hasOwnProperty.call(options, 'message')) {
            store.upload_message = options.message;
        }

        if (Object.prototype.hasOwnProperty.call(options, 'notice')) {
            store.upload_notice = options.notice;
        }

        SMMediaPicker.syncDialogInteractivity();
    },

    browserSearchInput: () => {
        return Swal.getHtmlContainer?.()?.querySelector('#content-browser input[name="search"]') || null;
    },

    browserSearchValue: () => {
        const input = SMMediaPicker.browserSearchInput();

        return input instanceof HTMLInputElement ? input.value : '';
    },

    addUploadedSelection: (name) => {
        const store = Alpine.store('media');
        if (!store || typeof name !== 'string' || name === '') {
            return;
        }

        if (!store.allow_multiple) {
            store.selected = [name];
            return;
        }

        if (!store.selected.some((item) => item === name)) {
            store.selected.push(name);
        }
    },

    upload: (files) => {
        const store = Alpine.store('media');
        if (!store || store.uploading) {
            return;
        }

        const validFiles = Array.from(files).filter((file) => {
            return SM.mimeMatches(file.type, store.require_mime_type);
        });

        if(validFiles.length === 0) {
            store.error = 'No files were uploaded because they do not meet the requirements.';
        } else if(validFiles.length !== files.length) {
            store.error = 'Some files were skipped because they do not meet the requirements.';
        } else {
            store.error = null;
        }

        if (validFiles.length === 0) {
            return;
        }

        const titles = Array.from(validFiles).map((file) => SM.toTitleCase(file.name));
        store.upload_notice = null;
        SMMediaPicker.setUploadingState(true, {
            progress: 0,
            message: validFiles.length > 1 ? `Preparing ${validFiles.length} uploads...` : `Preparing ${validFiles[0].name}...`,
        });

        SM.upload(validFiles, (response) => {
            if (response.success !== true) {
                return;
            }

            if(response.files) {
                response.files.forEach((file) => {
                    SMMediaPicker.addUploadedSelection(file.data.name);
                });
            }

            const searchInput = SMMediaPicker.browserSearchInput();
            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = '';
            }

            SMMediaPicker.setUploadingState(false, {
                progress: 100,
                message: null,
                notice: validFiles.length > 1 ? `${validFiles.length} files uploaded successfully.` : `${validFiles[0].name} uploaded successfully.`,
            });
            store.active_tab = store.allow_browser ? 'browser' : (store.custom_tabs[0]?.id || 'upload');
            if (store.allow_browser) {
                SMMediaPicker.query(1, '');
            }

            window.setTimeout(() => {
                if (store.upload_notice === (validFiles.length > 1 ? `${validFiles.length} files uploaded successfully.` : `${validFiles[0].name} uploaded successfully.`)) {
                    store.upload_notice = null;
                }
            }, 4000);
        }, titles, {
            showModal: false,
            successDelayMs: 0,
            fields: {
                visibility: store.public_usable_only ? 'public' : 'private',
                ...(store.upload_fields || {}),
            },
            onProgress: ({ file, index, count, percent }) => {
                SMMediaPicker.setUploadingState(true, {
                    progress: percent,
                    message: count > 1
                        ? `Uploading ${index + 1} of ${count}: ${file.name}`
                        : `Uploading ${file.name}`,
                });
            },
            onError: (message) => {
                SMMediaPicker.setUploadingState(false, {
                    progress: 0,
                    message: null,
                });
                store.error = message;
            },
        });

        ['media_upload'].forEach((inputId) => {
            const input = document.getElementById(inputId);
            if (input instanceof HTMLInputElement) {
                input.value = '';
            }
        });
    },

    gotoLink: (url) => {
        if(url !== null) {
            const page = new URL(url).searchParams.get('page');
            SMMediaPicker.query(page, SMMediaPicker.browserSearchValue());
        }
    },

    updateSelection: (name) => {
        if (Alpine.store('media').disabled.some((item) => item === name)) {
            return;
        }

        if (typeof name === 'string' && name !== '') {
            if (Alpine.store('media').selected.some(i => i === name)) {
                Alpine.store('media').selected = Alpine.store('media').selected.filter(i => i !== name);
            } else {
                if (!Alpine.store('media').allow_multiple) {
                    Alpine.store('media').selected = [name];
                } else {
                    Alpine.store('media').selected.push(name);
                }
            }
        }
    },

    doubleClick: (name) => {
        if (Alpine.store('media').disabled.some((item) => item === name)) {
            return;
        }

        if (!Alpine.store('media').allow_multiple) {
            Alpine.store('media').selected = [name];
            Swal.clickConfirm();
        }
    },

    search: () => {
        SMMediaPicker.query(null, SMMediaPicker.browserSearchValue());
    },

    confirmDelete: (item, event = null) => {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (!item?.can_delete || !item?.delete_url) {
            return;
        }

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            SMMediaPicker.deleteItem(item);
            return;
        }

        window.SM.confirm(
            'Delete media?',
            'Are you sure you want to delete this media? This action cannot be undone.',
            'Delete',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }

                SMMediaPicker.deleteItem(item);
            }
        );
    },

    deleteItem: (item) => {
        if (!item?.delete_url) {
            return;
        }

        axios.delete(item.delete_url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (response.data?.success !== true) {
                    throw new Error('Delete failed');
                }

                Alpine.store('media').selected = Alpine.store('media').selected.filter((name) => name !== item.name);
                Alpine.store('media').items = Alpine.store('media').items.filter((entry) => entry.name !== item.name);

                const search = SMMediaPicker.browserSearchValue();
                SMMediaPicker.query(Alpine.store('media').current_page || 1, search);

                if (window.SM?.alert) {
                    window.SM.alert('Media deleted', 'The selected media has been deleted.', 'success');
                }
            })
            .catch(() => {
                if (window.SM?.alert) {
                    window.SM.alert('Delete failed', 'Could not delete this media item.', 'danger');
                }
            });
    },

    query: (page, search) => {
        const store = Alpine.store('media');
        if (!store?.allow_browser) {
            return;
        }

        const params = {
            mime_type: store.require_mime_type,
            per_page: store.per_page,
            search: search,
            'selected[]': store.selected,
        };

        if (store.passwordless_only) params.passwordless_only = 1;
        if (store.public_usable_only) {
            params.public_usable_only = 1;
        }

        if(page !== null) {
            params.page = page;
        }

        axios.get('/media', {
            params: params,
        })
            .then((response) => {
                const currentPage = response.data.current_page;
                const pagination = (response.data.links || []).map((link, index, links) => {
                    const isPrevious = index === 0;
                    const isNext = index === links.length - 1;
                    const label = isPrevious ? '‹' : (isNext ? '›' : String(link.label || '').replace(/<[^>]*>/g, '').trim());

                    return {
                        url: link.url,
                        label,
                        disabled: link.url === null,
                        active: link.active === true || String(label) === String(currentPage),
                    };
                });

                response.data.data.forEach((file) => {
                    file.extension = file.name.split('.').pop();
                });

                store.current_page = response.data.current_page;
                store.per_page = response.data.per_page;
                store.to = response.data.to;
                store.total = response.data.total;
                store.items = response.data.data;
                store.pagination = [];
                store.previous_page_url = pagination.length > 0 ? pagination[0].url : null;
                store.next_page_url = pagination.length > 0 ? pagination[pagination.length - 1].url : null;

                response.data.data.forEach((file) => {
                    if(file.status === 'processing' || file.status === 'queued') {
                        const fileName = file.name;
                        setTimeout(() => {
                            SMMediaPicker.updateThumbnail(fileName);
                        }, 5000);
                    }
                });

                Alpine.nextTick(() => {
                    store.pagination = pagination;
                }).then(() => {
                    /* empty */
                });
            })
            .catch((error) => {
                console.error(error);
            });
    },

    buildHtml: () => {
        const store = Alpine.store('media');

        return `
        <div class="flex flex-col h-full w-full" x-data="{showFileDrop: false}">
            <template x-if="$store.media.error">
                <div class="flex justify-center" role="alert">
                    <p class="relative bg-red-100 border border-red-400 text-red-700 py-2 pl-4 pr-8 text-xs rounded mb-4"><span x-text="$store.media.error"></span><i class="fa-solid fa-close text-red-900 hover:text-red-700 cursor-pointer absolute top-2 right-2" x-on:click="$store.media.error=null;"></i></p>
                </div>
            </template>
            <template x-if="$store.media.upload_notice">
                <div class="flex justify-center" role="status">
                    <p class="relative bg-emerald-100 border border-emerald-300 text-emerald-800 py-2 pl-4 pr-8 text-xs rounded mb-4"><span x-text="$store.media.upload_notice"></span><i class="fa-solid fa-close text-emerald-900 hover:text-emerald-700 cursor-pointer absolute top-2 right-2" x-on:click="$store.media.upload_notice=null;"></i></p>
                </div>
            </template>
            <ul class="relative z-20 -mb-px flex flex-wrap gap-1 overflow-x-auto">
                ${store?.allow_uploads ? `<li class="cursor-pointer border border-transparent bg-transparent px-3 py-2 rounded-t-lg hover:bg-white hover:border-t-gray-300 hover:border-x-gray-300" data-media-tab-button="upload" x-on:click.prevent="$SMMediaPicker.activateTab('upload')">Upload</li>` : ''}
                ${store?.camera_supported ? `<li class="cursor-pointer border border-transparent bg-transparent px-3 py-2 rounded-t-lg hover:bg-white hover:border-t-gray-300 hover:border-x-gray-300" data-media-tab-button="camera" x-on:click.prevent="$SMMediaPicker.activateTab('camera')">Camera</li>` : ''}
                ${store?.allow_browser ? `<li class="cursor-pointer border border-transparent bg-transparent px-3 py-2 rounded-t-lg hover:bg-white hover:border-t-gray-300 hover:border-x-gray-300" data-media-tab-button="browser" x-on:click.prevent="$SMMediaPicker.activateTab('browser')">Browser</li>` : ''}
                ${SMMediaPicker.renderCustomTabButtons()}
            </ul>
            <div
                class="relative z-10 flex-1 min-h-0 border border-gray-300 overflow-hidden"
                x-on:dragenter.prevent="$store.media.allow_uploads && !$store.media.uploading ? showFileDrop = true : showFileDrop = false"
                x-on:dragover.prevent="$store.media.allow_uploads && !$store.media.uploading ? showFileDrop = true : showFileDrop = false">
                <div
                    id="content-upload"
                    class="w-full h-full flex flex-col px-4 py-8 justify-center items-center"
                    data-media-tab-panel="upload"
                    style="display:none">
                    <div x-show="!$store.media.uploading" class="flex h-full w-full flex-col items-center justify-center">
                        <h3 class="text-2xl font-bold mb-2">Drop files to upload</h3>
                        <p>or</p>
                        <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                            <label class="inline-block bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 transition" for="media_upload">Select files</label>
                        </div>
                        <input class="hidden" id="media_upload" name="media_upload" multiple type="file" x-on:change="$SMMediaPicker.upload(event.target.files)" x-bind:accept="$store.media.require_mime_type" x-bind:disabled="$store.media.uploading" />
                        <p class="text-xs mt-2">Maximum upload size: ${SM.bytesToString(SM.maxUploadSize())}</p>
                    </div>
                    <div x-show="$store.media.uploading" x-cloak class="flex h-full w-full flex-col items-center justify-center">
                        <div class="w-full max-w-md rounded-2xl border border-sky-100 bg-sky-50 px-5 py-5 text-center">
                            <div class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Uploading</div>
                            <div class="mt-2 text-lg font-semibold text-gray-900">Media upload in progress</div>
                            <p class="mt-2 text-sm text-gray-600" x-text="$store.media.upload_message || 'Preparing upload...'"></p>
                            <div class="mt-5 h-3 overflow-hidden rounded-full bg-sky-100">
                                <div class="h-full rounded-full bg-sky-600 transition-all duration-200" :style="$SM.mediaPickerProgressStyle($store.media.upload_progress)"></div>
                            </div>
                            <div class="mt-2 text-xs font-medium text-sky-800" x-text="$SM.mediaPickerProgressLabel($store.media.upload_progress)"></div>
                        </div>
                    </div>
                </div>
                <div
                    id="content-camera"
                    class="w-full h-full flex flex-col px-4 py-6 items-center"
                    data-media-tab-panel="camera"
                    style="display:none"
                >
                    <div class="w-full flex-1 min-h-0 flex flex-col items-center justify-center gap-4">
                        <div class="relative h-full aspect-video overflow-hidden rounded-xl bg-gray-500 flex items-center justify-center">
                            <video id="media_camera_preview" class="h-full w-full object-contain" :style="'transform: scale(' + ($store.media.camera_flip_x ? -1 : 1) + ', ' + ($store.media.camera_flip_y ? -1 : 1) + ');'" autoplay playsinline muted></video>
                            <div
                                x-show="$store.media.camera_countdown_active"
                                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/35 text-red-400"
                            >
                                <div class="text-7xl font-bold drop-shadow-lg" x-text="$store.media.camera_countdown"></div>
                            </div>
                        </div>
                        <template x-if="$store.media.camera_starting">
                            <p class="text-sm text-gray-500">Starting camera…</p>
                        </template>
                        <template x-if="$store.media.camera_error">
                            <p class="text-sm text-red-600 text-center" x-text="$store.media.camera_error"></p>
                        </template>
                        <div class="flex flex-wrap items-center justify-center gap-3">
                            <div>
                                <div class="my-4 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
                                  <i class="fa-solid fa-warning mr-2"></i>Anything you capture here may be publicly visible. Only photograph persons with consent and not sensitive information.
                                </div>
                            </div>
                            <div>
                                <button
                                    type="button"
                                    class="bg-primary-color hover:bg-primary-color-dark justify-center rounded-md text-white px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 transition mr-6"
                                    x-bind:disabled="!$store.media.camera_ready || $store.media.camera_countdown_active"
                                    x-on:click.prevent="$SMMediaPicker.startCameraCountdown()"
                                >
                                    <span>Capture photo</span>
                                </button>
                                <button
                                    type="button"
                                    class="bg-white w-8 h-8 border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 text-sm font-semibold leading-6 shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                                    x-bind:disabled="$store.media.camera_countdown_active"
                                    x-on:click.prevent="$SMMediaPicker.toggleCameraFlipX()"
                                    title="Flip horizontally"
                                >
                                    <i class="fa-solid fa-left-right"></i>
                                </button>
                                <button
                                    type="button"
                                    class="bg-white w-8 h-8 border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 text-sm font-semibold leading-6 shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                                    x-bind:disabled="$store.media.camera_countdown_active"
                                    x-on:click.prevent="$SMMediaPicker.toggleCameraFlipY()"
                                    title="Flip vertically"
                                >
                                    <i class="fa-solid fa-up-down"></i>
                                </button>
                                <button
                                    type="button"
                                    class="bg-white w-8 h-8 border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 text-sm font-semibold leading-6 shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                                    x-on:click.prevent="$SMMediaPicker.restartCamera()"
                                    title="Restart Camera"
                                >
                                    <i class="fa-solid fa-power-off"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="content-browser" class="flex flex-col h-full min-h-0 min-w-0 w-full p-4" data-media-tab-panel="browser" style="display:none">
                    <form x-on:submit.prevent="$SMMediaPicker.search()">
                        <div class="flex min-w-0 w-full mb-2">
                            <input class="bg-white min-w-0 w-0 flex-1 px-2.5 py-1 text-base sm:text-xs text-gray-900 rounded-l-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer border-gray-300 focus:ring-indigo-300" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="Search" aria-label="Search media" type="search" enterkeyhint="search" name="search" x-bind:disabled="$store.media.uploading" />
                            <button type="submit" aria-label="Search media" class="shrink-0 min-h-11 sm:min-h-0 hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color rounded-l-none px-4 justify-center rounded-md text-white py-1.5 text-xs font-semibold leading-6 shadow-sm focus-visible:outline-2 focus-visible:outline-offset-2 transition" x-bind:disabled="$store.media.uploading"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button>
                        </div>
                    </form>
                    <ul class="flex-1 min-h-0 overflow-y-auto p-2 gap-4 justify-center content-start flex flex-row flex-wrap" :class="{ 'pointer-events-none opacity-60': $store.media.uploading }">
                    <template x-for="item in $store.media.items" :key="item.name">
                        <li
                            class="flex text-center p-1 items-center flex-col h-40 w-56 border-2 rounded relative"
                            :class="{
                                'cursor-pointer border-primary-color': $store.media.selected.includes(item.name),
                                'cursor-pointer border-white': !$store.media.selected.includes(item.name) && !$store.media.disabled.includes(item.name),
                                'cursor-not-allowed border-gray-200 bg-gray-100 opacity-60': $store.media.disabled.includes(item.name)
                            }"
                            x-bind:aria-disabled="$store.media.disabled.includes(item.name) ? 'true' : 'false'"
                            x-on:click="$SMMediaPicker.updateSelection(item.name)"
                            x-on:dblclick="$SMMediaPicker.doubleClick(item.name)"
                            >
                            <div class="absolute right-1 top-1 z-10">
                                <span
                                    x-show="$store.media.selected.includes(item.name)"
                                    class="flex h-6 w-6 items-center justify-center rounded-full border border-white bg-primary-color text-xs text-white shadow"
                                    title="Selected"
                                    aria-label="Selected"
                                ><i class="fa-solid fa-check"></i></span>
                                <span
                                    x-show="$store.media.disabled.includes(item.name)"
                                    class="flex h-6 w-6 items-center justify-center rounded-full border border-white bg-gray-700 text-xs text-white shadow"
                                    x-bind:title="$store.media.disabled_item_text"
                                    x-bind:aria-label="$store.media.disabled_item_text"
                                ><i class="fa-solid fa-check"></i></span>
                            </div>
                            <div class="absolute left-1 top-1 z-10 flex items-center gap-1">
                                <span
                                    x-show="item.password"
                                    class="flex h-6 w-6 items-center justify-center rounded-full border border-gray-200 bg-white/95 text-xs text-gray-700 shadow"
                                    title="Password protected"
                                    aria-label="Password protected"
                                ><i class="fa-solid fa-lock"></i></span>
                                <span
                                    x-show="item.visibility && item.visibility !== 'public'"
                                    class="flex h-6 w-6 items-center justify-center rounded-full border border-gray-200 bg-white/95 text-xs text-gray-700 shadow"
                                    x-bind:title="item.visibility === 'protected' ? 'Protected' : 'Private'"
                                    x-bind:aria-label="item.visibility === 'protected' ? 'Protected' : 'Private'"
                                ><i class="fa-solid" x-bind:class="item.visibility === 'protected' ? 'fa-shield-halved' : 'fa-eye-slash'"></i></span>
                                <span
                                    x-show="item.is_private"
                                    class="flex h-6 w-6 items-center justify-center rounded-full border border-gray-200 bg-white/95 text-xs text-gray-700 shadow"
                                    title="Private owner"
                                    aria-label="Private owner"
                                ><i class="fa-solid fa-user-lock"></i></span>
                            </div>
                            <div class="group/image relative grow flex items-center justify-center select-none">
                                <img x-bind:src="item.thumbnail" class="rounded max-h-32 pointer-events-none" />
                            </div>
                            <div class="text-xs whitespace-nowrap overflow-hidden text-ellipsis" x-text="item.name" x-bind:title="item.name"></div>
                        </li>
                    </template>
                    </ul>

                    <div class="flex items-end pt-4 pb-1">
                        <div class="flex w-full items-center justify-between sm:justify-center md:justify-between">
                            <p x-show="$store.media.total" class="hidden md:block text-xs" x-text="$SM.mediaPickerResultSummary($store.media.current_page, $store.media.per_page, $store.media.total)"></p>
                            <p x-show="!$store.media.total" class="hidden md:block text-xs">No items found</p>

                            <ul class="hidden sm:flex border border-gray-300 rounded-lg text-sm">
                                <template x-for="link in $store.media.pagination">
                                    <li
                                        class="px-2 py-1.5 w-9 border-r last:border-r-0 text-center select-none whitespace-nowrap"
                                        :class="{
                                            'bg-gray-100 text-gray-400': link.disabled,
                                            'text-primary-color bg-sky-100': link.active,
                                            'cursor-pointer hover:text-primary-color hover:bg-sky-100': !link.disabled
                                            }"
                                        x-text="link.label"
                                        x-on:click="$SMMediaPicker.gotoLink(link.url)"></li>
                                </template>
                            </ul>

                            <a
                                :href="$store.media.previous_page_url || '#'"
                                class="sm:hidden px-2 py-1.5 border rounded text-center text-sm select-none whitespace-nowrap cursor-pointer"
                                :class="{
                                    'cursor-pointer hover:text-primary-color hover:bg-sky-100': $store.media.previous_page_url,
                                    'pointer-events-none text-gray-400 bg-gray-100': !$store.media.previous_page_url
                                }"
                                x-on:click.prevent="$SMMediaPicker.gotoLink($store.media.previous_page_url)"
                                :aria-disabled="!$store.media.previous_page_url"
                                ><i class="fa-solid fa-angle-left mr-2"></i>Prev
                            </a>
                            <a
                                :href="$store.media.next_page_url || '#'"
                                class="sm:hidden px-2 py-1.5 border rounded text-center text-sm select-none whitespace-nowrap"
                                :class="{
                                    'cursor-pointer hover:text-primary-color hover:bg-sky-100': $store.media.next_page_url,
                                    'pointer-events-none text-gray-400 bg-gray-100': !$store.media.next_page_url
                                }"
                                x-on:click.prevent="$SMMediaPicker.gotoLink($store.media.next_page_url)"
                                :aria-disabled="!$store.media.next_page_url"
                                >Next<i class="fa-solid fa-angle-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                ${SMMediaPicker.renderCustomTabPanels()}
            </div>
            <div
                x-show="showFileDrop && !$store.media.uploading"
                class="fixed flex top-0 left-0 w-full h-full z-10 bg-sky-800/95 text-white items-center p-4"
                x-on:dragenter.prevent="showFileDrop = true"
                x-on:dragover.prevent="showFileDrop = true"
                x-on:drop.prevent="if ($store.media.uploading) { showFileDrop = false; return; } $SMMediaPicker.upload($event.dataTransfer.files); showFileDrop = false;"
                x-on:dragleave.prevent="showFileDrop = false">
                <h2
                    class="pointer-events-none flex w-full h-full justify-center items-center text-lg font-bold border-dashed border">
                    Drop files to upload
                </h2>
            </div>
        </div>
    `;
    },

    onOpen: () => {
        const store = Alpine.store('media');
        const htmlContainer = Swal.getHtmlContainer?.();

        if (htmlContainer && window.Alpine?.initTree) {
            window.Alpine.initTree(htmlContainer);
        }

        SMMediaPicker.syncActiveTabDom();

        if (store?.allow_browser) {
            SMMediaPicker.query(null, '');
        } else if (store) {
            store.items = [];
            store.pagination = [];
            store.previous_page_url = null;
            store.next_page_url = null;
            store.total = 0;
            store.current_page = 1;
        }

        if (store?.active_tab === 'camera') {
            Alpine.nextTick(() => {
                SMMediaPicker.startCamera();
            }).then(() => {
                /* empty */
            });
        }

        const customTab = SMMediaPicker.findCustomTab(store?.active_tab);
        if (customTab?.onOpen) {
            customTab.onOpen(store, store.active_tab);
        }

        SMMediaPicker.syncDialogInteractivity();
    },

    preClose: () => {
        const store = Alpine.store('media');
        if (store?.uploading) {
            return false;
        }

        const customTab = SMMediaPicker.findCustomTab(store?.active_tab);
        if (customTab?.onConfirm) {
            SMMediaPicker.stopCamera();

            const payload = customTab.onConfirm(store, store.active_tab);
            if (payload === false) {
                return false;
            }

            return {
                __mediaPickerCustomResult: true,
                payload,
            };
        }

        SMMediaPicker.stopCamera();
    },

    open: (selected, options = {}, callback = null) => {
        if(!options.hasOwnProperty('require_mime_type')) options.require_mime_type = '*';
        if(!options.hasOwnProperty('allow_multiple')) options.allow_multiple = false;
        if(!options.hasOwnProperty('allow_uploads')) options.allow_uploads = false;
        if(!options.hasOwnProperty('allow_browser')) options.allow_browser = true;
        if(!options.hasOwnProperty('allow_camera')) options.allow_camera = false;
        if(!options.hasOwnProperty('public_usable_only')) options.public_usable_only = true;
        if(!options.hasOwnProperty('upload_fields')) options.upload_fields = {};
        if(!options.hasOwnProperty('custom_tabs')) options.custom_tabs = [];
        if(!options.hasOwnProperty('disabled')) options.disabled = [];

        if(selected === null || selected === '') selected = [];
        if(!Array.isArray(selected)) selected = [selected];
        if(options.disabled === null || options.disabled === '') options.disabled = [];
        if(!Array.isArray(options.disabled)) options.disabled = [options.disabled];

        const store = Alpine.store('media');
        store.disabled = [...new Set(options.disabled.map((item) => String(item || '').trim()).filter((item) => item !== ''))];
        store.selected = [...new Set(selected.map((item) => String(item || '').trim()).filter((item) => item !== '' && !store.disabled.includes(item)))];
        store.disabled_item_text = String(options.disabled_item_text || 'Unavailable');
        store.require_mime_type = options.require_mime_type;
        store.allow_multiple = options.allow_multiple;
        store.allow_uploads = options.allow_uploads;
        store.allow_browser = options.allow_browser;
        store.public_usable_only = options.public_usable_only;
        store.passwordless_only = options.passwordless_only === true;
        store.upload_fields = options.upload_fields && typeof options.upload_fields === 'object' ? JSON.parse(JSON.stringify(options.upload_fields)) : {};
        store.allow_camera = options.allow_camera && String(options.require_mime_type || '').includes('image/');
        store.camera_supported = store.allow_camera && SMMediaPicker.cameraSupported();
        store.camera_ready = false;
        store.camera_starting = false;
        store.camera_countdown_active = false;
        store.camera_countdown = 0;
        store.camera_flip_x = false;
        store.camera_flip_y = false;
        store.camera_error = null;
        store.uploading = false;
        store.upload_progress = 0;
        store.upload_message = null;
        store.upload_notice = null;
        store.callback = callback;
        store.custom_tabs = SMMediaPicker.normalizeCustomTabs(options.custom_tabs);
        store.custom_tab_state = {};

        store.custom_tabs.forEach((tab) => {
            store.custom_tab_state[tab.id] = typeof tab.state === 'object' && tab.state !== null
                ? JSON.parse(JSON.stringify(tab.state))
                : {};
        });

        const availableTabs = [
            ...(store.allow_uploads ? ['upload'] : []),
            ...(store.camera_supported ? ['camera'] : []),
            ...(store.allow_browser ? ['browser'] : []),
            ...store.custom_tabs.map((tab) => tab.id),
        ];
        store.active_tab = availableTabs.includes(options.initial_tab) ? options.initial_tab : (availableTabs[0] || 'browser');

        return Swal.fire({
            title: options.title || (options.allow_uploads ? 'Select or Upload Media' : 'Select Media'),
            html: SMMediaPicker.buildHtml(),
            confirmButtonText: options.confirm_button_text || 'Select',
            confirmButtonColor: '#0284C7',
            cancelButtonText: 'Cancel',
            showCancelButton: true,
            focusConfirm: false,
            reverseButtons: true,
            allowOutsideClick: () => !Alpine.store('media')?.uploading,
            allowEscapeKey: () => !Alpine.store('media')?.uploading,
            didOpen: SMMediaPicker.onOpen,
            preConfirm: SMMediaPicker.preClose,
            willClose: SMMediaPicker.stopCamera,
            customClass: {
                container: 'sm-media-picker-container',
                popup: 'sm-media-picker',
            },
        }).then((result) => {
            if(result.isConfirmed && callback) {
                if (result.value?.__mediaPickerCustomResult) {
                    callback(result.value.payload);
                    return;
                }

                if(Alpine.store('media').allow_multiple) {
                    callback(Alpine.store('media').selected);
                } else {
                    if(Alpine.store('media').selected.length > 0) {
                        callback(Alpine.store('media').selected[0]);
                    } else {
                        callback('');
                    }
                }
            }
        });
    },

    updateThumbnail: (name) => {
        axios.get('/media/' + name)
            .then((response) => {
                const item = Alpine.store('media').items.find(i => i.name === name);
                if(item) {
                    if(response.data.status === 'ready') {
                        item.thumbnail = response.data.thumbnail;
                        item.status = response.data.status;
                        item.variants = response.data.variants;
                    } else if(response.data.status === 'processing' || response.data.status === 'queued') {
                        setTimeout(() => {
                            SMMediaPicker.updateThumbnail(name);
                        }, 5000);
                    }
                }
            })
            .catch((error) => {
                console.error(error);
            });
    },
};

window.SMMediaPicker = SMMediaPicker;
window.SM = window.SM || {};
Object.assign(window.SM, {
    mediaPickerProgressStyle: SMMediaPicker.uploadProgressStyle,
    mediaPickerProgressLabel: SMMediaPicker.uploadProgressLabel,
    mediaPickerResultSummary: SMMediaPicker.resultSummary,
});

const initializeMediaStore = () => {
    if (Alpine.store('media')) return;
    Alpine.store('media', {
        require_mime_type: '*',
        allow_multiple: true,
        allow_uploads: false,
        allow_browser: true,
        allow_camera: false,
        upload_fields: {},
        camera_supported: false,
        camera_ready: false,
        camera_starting: false,
        camera_countdown_active: false,
        camera_countdown: 0,
        camera_flip_x: false,
        camera_flip_y: false,
        camera_error: null,
        active_tab: 'browser',
        uploading: false,
        upload_progress: 0,
        upload_message: null,
        upload_notice: null,
        current_page: 1,
        per_page: 24,
        to: 0,
        total: 0,
        items: [],
        selected: [],
        disabled: [],
        disabled_item_text: 'Unavailable',
        pagination: [],
        previous_page_url: null,
        next_page_url: null,
        custom_tabs: [],
        custom_tab_state: {},
    });
};

if (window.Alpine?.store) initializeMediaStore();
else window.SM.whenAlpineReady(initializeMediaStore);
