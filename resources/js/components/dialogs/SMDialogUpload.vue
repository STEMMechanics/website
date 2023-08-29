<template>
    <div
        class="fixed top-0 left-0 w-full h-full bg-black bg-op-20 backdrop-blur"></div>
    <div
        class="fixed top-0 left-0 right-0 bottom-0 flex-justify-center flex-items-center flex">
        <div
            class="flex flex-col m-4 border-1 bg-white rounded-xl text-gray-5 px-4 md:px-12 py-4 md:py-8 max-w-200 w-full overflow-hidden"
            v-if="uploadFileCount > 0">
            <h2 class="mb-2">Upload Media</h2>
            <div class="flex flex-col text-xs pb-2 my-4">
                <div class="w-full bg-gray-3 h-3 mb-2 rounded-2">
                    <div
                        class="bg-sky-600 h-3 rounded-2"
                        :style="{
                            width: `${progressUploadPercent}%`,
                        }"></div>
                </div>
                <p class="m-0">
                    {{ progressUploadStatus }}
                </p>
            </div>
            <div class="flex flex-col text-xs my-2">
                <div class="w-full bg-gray-3 h-3 mb-2 rounded-2">
                    <div
                        class="bg-sky-600 h-3 rounded-2"
                        :style="{
                            width: `${
                                (100 / uploadFileCount) * mediaItems.length
                            }%`,
                        }"></div>
                </div>
                <p class="m-0">
                    {{ progressFileStatus }}
                </p>
            </div>
        </div>
        <input
            id="file"
            ref="refUploadInput"
            type="file"
            style="display: none"
            :accept="computedAccepts"
            @change="handleChangeSelectFile" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, Ref } from "vue";
import { closeDialog } from "../SMDialog";
import { api } from "../../helpers/api";
import { ApiInfo, Media, MediaResponse } from "../../helpers/api.types";
import { convertFileNameToTitle } from "../../helpers/utils";
import { isUUID } from "../../helpers/uuid";
import { useToastStore } from "../../store/ToastStore";
import { bytesReadable } from "../../helpers/types";

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
    multiple: {
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
 * Max upload size
 */
const max_upload_size = ref("");

/**
 * List of current media items.
 */
const mediaItems: Ref<Media[]> = ref([]);
const processingItems: Ref<Media[]> = ref([]);

const uploadFileCount = ref(0);
const uploadFileNum = ref(0);
const uploadFileName = ref("");
const uploadFileProgress = ref(0);

const processFileName = ref("");
const processFileStatus = ref("");

const progressUploadPercent = computed(() => {
    if (uploadFileCount.value <= uploadFileNum.value) {
        return 100;
    }

    return (
        (100 / uploadFileCount.value) * uploadFileNum.value +
        (100 / uploadFileCount.value / 100) * uploadFileProgress.value
    );
});

const progressUploadStatus = computed(() => {
    if (uploadFileCount.value <= uploadFileNum.value) {
        return `Uploaded ${uploadFileName.value}`;
    }

    return `Uploading ${uploadFileName.value} - ${uploadFileProgress.value}%`;
});

const progressFileStatus = computed(() => {
    if (processFileName.value.length > 0) {
        return (
            processFileName.value +
            " - " +
            (processFileStatus.value.split(":").length > 1
                ? processFileStatus.value.split(":")[1].trim()
                : processFileStatus.value)
        );
    }

    return "Waiting for upload to complete";
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

const handleChangeSelectFile = async () => {
    if (refUploadInput.value != null && refUploadInput.value.files != null) {
        const fileList = Array.from(refUploadInput.value.files);
        uploadFileCount.value = fileList.length;

        for (
            uploadFileNum.value = 0;
            uploadFileNum.value < uploadFileCount.value;
            uploadFileNum.value++
        ) {
            const file = fileList[uploadFileNum.value];

            let submitFormData = new FormData();
            submitFormData.append("file", file);
            submitFormData.append("title", convertFileNameToTitle(file.name));
            submitFormData.append("description", "");
            try {
                uploadFileName.value = file.name;
                uploadFileProgress.value = 0;

                let result = await api.post({
                    url: "/media",
                    body: submitFormData,
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                    progress: (progressEvent) => {
                        uploadFileProgress.value = Math.floor(
                            (progressEvent.loaded / progressEvent.total) * 100,
                        );
                    },
                });
                if (result.data) {
                    const data = result.data as MediaResponse;
                    processingItems.value.push(data.medium);
                }
            } catch (error) {
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
                processFiles();
            }
        }
    } else {
        closeDialog(false);
    }
};

const processFilesNonce = ref(null);

const processFiles = async () => {
    if (processFilesNonce.value == null) {
        let remaining = false;

        for (let i = 0; i < processingItems.value.length; i++) {
            let item = processingItems.value[i];
            let breakLoop = true;

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
                            let removeItem = false;

                            const updateData =
                                updateResult.data as MediaResponse;
                            if (updateData.medium.status == "OK") {
                                mediaItems.value.push(updateData.medium);
                                removeItem = true;
                            } else if (
                                updateData.medium.status.startsWith("Error") ===
                                true
                            ) {
                                removeItem = true;

                                useToastStore().addToast({
                                    title: "Upload failed",
                                    type: "danger",
                                    content: updateData.medium.status,
                                });
                            } else {
                                processFileName.value = updateData.medium.name;
                                processFileStatus.value =
                                    updateData.medium.status;
                                breakLoop = true;
                            }

                            if (removeItem) {
                                processingItems.value =
                                    processingItems.value.filter(
                                        (mediaItem) =>
                                            mediaItem.id !==
                                            updateData.medium.id,
                                    );
                            }
                        } else {
                            throw "error";
                        }
                    })
                    .catch(() => {
                        /* error retreiving data */
                        processingItems.value = processingItems.value.filter(
                            (mediaItem) => mediaItem.id !== item.id,
                        );
                    });
            }

            if (!breakLoop) {
                break;
            }
        }

        if (remaining) {
            processFilesNonce.value = setTimeout(() => {
                processFilesNonce.value = null;
                processFiles();
            }, 500);
        } else {
            processFilesNonce.value = null;
        }
    }

    if (processFilesNonce.value == null) {
        if (mediaItems.value.length == 0) {
            closeDialog(false);
        }

        if (props.multiple == false) {
            closeDialog(mediaItems.value[0]);
        }

        closeDialog(mediaItems.value);
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

const handleFocus = () => {
    window.setTimeout(() => {
        if (uploadFileCount.value == 0) {
            closeDialog(false);
        }
    }, 20);
};

onMounted(() => {
    window.addEventListener("focus", handleFocus);

    if (refUploadInput.value != null) {
        refUploadInput.value.click();
    }
});

onUnmounted(() => {
    window.removeEventListener("focus", handleFocus);
});
</script>
