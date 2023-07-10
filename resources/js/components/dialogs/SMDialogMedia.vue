<template>
    <div
        class="fixed top-0 left-0 w-full h-full z-2 bg-black bg-op-20 backdrop-blur"></div>
    <div
        class="fixed top-0 left-0 w-full flex-justify-center flex z-3 max-h-screen">
        <div
            class="m-4 border-1 bg-white rounded-xl text-gray-5 px-12 py-8 w-full overflow-auto">
            <div>
                <SMLoading v-if="progressText" overlay :text="progressText" />
                <h2 class="mb-4">Insert Media</h2>
                <SMTabGroup v-model="selectedTab">
                    <SMTab id="tab-browser" label="Media Browser">
                        <div class="flex mb-4">
                            <button
                                title="View as grid"
                                :class="[
                                    'p-2',
                                    'rounded-l-2',
                                    'hover:shadow-md',
                                    'transition',
                                    'border-1',
                                    'border-sky-600',
                                    'cursor-pointer',
                                    listActive != 'grid'
                                        ? [
                                              'text-sky-600',
                                              'bg-white',
                                              'hover:bg-sky-500',
                                              'hover:text-white',
                                          ]
                                        : ['text-white', 'bg-sky-600'],
                                ]"
                                @click="handleClickLayout('grid')">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M3,11H11V3H3M3,21H11V13H3M13,21H21V13H13M13,3V11H21V3"
                                        fill="currentColor" />
                                </svg>
                            </button>
                            <button
                                title="View as list"
                                :class="[
                                    'p-2',
                                    'rounded-r-2',
                                    'hover:shadow-md',
                                    'transition',
                                    'hover:bg-sky-500',
                                    'border-1',
                                    'border-sky-600',
                                    'cursor-pointer',
                                    'mr-4',
                                    listActive != 'list'
                                        ? [
                                              'text-sky-600',
                                              'bg-white',
                                              'hover:bg-sky-500',
                                              'hover:text-white',
                                          ]
                                        : ['text-white', 'bg-sky-600'],
                                ]"
                                @click="handleClickLayout('list')">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M9,5V9H21V5M9,19H21V15H9M9,14H21V10H9M4,9H8V5H4M4,19H8V15H4M4,14H8V10H4V14Z"
                                        fill="currentColor" />
                                </svg>
                            </button>
                            <SMInput
                                v-model="itemSearch"
                                label="Search"
                                class="toolbar-search"
                                small
                                @keyup.enter="handleSearch"
                                @blur="handleSearch">
                                <template #append>
                                    <button
                                        type="button"
                                        class="font-medium px-4 py-3.1 rounded-r-2 hover:shadow-md transition bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                                        @click="handleSearch">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 -960 960 960"
                                            class="h-4">
                                            <path
                                                d="M796-121 533-384q-30 26-69.959 40.5T378-329q-108.162 0-183.081-75Q120-479 120-585t75-181q75-75 181.5-75t181 75Q632-691 632-584.85 632-542 618-502q-14 40-42 75l264 262-44 44ZM377-389q81.25 0 138.125-57.5T572-585q0-81-56.875-138.5T377-781q-82.083 0-139.542 57.5Q180-666 180-585t57.458 138.5Q294.917-389 377-389Z"
                                                fill="currentColor" />
                                        </svg>
                                    </button>
                                </template>
                            </SMInput>
                        </div>
                        <div class="flex">
                            <div
                                class="flex flex-justify-center overflow-auto w-full">
                                <SMLoading v-if="mediaLoading" />
                                <div
                                    v-else-if="
                                        !mediaLoading && mediaItems.length == 0
                                    "
                                    class="py-12 text-center">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 -960 960 960"
                                        class="h-24 text-gray-5">
                                        <path
                                            d="M453-280h60v-240h-60v240Zm26.982-314q14.018 0 23.518-9.2T513-626q0-14.45-9.482-24.225-9.483-9.775-23.5-9.775-14.018 0-23.518 9.775T447-626q0 13.6 9.482 22.8 9.483 9.2 23.5 9.2Zm.284 514q-82.734 0-155.5-31.5t-127.266-86q-54.5-54.5-86-127.341Q80-397.681 80-480.5q0-82.819 31.5-155.659Q143-709 197.5-763t127.341-85.5Q397.681-880 480.5-880q82.819 0 155.659 31.5Q709-817 763-763t85.5 127Q880-563 880-480.266q0 82.734-31.5 155.5T763-197.684q-54 54.316-127 86Q563-80 480.266-80Zm.234-60Q622-140 721-239.5t99-241Q820-622 721.188-721 622.375-820 480-820q-141 0-240.5 98.812Q140-622.375 140-480q0 141 99.5 240.5t241 99.5Zm-.5-340Z"
                                            fill="currentColor" />
                                    </svg>
                                    <p class="text-lg text-gray-5">
                                        No media found
                                    </p>
                                </div>
                                <ul
                                    v-if="
                                        !mediaLoading && mediaItems.length > 0
                                    "
                                    :class="[
                                        'flex',
                                        'flex-1',
                                        'gap-4',
                                        'border-1',
                                        'mb-4',
                                        'p-3',
                                        'overflow-auto',
                                        'flex-justify-center',
                                        listActive == 'grid'
                                            ? ['flex-row', 'flex-wrap']
                                            : ['flex-col', 'flex-nowrap'],
                                    ]">
                                    <li
                                        v-for="item in mediaItems"
                                        :key="item.id"
                                        :class="[
                                            'flex',
                                            'text-center',
                                            'border-3',
                                            'p-1px',
                                            'flex-items-center',
                                            listActive == 'grid'
                                                ? ['h-50', 'w-60', 'flex-col']
                                                : ['ha', 'w-full', 'flex-row'],
                                            item.id == selected
                                                ? 'border-sky-600'
                                                : 'border-white',
                                        ]"
                                        @click="handleClickItem(item.id)"
                                        @dblclick="handleDblClickItem(item.id)">
                                        <div
                                            :class="[
                                                listActive == 'grid'
                                                    ? ['h-40', 'w-60', 'mr-0']
                                                    : ['h-20', 'w-20', 'mr-2'],
                                                'bg-contain',
                                                'bg-center',
                                                'bg-no-repeat',
                                            ]"
                                            :style="{
                                                backgroundImage: `url('${mediaGetVariantUrl(
                                                    item,
                                                    'small',
                                                )}')`,
                                            }"></div>
                                        <span
                                            class="text-sm whitespace-nowrap overflow-hidden text-ellipsis block p-2"
                                            >{{ item.title }}</span
                                        >
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div>
                            <SMPagination
                                v-model="page"
                                :total="totalItems"
                                :per-page="perPage"
                                small
                                class="my-0" />
                        </div>
                    </SMTab>
                    <SMTab id="tab-upload" label="Upload">
                        <SMForm v-model="uploadForm" form-id="upload-form">
                            <SMFormError v-model="uploadForm" />
                            <div class="flex">
                                <div class="text-center mr-4 w-60">
                                    <div
                                        class="mb-4 h-34 border rounded-2 bg-cover bg-center bg-no-repeat"
                                        :style="{
                                            backgroundImage:
                                                uploadPreview.length > 0
                                                    ? `url(${uploadPreview})`
                                                    : `url(\'${uploadPreviewMissing}\')`,
                                        }"></div>
                                    <button
                                        type="button"
                                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                                        @click="handleClickSelectFile">
                                        Select File
                                    </button>
                                </div>
                                <div class="flex-1">
                                    <SMInput
                                        label="Title"
                                        class="mb-4"
                                        control="title"
                                        form-id="upload-form"
                                        :disabled="uploadPreview.length == 0" />
                                    <SMInput
                                        type="textarea"
                                        label="Description"
                                        control="description"
                                        form-id="upload-form"
                                        :disabled="uploadPreview.length == 0" />
                                </div>
                            </div>
                        </SMForm>
                        <input
                            v-if="props.allowUpload"
                            id="file"
                            ref="refUploadInput"
                            type="file"
                            style="display: none"
                            :accept="computedAccepts"
                            @change="handleChangeSelectFile" />
                    </SMTab>
                </SMTabGroup>
                <div class="flex flex-justify-end">
                    <button
                        type="button"
                        class="mr-4 font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        @click="handleClickCancel">
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="computedInsertDisabled"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        @click="handleClickInsert">
                        Insert
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import {
    computed,
    onMounted,
    onUnmounted,
    reactive,
    ref,
    Ref,
    watch,
} from "vue";
import { closeDialog } from "../SMDialog";
import { api } from "../../helpers/api";
import { Media, MediaCollection, MediaResponse } from "../../helpers/api.types";
import { useApplicationStore } from "../../store/ApplicationStore";
import { mediaGetVariantUrl } from "../../helpers/media";
import SMInput from "../SMInput.vue";
import SMPagination from "../SMPagination.vue";
import SMLoading from "../SMLoading.vue";
import SMTabGroup from "../SMTabGroup.vue";
import SMTab from "../SMTab.vue";
import { Form, FormControl } from "../../helpers/form";
import { And, Min, Required } from "../../helpers/validate";
import SMForm from "../SMForm.vue";
import SMFormError from "../SMFormError.vue";
import { convertFileNameToTitle } from "../../helpers/utils";

