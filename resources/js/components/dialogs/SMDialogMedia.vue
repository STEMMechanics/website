<template>
    <div
        v-if="showFileDrop"
        class="fixed flex top-0 left-0 w-full h-full z-4 bg-sky-800 bg-op-95 text-white"
        @dragenter.prevent="handleDragEnter"
        @dragover.prevent="handleDragOver"
        @drop.prevent="handleDrop"
        @dragleave.prevent="handleDragLeave">
        <h2
            class="pointer-events-none flex w-full flex-items-center flex-justify-center b-dashed border-1 m-4">
            Drop files to upload
        </h2>
    </div>
    <div
        class="fixed top-0 left-0 w-full h-full bg-black bg-op-20 backdrop-blur"></div>
    <div
        class="fixed top-0 left-0 right-0 bottom-0 flex-justify-center flex"
        @dragenter.prevent="handleDragEnter"
        @dragover.prevent="handleDragOver">
        <div
            class="flex flex-col m-4 border-1 bg-white rounded-xl text-gray-5 px-4 md:px-12 py-4 md:py-8 w-full overflow-hidden">
            <h2 class="mb-4">Select or Upload Media</h2>
            <SMTabGroup v-model="selectedTab" class="flex flex-col flex-1">
                <SMTab
                    id="tab-upload"
                    label="Upload"
                    :hide="!allowUploads"
                    class="flex flex-1 flex-col flex-items-center flex-justify-center">
                    <h2>Drop files to upload</h2>
                    <p class="text-sm my-2">or</p>
                    <button
                        type="button"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        @click="handleClickSelectFile">
                        Select File{{ props.multiple ? "s" : "" }}
                    </button>
                    <p class="text-sm mt-4">
                        Maximum upload file size: {{ max_upload_size }}
                    </p>
                    <input
                        v-if="allowUploads"
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
                                            selected.findIndex(
                                                (selectedItem) =>
                                                    selectedItem.id === item.id,
                                            ) > -1
                                                ? 'selected-checked'
                                                : 'border-white',
                                        ]"
                                        @click="handleClickItem(item.id)"
                                        @dblclick="handleDblClickItem(item.id)">
                                        <div
                                            :class="[
                                                'my-1',
                                                'h-30',
                                                'w-40',
                                                'bg-contain',
                                                'bg-center',
                                                'bg-no-repeat',
                                                'relative',
                                                'mb-6',
                                            ]"
                                            :style="{
                                                backgroundImage: `url('${mediaGetThumbnail(
                                                    item,
                                                )}')`,
                                            }">
                                            <div
                                                v-if="item.security_type != ''">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 24 24">
                                                    <title>locked</title>
                                                    <path
                                                        d="M12,17A2,2 0 0,0 14,15C14,13.89 13.1,13 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10C4,8.89 4.9,8 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z" />
                                                </svg>
                                            </div>
                                            <div
                                                class="absolute -bottom-6 small w-full text-ellipsis overflow-hidden whitespace-nowrap">
                                                {{ item.title }}
                                            </div>
                                            <SMLoading
                                                v-if="getMediaStatus(item).busy"
                                                small
                                                class="bg-white bg-op-90 w-full h-full">
                                                {{
                                                    getMediaStatusText(item)
                                                }}</SMLoading
                                            >
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
                                        {{ totalItems }}
                                        media item{{
                                            totalItems == 1 ? "" : "s"
                                        }}
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
                                v-if="getUploadingMediaItems().length > 0"
                                class="flex flex-col text-xs border-b border-gray-3 pb-4 mb-4">
                                <h3 class="text-xs mb-2">
                                    {{ computedUploadMediaTitle }}
                                </h3>
                                <div
                                    class="w-full bg-gray-3 h-3 mb-2 rounded-2">
                                    <div
                                        class="bg-sky-600 h-3 rounded-2"
                                        :style="{
                                            width: `${computedUploadProgress}%`,
                                        }"></div>
                                </div>
                                <p class="m-0">
                                    {{ computedUploadMediaStatus }}
                                </p>
                            </div>
                            <div
                                v-if="getProcessingMediaItems().length > 0"
                                class="flex flex-col text-xs border-b border-gray-3 pb-4 mb-4">
                                <h3 class="text-xs mb-2">
                                    {{ computedProcessingMediaTitle }}
                                </h3>
                                <div
                                    class="w-full bg-gray-3 h-3 mb-2 rounded-2">
                                    <div
                                        class="bg-sky-600 h-3 rounded-2"
                                        :style="{
                                            width: `${computedProcessingProgress}%`,
                                        }"></div>
                                </div>
                                <p class="m-0">
                                    {{ computedProcessingMediaStatus }}
                                </p>
                            </div>
                            <div v-if="lastSelected != null">
                                <div
                                    class="flex flex-col text-xs border-b border-gray-3 pb-4">
                                    <div class="flex">
                                        <div
                                            class="w-100 h-100 max-h-15 max-w-15 mr-2 bg-contain bg-no-repeat bg-center"
                                            :style="{
                                                backgroundImage: `url('${mediaGetThumbnail(
                                                    lastSelected,
                                                )}')`,
                                            }">
                                            <SMLoading
                                                v-if="
                                                    getMediaStatus(lastSelected)
                                                        .busy
                                                "
                                                small
                                                class="bg-white bg-op-90 w-full h-full" />
                                        </div>
                                        <div class="flex flex-col w-100">
                                            <p class="m-0 text-bold">
                                                {{ lastSelected.title }}
                                            </p>
                                            <p class="m-0">
                                                {{
                                                    formatDate(
                                                        lastSelected.created_at,
                                                    )
                                                }}
                                            </p>
                                            <p class="m-0">
                                                {{
                                                    bytesReadable(
                                                        lastSelected.size,
                                                        0,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    getMediaStatusText(
                                                        lastSelected,
                                                    ) != ''
                                                "
                                                class="m-0 italic">
                                                {{
                                                    getMediaStatusText(
                                                        lastSelected,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <table>
                                            <tr>
                                                <th
                                                    class="text-left vertical-top">
                                                    File
                                                </th>
                                                <td>{{ lastSelected.name }}</td>
                                            </tr>
                                            <tr>
                                                <th
                                                    class="text-left vertical-top">
                                                    Type
                                                </th>
                                                <td>
                                                    {{ lastSelected.mime_type }}
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div
                                        v-if="allowEditSelected"
                                        class="flex mt-2 gap-1">
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            v-if="allowRotateSelected"
                                            class="h-5 w-5 cursor-pointer text-gray-6 hover:text-gray-4"
                                            viewBox="0 0 24 24"
                                            @click="
                                                handleRotateLeft(lastSelected)
                                            ">
                                            <title>Rotate Left</title>
                                            <path
                                                d="M4 11C4 6.58 7.58 3 12 3L13 3.06V5.08L12 5C8.69 5 6 7.69 6 11H9L5 15L1 11H4M17 7H13C11.9 7 11 7.9 11 9V18C11 19.11 11.9 20 13 20H19C20.11 20 21 19.11 21 18V11L17 7M19 18H13V9H16V12H19V18Z"
                                                fill="currentColor" />
                                        </svg>
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            v-if="allowRotateSelected"
                                            class="h-5 w-5 cursor-pointer text-gray-6 hover:text-gray-4"
                                            viewBox="0 0 24 24"
                                            @click="
                                                handleRotateRight(lastSelected)
                                            ">
                                            <title>Rotate Right</title>
                                            <path
                                                d="M20 11H23L19 15L15 11H18C18 7.69 15.31 5 12 5L11 5.08V3.06L12 3C16.42 3 20 6.58 20 11M9 7H5C3.9 7 3 7.9 3 9V18C3 19.11 3.9 20 5 20H11C12.11 20 13 19.11 13 18V11L9 7M11 18H5V9H8V12H11V18Z"
                                                fill="currentColor" />
                                        </svg>
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-5 w-5 cursor-pointer text-red-6 hover:text-red-4 ml-auto"
                                            viewBox="0 0 24 24"
                                            @click="handleDelete(lastSelected)">
                                            <title>Delete Permanently</title>
                                            <path
                                                d="M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19M8,9H16V19H8V9M15.5,4L14.5,3H9.5L8.5,4H5V6H19V4H15.5Z"
                                                fill="currentColor" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="py-2">
                                    <SMInput
                                        class="mb-2"
                                        label="Title"
                                        :disabled="!allowEditSelected"
                                        v-model:modelValue="lastSelected.title"
                                        @change="handleUpdate"
                                        :small="true" />
                                    <SMInput
                                        class="mb-2"
                                        label="Description"
                                        textarea
                                        :disabled="!allowEditSelected"
                                        v-model:modelValue="
                                            lastSelected.description
                                        "
                                        @change="handleUpdate"
                                        :small="true" />
                                </div>
                            </div>
                        </div>
                    </div>
                </SMTab>
                <SMTab
                    id="tab-url"
                    label="Insert from URL"
                    :hide="!props.allowUrl"
                    class="flex flex-1 flex-col flex-items-center flex-justify-center">
                    <div>
                        <h2>Insert image from URL</h2>
                        <SMInput
                            class="mb-2"
                            label="Image URL"
                            control="url"
                            :form="form" />
                        <SMInput
                            class="mb-2"
                            label="Title"
                            control="title"
                            :form="form" />
                        <SMInput
                            class="mb-2"
                            label="Description"
                            textarea
                            control="description"
                            :form="form" />
                    </div>
                </SMTab>
            </SMTabGroup>
            <div class="relative h-38 md:h-15">
                <ul
                    v-if="props.multiple && selected.length > 0"
                    class="absolute top-0 left-0 right-0 md:right-60 overflow-auto flex p-0 gap-2 flex-row">
                    <li
                        v-for="item in selected"
                        :key="item.id"
                        :class="[
                            'flex',
                            'p-1px',
                            'flex-justify-center',
                            'flex-items-center',
                            'flex-col',
                        ]"
                        @click="handleChangeLastSelected(item.id)">
                        <div
                            :class="[
                                'flex',
                                'flex-items-center',
                                'flex-justify-center',
                                'h-15',
                                'w-20',
                                'bg-contain',
                                'bg-center',
                                'bg-no-repeat',
                                'border-3',
                                'border-white',
                                'relative',
                                'media-selected-list-item',
                            ]"
                            :style="{
                                backgroundImage: `url('${mediaGetThumbnail(
                                    item,
                                )}')`,
                            }">
                            <SMLoading
                                v-if="getMediaStatus(item).busy"
                                small
                                class="bg-white bg-op-90 w-full h-full" />
                            <div
                                class="absolute rounded-5 bg-white -top-1.5 -right-1.5 hidden item-delete"
                                @click="handleRemoveItemFromSelection(item.id)">
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6 block"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M12,2C17.53,2 22,6.47 22,12C22,17.53 17.53,22 12,22C6.47,22 2,17.53 2,12C2,6.47 6.47,2 12,2M15.59,7L12,10.59L8.41,7L7,8.41L10.59,12L7,15.59L8.41,17L12,13.41L15.59,17L17,15.59L13.41,12L17,8.41L15.59,7Z"
                                        fill="rgba(185,28,28,1)" />
                                </svg>
                            </div>
                        </div>
                    </li>
                </ul>
                <div
                    class="absolute bottom-0 left-0 right-0 md:left-a flex gap-2 flex-col md:flex-row">
                    <button
                        v-if="!formLoading"
                        type="button"
                        class="mr-4 font-medium block w-full md:inline-block md:w-auto px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        @click="handleClickCancel">
                        Cancel
                    </button>
                    <button
                        v-if="!formLoading"
                        type="button"
                        :disabled="computedSelectDisabled"
                        :class="[
                            'font-medium',
                            'block',
                            'md:inline-block',
                            'w-full',
                            'md:w-auto',
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
                        @click="handleClickSelect"
                        :loading="true">
                        Select
                    </button>
                    <SMLoading v-if="formLoading" small />
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
import {
    ApiInfo,
    Media,
    MediaCollection,
    MediaJobResponse,
    MediaResponse,
} from "../../helpers/api.types";
import { useApplicationStore } from "../../store/ApplicationStore";
import {
    mediaGetThumbnail,
    mimeMatches,
    mediaIsBusy,
    getMediaStatus,
    createMediaItem,
    createMediaJobItem,
    getMediaStatusText,
} from "../../helpers/media";
import SMInput from "../SMInput.vue";
import SMLoading from "../SMLoading.vue";
import SMTabGroup from "../SMTabGroup.vue";
import SMTab from "../SMTab.vue";
import { Form, FormControl, FormObject } from "../../helpers/form";
import { And, Required, Url } from "../../helpers/validate";
import {
    convertFileNameToTitle,
    generateRandomId,
    userHasPermission,
} from "../../helpers/utils";
import { bytesReadable } from "../../helpers/types";
import { SMDate } from "../../helpers/datetime";
import { isUUID } from "../../helpers/uuid";
import { useToastStore } from "../../store/ToastStore";
import { useUserStore } from "../../store/UserStore";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import { openDialog } from "../../components/SMDialog";

const props = defineProps({
    mime: {
        type: String,
        default: "image/*",
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
    allowUrl: {
        type: Boolean,
        default: false,
        required: false,
    },
    multiple: {
        type: Boolean,
        default: false,
        required: false,
    },
    initial: {
        type: [Array, Object],
        default: () => [],
        required: false,
    },
});

/**
 * Reference to the File Upload Input element.
 */
const refUploadInput = ref<HTMLInputElement | null>(null);
const refMediaList = ref<HTMLUListElement | null>(null);
const userStore = useUserStore();
const allowUploads = ref(props.allowUpload && userStore.id);
const formLoading = ref(false);
const form: FormObject = reactive(
    Form({
        url: FormControl("", And([Required(), Url()])),
        title: FormControl(""),
        description: FormControl(""),
    }),
);

/**
 * The selected tab
 */
const selectedTab = ref("tab-browser");

/**
 * Max upload size
 */
const max_upload_size = ref("");

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
const selected: Ref<Media[]> = ref([]);
let lastSelected: Ref<Media | null> = ref(null);

/**
 * How many media items are we showing per page.
 */
const perPage = ref(24);

const showFileDrop = ref(false);

const applicationStore = useApplicationStore();

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
const getMediaItemById = (item_id: string): Media | null => {
    let found: Media | null = null;

    mediaItems.value.every((item) => {
        if (item.id == item_id) {
            found = item;
            return false;
        }

        return true;
    });

    if (found == null) {
        selected.value.every((item) => {
            if (item.id == item_id) {
                found = item;
                return false;
            }

            return true;
        });
    }

    return found;
};

const setMediaItemById = (item_id: string, updatedMedia: Media): Media => {
    const index = mediaItems.value.findIndex((item) => item.id === item_id);

    if (index !== -1) {
        // Replace the existing media item with the updated one
        mediaItems.value.splice(index, 1, updatedMedia);
    }

    return updatedMedia;
};

const removeMediaItem = (item_id: string): void => {
    mediaItems.value = mediaItems.value.filter(
        (mediaItem) => mediaItem.id !== item_id,
    );
};

/**
 * Handle user clicking the cancel/close button.
 */
const handleClickCancel = () => {
    forceUpdate();
    closeDialog(false);
};

/**
 * Handle user clicking the select button.
 */
const handleClickSelect = async () => {
    forceUpdate();

    if (selectedTab.value == "tab-browser") {
        if (selected.value.length > 0) {
            if (props.multiple) {
                closeDialog(selected.value);
            } else {
                closeDialog(selected.value[0]);
            }
            return;
        }
    } else if (selectedTab.value == "tab-url") {
        formLoading.value = true;
        if (await form.validate()) {
            const response = await fetch(form.controls.url.value as string, {
                method: "HEAD",
            });

            if (response.status == 404) {
                form.controls.url.setValidationResult(
                    false,
                    "File not found on server",
                );
            } else if (response.status != 200) {
                form.controls.url.setValidationResult(
                    false,
                    "Error occurred retrieving file from server",
                );
            } else {
                const mime = response.headers
                    .get("Content-Type")
                    .split(";")[0]
                    .trim();
                if (!mimeMatches(props.mime, mime)) {
                    form.controls.url.setValidationResult(
                        false,
                        "Invalid file type",
                    );
                } else {
                    closeDialog(
                        createMediaItem({
                            title: form.controls.title.value as string,
                            mime_type: mime,
                            size: -1,
                            url: form.controls.url.value as string,
                            description: form.controls.description
                                .value as string,
                        }),
                    );
                }
            }
        }

        formLoading.value = false;
    }
};

/**
 * Handle user clicking a media item (selecting).
 * @param {string} item_id The media id.
 */
const handleClickItem = (item_id: string): void => {
    // only allow selecting of items that have a UUID (ie not items being uploaded)
    if (isUUID(item_id)) {
        const mediaItem = getMediaItemById(item_id);

        if (props.multiple) {
            if (selected.value.findIndex((item) => item.id === item_id) > -1) {
                selected.value = selected.value.filter(
                    (item) => item.id != item_id,
                );

                if (lastSelected.value && lastSelected.value.id === item_id) {
                    if (selected.value.length > 0) {
                        lastSelected.value = selected.value[0];
                    } else {
                        lastSelected.value = null;
                    }
                }
            } else {
                selected.value.push(mediaItem);
                lastSelected.value = mediaItem;
            }
        } else {
            selected.value[0] = getMediaItemById(item_id);
            lastSelected.value = mediaItem;
        }
    }
};

/**
 * Handle user double clicking a media item.
 * @param {string} item_id The media id.
 */
const handleDblClickItem = (item_id: string): void => {
    if (!props.multiple) {
        if (isUUID(item_id)) {
            const mediaItem = getMediaItemById(item_id);
            if (mediaItem != null) {
                closeDialog(mediaItem);
            } else {
                closeDialog(false);
            }
        }
    }
};

/**
 * Change last selected item.
 * @param {string} item_id The item id to make the last selected.
 */
const handleChangeLastSelected = (item_id: string): void => {
    const index = selected.value.findIndex((item) => item.id === item_id);
    if (index > -1) {
        lastSelected.value = selected.value[index];
    }
};

/**
 * Remove an item from the selection list.
 * @param {string} item_id The item id to remove.
 */
const handleRemoveItemFromSelection = (item_id: string): void => {
    selected.value = selected.value.filter((item) => item.id != item_id);
    if (lastSelected.value && lastSelected.value.id === item_id) {
        if (selected.value.length > 0) {
            lastSelected.value = selected.value[0];
        } else {
            lastSelected.value = null;
        }
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
    if (refUploadInput.value != null && refUploadInput.value.files != null) {
        handleFilesUpload(refUploadInput.value.files);
        showFileBrowserTab();
    }
    refUploadInput.value.value = "";
};

/**
 * Process the file list, uploading to the server.
 * @param {FileList} files The list of files to upload to the server.
 */
const handleFilesUpload = (files: FileList) => {
    const fileList = [];
    let count = 0;
    let maxCount = 15;
    let warnedUser = false;

    fileList.push(...Array.from(files));

    Array.from(fileList).forEach((file: File) => {
        if (mimeMatches(props.mime, file.type) == true) {
            count = getUploadingMediaItems().length;

            if (count <= maxCount) {
                const uploadId = generateRandomId("upload_", 8, (s) => {
                    return getMediaItemById(s) != null;
                });

                mediaItems.value.unshift(
                    createMediaItem({
                        id: uploadId,
                        name: convertFileNameToTitle(file.name),
                    }),
                );

                window.setTimeout(() => {
                    uploadFileById(uploadId, file);
                }, 50);
            } else if (count > maxCount && warnedUser == false) {
                warnedUser = true;
                useToastStore().addToast({
                    title: "Maximum Files",
                    type: "warning",
                    content: `You cannot upload more than ${maxCount} files at a time`,
                });

                return;
            }
        } else {
            useToastStore().addToast({
                title: "Incorrect File",
                type: "danger",
                content: `Cannot upload the file ${file.name} as the file type is not supported.`,
            });
        }
    });
};

const getUploadingMediaItems = (): Media[] => {
    return mediaItems.value.filter((item) => {
        return (
            item.id.startsWith("upload_") &&
            item.jobs.length > 0 &&
            item.jobs[0].status === "uploading"
        );
    });
};

const computedUploadMediaTitle = computed(() => {
    const items = getUploadingMediaItems();
    return `Uploading ${items.length} File${items.length == 1 ? "" : "s"}`;
});

const computedUploadMediaStatus = computed(() => {
    const items = getUploadingMediaItems();
    let bytes = 0;
    let maxBytes = 0;

    items.forEach((item) => {
        if (item.jobs.length > 0) {
            bytes += item.jobs[0].progress;
            maxBytes += item.jobs[0].progress_max;
        }
    });

    return `${bytesReadable(bytes)} of ${bytesReadable(maxBytes)}`;
});

const computedUploadProgress = computed(() => {
    const items = getUploadingMediaItems();
    if (items.length === 0) {
        return 100;
    }

    let bytes = 0;
    let maxBytes = 0;

    items.forEach((item) => {
        if (item.jobs.length > 0) {
            bytes += item.jobs[0].progress;
            maxBytes += item.jobs[0].progress_max;
        }
    });

    return Math.floor((bytes / maxBytes) * 100);
});

const getProcessingMediaItems = (): Media[] => {
    return mediaItems.value.filter((item) => {
        return (
            item.id.startsWith("upload_") &&
            item.jobs.length > 0 &&
            item.jobs[0].status !== "uploading"
        );
    });
};

const computedProcessingMediaTitle = computed(() => {
    const items = getProcessingMediaItems();
    return `Processing ${items.length} File${items.length == 1 ? "" : "s"}`;
});

const computedProcessingProgress = computed(() => {
    const items = getProcessingMediaItems();
    if (items.length === 0) {
        return 100;
    }

    const totalProgress = items.reduce((accumulator, item) => {
        if (item.jobs.length > 0) {
            if (item.jobs[0].progress_max != 0) {
                accumulator +=
                    Math.floor(
                        (item.jobs[0].progress / item.jobs[0].progress_max) *
                            100,
                    ) || 100;
            }
        }
        return accumulator;
    }, 0);

    return Math.floor(totalProgress / items.length);
});

const computedProcessingMediaStatus = computed(() => {
    const items = getProcessingMediaItems();
    let status = "";

    items.every((item) => {
        let itemStatus = getMediaStatusText(item);
        if (status == "" || (itemStatus != "" && itemStatus != "Queued")) {
            const endLoop = !(status == "");
            status = `${item.name}: ${itemStatus}`;

            if (endLoop == true) {
                return false;
            }
        }

        return true;
    });

    return status;
    // return `${bytesReadable(bytes)} of ${bytesReadable(maxBytes)}`;
});

/**
 * Upload a File to the server.
 * @param {string} uploadId The ID of the new media item.
 * @param {File} file The file object.
 * @returns {void}
 */
const uploadFileById = (uploadId: string, file: File): void => {
    let submitFormData = new FormData();
    submitFormData.append("file", file);
    submitFormData.append("title", convertFileNameToTitle(file.name));
    submitFormData.append("description", "");

    api.chunk({
        url: "/media",
        body: submitFormData,
        headers: {
            "Content-Type": "multipart/form-data",
        },
        chunk: "file",
        progress: (progressEvent) => {
            const mediaItem = getMediaItemById(uploadId);
            if (mediaItem != null) {
                mediaItem.jobs[0] = createMediaJobItem({
                    status: "uploading",
                    progress: progressEvent.loaded,
                    progress_max: progressEvent.total,
                });
            }
        },
    })
        .then((result) => {
            if (result.data) {
                const mediaItem = getMediaItemById(uploadId);
                if (mediaItem != null) {
                    mediaItem.jobs[0] = (
                        result.data as MediaJobResponse
                    ).media_job;
                }

                updateMediaItem(uploadId);
            }
        })
        .catch((error) => {
            if (error.status == 413) {
                useToastStore().addToast({
                    title: "File too large",
                    type: "danger",
                    content: `Cannot upload the file ${file.name} as it larger than ${max_upload_size.value}.`,
                });
            } else {
                useToastStore().addToast({
                    title: "File upload error",
                    type: "danger",
                    content: `Cannot upload the file ${file.name} as a server error occurred.`,
                });
            }

            removeMediaItem(uploadId);
        });
};

/**
 * Update media item.
 * @param {string} id The media item id.
 * @returns {void}
 */
const updateMediaItem = (id: string): void => {
    let media = getMediaItemById(id);
    let timeout = 200;

    if (media != null && media.jobs.length > 0) {
        if (id.startsWith("upload_")) {
            api.get({
                url: "/media/jobs/{id}",
                params: {
                    id: media.jobs[0].id,
                },
            })
                .then((result) => {
                    const data = result.data as MediaJobResponse;
                    if (data.media_job.media_id != null) {
                        media.id = data.media_job.media_id;
                    }
                    if (data.media_job.id == media.jobs[0].id) {
                        media.jobs[0] = data.media_job;
                    }
                })
                .catch((error) => {
                    if (error.status == 429) {
                        timeout = 1000;
                    }

                    /* error */
                    console.log(error);
                })
                .finally(() => {
                    setTimeout(() => {
                        updateMediaItem(media.id);
                    }, timeout);
                });
        } else {
            api.get({
                url: "/media/{id}",
                params: {
                    id: id,
                },
            })
                .then((result) => {
                    const data = result.data as MediaResponse;
                    media = setMediaItemById(id, data.medium);

                    const activeJobs = media.jobs.filter(
                        (job) =>
                            !["complete", "invalid", "failed"].includes(
                                job.status,
                            ),
                    );

                    selectedMediaUpdateId(media);
                    if (activeJobs.length > 0) {
                        setTimeout(() => {
                            updateMediaItem(id);
                        }, 100);
                    }
                })
                .catch((error) => {
                    if (error.status == 429) {
                        setTimeout(() => {
                            updateMediaItem(id);
                        }, 500);
                    }

                    /* error */
                    console.log(error);
                });
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

    const mimeTypes = props.accepts.replaceAll("*", "").split(/\s*,\s*/);

    let mimeTypesFilter = "";
    if (mimeTypes.length > 0) {
        const validMimeTypes = mimeTypes.filter((type) => type.length > 1);
        if (validMimeTypes.length > 0) {
            mimeTypesFilter = validMimeTypes
                .map((type) => `mime_type:${type}`)
                .join(",OR,");
        }
    }

    let params = {
        page: page.value,
        limit: perPage.value,
        filter: "",
        sort: "-created_at",
    };

    if (mimeTypesFilter) {
        params.filter = `(${mimeTypesFilter})`;
    }
    if (itemSearch.value.length > 0) {
        let value = itemSearch.value.replace(/"/g, '\\"');
        if (params.filter.length > 0) {
            params.filter += ",AND,";
        }
        params.filter += `(title:"${value}",OR,name:"${value}",OR,description:"${value}")`;
    }

    api.get({
        url: "/media",
        params: params,
    })
        .then((result) => {
            if (result.data) {
                const data = result.data as MediaCollection;

                // const mediaIds = new Set(
                //     mediaItems.value.map((item) => item.id),
                // );
                // const filteredItems = data.media.filter(
                //     (item) => !mediaIds.has(item.id),
                // );

                totalItems.value = data.total;
                mediaItems.value.push(...data.media);
            }
        })
        .catch(() => {
            /* empty */
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
        if (selected.value.length > 0) {
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

watch(mediaItems, () => {
    selected.value = selected.value.map((selectedItem) => {
        const matchingMediaItem = mediaItems.value.find(
            (mediaItem) => mediaItem.id === selectedItem.id,
        );
        return matchingMediaItem || selectedItem;
    });

    if (lastSelected.value) {
        lastSelected.value =
            mediaItems.value.find(
                (mediaItem) => mediaItem.id === lastSelected.value.id,
            ) || lastSelected.value;
    }
});

/**
 * Determine if the Select button should be disabled
 */
const computedSelectDisabled = computed(() => {
    if (selectedTab.value == "tab-browser") {
        return (
            selected.value.length == 0 // ||
            // selected.value.findIndex((item) => item.status !== "OK") > -1
        );
    } else if (selectedTab.value == "tab-url") {
        return (
            !form.controls.url.isValid() ||
            (form.controls.url.value as string).length == 0
        );
    }

    return true;
});

const handleDragEnter = () => {
    if (allowUploads.value && !showFileDrop.value) {
        showFileDrop.value = true;
    }
};

const handleDragOver = () => {
    if (allowUploads.value && !showFileDrop.value) {
        showFileDrop.value = true;
    }
};

const handleDragLeave = () => {
    if (showFileDrop.value) {
        showFileDrop.value = false;
    }
};

const handleDrop = (event) => {
    if (!allowUploads.value) {
        return;
    }

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

const formatDate = (date) => {
    const smdate = new SMDate(date).format("MMM dd, yyyy");
    return smdate;
};

const allowRotateSelected = computed(() => {
    return (
        lastSelected.value != null &&
        mediaIsBusy(lastSelected.value) == false &&
        (mimeMatches("image/*", lastSelected.value.mime_type) ||
            mimeMatches("video/*", lastSelected.value.mime_type)) &&
        userStore.id &&
        (userHasPermission("admin/media") ||
            lastSelected.value.user_id == userStore.id)
    );
});

const allowEditSelected = computed(() => {
    return (
        lastSelected.value != null &&
        mediaIsBusy(lastSelected.value) == false &&
        userStore.id &&
        (userHasPermission("admin/media") ||
            lastSelected.value.user_id == userStore.id)
    );
});

interface MediaUpdate {
    id: string;
    title: string;
    description: string;
    timer: string | number;
}

const pendingUpdates = ref<MediaUpdate[]>([]);

const handleUpdate = () => {
    if (lastSelected.value != null) {
        addUpdate(
            lastSelected.value.id,
            lastSelected.value.title,
            lastSelected.value.description,
        );
    }
};

/**
 * Rotate a Media Item to the left.
 * @param {Media} item The media item to rotate from the server.
 * @returns {void}
 */
const handleRotateLeft = (item: Media): void => {
    api.put({
        url: "/media/{id}",
        params: {
            id: item.id,
        },
        body: {
            transform: "rotate-90",
        },
    })
        .then(() => {
            updateMediaItem(item.id);
        })
        .catch((error) => {
            /* error */
            console.log(error);
        });
};

/**
 * Rotate a Media Item to the right.
 * @param {Media} item The media item to rotate from the server.
 * @returns {void}
 */
const handleRotateRight = (item: Media): void => {
    api.put({
        url: "/media/{id}",
        params: {
            id: item.id,
        },
        body: {
            transform: "rotate-270",
        },
    })
        .then(() => {
            updateMediaItem(item.id);
        })
        .catch((error) => {
            /* error */
            console.log(error);
        });
};

/**
 * Delete a Media item from the server.
 * @param {Media} item The media item to delete from the server.
 * @returns {Promise<void>}
 */
const handleDelete = async (item: Media): Promise<void> => {
    let result = await openDialog(SMDialogConfirm, {
        title: "Delete File?",
        text: `Are you sure you want to delete the file <strong>${item.title}</strong>?`,
        cancel: {
            type: "secondary",
            label: "Cancel",
        },
        confirm: {
            type: "danger",
            label: "Delete File",
        },
    });

    if (result == true) {
        api.delete({
            url: "/media/{id}",
            params: {
                id: item.id,
            },
        })
            .then(() => {
                if (lastSelected.value && lastSelected.value.id === item.id) {
                    lastSelected.value = null;
                }

                // Remove the item with matching id from selected array
                selected.value = selected.value.filter(
                    (selectedItem) => selectedItem.id !== item.id,
                );

                // Remove the item with matching id from mediaItems array
                mediaItems.value = mediaItems.value.filter(
                    (mediaItem) => mediaItem.id !== item.id,
                );

                totalItems.value--;
            })
            .catch((error) => {
                /* error */
                console.log(error);
            });
    }
};

const selectedMediaUpdateId = (media: Media): void => {
    if (lastSelected.value && lastSelected.value.id == media.id) {
        lastSelected.value = media;
    }

    const index = selected.value.findIndex((item) => item.id === media.id);
    if (index !== -1) {
        selected.value[index] = media;
    }
};

const addUpdate = (id: string, title: string, description: string): void => {
    let found = false;

    pendingUpdates.value.every((item, index) => {
        if (item.id == id) {
            found = true;
            pendingUpdates.value[index].title = title;
            pendingUpdates.value[index].description = description;
            if (pendingUpdates.value[index].timer != null) {
                clearTimeout(pendingUpdates.value[index].timer);
                pendingUpdates.value[index].timer = window.setTimeout(() => {
                    const data = pendingUpdates.value[index];

                    pendingUpdates.value.splice(index, 1);
                    postUpdate(data);
                }, 4000);
            }
            return false;
        }

        return true;
    });

    if (!found) {
        const index = pendingUpdates.value.push({
            id: id,
            title: title,
            description: description,
            timer: null,
        });

        pendingUpdates.value[index - 1].timer = window.setTimeout(() => {
            const data = pendingUpdates.value[index - 1];

            pendingUpdates.value.splice(index - 1, 1);
            postUpdate(data);
        }, 4000);
    }
};

const postUpdate = (data: MediaUpdate): void => {
    api.put({
        url: "/media/{id}",
        params: {
            id: data.id,
        },
        body: {
            title: data.title,
            description: data.description,
        },
    }).catch((error) => {
        console.log("postupdate: " + error);
    });
};

const forceUpdate = () => {
    formLoading.value = true;
    pendingUpdates.value.forEach((item, index) => {
        if (pendingUpdates.value[index].timer != null) {
            clearTimeout(pendingUpdates.value[index].timer);
            pendingUpdates.value[index].timer = null;
            postUpdate(pendingUpdates.value[index]);
        }
    });

    pendingUpdates.value = [];
    formLoading.value = false;
};

/**
 * Load initial items
 */
const loadInitial = () => {
    selected.value = Array.isArray(props.initial)
        ? props.initial
        : [props.initial];

    totalItems.value = selected.value.length;
    if (selected.value.length > 0) {
        mediaItems.value.push(...selected.value);
    }
};

// Get max upload size
api.get({
    url: "",
})
    .then((result) => {
        if (result.data) {
            const data = result.data as ApiInfo;

            max_upload_size.value = bytesReadable(data.max_upload_size);
        }
    })
    .catch(() => {
        /* empty */
    });

loadInitial();
handleLoad();
</script>

<style lang="scss">
.media-selected-list-item:hover .item-delete {
    display: block;
}
</style>
