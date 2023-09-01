<template>
    <SMPageStatus v-if="!userHasPermission('admin/media')" :status="403" />
    <template v-else>
        <SMMastHead
            :title="pageHeading"
            :back-link="{ name: 'dashboard-media-list' }"
            back-title="Back to Media" />
        <SMLoading v-if="form.loading()" />
        <div v-else class="max-w-4xl mx-auto px-4 mt-8">
            <SMForm
                :model-value="form"
                @submit="handleSubmit"
                @failed-validation="handleFailValidation">
                <div>
                    <SMImageGallery class="mb-4" :model-value="galleryItems" />
                </div>
                <SMSelectFile
                    v-if="!editMultiple"
                    control="file"
                    upload-only
                    accepts="*"
                    class="mb-4" />
                <SMInput control="title" class="mb-4" />
                <SMInput control="permission" class="mb-4" />
                <div
                    v-if="!editMultiple"
                    class="flex flex-col md:flex-row gap-4">
                    <SMInput
                        class="mb-4"
                        v-model="computedFileSize"
                        disabled
                        label="File Size" />
                    <SMInput
                        class="mb-4"
                        v-model="fileData.mime_type"
                        disabled
                        label="File Mime Type" />
                </div>
                <div
                    v-if="!editMultiple"
                    class="flex flex-col md:flex-row gap-4">
                    <SMInput
                        class="mb-4"
                        v-model="fileData.status"
                        disabled
                        label="Status" />
                    <SMInput
                        class="mb-4"
                        v-model="fileData.dimensions"
                        disabled
                        label="Dimensions" />
                </div>
                <SMInput
                    v-if="!editMultiple"
                    class="mb-4"
                    v-model="fileData.url"
                    disabled
                    label="URL" />
                <SMInput class="mb-4" textarea control="description" />
                <div class="flex flex-justify-end gap-4">
                    <button
                        v-if="route.params.id"
                        type="button"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-red-600 hover:bg-red-500 text-white cursor-pointer"
                        @click="handleDelete">
                        {{ editMultiple ? "Delete All" : "Delete" }}
                    </button>
                    <input
                        role="button"
                        type="submit"
                        class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                        :value="editMultiple ? 'Save All' : 'Save'" />
                </div>
            </SMForm>
        </div>
    </template>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ApiOptions, api } from "../../helpers/api";
import { Form, FormControl } from "../../helpers/form";
import { bytesReadable } from "../../helpers/types";
import { And, Required } from "../../helpers/validate";
import {
    Media,
    MediaJobResponse,
    MediaResponse,
} from "../../helpers/api.types";
import { closeDialog, openDialog } from "../../components/SMDialog";
import DialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMForm from "../../components/SMForm.vue";
import SMInput from "../../components/SMInput.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMLoading from "../../components/SMLoading.vue";
import { useToastStore } from "../../store/ToastStore";
import SMPageStatus from "../../components/SMPageStatus.vue";
import SMSelectFile from "../../components/SMSelectFile.vue";
import { userHasPermission } from "../../helpers/utils";
import SMImageGallery from "../../components/SMImageGallery.vue";
import { toTitleCase } from "../../helpers/string";
import SMDialogProgress from "../../components/dialogs/SMDialogProgress.vue";

const route = useRoute();
const router = useRouter();

const pageError = ref(200);
const editMultiple = "id" in route.params && route.params.id.includes(",");
const pageHeading = route.params.id
    ? editMultiple
        ? "Edit Multiple Media"
        : "Edit Media"
    : "Upload Media";
const galleryItems = ref([]);

const form = reactive(
    Form({
        file: FormControl("", And([Required()])),
        title: FormControl("", Required()),
        description: FormControl(),
        permission: FormControl(),
    }),
);

const fileData = reactive({
    url: "Not available",
    mime_type: "--",
    size: 0,
    storage: "--",
    status: "--",
    dimensions: "--",
    user: {},
});

const imageUrl = ref("");

