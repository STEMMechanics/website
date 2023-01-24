<template>
    <SMModal>
        <SMDialog
            :loading="formLoading"
            full
            :loading_message="formLoadingMessage">
            <h1>Insert Media</h1>
            <SMMessage
                v-if="formMessage.message"
                :icon="formMessage.icon"
                :type="formMessage.type"
                :message="formMessage.message" />
            <div v-if="mediaItems.length > 0" class="media-browser">
                <ul class="media-browser-list">
                    <li
                        v-for="item in mediaItems"
                        :key="item.id"
                        :class="[{ selected: item == selected }]"
                        @click="handleSelection(item)"
                        @dblclick="handlePickSelection(item)">
                        <img :src="item.url" :title="item.title" />
                    </li>
                </ul>
                <div class="media-browser-page-info">
                    <span class="media-browser-page-number"
                        >Page {{ page }} of {{ totalPages }}</span
                    >
                    <span class="media-browser-page-changer">
                        <font-awesome-icon
                            :class="[
                                'changer-button',
                                { disabled: prevDisabled },
                            ]"
                            icon="fa-solid fa-angle-left"
                            @click="handlePrev" />
                        <font-awesome-icon
                            :class="[
                                'changer-button',
                                { disabled: nextDisabled },
                            ]"
                            icon="fa-solid fa-angle-right"
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
import axios from "axios";
import { computed, watch, ref, reactive, onMounted, onUnmounted } from "vue";
import { closeDialog } from "vue3-promise-dialog";
import SMButton from "../SMButton.vue";
import SMFormFooter from "../SMFormFooter.vue";
import SMDialog from "../SMDialog.vue";
import SMMessage from "../SMMessage.vue";
import SMModal from "../SMModal.vue";
import { toParamString } from "../../helpers/common";

const uploader = ref(null);
const formLoading = ref(false);
const formLoadingMessage = ref("");
const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

const page = ref(1);
const totalItems = ref(0);
const mediaItems = ref([]);
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

const handleSelection = (item) => {
    selected.value = item;
};

const handlePickSelection = (item) => {
    closeDialog(item);
};

const handleLoad = async () => {
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";
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

        let res = await axios.get(`media${toParamString(params)}`);

        totalItems.value = res.data.total;
        mediaItems.value = res.data.media;
    } catch (error) {
        if (error.response.status == 404) {
            formMessage.type = "primary";
            formMessage.icon = "fa-regular fa-folder-open";
            formMessage.message = "No media items found";
        } else {
            formMessage.message =
                error.response?.data?.message || "An unexpected error occurred";
        }
    }
};

const handleAskUpload = () => {
    uploader.value.click();
};

const handleUpload = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    try {
        let submitFormData = new FormData();
        if (uploader.value.files[0] instanceof File) {
            submitFormData.append("file", uploader.value.files[0]);

            let res = await axios.post("media", submitFormData, {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
                onUploadProgress: (progressEvent) =>
                    (formLoadingMessage.value = `Uploading Files ${Math.floor(
                        (progressEvent.loaded / progressEvent.total) * 100
                    )}%`),
            });

            if (res.data.medium) {
                closeDialog(res.data.medium);
            } else {
                formMessage.message =
                    "An unexpected response was received from the server";
            }
        } else {
            formMessage.message = "No file was selected to upload";
        }
    } catch (err) {
        console.log(err);
        formMessage.message =
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
.media-browser-list {
    border: 1px solid $border-color;
    background-color: #fff;
    overflow: auto;
    max-height: 40vh;
    display: flex;
    list-style-type: none;
    margin: 0 0 1rem 0;
    padding: map-get($spacer, 3);
    justify-content: center;
    gap: 0.3rem;
    flex-wrap: wrap;

    li {
        display: flex;
        height: 7.5rem;
        width: 13rem;
        border: 3px solid transparent;
        padding: 1px;

        &.selected {
            border-color: $primary-color-darker;
        }

        img {
            width: 100%;
            height: 100%;
        }
    }
}

.media-browser-page-info {
    margin-bottom: 1rem;
    display: flex;
    justify-content: flex-end;

    .media-browser-page-changer {
        margin-left: 1rem;
    }

    .changer-button {
        cursor: pointer;
        transition: color 0.1s ease-in;
        color: $font-color;
        margin: 0 0.25rem;

        &.disabled {
            cursor: not-allowed;
            color: $secondary-color;
        }

        &:not(.disabled) {
            &:hover {
                color: $primary-color;
            }
        }
    }
}
</style>
