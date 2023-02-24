<template>
    <SMModal>
        <SMDialog
            :loading="formLoading"
            full
            :loading_message="formLoadingMessage"
            class="sm-dialog-media">
            <h1>Insert Media</h1>
            <SMMessage
                v-if="formMessage"
                icon="alert-circle-outline"
                type="error"
                :message="formMessage" />
            <div
                v-if="mediaItems.length > 0"
                class="media-browser media-browser-grid">
                <ul>
                    <li
                        v-for="item in mediaItems"
                        :key="item.id"
                        :class="[{ selected: item.id == selected }]"
                        @click="handleSelection(item.id)"
                        @dblclick="handlePickSelection(item.id)">
                        <div
                            :style="{ backgroundImage: `url('${item.url}')` }"
                            class="media-image"></div>
                        <span class="media-title">{{ item.title }}</span>
                        <span class="media-size">{{
                            bytesReadable(item.size)
                        }}</span>
                    </li>
                </ul>
                <div class="media-browser-page-info">
                    <span class="media-browser-layouts">
                        <ion-icon name="grid-outline"></ion-icon>
                        <ion-icon name="list-outline"></ion-icon>
                    </span>
                    <span class="media-browser-page-changer">
                        <ion-icon
                            name="chevron-back-outline"
                            :class="[
                                'changer-button',
                                { disabled: prevDisabled },
                            ]"
                            @click="handlePrev" />
                        <span class="media-browser-page-number"
                            >{{ (page - 1) * perPage + 1 }} -
                            {{ (page - 1) * perPage + 12 }} of
                            {{ totalItems }}</span
                        >
                        <ion-icon
                            name="chevron-forward-outline"
                            :class="[
                                'changer-button',
                                { disabled: nextDisabled },
                            ]"
                            @click="handleNext" />
                    </span>
                </div>
            </div>
            <SMFormFooter>
                <template #left>
                    <SMButton
                        type="button"
                        label="Cancel"
                        @click="handleCancel" />
                </template>
                <template #right>
                    <SMButton
                        type="button"
                        label="Upload"
                        @click="handleAskUpload" />
                    <SMButton
                        type="primary"
                        label="Insert"
                        :disabled="selected.length == 0"
                        @click="handleConfirm" />
                </template>
            </SMFormFooter>
            <input
                id="file"
                ref="uploader"
                type="file"
                style="display: none"
                @change="handleUpload" />
        </SMDialog>
    </SMModal>
</template>

<script setup lang="ts">
import { computed, watch, ref, onMounted, onUnmounted, Ref } from "vue";
import { closeDialog } from "vue3-promise-dialog";
import SMButton from "../SMButton.vue";
import SMFormFooter from "../SMFormFooter.vue";
import SMDialog from "../SMDialog.vue";
import SMMessage from "../SMMessage.vue";
import SMModal from "../SMModal.vue";
import { api } from "../../helpers/api";
import { bytesReadable } from "../../helpers/types";
import { Media } from "../../helpers/api.types";

const props = defineProps({
    mime: {
        type: String,
        default: "image/",
        required: false,
    },
});

const uploader = ref(null);
const formLoading = ref(false);
const formLoadingMessage = ref("");
const formMessage = ref("");

const page = ref(1);
const totalItems = ref(0);
const mediaItems: Ref<Media[]> = ref([]);
const selected = ref("");
const perPage = ref(12);

const handleCancel = () => {
    closeDialog(false);
};

const handleConfirm = () => {
    if (selected.value !== "") {
        closeDialog(selected.value);
    } else {
        closeDialog(false);
    }
};

const handleSelection = (item_id: string): void => {
    selected.value = item_id;
};

const handlePickSelection = (item_id: string): void => {
    closeDialog(item_id);
};

const handleLoad = async () => {
    formMessage.value = "";
    selected.value = "";

    try {
        let params = {
            page: 0,
            limit: 0,
            // fields: "",
        };
        params.page = page.value;
        params.limit = perPage.value;
        // params.fields = "url";

        let res = await api.get({
            url: "/media",
            params: params,
        });

        totalItems.value = res.data.total;
        mediaItems.value = res.data.media;
    } catch (error) {
        if (error.status == 404) {
            // formMessage.type = "primary";
            // formMessage.icon = "folder-open-outline";
            // formMessage.message = "No media items found";
        } else {
            formMessage.value =
                error?.data?.message || "An unexpected error occurred";
        }
    }
};

