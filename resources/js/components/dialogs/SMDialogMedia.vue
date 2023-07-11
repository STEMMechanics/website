<template>
    <div
        v-if="showFileDrop"
        class="fixed flex flex-items-center flex-justify-center justify-center top-0 left-0 w-full h-full z-4 bg-sky-800 bg-op-95 text-white"
        @dragenter.prevent="handleDragEnter"
        @dragover.prevent="handleDragOver"
        @drop.prevent="handleDrop"
        @dragleave.prevent="handleDragLeave">
        <h2 class="pointer-events-none">Drop files to upload</h2>
    </div>
    <div
        class="fixed top-0 left-0 w-full h-full z-2 bg-black bg-op-20 backdrop-blur"></div>
    <div
        class="fixed top-0 left-0 right-0 bottom-0 flex-justify-center flex z-3">
        <div
            class="flex flex-col m-4 border-1 bg-white rounded-xl text-gray-5 px-4 md:px-12 py-4 md:py-8 w-full overflow-hidden">
            <SMLoading v-if="progressText" overlay :text="progressText" />
            <h2 class="mb-4">Select or Upload Media</h2>
            <SMTabGroup v-model="selectedTab" class="flex flex-col flex-1">
                <SMTab
                    id="tab-upload"
                    label="Upload"
                    :hide="!props.allowUpload"
                    class="flex flex-1 flex-col flex-items-center flex-justify-center"
                    @dragenter.prevent="handleDragEnter"
                    @dragover.prevent="handleDragOver">
                    <h2>Drop files to upload</h2>
                    <p class="text-sm my-2">or</p>
                    <button
                        type="button"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        @click="handleClickSelectFile">
                        Select Files
                    </button>
                    <p class="text-sm mt-4">
                        {{ max_upload_size }}
                    </p>
                    <input
                        v-if="props.allowUpload"
                        id="file"
                        ref="refUploadInput"
                        type="file"
                        style="display: none"
                        :accept="computedAccepts"
                        @change="handleChangeSelectFile" />
                </SMTab>
                <SMTab
                    id="tab-browser"
                    label="Media Browser"
                    class="flex flex-1 !p-0">
                    <div class="relative h-full w-full">
                        <div
                            ref="refMediaList"
                            class="absolute top-0 left-0 bottom-0 md:right-60 p-4 overflow-auto">
                            <SMInput
                                v-model="itemSearch"
                                label="Search"
                                class="toolbar-search mb-4"
                                small
                                @keyup.enter="handleSearch"
                                @blur="handleSearch">
                                <template #append>
                                    <button
                                        type="button"
                                        class="font-medium px-3 rounded-r-2 hover:shadow-md transition bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                                        @click="handleSearch">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 -960 960 960"
                                            class="h-5">
                                            <path
                                                d="M796-121 533-384q-30 26-69.959 40.5T378-329q-108.162 0-183.081-75Q120-479 120-585t75-181q75-75 181.5-75t181 75Q632-691 632-584.85 632-542 618-502q-14 40-42 75l264 262-44 44ZM377-389q81.25 0 138.125-57.5T572-585q0-81-56.875-138.5T377-781q-82.083 0-139.542 57.5Q180-666 180-585t57.458 138.5Q294.917-389 377-389Z"
                                                fill="currentColor" />
                                        </svg>
                                    </button>
                                </template>
                            </SMInput>
                            <div
                                class="flex flex-col flex-justify-center flex-items-center overflow-auto">
                                <div
                                    v-if="
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
                                    v-if="mediaItems.length > 0"
                                    :class="[
                                        'flex',
                                        'flex-1',
                                        'p-2',
                                        'gap-4',
                                        'overflow-auto',
                                        'flex-justify-center',
                                        'flex-row',
                                        'flex-wrap',
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
                                            'flex-col',
                                            selected != null &&
                                            item.id == selected.id
                                                ? 'selected-checked'
                                                : 'border-white',
                                        ]"
                                        @click="handleClickItem(item.id)"
                                        @dblclick="handleDblClickItem(item.id)">
                                        <div
                                            :class="[
                                                'flex',
                                                'flex-items-center',
                                                'flex-justify-center',
                                                'h-30',
                                                'w-40',
                                                'bg-contain',
                                                'bg-center',
                                                'bg-no-repeat',
                                            ]"
                                            :style="{
                                                backgroundImage:
                                                    item.status === 'OK'
                                                        ? `url('${mediaGetVariantUrl(
                                                              item,
                                                              'small',
                                                          )}')`
                                                        : 'none',
                                                backgroundColor:
                                                    item.status === 'OK'
                                                        ? 'initial'
                                                        : 'rgba(220,220,220,1)',
                                            }">
                                            <SMLoading
                                                v-if="item.status !== 'OK'"
                                                small />
                                        </div>
                                    </li>
                                </ul>
                                <SMLoading v-if="mediaLoading" />
                                <div
                                    v-if="
                                        !mediaLoading && mediaItems.length > 0
                                    "
                                    class="mt-4 text-center">
                                    <p class="text-xs text-black mb-4">
                                        Showing {{ mediaItems.length }} of
                                        {{ totalItems }} media items
                                    </p>
                                    <button
                                        v-if="mediaItems.length < totalItems"
                                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-xs border-1 border-sky-600 text-sky-600 bg-white hover:bg-sky-500 hover:text-white cursor-pointer"
                                        @click.prevent="handleLoadMore">
                                        Load more
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div
                            class="absolute top-0 right-0 bottom-0 w-60 p-4 border-l border-gray-3 bg-gray-1 rounded-r-2 overflow-auto hidden md:block">
                            <div
                                v-if="uploadFileList"
                                class="flex flex-col text-xs border-b border-gray-3 pb-4 mb-4">
                                <h3 class="text-xs mb-2">Uploading</h3>
                                <div
                                    class="w-full bg-gray-3 h-3 mb-2 rounded-2">
                                    <div
                                        class="bg-sky-600 h-3 rounded-2"
                                        :style="{
                                            width: `${
                                                (100 / uploadFileList.length) *
                                                (currentUploadFileNum - 1)
                                            }%`,
                                        }"></div>
                                </div>
                                <p class="m-0">
                                    {{ currentUploadFileNum }} /
                                    {{
                                        uploadFileList && uploadFileList.length
                                    }}
                                    -
                                    {{
                                        uploadFileList &&
                                        uploadFileList.length >=
                                            currentUploadFileNum
                                            ? uploadFileList[
                                                  currentUploadFileNum - 1
                                              ].name
                                            : ""
                                    }}
                                </p>
                            </div>
                            <div v-if="selected != null">
                                <div
                                    class="flex flex-col text-xs border-b border-gray-3 pb-4">
                                    <p class="m-0 text-bold">
                                        {{ selected.title }}
                                    </p>
                                    <p class="m-0">
                                        {{
                                            new SMDate(
                                                selected.created_at,
                                            ).format("MMM dd, yyyy")
                                        }}
                                    </p>
                                    <p class="m-0">
                                        {{ bytesReadable(selected.size, 0) }}
                                    </p>
                                </div>
                                <div class="py-2">
                                    <SMInput
                                        class="mb-2"
                                        label="Title"
                                        v-model:modelValue="selected.title"
                                        :small="true" />
                                    <SMInput
                                        class="mb-2"
                                        label="Description"
                                        textarea
                                        v-model:modelValue="
                                            selected.description
                                        "
                                        :small="true" />
                                </div>
                            </div>
                        </div>
                    </div>
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
                    :disabled="computedSelectDisabled"
                    :class="[
                        'font-medium',
                        'px-6',
                        'py-1.5',
                        'rounded-md',
                        'hover:shadow-md',
                        'transition',
                        'text-sm',
                        'bg-sky-600',
                        'hover:bg-sky-500',
                        'text-white',
                        'cursor-pointer',
                        [
                            'disabled-bg-gray',
                            'disabled-text-white',
                            'hover-disabled-bg-gray',
                            'disabled-cursor-not-allowed',
                        ],
                    ]"
                    @click="handleClickSelect">
                    Select
                </button>
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
import {
    ApiInfo,
    Media,
    MediaCollection,
    MediaResponse,
} from "../../helpers/api.types";
import { useApplicationStore } from "../../store/ApplicationStore";
import { mediaGetVariantUrl } from "../../helpers/media";
import SMInput from "../SMInput.vue";
import SMLoading from "../SMLoading.vue";
import SMTabGroup from "../SMTabGroup.vue";
import SMTab from "../SMTab.vue";
import { Form, FormControl } from "../../helpers/form";
import { And, Min, Required } from "../../helpers/validate";
import { convertFileNameToTitle } from "../../helpers/utils";
import { bytesReadable } from "../../helpers/types";
import { SMDate } from "../../helpers/datetime";
import { isUUID } from "../../helpers/uuid";

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

