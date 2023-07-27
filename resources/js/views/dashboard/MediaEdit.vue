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
                    <SMImage
                        v-if="!editMultiple"
                        class="mb-8"
                        :src="imageUrl" />
                    <SMImageStack v-else class="mb-8" :src="imageStackUrls" />
                </div>
                <SMSelectImage
                    v-if="!editMultiple"
                    control="file"
                    allow-upload
                    accepts="*"
                    class="mb-8" />
                <SMInput control="title" class="mb-8" />
                <SMInput control="permission" class="mb-8" />
                <div
                    v-if="!editMultiple"
                    class="flex flex-col md:flex-row gap-4">
                    <SMInput
                        class="mb-8"
                        v-model="computedFileSize"
                        type="static"
                        label="File Size" />
                    <SMInput
                        class="mb-8"
                        v-model="fileData.mime_type"
                        type="static"
                        label="File Mime Type" />
                </div>
                <div
                    v-if="!editMultiple"
                    class="flex flex-col md:flex-row gap-4">
                    <SMInput
                        class="mb-8"
                        v-model="fileData.status"
                        type="static"
                        label="Status" />
                    <SMInput
                        class="mb-8"
                        v-model="fileData.dimensions"
                        type="static"
                        label="Dimensions" />
                </div>
                <SMInput
                    v-if="!editMultiple"
                    class="mb-8"
                    v-model="fileData.url"
                    type="static"
                    label="URL" />
                <SMInput class="mb-8" textarea control="description" />
                <input
                    role="button"
                    type="submit"
                    class="font-medium px-6 py-1.5 rounded-md hover:shadow-md transition text-sm bg-sky-600 hover:bg-sky-500 text-white cursor-pointer"
                    :value="editMultiple ? 'Save All' : 'Save'" />
                <button
                    v-if="route.params.id"
                    type="button"
                    @click="handleDelete">
                    {{ editMultiple ? "Delete All" : "Delete" }}
                </button>
            </SMForm>
        </div>
    </template>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../../helpers/api";
import { Form, FormControl } from "../../helpers/form";
import { bytesReadable } from "../../helpers/types";
import { And, FileSize, Required } from "../../helpers/validate";
import { MediaResponse } from "../../helpers/api.types";
import { openDialog } from "../../components/SMDialog";
import DialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMForm from "../../components/SMForm.vue";
import SMInput from "../../components/SMInput.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMLoading from "../../components/SMLoading.vue";
import { useToastStore } from "../../store/ToastStore";
import SMImage from "../../components/SMImage.vue";
import SMImageStack from "../../components/SMImageStack.vue";
import SMPageStatus from "../../components/SMPageStatus.vue";
import SMSelectImage from "../../components/SMSelectImage.vue";
import { userHasPermission } from "../../helpers/utils";

const route = useRoute();
const router = useRouter();

const pageError = ref(200);
const pageLoading = ref(true);
const editMultiple = "id" in route.params && route.params.id.includes(",");
const pageHeading = route.params.id
    ? editMultiple
        ? "Edit Multiple Media"
        : "Edit Media"
    : "Upload Media";
const progressText = ref("");
const imageStackUrls = ref([]);

const form = reactive(
    Form({
        file: FormControl("", And([Required()])),
        title: FormControl(),
        description: FormControl(),
        permission: FormControl(),
    }),
);

const fileData = reactive({
    url: "Not available",
    mime_type: "--",
    size: "--",
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
                let result = await api.get({
                    url: "/media/{id}",
                    params: {
                        id: route.params.id,
                    },
                });

                const data = result.data as MediaResponse;

                form.controls.file.value = data.medium.name;
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
            }
        } else {
            (route.params.id as string).split(",").forEach(async (id) => {
                try {
                    let result = await api.get({
                        url: "/media/{id}",
                        params: {
                            id: id,
                        },
                    });

                    const data = result.data as MediaResponse;
                    imageStackUrls.value.push(data.medium.url);
                } catch (err) {
                    pageError.value = err.status;
                }
            });
        }
    }

    pageLoading.value = false;
};

const handleSubmit = async (enableFormCallBack) => {
    try {
        form.loading(true);
        if (editMultiple === false) {
            let submitData = new FormData();

            // add file if there is one
            if (form.controls.file.value instanceof File) {
                submitData.append("file", form.controls.file.value);
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

            if (route.params.id) {
                await api.put({
                    url: "/media/{id}",
                    params: {
                        id: route.params.id,
                    },
                    body: submitData,
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                    progress: (progressEvent) =>
                        (progressText.value = `Uploading File: ${Math.floor(
                            (progressEvent.loaded / progressEvent.total) * 100,
                        )}%`),
                });
            } else {
                await api.post({
                    url: "/media",
                    body: submitData,
                    headers: {
                        "Content-Type": "multipart/form-data",
                    },
                    progress: (progressEvent) =>
                        (progressText.value = `Uploading File: ${Math.floor(
                            (progressEvent.loaded / progressEvent.total) * 100,
                        )}%`),
                });
            }

            useToastStore().addToast({
                title: route.params.id ? "Media Updated" : "Media Created",
                content: route.params.id
                    ? "The media item has been updated."
                    : "The media item been created.",
                type: "success",
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

        const urlParams = new URLSearchParams(window.location.search);
        const returnUrl = urlParams.get("return");
        if (returnUrl) {
            router.push(decodeURIComponent(returnUrl));
        } else {
            router.push({ name: "dashboard-media-list" });
        }
    } catch (error) {
        useToastStore().addToast({
            title: "Server error",
            content: "An error occurred saving the media.",
            type: "danger",
        });

        enableFormCallBack();
    } finally {
        progressText.value = "";
        form.loading(false);
    }
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
        fileData.mime_type = (newValue as File).type;
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