const props = defineProps({
    mime: {
        type: String,
        default: "image/",
        required: false,
    },
    accepts: {
        type: String,
        default: "image/*",
        required: false,
    },
    allowUpload: {
        type: Boolean,
        default: false,
        required: false,
    },
});

/**
 * Reference to the File Upload Input element.
 */
const refUploadInput = ref<HTMLInputElement | null>(null);

/**
 * The selected tab
 */
const selectedTab = ref("");

/**
 * Upload form
 */
let uploadForm = reactive(
    Form({
        title: FormControl("", And([Required(), Min(4)])),
        description: FormControl(""),
    }),
);

const uploadPreview = ref("");
const uploadPreviewMissing = ref(
    'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="-20 -20 64 64"%3E%3Cpath d="M22 20.7L3.3 2L2 3.3L3 4.3V19C3 20.1 3.9 21 5 21H19.7L20.7 22L22 20.7M5 19V6.3L12.6 13.9L11.1 15.8L9 13.1L6 17H15.7L17.7 19H5M8.8 5L6.8 3H19C20.1 3 21 3.9 21 5V17.2L19 15.2V5H8.8" /%3E%3C/svg%3E',
);
/**
 * Is the media loading/busy
 */
const mediaLoading = ref(true);

/**
 * Classes to apply to the media browser
 */
