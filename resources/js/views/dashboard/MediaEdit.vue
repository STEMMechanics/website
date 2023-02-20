<template>
    <SMPage :page-error="pageError" permission="admin/media">
        <SMRow>
            <SMDialog>
                <h1>{{ page_title }}</h1>
                <SMForm
                    :model-value="form"
                    :loading_message="formLoadingMessage"
                    @submit="handleSubmit">
                    <SMRow>
                        <SMColumn>
                            <SMInput control="file" type="file" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                contorl="url"
                                type="link"
                                label="URL"
                                :href="formData.url.value" />
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
                                v-model="formData.mime.value"
                                type="static"
                                label="File Mime" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                v-model="formData.permission.value"
                                label="Permission"
                                :error="formData.permission.error"
                                @blur="fieldValidate(formData.permission)" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMButton
                                type="danger"
                                label="Delete"
                                @click="handleDelete" />
                        </SMColumn>
                        <SMColumn class="justify-content-end">
                            <SMButton type="submit" label="Save" />
                        </SMColumn>
                    </SMRow>
                </SMForm>
            </SMDialog>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue";
import SMInput from "../../components/SMInput.vue";
import SMButton from "../../components/SMButton.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMForm from "../../components/SMForm.vue";
import SMPage from "../../components/SMPage.vue";
import { api } from "../../helpers/api";
import { FormObject, FormControl } from "../../helpers/form";
import { And, Required, FileSize } from "../../helpers/validate";
import { useRoute } from "vue-router";
import { bytesReadable } from "../../helpers/types";
import { useRouter } from "vue-router";

const router = useRouter();
const pageError = ref(200);
const formLoadingMessage = ref("");

const route = useRoute();
const page_title = route.params.id ? "Edit Media" : "Upload Media";

const form = reactive(
    FormObject({
        file: FormControl("", And([Required(), FileSize(5242880)])),
        permission: FormControl(),
    })
);

const fileData = reactive({
    url: "",
    mime: "",
    size: 0,
});

const handleLoad = async () => {
    if (route.params.id) {
        try {
            let res = await api.get(`media/${route.params.id}`);

            form.file.value = res.data.media.name;
            form.permission.value = res.data.media.permission;
            fileData.url = res.data.media.url;
            fileData.mime = res.data.media.mime;
            fileData.size = res.data.media.size;
        } catch (err) {
            form.apiErrors(err);
        }
    }

    form.loading(false);
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

const handleDelete = async () => {
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
        } catch (err) {
            alert(
                err.response?.data?.message ||
                    "An unexpected server error occurred"
            );
        }
    }
};

const computedFileSize = computed(() => {
    return bytesReadable(fileData.size);
});

handleLoad();
</script>
