<template>
    <div
        class="fixed top-0 left-0 w-full h-full z-2 bg-black bg-op-20 backdrop-blur"></div>
    <div class="fixed top-0 left-0 w-full h-full flex-justify-center flex z-3">
        <div
            class="m-4 border-1 bg-white rounded-xl text-gray-5 px-12 py-8 w-full">
            <div class="dialog-media">
                <SMLoading v-if="progressText" overlay :text="progressText" />
                <h2 class="mb-4">Insert Media</h2>
                <SMTabGroup v-model="selectedTab">
                    <SMTab id="tab-browser" label="Media Browser">
                        <div class="flex mb-4">
                            <SMGroupButtons
                                :buttons="[
                                    {
                                        name: 'grid',
                                        icon: 'grid-outline',
                                    },
                                    {
                                        name: 'list',
                                        icon: 'list-outline',
                                    },
                                ]"
                                :active="listActive"
                                @click="handleClickLayout" />
                            <SMInput
                                v-model="itemSearch"
                                label="Search"
                                class="toolbar-search"
                                small
                                @keyup.enter="handleSearch"
                                @blur="handleSearch">
                                <template #append>
                                    <button
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
                        <div class="media-browser" :class="mediaBrowserClasses">
                            <div class="media-browser-content">
                                <SMLoading v-if="mediaLoading" />
                                <div
                                    v-if="
                                        !mediaLoading && mediaItems.length == 0
                                    "
                                    class="media-none">
                                    <ion-icon name="sad-outline"></ion-icon>
                                    <p>No media found</p>
                                </div>
                                <ul
                                    v-if="
                                        !mediaLoading && mediaItems.length > 0
                                    ">
                                    <li
                                        v-for="item in mediaItems"
                                        :key="item.id"
                                        :class="[
                                            { selected: item.id == selected },
                                        ]"
                                        @click="handleClickItem(item.id)"
                                        @dblclick="handleDblClickItem(item.id)">
                                        <div
                                            :style="{
                                                backgroundImage: `url('${mediaGetVariantUrl(
                                                    item,
                                                    'small'
                                                )}')`,
                                            }"
                                            class="media-image"></div>
                                        <span class="media-title">{{
                                            item.title
                                        }}</span>
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
                                <div width="250px">
                                    <div
                                        class="upload-preview mb-4"
                                        :style="{
                                            backgroundImage: `url(${uploadPreview})`,
                                        }"></div>
                                    <button
                                        v-if="props.allowUpload"
                                        @click="handleClickSelectFile">
                                        Select File
                                    </button>
                                </div>
                                <div>
                                    <SMInput
                                        label="Title"
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
import SMGroupButtons from "../SMGroupButtons.vue";
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
        default: true,
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
    })
);

const uploadPreview = ref("");

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
                    uploadForm.controls.description.value
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
                                (progressData.loaded / progressData.total) * 100
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
                                        setTimeout(resolve, 500)
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
                                                    "Failed"
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
                    firstFile.name
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

<style lang="scss">
.dialog-media {
    width: 100%;

    h3 {
        margin-bottom: 16px;
    }

    .media-browser {
        display: flex;
        flex-direction: column;

        .media-browser-content {
            display: flex;
            height: 40vh;
            border: 1px solid var(--base-color-border);
            background-color: var(--base-color-light);
            justify-content: center;
            align-items: center;
            margin: 0 0 16px 0;

            .media-none {
                font-size: 150%;
                text-align: center;

                ion-icon {
                    font-size: 200%;
                    margin-bottom: 16px;
                }
            }

            ul {
                display: block;
                list-style-type: none;
                overflow: auto;
                max-height: 40vh;
                height: 100%;
                width: 100%;
                gap: 8px;
                justify-content: center;
                padding: 0;

                li {
                    display: flex;
                    align-items: center;
                    border: 3px solid transparent;
                    box-sizing: content-box;
                    padding: 2px;

                    &.selected,
                    &:hover {
                        border-color: var(--primary-color);
                    }

                    .media-image {
                        background-size: contain;
                        background-position: center;
                        background-repeat: no-repeat;
                    }
                }
            }
        }

        &.media-browser-list {
            ul {
                flex-direction: column;
                flex-wrap: nowrap;
            }

            li {
                height: auto;
                width: auto;
            }

            .media-image {
                width: 64px;
                height: 64px;
                margin-right: 8px;
            }

            .media-title {
                flex: 1;
                text-align: left;
            }
        }

        &.media-browser-grid {
            ul {
                display: flex;
                flex-direction: row;
                flex-wrap: wrap;
            }

            li {
                flex-direction: column;
                height: 160px;
                width: 220px;

                .media-image {
                    min-height: 132px;
                    min-width: 220px;
                }

                .media-title {
                    text-align: center;
                    padding: 4px;
                    font-size: 80%;
                    width: 224px;
                    display: block;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
            }
        }
    }

    .upload-preview {
        width: 250px;
        height: 140px;
        border: 1px solid var(--base-color-dark);
        border-radius: 8px;
        background-position: center;
        background-size: cover;
    }
}
</style>