const handleLoad = async () => {
    if (route.params.id) {
        if (editMultiple === false) {
            try {
                form.loading(true);

                let result = await api.get({
                    url: "/media/{id}",
                    params: {
                        id: route.params.id,
                    },
                });

                const data = result.data as MediaResponse;

                form.controls.file.value = data.medium;
                form.controls.title.value = data.medium.title;
                form.controls.description.value = data.medium.description;
                form.controls.permission.value = data.medium.permission;
                fileData.url = data.medium.url;
                fileData.mime_type = data.medium.mime_type;
                fileData.size = data.medium.size;
                fileData.storage = data.medium.storage;
                fileData.status =
                    data.medium.status == "" ? "OK" : data.medium.status;

                fileData.dimensions = data.medium.dimensions;

                imageUrl.value = fileData.url;
            } catch (err) {
                pageError.value = err.status;
            } finally {
                form.loading(false);
            }
        } else {
            (route.params.id as string).split(",").forEach(async (id) => {
                try {
                    form.loading(true);

                    let result = await api.get({
                        url: "/media/{id}",
                        params: {
                            id: id,
                        },
                    });

                    const data = result.data as MediaResponse;
                    galleryItems.value.push(data.medium);
                } catch (err) {
                    pageError.value = err.status;
                } finally {
                    form.loading(false);
                }
            });
        }
    }
};

const dialogDataSetStatus = (dialogData, status, progress, add) => {
    if (add) {
        dialogData.rows.push(status);
        dialogData.progress.push(progress);
    } else {
        const index = dialogData.rows.length - 1;
        if (status.length > 0) {
            dialogData.rows[index] = status;
        }
        if (progress > -1) {
            dialogData.progress[index] = progress;
        }
    }
};

const handleSubmit = async (enableFormCallBack) => {
    if (editMultiple === false) {
        let dialogData = ref({
            title: "Upload Media",
            rows: [],
            progress: [],
        });

        openDialog(SMDialogProgress, dialogData.value);
        let submitData = new FormData();

        // add file if there is one
        if (form.controls.file.value instanceof File) {
            submitData.append("file", form.controls.file.value);
            dialogDataSetStatus(
                dialogData.value,
                `Uploading File: ${form.controls.file.value.name}`,
                0,
                true,
            );
        }

        submitData.append("title", form.controls.title.value as string);
        submitData.append(
            "permission",
            form.controls.permission.value as string,
        );
        submitData.append(
            "description",
            form.controls.description.value as string,
        );

        let apiRequest: ApiOptions = {
            url: "/media",
            body: submitData,
            headers: {
                "Content-Type": "multipart/form-data",
            },
            progress: (progressEvent) => {
                dialogDataSetStatus(
                    dialogData.value,
                    "",
                    Math.floor(
                        (progressEvent.loaded / progressEvent.total) * 100,
                    ),
                    false,
                );
            },
        };

        if (submitData.has("file") == true) {
            apiRequest.chunk = "file";
        }

        if (route.params.id) {
            apiRequest.url = "/media/{id}";
            apiRequest.method = "PUT";
            apiRequest.params = {
                id: route.params.id,
            };
        }

        api.chunk(apiRequest)
            .then((result) => {
                if (submitData.has("file") == true) {
                    dialogDataSetStatus(
                        dialogData.value,
                        "Upload Complete",
                        100,
                        false,
                    );
                }

                dialogDataSetStatus(dialogData.value, "Processing", 0, true);

                const mediaJobId = (result.data as MediaJobResponse).media_job
                    .id;
                const mediaJobUpdate = async () => {
                    api.get({
                        url: "/media/job/{id}",
                        params: {
                            id: mediaJobId,
                        },
                    })
                        .then((result) => {
                            const data = result.data as MediaJobResponse;

                            const statusText = toTitleCase(
                                data.media_job.status_text,
                            );

                            if (data.media_job.status != "complete") {
                                if (data.media_job.status == "queued") {
                                    dialogDataSetStatus(
                                        dialogData.value,
                                        "Queued for processing",
                                        0,
                                        false,
                                    );
                                } else if (
                                    data.media_job.status == "processing"
                                ) {
                                    dialogDataSetStatus(
                                        dialogData.value,
                                        statusText,
                                        data.media_job.progress,
                                        false,
                                    );
                                } else if (
                                    data.media_job.status == "invalid" ||
                                    data.media_job.status == "failed"
                                ) {
                                    useToastStore().addToast({
                                        title: "Error Processing Media",
                                        content: statusText,
                                        type: "danger",
                                    });

                                    form.controls.file.setValidationResult(
                                        false,
                                        statusText,
                                    );

                                    closeDialog();
                                    enableFormCallBack();
                                    return;
                                }

                                window.setTimeout(mediaJobUpdate, 500);
                            } else {
                                useToastStore().addToast({
                                    title: route.params.id
                                        ? "Media Updated"
                                        : "Media Created",
                                    content: route.params.id
                                        ? "The media item has been updated."
                                        : "The media item been created.",
                                    type: "success",
                                });

                                closeDialog();
                                enableFormCallBack();

                                // return to dashboard
                                const urlParams = new URLSearchParams(
                                    window.location.search,
                                );
                                const returnUrl = urlParams.get("return");
                                if (returnUrl) {
                                    router.push(decodeURIComponent(returnUrl));
                                } else {
                                    router.push({
                                        name: "dashboard-media-list",
                                    });
                                }
                                return;
                            }
                        })
                        .catch(() => {
                            useToastStore().addToast({
                                title: "Error Uploading Media",
                                content: "A server error occurred.",
                                type: "danger",
                            });

                            closeDialog();
                            enableFormCallBack();
                        });
                };

                mediaJobUpdate();
            })
            .catch((error) => {
                if (error.status == 413) {
                    form.controls.file.setValidationResult(
                        false,
                        "The file size is too large",
                    );

                    useToastStore().addToast({
                        title: "Error Uploading Media",
                        content: "The file size is too large.",
                        type: "danger",
                    });
                } else {
                    useToastStore().addToast({
                        title: "Error Uploading Media",
                        content: "A server error occurred.",
                        type: "danger",
                    });
                }

                closeDialog();
                enableFormCallBack();
            });
    } else {
        let successCount = 0;
        let errorCount = 0;

        (route.params.id as string).split(",").forEach(async (id) => {
            try {
                let data = {
                    title: form.controls.title.value,
                    content: form.controls.content.value,
                };

                await api.put({
                    url: "/media/{id}",
                    params: {
                        id: id,
                    },
                    body: data,
                });

                successCount++;
            } catch (err) {
                errorCount++;
            }
        });

        if (errorCount === 0) {
            useToastStore().addToast({
                title: "Media Updated",
                content: `The selected media have been updated.`,
                type: "success",
            });
        } else if (successCount === 0) {
            useToastStore().addToast({
                title: "Error Updating Media",
                content: "An unexpected server error occurred.",
                type: "danger",
            });
        } else {
            useToastStore().addToast({
                title: "Some Media Updated",
                content: `Only ${successCount} media items where updated. ${errorCount} could not because of an unexpected error.`,
                type: "warning",
            });
        }
    }

    // const urlParams = new URLSearchParams(window.location.search);
    // const returnUrl = urlParams.get("return");
    // if (returnUrl) {
    //     router.push(decodeURIComponent(returnUrl));
    // } else {
    //     router.push({ name: "dashboard-media-list" });
    // }
};