const mediaBrowserClasses = ref(["media-browser-grid"]);

/**
 * Current page.
 */
const page = ref(1);

/**
 * Total media items expressed by API.
 */
const totalItems = ref(0);

/**
 * List of current media items.
 */
const mediaItems: Ref<Media[]> = ref([]);

/**
 * Selected media item id.
 */
const selected = ref("");

/**
 * How many media items are we showing per page.
 */
const perPage = ref(24);

const applicationStore = useApplicationStore();
const progressText = ref("");

/**
 * Returns the file types accepted.
 */
const computedAccepts = computed(() => {
    if (props.accepts.length > 0) {
        return props.accepts;
    }

    if (props.mime.endsWith("/")) {
        return `${props.mime}*`;
    }

    return props.mime;
});

/**
 * Get the media item by id.
 * @param {string} item_id The media item id.
 * @returns {Media | null} The media object or null.
 */
const getMediaItem = (item_id: string): Media | null => {
    let found: Media | null = null;

    mediaItems.value.every((item) => {
        if (item.id == item_id) {
            found = item;
            return false;
        }

        return true;
    });

    return found;
};

/**
 * Handle user clicking the cancel/close button.
 */
const handleClickCancel = () => {
    closeDialog(false);
};

/**
 * Handle user clicking the insert button.
 */
const handleClickInsert = async () => {
    if (selectedTab.value == "tab-browser") {
        if (selected.value !== "") {
            const mediaItem = getMediaItem(selected.value);
            if (mediaItem != null) {
                closeDialog(mediaItem);
                return;
            }
        }
    } else if (selectedTab.value == "tab-upload") {
        if (
            refUploadInput.value != null &&
            refUploadInput.value.files != null
        ) {
            const firstFile: File | undefined = refUploadInput.value.files[0];
            if (firstFile != null) {
                let submitFormData = new FormData();
                submitFormData.append("file", firstFile);
                submitFormData.append("title", uploadForm.controls.title.value);
                submitFormData.append(
                    "description",
                    uploadForm.controls.description.value,
                );
                try {
                    let result = await api.post({
                        url: "/media",
                        body: submitFormData,
                        headers: {
                            "Content-Type": "multipart/form-data",
                        },
                        progress: (progressData) =>
                            (progressText.value = `Uploading File: ${Math.floor(
                                (progressData.loaded / progressData.total) *
                                    100,
                            )}%`),
                    });
                    if (result.data) {
                        const data = result.data as MediaResponse;
                        if (
                            data.medium.status != "OK" &&
                            data.medium.status.startsWith("Failed") == false
                        ) {
                            progressText.value = `${data.medium.status}...`;
                            let mediaProcessed = false;
                            let timeout = 0;
                            while (mediaProcessed == false) {
                                timeout++;
                                if (timeout >= 60) {
                                    mediaProcessed = true;
                                    uploadForm._message =
                                        "The server is taking longer then expected to process the file.\nOnce the file has been processed, select it from the media browser.";
                                } else {
                                    await new Promise((resolve) =>
                                        setTimeout(resolve, 500),
                                    );
                                    try {
                                        let updateResult = await api.get({
                                            url: "/media/{id}",
                                            params: {
                                                id: data.medium.id,
                                            },
                                        });
                                        if (updateResult.data) {
                                            const updateData =
                                                updateResult.data as MediaResponse;

                                            if (
                                                updateData.medium.status == "OK"
                                            ) {
                                                data.medium = updateData.medium;
                                                mediaProcessed = true;
                                            } else if (
                                                updateData.medium.status.startsWith(
                                                    "Failed",
                                                ) == true
                                            ) {
                                                throw "error";
                                            } else {
                                                progressText.value = `${updateData.medium.status}...`;
                                            }
                                        } else {
                                            throw "error";
                                        }
                                    } catch {
                                        mediaProcessed = true;
                                        uploadForm._message =
                                            "An server error occurred processing the file.";
                                    }
                                }
                            }

                            if (data.medium.status == "OK") {
                                closeDialog(data.medium);
                            } else {
                                return;
                            }
                        }
                    } else {
                        uploadForm._message =
                            "An unexpected response was received from the server";
                    }
                } catch (error) {
                    if (error.status === 413) {
                        uploadForm._message =
                            "The selected file is larger than the maximum size limit";
                    } else {
                        uploadForm._message =
                            error.response?.data?.message ||
                            "An unexpected error occurred";
                    }
                } finally {
                    progressText.value = "";
                }
            } else {
                uploadForm._message = "No file was selected to upload";
            }
        } else {
            uploadForm._message = "No file was selected to upload";
        }
    }

    // closeDialog(false);
};