const refMediaList = ref<HTMLUListElement | null>(null);

/**
 * The selected tab
 */
const selectedTab = ref("tab-browser");

/**
 * Max upload size
 */
const max_upload_size = ref(" ");

/**
 * Upload form
 */
let uploadForm = reactive(
    Form({
        title: FormControl("", And([Required(), Min(4)])),
        description: FormControl(""),
    }),
);

/**
 * Is the media loading/busy
 */
const mediaLoading = ref(true);

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
const selected: Ref<Media | null> = ref(null);

/**
 * How many media items are we showing per page.
 */
const perPage = ref(24);

const showFileDrop = ref(false);

const applicationStore = useApplicationStore();
const progressText = ref("");

const currentUploadFileNum = ref(0);
const uploadFileList: Ref<File[]> = ref(null);

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
 * Handle user clicking the select button.
 */
const handleClickSelect = async () => {
    if (selectedTab.value == "tab-browser") {
        if (selected.value != null) {
            closeDialog(selected.value);
            return;
        }
    }
};

/**
 * Handle user clicking a media item (selecting).
 * @param {string} item_id The media id.
 */
const handleClickItem = (item_id: string): void => {
    if (isUUID(item_id)) {
        selected.value = getMediaItem(item_id);
    } else {
        selected.value = null;
    }
};

