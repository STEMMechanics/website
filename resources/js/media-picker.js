const SMMediaPicker = {
    upload: (files) => {
        const validFiles = Array.from(files).filter((file) => {
            return SM.mimeMatches(file.type, Alpine.store('media').require_mime_type);
        });

        const titles = Array.from(validFiles).map((file) => SM.toTitleCase(file.name));

        SM.upload(validFiles, (response) => {
            response.files.forEach((file) => {
                SMMediaPicker.updateSelection(file.data.name);
            });
            SMMediaPicker.open(
                Alpine.store('media').selected,
                {
                    require_mime_type: Alpine.store('media').require_mime_type,
                    allow_multiple: Alpine.store('media').allow_multiple,
                    allow_uploads: Alpine.store('media').allow_uploads
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
                    console.log(file.name, file.status);
                    if(file.status === 'processing') {
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
            <ul class="flex -mb-[1px] z-10">
                <li x-show="$store.media.allow_uploads" class="cursor-pointer border px-3 py-2 rounded-t-lg hover:border-t-gray-300 hover:border-x-gray-300" :class="{ 'border-gray-300': tab === 'upload', 'border-b-white': tab === 'upload', 'border-transparent': tab !== 'upload' }" x-on:click.prevent="tab='upload'">Upload</li>
                <li class="cursor-pointer border px-3 py-2 rounded-t-lg hover:border-t-gray-300 hover:border-x-gray-300" :class="{ 'border-gray-300': tab === 'browser', 'border-b-white': tab === 'browser', 'border-transparent': tab !== 'browser' }" x-on:click.prevent="tab='browser'">Browser</li>
            </ul>
            <div
                class="flex-1 border border-gray-300 overflow-y-scroll"
                x-on:dragenter.prevent="$store.media.allow_uploads ? showFileDrop = true : showFileDrop = false"
                x-on:dragover.prevent="$store.media.allow_uploads ? showFileDrop = true : showFileDrop = false">
                <div
                    id="content-upload"
                    class="w-full h-full flex flex-col px-4 py-8 justify-center items-center"
                    x-show="tab === 'upload'">
                    <h3 class="text-2xl font-bold mb-2">Drop files to upload</h3>
                    <p>or</p>
                    <label class="inline-block my-2 bg-white border border-gray-300 hover:bg-gray-300 justify-center rounded-md text-gray-700 px-8 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition" for="media_upload">Select files</label>
                    <input class="hidden" id="media_upload" name="media_upload" multiple type="file" x-on:change="SMMediaPicker.upload(event.target.files)" x-bind:accept="$store.media.require_mime_type" />
                    <p class="text-xs">Maximum upload size: ${SM.bytesToString(SM.maxUploadSize())}</p>
                </div>
                <div id="content-browser" class="flex flex-col h-full w-full p-4" x-show="tab === 'browser'">
                    <form x-on:submit.prevent="SMMediaPicker.search()">
                        <div class="flex mb-2">
                            <input class="bg-white flex-grow px-2.5 py-1 text-xs text-gray-900 bg-transparent rounded-l-lg border appearance-none focus:outline-none focus:ring-0 focus:border-blue-600 peer border-gray-300 focus:ring-indigo-300" autocomplete="off" placeholder="Search" type="text" name="search" />
                            <button class="hover:bg-primary-color-dark focus-visible:outline-primary-color bg-primary-color rounded-l-none px-4 justify-center rounded-md text-white py-1.5 text-xs font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </div>
                    </form>

                    <ul class="flex p-2 gap-4 justify-center flex-row flex-wrap">
                    <template x-for="item in $store.media.items" :key="item.name">
                        <li
                            class="cursor-pointer flex text-center p-1 flex-items-center flex-col h-40 w-56 border-2 rounded relative"
                            :class="{'border-primary-color': $store.media.selected.some(i => i === item.name), 'border-white': !$store.media.selected.some(i => i === item.name)}"
                            x-on:click="SMMediaPicker.updateSelection(item.name)"
                            x-on:dblclick="SMMediaPicker.doubleClick(item.name)"
                            >
                            <i x-show="item.password" class="fa-solid fa-lock text-xl text-gray-600 absolute -top-2 -left-1" style="text-shadow: -1px -1px 0 #FFF, 1px -1px 0 #FFF, -1px 1px 0 #FFF, 1px 1px 0 #FFF;"></i>
                            <div x-show="$store.media.selected.some(i => i === item.name)" class="absolute -top-1.5 -right-2 w-6 h-6 bg-primary-color text-white flex items-center justify-center text-lg border border-white rounded"><i class="fa-solid fa-check"></i></div>
                            <div class="flex-grow flex items-center justify-center pointer-events-none select-none">
                                <img x-bind:src="item.thumbnail" class="rounded max-h-32" />
                            </div>
                            <div class="text-xs whitespace-nowrap overflow-hidden text-ellipsis" x-text="item.name" x-bind:title="item.name"></div>
                        </li>
                    </template>
                    </ul>

                    <div class="flex flex-1 items-end pt-4 pb-4">
                        <div class="flex w-full items-center justify-between sm:justify-center md:justify-between">
                            <p x-show="$store.media.total > 0" class="hidden md:block text-xs" x-text="'Showing ' + ((($store.media.current_page - 1) * $store.media.per_page) + 1) + ' to ' + ($store.media.current_page * $store.media.per_page > $store.media.total ? $store.media.total : $store.media.current_page * $store.media.per_page) + ' of ' + ($store.media.total) + ' results'"></p>
                            <p x-show="$store.media.total === 0" class="hidden md:block text-xs">No items found</p>

                            <ul class="hidden sm:flex border rounded-lg text-sm">
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
                                        </div>
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
        /* empty */
    },

    open: (selected, options = {}, callback = null) => {
        if(!options.hasOwnProperty('require_mime_type')) options.require_mime_type = '*';
        if(!options.hasOwnProperty('allow_multiple')) options.allow_multiple = false;
        if(!options.hasOwnProperty('allow_uploads')) options.allow_uploads = false;

        if(selected === null || selected === '') selected = [];
        if(!Array.isArray(selected)) selected = [selected];
        Alpine.store('media').selected = selected;

        Alpine.store('media').require_mime_type = options.require_mime_type;
        Alpine.store('media').allow_multiple = options.allow_multiple;
        Alpine.store('media').allow_uploads = options.allow_uploads;
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
                console.log(response.data.name, response.data.status);
                const item = Alpine.store('media').items.find(i => i.name === name);
                if(item) {
                    if(response.data.status === 'ready') {
                        item.thumbnail = response.data.thumbnail;
                        item.status = response.data.status;
                        item.variants = response.data.variants;
                    } else if(response.data.status === 'processing') {
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
        current_page: 1,
        per_page: 24,
        to: 0,
        total: 0,
        items: [],
        selected: [],
        pagination: [],
    });
})

