<template>
    <SMPage :page-error="pageError" permission="admin/media">
        <SMMastHead
            :title="pageHeading"
            :back-link="{ name: 'dashboard-media-list' }"
            back-title="Back to Media" />
        <SMContainer class="flex-grow-1">
            <SMLoading v-if="pageLoading" large />
            <SMForm v-else :model-value="form" @submit="handleSubmit">
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
                <SMRow class="px-2 justify-content-space-between">
                    <SMButton
                        type="danger"
                        label="Delete"
                        @click="handleDelete" />
                    <SMButton type="submit" label="Save" />
                </SMRow>
            </SMForm>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api } from "../../helpers/api";
import { Form, FormControl } from "../../helpers/form";
import { bytesReadable } from "../../helpers/types";
import { And, FileSize, Required } from "../../helpers/validate";
import { Media, MediaResponse } from "../../helpers/api.types";
import { openDialog } from "../../components/SMDialog";
import DialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMButton from "../../components/SMButton.vue";
import SMForm from "../../components/SMForm.vue";
import SMInput from "../../components/SMInput.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import SMLoading from "../../components/SMLoading.vue";
import { toTitleCase } from "../../helpers/string";

const route = useRoute();
const router = useRouter();

const pageError = ref(200);
const pageLoading = ref(true);
const pageHeading = route.params.id ? "Edit Media" : "Upload Media";

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

const handleLoad = async () => {
    if (route.params.id) {
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
        } catch (err) {
            pageError.value = err.status;
        }
    }

    pageLoading.value = false;
};

const handleSubmit = async () => {
    try {
        let res = null;
        // let data = {
        //     title: formData.title.value,
        //     slug: formData.slug.value,
        //     user_id: formData.user_id.value,
        //     content: formData.content.value
        // }

        // if(route.params.id) {
        //     res = await axios.put(`posts/${route.params.id}`, data);
        // } else {
        //     res = await axios.post(`posts`, data);
        // }

        let submitFormData = new FormData();
        if (form.file.value instanceof File) {
            submitFormData.append("file", form.file.value);
        }

        submitFormData.append("permission", form.permission.value);

        await api.post({
            url: "/media",
            body: submitFormData,
            headers: {
                "Content-Type": "multipart/form-data",
            },
            progress: (progressEvent) =>
                (formLoadingMessage.value = `Uploading Files ${Math.floor(
                    (progressEvent.loaded / progressEvent.total) * 100
                )}%`),
        });

        form.message("Your details have been updated", "success");
    } catch (err) {
        form.apiErrors(err);
    }

    form.loading(false);
};

const handleDelete = async (item: Media) => {
    let result = await openDialog(DialogConfirm, {
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

    if (result) {
        try {
            await api.delete(`media/${item.id}`);
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