/**
 * Handle user double clicking a media item.
 * @param item_id The media id.
 */
const handleDblClickItem = (item_id: string): void => {
    if (isUUID(item_id)) {
        const mediaItem = getMediaItem(item_id);
        if (mediaItem != null) {
            closeDialog(mediaItem);
            return;
        }

        closeDialog(false);
    }
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
        handleFilesUpload(refUploadInput.value.files);
        showFileBrowserTab();
    }
};

const handleFilesUpload = (files: FileList) => {
    Array.from(files).forEach((file, index) => {
        console.log(index);
        mediaItems.value.unshift({
            id: (currentUploadFileNum.value + index + 1).toString(),
            user_id: "",
            title: "",
            name: file.name,
            mime_type: "",
            permission: "",
            size: 0,
            status: "",
            storage: "",
            url: "",
            description: "",
            dimensions: "",
            variants: {},
            created_at: "",
            updated_at: "",
        });
    });

    if (uploadFileList.value != null) {
        uploadFileList.value.push(...Array.from(files));
    } else {
        uploadFileList.value = Array.from(files);
    }

    startFilesUpload();
};

const startFilesUpload = () => {
    if (uploadFileList.value != null) {
        if (currentUploadFileNum.value < 1) {
            currentUploadFileNum.value = 1;

            while (currentUploadFileNum.value <= uploadFileList.value.length) {
                let submitFormData = new FormData();
    submitFormData.append("file", file);
    submitFormData.append("title", convertFileNameToTitle(file.name));
    submitFormData.append("description", "");
    try {
        let result = await api.post({
            url: "/media",
            body: submitFormData,
            headers: {
                "Content-Type": "multipart/form-data",
            }
        });
        if(result.adata)
    }
    catch ((error) => {
            /* empty */
    });

            }
        }
    }
};

