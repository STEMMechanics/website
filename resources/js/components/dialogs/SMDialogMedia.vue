<template>
    <SMModal>
        <SMDialog
            :loading="dialogLoading"
            full
            :loading-message="dialogLoadingMessage"
            class="sm-dialog-media">
            <h1>Insert Media</h1>
            <SMMessage
                v-if="formMessage"
                icon="alert-circle-outline"
                type="error"
                :message="formMessage"
                class="d-flex" />
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
                                    backgroundImage: `url('${getFilePreview(
                                        item.url
                                    )}')`,
                                }"
                                class="media-image"></div>
                            <span class="media-title">{{ item.title }}</span>
                            <span class="media-size">{{
                                bytesReadable(item.size)
                            }}</span>
                        </li>
                    </ul>
                </div>
                <div class="media-browser-toolbar">
                    <div class="layout-buttons">
                        <ion-icon
                            name="grid-outline"
                            class="layout-button-grid"
                            @click="handleClickGridLayout"></ion-icon>
                        <ion-icon
                            name="list-outline"
                            class="layout-button-list"
                            @click="handleClickListLayout"></ion-icon>
                    </div>
                    <div class="pagination-buttons">
                        <ion-icon
                            name="chevron-back-outline"
                            :class="[{ disabled: computedDisablePrevButton }]"
                            @click="handleClickPrev" />
                        <span class="pagination-info">{{
                            computedPaginationInfo
                        }}</span>
                        <ion-icon
                            name="chevron-forward-outline"
                            :class="[{ disabled: computedDisableNextButton }]"
                            @click="handleClickNext" />
                    </div>
                </div>
            </div>
            <SMFormFooter>
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
            </SMFormFooter>
            <input
                v-if="props.allowUpload"
                id="file"
                ref="refUploadInput"
                type="file"
                style="display: none"
                :accept="computedAccepts"
                @change="handleChangeUpload" />
        </SMDialog>
    </SMModal>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, Ref, watch } from "vue";
import { closeDialog } from "vue3-promise-dialog";
import { api } from "../../helpers/api";
import { Media, MediaCollection, MediaResponse } from "../../helpers/api.types";
import { bytesReadable } from "../../helpers/types";
import { getFilePreview } from "../../helpers/utils";
import { useApplicationStore } from "../../store/ApplicationStore";
import SMButton from "../SMButton.vue";
import SMDialog from "../SMDialog.vue";
import SMFormFooter from "../SMFormFooter.vue";
import SMLoadingIcon from "../SMLoadingIcon.vue";
import SMMessage from "../SMMessage.vue";
import SMModal from "../SMModal.vue";

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
const perPage = ref(12);

const applicationStore = useApplicationStore();

/**
 * Returns the pagination info
 */
const computedPaginationInfo = computed(() => {
    if (totalItems.value == 0) {
        return "0 - 0 of 0";
    }

    const start = (page.value - 1) * perPage.value + 1;
    const end = start + perPage.value - 1;

    return `${start} - ${end} of ${totalItems.value}`;
});

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
 * Return the total number of pages.
 */
const computedTotalPages = computed(() => {
    return Math.ceil(totalItems.value / perPage.value);
});

/**
 * Return if the previous button should be disabled.
 */
const computedDisablePrevButton = computed(() => {
    return page.value <= 1;
});

/**
 * Return if the next button should be disabled.
 */
const computedDisableNextButton = computed(() => {
    return page.value >= computedTotalPages.value;
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

/**
 * Handle Grid layout request click
 */
const handleClickGridLayout = () => {
    mediaBrowserClasses.value = ["media-browser-grid"];
};

/**
 * Handle List layout request click
 */
const handleClickListLayout = () => {
    mediaBrowserClasses.value = ["media-browser-list"];
};

/**
 * Handle click on previous button
 *
 * @param {MouseEvent} $event The mouse event.
 */
const handleClickPrev = ($event: MouseEvent): void => {
    if (
        $event.target &&
        ($event.target as HTMLElement).classList.contains("disabled") ==
            false &&
        page.value > 1
    ) {
        page.value--;
    }
};

/**
 * Handle click on next button
 *
 * @param {MouseEvent} $event The mouse event.
 */
const handleClickNext = ($event: MouseEvent): void => {
    if (
        $event.target &&
        ($event.target as HTMLElement).classList.contains("disabled") ==
            false &&
        page.value < computedTotalPages.value
    ) {
        page.value++;
    }
};

/**
 * When the user clicks the upload button
 */
const handleClickUpload = () => {
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

                    closeDialog(data.medium);
                } else {
                    formMessage.value =
                        "An unexpected response was received from the server";
                }
            } catch (error) {
                formMessage.value =
                    error.response?.data?.message ||
                    "An unexpected error occurred";
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

/**
 * Load the data of the dialog
 */
const handleLoad = async () => {
    mediaLoading.value = true;

    api.get({
        url: "/media",
        params: {
            page: page.value,
            limit: perPage.value,
        },
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
.sm-dialog-media {
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
            margin: 0 0 1rem 0;

            .media-none {
                font-size: 1.5rem;
                text-align: center;

                ion-icon {
                    font-size: 3rem;
                    margin-bottom: 0.5rem;
                }
            }

            ul {
                display: block;
                list-style-type: none;
                overflow: auto;
                max-height: 40vh;
                height: 100%;
                width: 100%;
                gap: 1rem;
                justify-content: center;
                padding: map-get($spacer, 3);

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

        .media-browser-toolbar {
            display: flex;
            margin-bottom: map-get($spacer, 3);

            .layout-buttons,
            .pagination-buttons {
                flex: 1;
                display: flex;
                align-items: center;
            }

            .layout-buttons {
                ion-icon {
                    &:first-of-type {
                        border-top-right-radius: 0;
                        border-bottom-right-radius: 0;
                    }
                    &:last-of-type {
                        border-top-left-radius: 0;
                        border-bottom-left-radius: 0;
                        border-left: 0;
                    }
                }
            }

            .pagination-buttons {
                justify-content: right;
            }

            ion-icon {
                border: 1px solid $secondary-color;
                border-radius: 4px;
                padding: 0.25rem;

                cursor: pointer;
                transition: color 0.1s ease-in-out,
                    background-color 0.1s ease-in-out;
                color: $font-color;

                &.disabled {
                    cursor: not-allowed;
                    color: $secondary-color;
                }

                &:not(.disabled) {
                    &:hover {
                        background-color: $secondary-color;
                        color: #eee;
                    }
                }
            }

            .pagination-info {
                margin: 0 map-get($spacer, 3);
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
                margin-right: map-get($spacer, 1);
            }

            .media-title {
                flex: 1;
                text-align: left;
            }

            .media-size {
                font-size: 75%;
            }

            .media-browser-toolbar {
                .layout-button-grid {
                    color: $font-color;
                }

                .layout-button-list {
                    color: $primary-color;
                }
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
                height: 194px;
                width: 220px;

                .media-image {
                    min-height: 132px;
                    min-width: 220px;
                }

                .media-title {
                    text-align: center;
                    padding: map-get($spacer, 1) 4px;
                    width: 13rem;
                    display: block;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .media-size {
                    font-size: 75%;
                }
            }

            .media-browser-toolbar {
                .layout-button-grid {
                    color: $primary-color;
                }

                .layout-button-list {
                    color: $font-color;
                }
            }
        }
    }
}
</style>
