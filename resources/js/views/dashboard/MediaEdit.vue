<template>
    <SMPage :page-error="pageError" permission="admin/media">
        <SMMastHead
            :title="pageHeading"
            :back-link="{ name: 'dashboard-media-list' }"
            back-title="Back to Media" />
        <SMContainer class="flex-grow-1">
            <SMLoading v-if="pageLoading" large />
            <SMForm
                v-else
                :model-value="form"
                @submit="handleSubmit"
                @failed-validation="handleFailValidation">
                <SMRow>
                    <SMColumn class="media-container">
                        <!-- <div class="media-container"> -->
                        <SMImage :src="imageUrl" />
                        <!-- </div> -->
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput control="file" type="file" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput control="title" />
                    </SMColumn>
                    <SMColumn>
                        <SMInput control="permission" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput
                            v-model="computedFileSize"
                            type="static"
                            label="File Size" />
                    </SMColumn>
                    <SMColumn>
                        <SMInput
                            v-model="fileData.mime_type"
                            type="static"
                            label="File Mime Type" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput
                            v-model="fileData.status"
                            type="static"
                            label="Status" />
                    </SMColumn>
                    <SMColumn>
                        <SMInput
                            v-model="fileData.dimensions"
                            type="static"
                            label="Dimensions" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput
                            v-model="fileData.url"
                            type="static"
                            label="URL" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInput type="textarea" control="description" />
                    </SMColumn>
                </SMRow>
                <SMButtonRow>
                    <template #right>
                        <SMButton type="submit" label="Save" :form="form" />
                    </template>
                    <template #left>
                        <SMButton
                            :form="form"
                            v-if="route.params.id !== 'create'"
                            type="danger"
                            label="Delete"
                            @click="handleDelete" />
                    </template>
                </SMButtonRow>
            </SMForm>
        </SMContainer>
    </SMPage>
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
import SMButton from "../../components/SMButton.vue";
import SMForm from "../../components/SMForm.vue";
import SMInput from "../../components/SMInput.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMLoading from "../../components/SMLoading.vue";
import { toTitleCase } from "../../helpers/string";
import { useToastStore } from "../../store/ToastStore";
import SMColumn from "../../components/SMColumn.vue";
import SMImage from "../../components/SMImage.vue";
import SMButtonRow from "../../components/SMButtonRow.vue";

const route = useRoute();
const router = useRouter();

const pageError = ref(200);
const pageLoading = ref(true);
const pageHeading =
    route.params.id !== "create" ? "Edit Media" : "Upload Media";

const form = reactive(
    Form({
        file: FormControl("", And([Required(), FileSize({ size: 5242880 })])),
        title: FormControl(),
        description: FormControl(),
        permission: FormControl(),
    })
);

const fileData = reactive({
    url: "",
    mime_type: "",
    size: 0,
    storage: "",
    status: "",
    dimensions: "",
    user: {},
});

const imageUrl = ref("");

const handleLoad = async () => {
    if (route.params.id !== "create") {
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
                data.medium.status == ""
                    ? "OK"
                    : toTitleCase(data.medium.status);

            fileData.dimensions = data.medium.dimensions;

            imageUrl.value = fileData.url;
        } catch (err) {
            pageError.value = err.status;
        }
    }

    pageLoading.value = false;
};

const handleSubmit = async () => {
    try {
        form.loading(true);
        let submitData = new FormData();

        // add file if there is one
        if (form.controls.file.value instanceof File) {
            submitData.append("file", form.controls.file.value);
        }

        submitData.append("title", form.controls.title.value as string);
        submitData.append(
            "permission",
            form.controls.permission.value as string
        );
        submitData.append(
            "description",
            form.controls.description.value as string
        );

        if (route.params.id !== "create") {
            await api.put({
                url: "/media/{id}",
                params: {
                    id: route.params.id,
                },
                body: submitData,
                headers: {
                    "Content-Type": "multipart/form-data",
                },
            });
        } else {
            await api.post({
                url: "/media",
                body: submitData,
                headers: {
                    "Content-Type": "multipart/form-data",
                },
                // progress: (progressEvent) =>
                //     (formLoadingMessage.value = `Uploading Files ${Math.floor(
                //         (progressEvent.loaded / progressEvent.total) * 100
                //     )}%`),
            });
        }

        useToastStore().addToast({
            title:
                route.params.id !== "create"
                    ? "Media Updated"
                    : "Media Created",
            content: route.params.id
                ? "The media item has been updated."
                : "The media item been created.",
            type: "success",
        });

        router.push({ name: "dashboard-media-list" });
    } catch (error) {
        useToastStore().addToast({
            title: "Server error",
            content: "An error occurred saving the media.",
            type: "danger",
        });
    } finally {
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
            await api.delete(`media/${route.params.id}`);
            router.push({ name: "media" });
        } catch (error) {
            pageError.value = error.status;
        }
    }
};

const computedFileSize = computed(() => {
    return bytesReadable(fileData.size);
});

handleLoad();
</script>

<style lang="scss">
.page-dashboard-media-edit {
    .media-container {
        max-height: 300px;
        display: flex;
        justify-content: center;
        align-items: center;
    }
}
</style>