// const firstFile: File | undefined = refUploadInput.value.files[0];
//     if (firstFile != null) {
//         if (uploadForm.controls.title.value.length == 0) {
//             uploadForm.controls.title.value = convertFileNameToTitle(
//                 firstFile.name,
//             );
//         }
//         const reader = new FileReader();
//         reader.onload = (event) => {
//             const imgSrc = event.target.result;
//             uploadPreview.value = imgSrc as string;
//         };
//         reader.readAsDataURL(firstFile);
//     }
// } else if (selectedTab.value == "tab-upload") {
//     if (
//         refUploadInput.value != null &&
//         refUploadInput.value.files != null
//     ) {
//         const firstFile: File | undefined = refUploadInput.value.files[0];
//         if (firstFile != null) {
//             let submitFormData = new FormData();
//             submitFormData.append("file", firstFile);
//             submitFormData.append("title", uploadForm.controls.title.value);
//             submitFormData.append(
//                 "description",
//                 uploadForm.controls.description.value,
//             );
//             try {
//                 let result = await api.post({
//                     url: "/media",
//                     body: submitFormData,
//                     headers: {
//                         "Content-Type": "multipart/form-data",
//                     },
//                     progress: (progressData) =>
//                         (progressText.value = `Uploading File: ${Math.floor(
//                             (progressData.loaded / progressData.total) *
//                                 100,
//                         )}%`),
//                 });
//                 if (result.data) {
//                     const data = result.data as MediaResponse;
//                     if (
//                         data.medium.status != "OK" &&
//                         data.medium.status.startsWith("Failed") == false
//                     ) {
//                         progressText.value = `${data.medium.status}...`;
//                         let mediaProcessed = false;
//                         let timeout = 0;
//                         while (mediaProcessed == false) {
//                             timeout++;
//                             if (timeout >= 60) {
//                                 mediaProcessed = true;
//                                 uploadForm._message =
//                                     "The server is taking longer then expected to process the file.\nOnce the file has been processed, select it from the media browser.";
//                             } else {
//                                 await new Promise((resolve) =>
//                                     setTimeout(resolve, 500),
//                                 );
//                                 try {
//                                     let updateResult = await api.get({
//                                         url: "/media/{id}",
//                                         params: {
//                                             id: data.medium.id,
//                                         },
//                                     });
//                                     if (updateResult.data) {
//                                         const updateData =
//                                             updateResult.data as MediaResponse;
//                                         if (
//                                             updateData.medium.status == "OK"
//                                         ) {
//                                             data.medium = updateData.medium;
//                                             mediaProcessed = true;
//                                         } else if (
//                                             updateData.medium.status.startsWith(
//                                                 "Failed",
//                                             ) == true
//                                         ) {
//                                             throw "error";
//                                         } else {
//                                             progressText.value = `${updateData.medium.status}...`;
//                                         }
//                                     } else {
//                                         throw "error";
//                                     }
//                                 } catch {
//                                     mediaProcessed = true;
//                                     uploadForm._message =
//                                         "An server error occurred processing the file.";
//                                 }
//                             }
//                         }
//                         if (data.medium.status == "OK") {
//                             closeDialog(data.medium);
//                         } else {
//                             return;
//                         }
//                     }
//                 } else {
//                     uploadForm._message =
//                         "An unexpected response was received from the server";
//                 }
//             } catch (error) {
//                 if (error.status === 413) {
//                     uploadForm._message =
//                         "The selected file is larger than the maximum size limit";
//                 } else {
//                     uploadForm._message =
//                         error.response?.data?.message ||
//                         "An unexpected error occurred";
//                 }
//             } finally {
//                 progressText.value = "";
//             }
//         } else {
//             uploadForm._message = "No file was selected to upload";
//         }
//     } else {
//         uploadForm._message = "No file was selected to upload";
//     }

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
        status: "!Failed",
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
                mediaItems.value.push(...data.media);
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
const eventKeyPress = (event: KeyboardEvent): boolean => {
    if (event.key === "Escape") {
        handleClickCancel();
        return true;
    } else if (event.key === "Enter") {
        if (selected.value != null) {
            handleClickSelect();
        }

        return true;
    }

    return false;
};

onMounted(() => {
    applicationStore.addKeyPressListener(eventKeyPress);
});

onUnmounted(() => {
    applicationStore.removeKeyPressListener(eventKeyPress);
});

watch(page, () => {
    handleLoad();
});

/**
 * Determine if the Select button should be disabled
 */
const computedSelectDisabled = computed(() => {
    if (selectedTab.value == "tab-browser") {
        return selected.value == null;
    }

    return true;
});

const handleDragEnter = () => {
    if (!showFileDrop.value) {
        showFileDrop.value = true;
    }
};

const handleDragOver = () => {
    if (!showFileDrop.value) {
        showFileDrop.value = true;
    }
};

const handleDragLeave = () => {
    if (showFileDrop.value) {
        showFileDrop.value = false;
    }
};

const handleDrop = (event) => {
    showFileDrop.value = false;
    handleFilesUpload(event.dataTransfer.files);
    showFileBrowserTab();
};

const handleLoadMore = () => {
    page.value++;
};

const showFileBrowserTab = () => {
    selectedTab.value = "tab-browser";
    window.setTimeout(() => {
        refMediaList.value.scrollTo({
            top: 0,
            left: 0,
            behavior: "smooth",
        });
    }, 50);
};

// Get max upload size
api.get({
    url: "",
})
    .then((result) => {
        if (result.data) {
            const data = result.data as ApiInfo;

            max_upload_size.value = `Maximum upload file size: ${bytesReadable(
                data.max_upload_size,
            )}.`;
        }
    })
    .catch(() => {
        /* empty */
    });

handleLoad();
</script>
