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
            <SMLoading v-if="progressText" overlay>{{
                progressText
            }}</SMLoading>
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
                        Select Files
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
                                                'h-30',
                                                'w-40',
                                                'bg-contain',
                                                'bg-center',
                                                'bg-no-repeat',
                                                'relative',
                                                { 'mb-6': showMediaName(item) },
                                            ]"
                                            :style="{
                                                backgroundImage:
                                                    item.status === 'OK'
                                                        ? `url('${mediaGetThumbnail(
                                                              item,
                                                          )}')`
                                                        : 'initial',
                                                backgroundColor:
                                                    item.status === 'OK'
                                                        ? 'initial'
                                                        : 'rgba(220,220,220,1)',
                                            }">
                                            <div
                                                v-if="showMediaName(item)"
                                                class="absolute -bottom-6 small w-full text-ellipsis overflow-hidden whitespace-nowrap">
                                                {{ item.title }}
                                            </div>
                                            <SMLoading
                                                v-if="
                                                    item.status !== 'OK' &&
                                                    item.status.startsWith(
                                                        'Error',
                                                    ) === false
                                                "
                                                small
                                                class="bg-white bg-op-90 w-full h-full"
                                                >{{
                                                    item.status.split(":")
                                                        .length > 1
                                                        ? item.status
                                                              .split(":")[1]
                                                              .trim()
                                                        : item.status
                                                }}</SMLoading
                                            >
                                            <div
                                                v-if="
                                                    item.status.startsWith(
                                                        'Error',
                                                    ) === true
                                                ">
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-10 w-10"
                                                    viewBox="0 0 24 24">
                                                    <path
                                                        d="M13,13H11V7H13M13,17H11V15H13M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2Z"
                                                        fill="rgba(220,38,38,1)" />
                                                </svg>
                                            </div>
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
                                                    (currentUploadFileNum - 1) +
                                                (100 /
                                                    uploadFileList.length /
                                                    100) *
                                                    currentUploadFileProgress
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
                            <div v-if="lastSelected != null">
                                <div
                                    class="flex text-xs border-b border-gray-3 pb-4">
                                    <div
                                        class="w-100 h-100 max-h-20 max-w-20 mr-2 bg-contain bg-no-repeat bg-center"
                                        :style="{
                                            backgroundImage: `url('${mediaGetThumbnail(
                                                lastSelected,
                                            )}')`,
                                        }"></div>
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
                                            v-if="lastSelected.status != 'OK'"
                                            class="m-0 italic">
                                            {{
                                                lastSelected.status.split(":")
                                                    .length > 1
                                                    ? lastSelected.status
                                                          .split(":")[1]
                                                          .trim()
                                                    : lastSelected.status
                                            }}
                                        </p>
                                        <p
                                            v-if="allowEditSelected"
                                            class="flex gap-1">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 cursor-pointer text-gray-6 hover:text-gray-4"
                                                viewBox="0 0 24 24"
                                                @click="
                                                    handleRotateLeft(
                                                        lastSelected,
                                                    )
                                                ">
                                                <title>Rotate Left</title>
                                                <path
                                                    d="M4 11C4 6.58 7.58 3 12 3L13 3.06V5.08L12 5C8.69 5 6 7.69 6 11H9L5 15L1 11H4M17 7H13C11.9 7 11 7.9 11 9V18C11 19.11 11.9 20 13 20H19C20.11 20 21 19.11 21 18V11L17 7M19 18H13V9H16V12H19V18Z"
                                                    fill="currentColor" />
                                            </svg>
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 cursor-pointer text-gray-6 hover:text-gray-4"
                                                viewBox="0 0 24 24"
                                                @click="
                                                    handleRotateRight(
                                                        lastSelected,
                                                    )
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
                                                @click="
                                                    handleDelete(lastSelected)
                                                ">
                                                <title>
                                                    Delete Permanently
                                                </title>
                                                <path
                                                    d="M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19M8,9H16V19H8V9M15.5,4L14.5,3H9.5L8.5,4H5V6H19V4H15.5Z"
                                                    fill="currentColor" />
                                            </svg>
                                        </p>
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
                        @click="handleShowFileItem(item.id)">
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
                                backgroundColor:
                                    item.status === 'OK'
                                        ? 'initial'
                                        : 'rgba(220,220,220,1)',
                            }">
                            <SMLoading
                                v-if="
                                    item.status !== 'OK' &&
                                    item.status.startsWith('Error') === false
                                "
                                small
                                class="bg-white bg-op-90 w-full h-full" />
                            <div
                                class="absolute rounded-5 bg-white -top-1.5 -right-1.5 hidden item-delete"
                                @click="handleRemoveItem(item.id)">
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
    MediaResponse,
} from "../../helpers/api.types";
import { useApplicationStore } from "../../store/ApplicationStore";
import { mediaGetThumbnail, mimeMatches } from "../../helpers/media";
import SMInput from "../SMInput.vue";
import SMLoading from "../SMLoading.vue";
import SMTabGroup from "../SMTabGroup.vue";
import SMTab from "../SMTab.vue";
import { Form, FormControl, FormObject } from "../../helpers/form";
import { And, Required, Url } from "../../helpers/validate";
import { convertFileNameToTitle, userHasPermission } from "../../helpers/utils";
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