const handleFailValidation = () => {
    useToastStore().addToast({
        title: "Save Error",
        content:
            "There are some errors in the form. Fix these before continuing.",
        type: "danger",
    });
};

const handleDelete = async () => {
    let result = await openDialog(DialogConfirm, {
        title: "Delete File?",
        text: `Are you sure you want to delete the file <strong>${form.controls.title.value}</strong>?`,
        cancel: {
            type: "secondary",
            label: "Cancel",
        },
        confirm: {
            type: "danger",
            label: "Delete File",
        },
    });

    if (result) {
        try {
            await api.delete({
                url: "/media/{id}",
                params: {
                    id: route.params.id,
                },
            });
            router.push({ name: "media" });
        } catch (error) {
            useToastStore().addToast({
                title: "Error Deleting File",
                content:
                    error.data?.message ||
                    "An unexpected server error occurred",
                type: "danger",
            });
        }
    }
};

const computedFileSize = computed(() => {
    if (isNaN(+fileData.size) == true) {
        return fileData.size;
    }

    return bytesReadable(fileData.size);
});

watch(
    () => form.controls.file.value,
    (newValue) => {
        if (typeof newValue === "object" && newValue !== null) {
            if ("type" in newValue && typeof newValue.type === "string") {
                fileData.mime_type = newValue.type;
            } else if (
                "mime_type" in newValue &&
                typeof newValue.mime_type === "string"
            ) {
                fileData.mime_type = newValue.mime_type;
            }

            if ("size" in newValue && typeof newValue.size === "number") {
                fileData.size = newValue.size;
            }
        }
        fileData.mime_type =
            (newValue as File).type || (newValue as Media).mime_type;
        fileData.size = (newValue as File).size;

        if ((form.controls.title.value as string).length == 0) {
            form.controls.title.value = (newValue as File).name
                .replace(/\.[^/.]+$/, "")
                .replace(/[^\w\s]/g, " ")
                .toLowerCase()
                .replace(/\b\w/g, (c) => c.toUpperCase());
        }
    },
);

handleLoad();
</script>

<style lang="scss">
.page-dashboard-media-edit {
    .media-container {
        display: flex;
        justify-content: center;
        align-items: center;
    }
}
</style>