/**
 * Handle user clicking a media item (selecting).
 * @param {string} item_id The media id.
 */
const handleClickItem = (item_id: string): void => {
    selected.value = item_id;
};

/**
 * Handle user double clicking a media item.
 * @param item_id The media id.
 */
const handleDblClickItem = (item_id: string): void => {
    const mediaItem = getMediaItem(item_id);
    if (mediaItem != null) {
        closeDialog(mediaItem);
        return;
    }

    closeDialog(false);
};

const listActive = ref("grid");

/**
 * Handle Grid layout request click
 * @param name
 */
const handleClickLayout = (name: string) => {
    listActive.value = name;
    mediaBrowserClasses.value = [`media-browser-${listActive.value}`];
};

/**
 * When the user clicks the upload button
 */
const handleClickSelectFile = async () => {
    if (refUploadInput.value != null) {
        refUploadInput.value.click();
    }
};

/**
 * Upload the file to the server.
 */
const handleChangeSelectFile = async () => {
    uploadForm._message = "";

    if (refUploadInput.value != null && refUploadInput.value.files != null) {
        const firstFile: File | undefined = refUploadInput.value.files[0];
        if (firstFile != null) {
            if (uploadForm.controls.title.value.length == 0) {
                uploadForm.controls.title.value = convertFileNameToTitle(
                    firstFile.name,
                );
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                const imgSrc = event.target.result;
                uploadPreview.value = imgSrc as string;
            };
            reader.readAsDataURL(firstFile);
        }
    }
};

let prevItemSearch = "";
const itemSearch = ref("");

const handleSearch = () => {
    if (prevItemSearch !== itemSearch.value) {
        prevItemSearch = itemSearch.value;
        mediaItems.value = [];
        totalItems.value = 0;
        page.value = 1;

        handleLoad();
    }
};

/**
 * Load the data of the dialog
 */
const handleLoad = async () => {
    mediaLoading.value = true;

    let params = {
        page: page.value,
        limit: perPage.value,
    };

    if (itemSearch.value.length > 0) {
        let value = itemSearch.value.replace(/"/g, '\\"');
        params[
            "filter"
        ] = `(title:"${value}",OR,name:"${value}",OR,description:"${value}")`;
    }

    api.get({
        url: "/media",
        params: params,
    })
        .then((result) => {
            if (result.data) {
                const data = result.data as MediaCollection;

                totalItems.value = data.total;
                mediaItems.value = data.media;
            }
        })
        .catch((error) => {
            uploadForm._message =
                error?.data?.message || "An unexpected error occurred";
        })
        .finally(() => {
            mediaLoading.value = false;
        });
};

/**
 * Handle a keyboard event in this component.
 * @param {KeyboardEvent} event The keyboard event.
 * @returns {boolean} If the event was handled.
 */
const eventKeyUp = (event: KeyboardEvent): boolean => {
    if (event.key === "Escape") {
        handleClickCancel();
        return true;
    } else if (event.key === "Enter") {
        if (selected.value.length > 0) {
            handleClickInsert();
        }

        return true;
    }

    return false;
};

onMounted(() => {
    applicationStore.addKeyUpListener(eventKeyUp);
});

onUnmounted(() => {
    applicationStore.removeKeyUpListener(eventKeyUp);
});

watch(page, () => {
    handleLoad();
});

/**
 * Determine if the Insert button should be disabled
 */
const computedInsertDisabled = computed(() => {
    if (selectedTab.value == "tab-browser") {
        return selected.value.length == 0;
    } else if (selectedTab.value == "tab-upload") {
        return uploadPreview.value.length == 0;
    }

    return false;
});

handleLoad();
</script>
