<template>
    <SMFormCard full class="dialog-media">
        <h3>Insert Media</h3>
        <SMToolbar class="align-items-center">
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
                size="small"
                @keyup.enter="handleSearch"
                @blur="handleSearch">
                <template #append>
                    <SMButton
                        type="primary"
                        label="Search"
                        icon="search-outline"
                        @click="handleSearch" />
                </template>
            </SMInput>
        </SMToolbar>
        <div class="media-browser" :class="mediaBrowserClasses">
            <div class="media-browser-content">
                <SMLoadingIcon v-if="mediaLoading" />
                <div
                    v-if="!mediaLoading && mediaItems.length == 0"
                    class="media-none">
                    <ion-icon name="sad-outline"></ion-icon>
                    <p>No media found</p>
                </div>
                <ul v-if="!mediaLoading && mediaItems.length > 0">
                    <li
                        v-for="item in mediaItems"
                        :key="item.id"
                        :class="[{ selected: item.id == selected }]"
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
                        <span class="media-title">{{ item.title }}</span>
                    </li>
                </ul>
            </div>
        </div>
        <SMRow>
            <SMPagination
                v-model="page"
                :total="totalItems"
                :per-page="perPage"
                size="small"
                class="mt-1" />
        </SMRow>
        <SMButtonRow>
            <template #left>
                <SMButton
                    type="button"
                    label="Cancel"
                    @click="handleClickCancel" />
            </template>
            <template #right>
                <SMButton
                    v-if="props.allowUpload"
                    type="button"
                    label="Upload"
                    @click="handleClickUpload" />
                <SMButton
                    type="primary"
                    label="Insert"
                    :disabled="selected.length == 0"
                    @click="handleClickInsert" />
            </template>
        </SMButtonRow>
        <input
            v-if="props.allowUpload"
            id="file"
            ref="refUploadInput"
            type="file"
            style="display: none"
            :accept="computedAccepts"
            @change="handleChangeUpload" />
    </SMFormCard>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, Ref, watch } from "vue";
import { closeDialog } from "../SMDialog";
import { api } from "../../helpers/api";
import { Media, MediaCollection, MediaResponse } from "../../helpers/api.types";
import { bytesReadable } from "../../helpers/types";
import { useApplicationStore } from "../../store/ApplicationStore";
import SMButton from "../SMButton.vue";
import SMFormCard from "../SMFormCard.vue";
import SMLoadingIcon from "../SMLoadingIcon.vue";
import { mediaGetVariantUrl } from "../../helpers/media";
import SMToolbar from "../SMToolbar.vue";
import SMInput from "../SMInput.vue";
import SMGroupButtons from "../SMGroupButtons.vue";
import SMPagination from "../SMPagination.vue";
import SMButtonRow from "../SMButtonRow.vue";

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
 * Is the dialog loading/busy
 */
const dialogLoading = ref(false);

/**
 * The dialog loading message to display
 */
const dialogLoadingMessage = ref("");

/**
 * The form user message to display
 */
const formMessage = ref("");

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
 *
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
const handleClickInsert = () => {
    if (selected.value !== "") {
        const mediaItem = getMediaItem(selected.value);
        if (mediaItem != null) {
            closeDialog(mediaItem);
            return;
        }
    }

    closeDialog(false);
};

/**
 * Handle user clicking a media item (selecting).
 *
 * @param {string} item_id The media id.
 */
const handleClickItem = (item_id: string): void => {
    selected.value = item_id;
};

/**
 * Handle user double clicking a media item.
 *
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
 *
 * @param name
 */
const handleClickLayout = (name: string) => {
    listActive.value = name;
    mediaBrowserClasses.value = [`media-browser-${listActive.value}`];
};

/**
 * When the user clicks the upload button
 */
const handleClickUpload = async () => {
    if (refUploadInput.value != null) {
        refUploadInput.value.click();
    }
};

/**
 * Upload the file to the server.
 */
const handleChangeUpload = async () => {
    formMessage.value = "";

    if (refUploadInput.value != null && refUploadInput.value.files != null) {
        const firstFile: File | undefined = refUploadInput.value.files[0];
        if (firstFile != null) {
            let submitFormData = new FormData();
            submitFormData.append("file", firstFile);

            dialogLoading.value = true;
            dialogLoadingMessage.value = "Uploading file...";

            try {
                let result = await api.post({
                    url: "/media",
                    body: submitFormData,
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                    progress: (progressData) =>
                        (dialogLoadingMessage.value = `Uploading Files ${Math.floor(
                            (progressData.loaded / progressData.total) * 100
                        )}%`),
                });

                if (result.data) {
                    const data = result.data as MediaResponse;

                    if (
                        data.medium.status != "" &&
                        data.medium.status.startsWith("Failed") == false
                    ) {
                        dialogLoadingMessage.value = `${data.medium.status}...`;

                        let mediaProcessed = false;

                        while (mediaProcessed == false) {
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
                                        updateData.medium.status == "" &&
                                        data.medium.status.startsWith(
                                            "Failed"
                                        ) == false
                                    ) {
                                        mediaProcessed = true;
                                    } else {
                                        dialogLoadingMessage.value = `${updateData.medium.status}...`;
                                    }
                                } else {
                                    throw "error";
                                }
                            } catch {
                                mediaProcessed = true;
                                formMessage.value =
                                    "An server error occurred processing the file";
                            }
                        }

                        dialogLoadingMessage.value;
                    }

                    closeDialog(data.medium);
                } else {
                    formMessage.value =
                        "An unexpected response was received from the server";
                }
            } catch (error) {
                if (error.status === 413) {
                    formMessage.value =
                        "The selected file is larger than the maximum size limit";
                } else {
                    formMessage.value =
                        error.response?.data?.message ||
                        "An unexpected error occurred";
                }
            } finally {
                dialogLoading.value = false;
            }
        } else {
            formMessage.value = "No file was selected to upload";
        }
    } else {
        formMessage.value = "No file was selected to upload";
    }
};

const itemSearch = ref("");

const handleSearch = () => {
    mediaItems.value = [];
    totalItems.value = 0;
    page.value = 1;

    handleLoad();
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
            formMessage.value =
                error?.data?.message || "An unexpected error occurred";
        })
        .finally(() => {
            mediaLoading.value = false;
        });
};

/**
 * Handle a keyboard event in this component.
 *
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
            border: 1px solid $border-color;
            background-color: #fff;
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
                        border-color: $primary-color-dark;
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
}
</style>
