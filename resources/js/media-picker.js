const SMMediaPicker = {
    cameraStream: null,

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

    upload: (files) => {
        const validFiles = Array.from(files).filter((file) => {
            return SM.mimeMatches(file.type, Alpine.store('media').require_mime_type);
        });

        if(validFiles.length === 0) {
            Alpine.store('media').error = 'No files where uploaded as they do not meet the requirements.';
        } else if(validFiles.length !== files.length) {
            Alpine.store('media').error = 'Some files where not uploaded as they do not meet the requirements.';
        } else {
            Alpine.store('media').error = null;
        }

        const titles = Array.from(validFiles).map((file) => SM.toTitleCase(file.name));

        SM.upload(validFiles, (response) => {
            if(response.files) {
                response.files.forEach((file) => {
                    SMMediaPicker.updateSelection(file.data.name);
                });
            }

            SMMediaPicker.open(
                Alpine.store('media').selected,
                {
                    require_mime_type: Alpine.store('media').require_mime_type,
                    allow_multiple: Alpine.store('media').allow_multiple,
                    allow_uploads: Alpine.store('media').allow_uploads,
                    allow_camera: Alpine.store('media').allow_camera
                },
                Alpine.store('media').callback
            );
        }, titles);
    },

    gotoLink: (url) => {
        if(url !== null) {
            const page = new URL(url).searchParams.get('page');
            SMMediaPicker.query(page, document.querySelector('input[name="search"]').value);
        }
    },

    updateSelection: (name) => {
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
        if (!Alpine.store('media').allow_multiple) {
            Alpine.store('media').selected = [name];
            Swal.clickConfirm();
        }
    },

    search: () => {
        SMMediaPicker.query(null, document.querySelector('input[name="search"]').value);
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

                const search = document.querySelector('input[name="search"]')?.value || '';
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
        let params = {
            mime_type: Alpine.store('media').require_mime_type,
            per_page: Alpine.store('media').per_page,
            search: search,
            'selected[]': Alpine.store('media').selected
        };

        if(page !== null) {
            params.page = page;
        }

        axios.get('/media', {
            params: params
        })
            .then(response => {
                response.data.links[0].label = '<i class="fa-solid fa-angle-left"></i>';
                response.data.links[response.data.links.length - 1].label = '<i class="fa-solid fa-angle-right"></i>';

                response.data.data.forEach((file) => {
                    file.extension = file.name.split('.').pop();
                });

                Alpine.store('media').current_page = response.data.current_page;
                Alpine.store('media').per_page = response.data.per_page;
                Alpine.store('media').to = response.data.to;
                Alpine.store('media').total = response.data.total;
                Alpine.store('media').items = response.data.data;

                Alpine.store('media').pagination = [];

                response.data.data.forEach((file) => {
                    if(file.status === 'processing' || file.status === 'queued') {
                        const fileName = file.name;
                        setTimeout(() => {
                            SMMediaPicker.updateThumbnail(fileName);
                        }, 5000);
                    }
                });

                Alpine.nextTick(() => {
                    Alpine.store('media').pagination = response.data.links;
                }).then(r => {
                    /* empty */
                });
            })
        .catch(error => {
                console.error(error);
            });
    },

    html: `
        <div class="flex flex-col h-full w-full" x-data="{tab: 'browser', showFileDrop: false}">
            <template x-if="$store.media.error">
                <div class="flex justify-center" role="alert">
                    <p class="relative bg-red-100 border border-red-400 text-red-700 py-2 pl-4 pr-8 text-xs rounded mb-4"><span x-text="$store.media.error"></span><i class="fa-solid fa-close text-red-900 hover:text-red-700 cursor-pointer absolute top-2 right-2" x-on:click="$store.media.error=null;"></i></p>
                </div>
            </template>
            <ul class="flex -mb-[1px] z-10">
                <li x-show="$store.media.allow_uploads" class="cursor-pointer border px-3 py-2 rounded-t-lg hover:border-t-gray-300 hover:border-x-gray-300" :class="{ 'border-gray-300': tab === 'upload', 'border-b-white': tab === 'upload', 'border-transparent': tab !== 'upload' }" x-on:click.prevent="tab='upload'; SMMediaPicker.stopCamera()">Upload</li>
                <li x-show="$store.media.camera_supported" class="cursor-pointer border px-3 py-2 rounded-t-lg hover:border-t-gray-300 hover:border-x-gray-300" :class="{ 'border-gray-300': tab === 'camera', 'border-b-white': tab === 'camera', 'border-transparent': tab !== 'camera' }" x-on:click.prevent="tab='camera'; $nextTick(() => SMMediaPicker.startCamera())">Camera</li>
                <li class="cursor-pointer border px-3 py-2 rounded-t-lg hover:border-t-gray-300 hover:border-x-gray-300" :class="{ 'border-gray-300': tab === 'browser', 'border-b-white': tab === 'browser', 'border-transparent': tab !== 'browser' }" x-on:click.prevent="tab='browser'; SMMediaPicker.stopCamera()">Browser</li>
            </ul>
            <div
                class="flex-1 min-h-0 border border-gray-300 overflow-hidden"
                x-on:dragenter.prevent="$store.media.allow_uploads ? showFileDrop = true : showFileDrop = false"
                x-on:dragover.prevent="$store.media.allow_uploads ? showFileDrop = true : showFileDrop = false">
                <div
                    id="content-upload"
                    class="w-full h-full flex flex-col px-4 py-8 justify-center items-center"
                    x-show="tab === 'upload'">
                    <h3 class="text-2xl font-bold mb-2">Drop files to upload</h3>
                    <p>or</p>
                    <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                        <label class="inline-block bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" for="media_upload">Select files</label>
                        <label
                            x-show="$store.media.allow_camera"
                            class="inline-block bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                            for="media_camera_upload"
                        >
                            Take photo
                        </label>
                    </div>
                    <input class="hidden" id="media_upload" name="media_upload" multiple type="file" x-on:change="SMMediaPicker.upload(event.target.files)" x-bind:accept="$store.media.require_mime_type" />
                    <input class="hidden" id="media_camera_upload" name="media_camera_upload" type="file" x-on:change="SMMediaPicker.upload(event.target.files)" x-bind:accept="$store.media.require_mime_type" capture="environment" />
                    <p class="text-xs">Maximum upload size: ${SM.bytesToString(SM.maxUploadSize())}</p>
                </div>
                <div
                    id="content-camera"
                    class="w-full h-full flex flex-col px-4 py-6 items-center"
                    x-show="tab === 'camera'"
                >
                    <div class="w-full max-w-3xl flex-1 min-h-0 flex flex-col items-center justify-center gap-4">
                        <div class="relative w-full aspect-video max-h-[24rem] overflow-hidden rounded-xl bg-gray-900 flex items-center justify-center">
                            <video id="media_camera_preview" class="h-full w-full object-cover" :style="'transform: scale(' + ($store.media.camera_flip_x ? -1 : 1) + ', ' + ($store.media.camera_flip_y ? -1 : 1) + ');'" autoplay playsinline muted></video>
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
                                    class="bg-primary-color hover:bg-primary-color-dark justify-center rounded-md text-white px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition mr-6"
                                    x-bind:disabled="!$store.media.camera_ready || $store.media.camera_countdown_active"
                                    x-on:click.prevent="SMMediaPicker.startCameraCountdown()"
                                >
                                    <span>Capture photo</span>
                                </button>
                                <button
                                    type="button"
                                    class="bg-white w-8 h-8 border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                                    x-bind:disabled="$store.media.camera_countdown_active"
                                    x-on:click.prevent="SMMediaPicker.toggleCameraFlipX()"
                                    title="Flip horizontally"
                                >
                                    <i class="fa-solid fa-left-right"></i>
                                </button>
                                <button
                                    type="button"
                                    class="bg-white w-8 h-8 border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                                    x-bind:disabled="$store.media.camera_countdown_active"
                                    x-on:click.prevent="SMMediaPicker.toggleCameraFlipY()"
                                    title="Flip vertically"
                                >
                                    <i class="fa-solid fa-up-down"></i>
                                </button>
                                <button
                                    type="button"
                                    class="bg-white w-8 h-8 border border-gray-300 hover:bg-gray-100 justify-center rounded-md text-gray-700 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"
                                    x-on:click.prevent="SMMediaPicker.stopCamera(); $nextTick(() => SMMediaPicker.startCamera())"
                                    title="Restart Camera"
                                >
                                    <i class="fa-solid fa-power-off"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="content-browser" class="flex flex-col h-full min-h-0 w-full p-4" x-show="tab === 'browser'">
                    <form x-on:submit.prevent="SMMediaPicker.search()">
                        <div class="flex mb-2">
                            <input class="bg-white flex-grow px-2.5 py-1 text-xs text-gray-900 bg-transparent rounded-l-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer border-gray-300 focus:ring-indigo-300" autocomplete="off" placeholder="Search" type="text" name="search" />
                            <button class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color rounded-l-none px-4 justify-center rounded-md text-white py-1.5 text-xs font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </div>
                    </form>
                    <ul class="flex-1 min-h-0 overflow-y-auto p-2 gap-4 justify-center content-start flex flex-row flex-wrap">
                    <template x-for="item in $store.media.items" :key="item.name">
                        <li
                            class="cursor-pointer flex text-center p-1 flex-items-center flex-col h-40 w-56 border-2 rounded relative"
                            :class="{'border-primary-color': $store.media.selected.some(i => i === item.name), 'border-white': !$store.media.selected.some(i => i === item.name)}"
                            x-on:click="SMMediaPicker.updateSelection(item.name)"
                            x-on:dblclick="SMMediaPicker.doubleClick(item.name)"
                            >
                            <div class="absolute top-0 left-0 flex flex-col gap-1 z-10">
                                <i x-show="item.is_private" class="fa-solid fa-eye  text-gray-600 bg-white p-0.75 rounded-full" title="Private media" style="text-shadow: -1px -1px 0 #FFF, 1px -1px 0 #FFF, -1px 1px 0 #FFF, 1px 1px 0 #FFF;"></i>
                                <i x-show="item.password" class="fa-solid fa-lock text-gray-600 bg-white p-0.75 rounded-full" title="Password protected" style="text-shadow: -1px -1px 0 #FFF, 1px -1px 0 #FFF, -1px 1px 0 #FFF, 1px 1px 0 #FFF;"></i>
                            </div>
                            <div x-show="$store.media.selected.some(i => i === item.name)" class="absolute -top-1.5 -right-2 w-6 h-6 bg-primary-color text-white z-10 flex items-center justify-center text-lg border border-white rounded"><i class="fa-solid fa-check"></i></div>
                            <div class="group/image relative flex-grow flex items-center justify-center select-none">
                                <img x-bind:src="item.thumbnail" class="rounded max-h-32 pointer-events-none" />
                            </div>
                            <div class="text-xs whitespace-nowrap overflow-hidden text-ellipsis" x-text="item.name" x-bind:title="item.name"></div>
                        </li>
                    </template>
                    </ul>

                    <div class="flex items-end pt-4 pb-1">
                        <div class="flex w-full items-center justify-between sm:justify-center md:justify-between">
                            <p x-show="$store.media.total > 0" class="hidden md:block text-xs" x-text="'Showing ' + ((($store.media.current_page - 1) * $store.media.per_page) + 1) + ' to ' + ($store.media.current_page * $store.media.per_page > $store.media.total ? $store.media.total : $store.media.current_page * $store.media.per_page) + ' of ' + ($store.media.total) + ' results'"></p>
                            <p x-show="$store.media.total === 0" class="hidden md:block text-xs">No items found</p>

                            <ul class="hidden sm:flex border border-gray-300 rounded-lg text-sm">
                                <template x-for="link in $store.media.pagination">
                                    <li
                                        class="px-2 py-1.5 w-9 border-r last:border-r-0 text-center select-none whitespace-nowrap"
                                        :class="{
                                            'bg-gray-100':              link.url === null,
                                            'text-gray-400':            link.url === null,
                                            'text-primary-color':       link.url !== null && link.label == $store.media.current_page,
                                            'bg-sky-100':               link.url !== null && link.label == $store.media.current_page,
                                            'cursor-pointer':           link.url !== null,
                                            'hover:text-primary-color': link.url !== null,
                                            'hover:bg-sky-100':         link.url !== null
                                            }"
                                        x-html="link.label"
                                        x-on:click="SMMediaPicker.gotoLink(link.url)"></li>
                                </template>
                            </ul>

                            <a
                                :href="$store.media.pagination[0]?.url"
                                class="sm:hidden px-2 py-1.5 border rounded text-center text-sm select-none whitespace-nowrap cursor-pointer"
                                :class="{
                                    'cursor-pointer hover:text-primary-color hover:bg-sky-100': $store.media.pagination[0]?.url,
                                    'pointer-events-none text-gray-400 bg-gray-100': !$store.media.pagination[0]?.url
                                }"
                                x-on:click.prevent="SMMediaPicker.gotoLink($store.media.pagination[0].url)"
                                :disabled="!$store.media.pagination[0]?.url"
                                ><i class="fa-solid fa-angle-left mr-2"></i>Prev
                            </a>
                            <a
                                :href="$store.media.pagination[$store.media.pagination.length - 1]?.url"
                                class="sm:hidden px-2 py-1.5 border rounded text-center text-sm select-none whitespace-nowrap"
                                :class="{
                                    'cursor-pointer hover:text-primary-color hover:bg-sky-100': $store.media.pagination[$store.media.pagination.length - 1]?.url,
                                    'pointer-events-none text-gray-400 bg-gray-100': !$store.media.pagination[$store.media.pagination.length - 1]?.url
                                }"
                                x-on:click.prevent="SMMediaPicker.gotoLink($store.media.pagination[$store.media.pagination.length - 1].url)"
                                :disabled="!$store.media.pagination[$store.media.pagination.length - 1]?.url"
                                >Next<i class="fa-solid fa-angle-right ml-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div
                x-show="showFileDrop"
                class="fixed flex top-0 left-0 w-full h-full z-10 bg-sky-800 bg-opacity-95 text-white items-center p-4"
                x-on:dragenter.prevent="showFileDrop = true"
                x-on:dragover.prevent="showFileDrop = true"
                x-on:drop.prevent="SMMediaPicker.upload($event.dataTransfer.files); showFileDrop = false;"
                x-on:dragleave.prevent="showFileDrop = false">
                <h2
                    class="pointer-events-none flex w-full h-full justify-center items-center text-lg font-bold border-dashed border">
                    Drop files to upload
                </h2>
            </div>
        </div>
    `,

    onOpen: () => {
        SMMediaPicker.query(null, '');
    },

    preClose: () => {
        SMMediaPicker.stopCamera();
    },

    open: (selected, options = {}, callback = null) => {
        if(!options.hasOwnProperty('require_mime_type')) options.require_mime_type = '*';
        if(!options.hasOwnProperty('allow_multiple')) options.allow_multiple = false;
        if(!options.hasOwnProperty('allow_uploads')) options.allow_uploads = false;
        if(!options.hasOwnProperty('allow_camera')) options.allow_camera = false;

        if(selected === null || selected === '') selected = [];
        if(!Array.isArray(selected)) selected = [selected];
        Alpine.store('media').selected = selected;

        Alpine.store('media').require_mime_type = options.require_mime_type;
        Alpine.store('media').allow_multiple = options.allow_multiple;
        Alpine.store('media').allow_uploads = options.allow_uploads;
        Alpine.store('media').allow_camera = options.allow_camera && String(options.require_mime_type || '').includes('image/');
        Alpine.store('media').camera_supported = Alpine.store('media').allow_camera && SMMediaPicker.cameraSupported();
        Alpine.store('media').camera_ready = false;
        Alpine.store('media').camera_starting = false;
        Alpine.store('media').camera_countdown_active = false;
        Alpine.store('media').camera_countdown = 0;
        Alpine.store('media').camera_flip_x = false;
        Alpine.store('media').camera_flip_y = false;
        Alpine.store('media').camera_error = null;
        Alpine.store('media').callback = callback;

        Swal.fire({
            title: options.allow_uploads ? 'Select or Upload Media' : 'Select Media',
            html: SMMediaPicker.html,
            confirmButtonText: 'Select',
            confirmButtonColor: '#0284C7',
            cancelButtonText: 'Cancel',
            showCancelButton: true,
            focusConfirm: false,
            reverseButtons: true,
            didOpen: SMMediaPicker.onOpen,
            preConfirm: SMMediaPicker.preClose,
            willClose: SMMediaPicker.stopCamera,
            customClass: {
                container: 'sm-media-picker-container',
                popup: 'sm-media-picker',
            }
        }).then((result) => {
            if(result.isConfirmed && callback) {
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
        })
    },

    updateThumbnail: (name) => {
        axios.get('/media/' + name)
            .then(response => {
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
            .catch(error => {
                console.error(error);
            });
    }
};

window.SMMediaPicker = SMMediaPicker;

document.addEventListener('DOMContentLoaded', () => {
    Alpine.store('media', {
        require_mime_type: '*',
        allow_multiple: true,
        allow_uploads: false,
        allow_camera: false,
        camera_supported: false,
        camera_ready: false,
        camera_starting: false,
        camera_countdown_active: false,
        camera_countdown: 0,
        camera_flip_x: false,
        camera_flip_y: false,
        camera_error: null,
        current_page: 1,
        per_page: 24,
        to: 0,
        total: 0,
        items: [],
        selected: [],
        pagination: [],
    });
})