const handleAskUpload = () => {
    uploader.value.click();
};

const handleUpload = async () => {
    formLoading.value = true;
    formMessage.value = "";

    try {
        let submitFormData = new FormData();
        if (uploader.value.files[0] instanceof File) {
            submitFormData.append("file", uploader.value.files[0]);

            let res = await api.post({
                url: "/media",
                params: {
                    mime: props.mime,
                },
                body: submitFormData,
                headers: {
                    "Content-Type": "multipart/form-data",
                },
                // onUploadProgress: (progressEvent) =>
                //     (formLoadingMessage.value = `Uploading Files ${Math.floor(
                //         (progressEvent.loaded / progressEvent.total) * 100
                //     )}%`),
            });

            if (res.data.medium) {
                closeDialog(res.data.medium);
            } else {
                formMessage.value =
                    "An unexpected response was received from the server";
            }
        } else {
            formMessage.value = "No file was selected to upload";
        }
    } catch (err) {
        console.log(err);
        formMessage.value =
            err.response?.data?.message || "An unexpected error occurred";
    }

    formLoading.value = false;
};

const handlePrev = ($event) => {
    if (
        $event.target.classList.contains("disabled") == false &&
        page.value > 1
    ) {
        page.value--;
    }
};

const handleNext = ($event) => {
    if (
        $event.target.classList.contains("disabled") == false &&
        page.value < totalPages.value
    ) {
        page.value++;
    }
};

const eventKeyUp = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        handleCancel();
    } else if (event.key === "Enter") {
        handleConfirm();
    }
};

onMounted(() => {
    document.addEventListener("keyup", eventKeyUp);
});

onUnmounted(() => {
    document.removeEventListener("keyup", eventKeyUp);
});

const totalPages = computed(() => {
    return Math.ceil(totalItems.value / perPage.value);
});

const prevDisabled = computed(() => {
    return page.value <= 1;
});

const nextDisabled = computed(() => {
    return page.value >= totalPages.value;
});

watch(page, (value) => {
    handleLoad();
});

handleLoad();
</script>

<style lang="scss">
.sm-dialog-media {
    .media-browser {
        ul {
            display: flex;
            list-style-type: none;
            margin: 0 0 1rem 0;
            padding: map-get($spacer, 3);
            overflow: auto;
            max-height: 40vh;
            border: 1px solid $border-color;
            background-color: #fff;
            gap: 1rem;
            justify-content: center;

            li {
                display: flex;
                align-items: center;
            }

            .media-image {
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
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
        }

        &.media-browser-grid {
            ul {
                flex-direction: row;
                flex-wrap: wrap;
            }

            li {
                flex-direction: column;
                height: 11rem;
                width: 13rem;
            }

            .media-image {
                min-height: 7.5rem;
                min-width: 13rem;
                margin-right: map-get($spacer, 1);
            }

            .media-title {
                text-align: center;
                padding: map-get($spacer, 1) 0;
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
    }
}

// .media-browser-list {
//     border: 1px solid $border-color;
//     background-color: #fff;
//     overflow: auto;
//     max-height: 40vh;
//     display: flex;
//     list-style-type: none;
//     margin: 0 0 1rem 0;
//     padding: map-get($spacer, 3);
//     justify-content: center;
//     gap: 0.3rem;
//     flex-wrap: wrap;

//     li {
//         display: flex;
//         height: 7.5rem;
//         width: 13rem;
//         border: 3px solid transparent;
//         padding: 1px;

//         &.selected {
//             border-color: $primary-color-darker;
//         }

//         img {
//             width: 100%;
//             height: 100%;
//         }
//     }
// }

// .media-browser-page-info {
//     margin-bottom: 1rem;
//     display: flex;
//     justify-content: flex-end;

//     .media-browser-page-changer {
//         margin-left: 1rem;
//     }

//     .changer-button {
//         cursor: pointer;
//         transition: color 0.1s ease-in;
//         color: $font-color;
//         margin: 0 0.25rem;

//         &.disabled {
//             cursor: not-allowed;
//             color: $secondary-color;
//         }

//         &:not(.disabled) {
//             &:hover {
//                 color: $primary-color;
//             }
//         }
//     }
// }
</style>