const forceRefresh = [];

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
const max_upload_size = ref(" ");

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
const progressText = ref("");

const currentUploadFileNum = ref(0);
const currentUploadFileProgress = ref(0);
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

const showMediaName = (media: Media): boolean => {
    return !(
        media.mime_type.startsWith("image/") ||
        media.mime_type.startsWith("video/")
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
            const response = await fetch(form.controls.url.value, {
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
                    closeDialog({
                        id: "",
                        user_id: "",
                        title: form.controls.title.value,
                        name: "",
                        mime_type: mime,
                        permission: "",
                        size: -1,
                        status: "OK",
                        storage: "",
                        url: form.controls.url.value,
                        description: form.controls.description.value,
                        dimensions: "",
                        variants: {},
                        created_at: "",
                        updated_at: "",
                    });
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
    if (isUUID(item_id)) {
        const mediaItem = getMediaItem(item_id);

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
            selected.value[0] = getMediaItem(item_id);
            lastSelected.value = mediaItem;
        }
    } else {
        // selected.value = null;
    }
};

/**
 * Handle user double clicking a media item.
 * @param item_id The media id.
 */
const handleDblClickItem = (item_id: string): void => {
    if (!props.multiple) {
        if (isUUID(item_id)) {
            const mediaItem = getMediaItem(item_id);
            if (mediaItem != null) {
                closeDialog(mediaItem);
                return;
            }

            closeDialog(false);
        }
    }
};

const handleShowFileItem = (item_id: string): void => {
    const index = selected.value.findIndex((item) => item.id === item_id);
    if (index > -1) {
        lastSelected.value = selected.value[index];
    }
};

const handleRemoveItem = (item_id: string): void => {
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

const handleFilesUpload = (files: FileList) => {
    Array.from(files).forEach((file, index) => {
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
            thumbnail: "",
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

const startFilesUpload = async () => {
    if (uploadFileList.value != null) {
        if (currentUploadFileNum.value < 1) {
            currentUploadFileNum.value = 1;

            while (currentUploadFileNum.value <= uploadFileList.value.length) {
                const file =
                    uploadFileList.value[currentUploadFileNum.value - 1];

                let submitFormData = new FormData();
                submitFormData.append("file", file);
                submitFormData.append(
                    "title",
                    convertFileNameToTitle(file.name),
                );
                submitFormData.append("description", "");
                try {
                    let result = await api.post({
                        url: "/media",
                        body: submitFormData,
                        headers: {
                            "Content-Type": "multipart/form-data",
                        },
                        progress: (progressEvent) => {
                            const currentUploadFileNumStr =
                                currentUploadFileNum.value.toString();
                            currentUploadFileProgress.value = Math.floor(
                                (progressEvent.loaded / progressEvent.total) *
                                    100,
                            );
                            mediaItems.value.every((item, index) => {
                                if (item.id == currentUploadFileNumStr) {
                                    mediaItems.value[
                                        index
                                    ].status = `${currentUploadFileProgress.value}% Uploaded`;
                                    return false;
                                }

                                return true;
                            });
                        },
                    });
                    if (result.data) {
                        const data = result.data as MediaResponse;

                        const currentUploadFileNumStr =
                            currentUploadFileNum.value.toString();
                        mediaItems.value.every((item, index) => {
                            if (item.id == currentUploadFileNumStr) {
                                mediaItems.value[index] = data.medium;
                                if (!selected.value) {
                                    selected.value.push(data.medium);
                                } else if (props.multiple) {
                                    selected.value.push(data.medium);
                                }
                                return false;
                            }

                            return true;
                        });

                        totalItems.value++;
                    }
                } catch (error) {
                    const currentUploadFileNumStr =
                        currentUploadFileNum.value.toString();
                    mediaItems.value.every((item, index) => {
                        if (item.id == currentUploadFileNumStr) {
                            mediaItems.value[index].status = "Error";
                            return false;
                        }

                        return true;
                    });

                    let errorString = "A server error occurred";

                    if (error.status == 413) {
                        errorString = `The file is larger than ${max_upload_size.value}`;
                    }

                    useToastStore().addToast({
                        title: "Upload failed",
                        type: "danger",
                        content: errorString,
                    });
                } finally {
                    currentUploadFileNum.value++;
                    updateFiles();
                }
            }

            uploadFileList.value = null;
            currentUploadFileNum.value = 0;
        }
    }
};

const updateFilesNonce = ref(null);

const updateFiles = async () => {
    if (updateFilesNonce.value == null) {
        let remaining = false;

        mediaItems.value.forEach((item, index) => {
            if (
                isUUID(item.id) &&
                item.status != "OK" &&
                item.status.startsWith("Error") == false
            ) {
                remaining = true;

                api.get({
                    url: "/media/{id}",
                    params: {
                        id: item.id,
                    },
                })
                    .then((updateResult) => {
                        if (updateResult.data) {
                            const updateData =
                                updateResult.data as MediaResponse;
                            mediaItems.value[index].status =
                                updateData.medium.status;
                            if (updateData.medium.status == "OK") {
                                mediaItems.value[index] = updateData.medium;
                                forceRefresh.push(updateData.medium.id);
                                if (
                                    lastSelected.value &&
                                    lastSelected.value.id ==
                                        updateData.medium.id
                                ) {
                                    lastSelected.value = updateData.medium;
                                }
                            } else if (
                                updateData.medium.status.startsWith("Error") ===
                                true
                            ) {
                                mediaItems.value = mediaItems.value.filter(
                                    (mediaItem) =>
                                        mediaItem.id !== updateData.medium.id,
                                );
                                lastSelected.value = null;
                                totalItems.value--;

                                useToastStore().addToast({
                                    title: "Upload failed",
                                    type: "danger",
                                    content: updateData.medium.status,
                                    // content: `${item.name} failed to be processed by the server.`,
                                });
                            }
                        } else {
                            throw "error";
                        }
                    })
                    .catch(() => {
                        /* error retreiving data */
                        mediaItems.value = mediaItems.value.filter(
                            (mediaItem) => mediaItem.id !== item.id,
                        );
                    });
            }
        });

        mediaItems.value = mediaItems.value.filter(
            (item) => item.status.startsWith("Error") === false,
        );

        if (remaining) {
            updateFilesNonce.value = setTimeout(() => {
                updateFilesNonce.value = null;
                updateFiles();
            }, 1000);
        } else {
            updateFilesNonce.value = null;
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
        status: "!Error",
        filter: "",
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

                const mediaIds = new Set(
                    mediaItems.value.map((item) => item.id),
                );
                const filteredItems = data.media.filter(
                    (item) => !mediaIds.has(item.id),
                );

                totalItems.value = data.total;
                mediaItems.value.push(...filteredItems);
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
            selected.value.length == 0 ||
            selected.value.findIndex((item) => item.status !== "OK") > -1
        );
    } else if (selectedTab.value == "tab-url") {
        return (
            !form.controls.url.isValid() || form.controls.url.value.length == 0
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

const allowEditSelected = computed(() => {
    return (
        lastSelected.value != null &&
        lastSelected.value.status === "OK" &&
        userStore.id &&
        (userHasPermission("admin/media") ||
            lastSelected.value.user_id == userStore.id)
    );
});

interface MediaUpdate {
    id: string;
    title: string;
    description: string;
    timer: any;
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

const handleRotateLeft = async (item: Media) => {
    api.put({
        url: "/media/{id}",
        params: {
            id: item.id,
        },
        body: {
            transform: "rotate-90",
        },
    })
        .then((result) => {
            if (result.data) {
                const data = result.data as MediaResponse;
                const index = mediaItems.value.findIndex(
                    (mediaItem) => mediaItem.id === item.id,
                );

                if (index !== -1) {
                    mediaItems.value[index] = data.medium;
                }

                updateFiles();
            }
        })
        .catch(() => {
            /* empty */
        });
};

const handleRotateRight = async (item: Media) => {
    api.put({
        url: "/media/{id}",
        params: {
            id: item.id,
        },
        body: {
            transform: "rotate-270",
        },
    })
        .then((result) => {
            if (result.data) {
                const data = result.data as MediaResponse;
                const index = mediaItems.value.findIndex(
                    (mediaItem) => mediaItem.id === item.id,
                );

                if (index !== -1) {
                    mediaItems.value[index] = data.medium;
                }

                updateFiles();
            }
        })
        .catch(() => {
            /* empty */
        });
};

const handleDelete = async (item: Media) => {
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
                console.log(error);
            });
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
                pendingUpdates.value[index].timer = setTimeout(() => {
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

        pendingUpdates.value[index - 1].timer = setTimeout(() => {
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
        console.log(error);
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

const itemRequiresRefresh = (id) => {
    const index = forceRefresh.indexOf(id);

    if (index !== -1) {
        forceRefresh.splice(index, 1); // Remove the item at the found index
        return true;
    }

    return false;
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
