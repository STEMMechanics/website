<template>
    <SMContainer :page-error="pageError" permission="admin/media">
        <SMRow>
            <SMDialog
                :loading="formLoading"
                :loading_message="formLoadingMessage">
                <h1>{{ page_title }}</h1>
                <SMMessage
                    v-if="formMessage.message"
                    :icon="formMessage.icon"
                    :type="formMessage.type"
                    :message="formMessage.message" />
                <form @submit.prevent="handleSubmit">
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                v-model="formData.file.value"
                                type="file"
                                label="File"
                                required
                                :error="formData.file.error"
                                @blur="fieldValidate(formData.file)" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                v-model="formData.url.value"
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
                </form>
            </SMDialog>
        </SMRow>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from "vue";
import SMInput from "../../components/SMInput.vue";
import SMButton from "../../components/SMButton.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMMessage from "../../components/SMMessage.vue";
import axios from "axios";
import {
    useValidation,
    isValidated,
    fieldValidate,
    restParseErrors,
} from "../../helpers/validation";
import { useRoute } from "vue-router";
import { bytesReadable } from "../../helpers/common";
import { useRouter } from "vue-router";

const router = useRouter();
const pageError = ref(200);
const formLoading = ref(false);
const formLoadingMessage = ref("");

const route = useRoute();
const page_title = route.params.id ? "Edit Media" : "Upload Media";

const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});
const formData = reactive({
    file: {
        value: null,
        error: "",
        rules: {
            required: true,
            required_message: "A file is required",
            fileSize: 5242880,
            fileSize_message: "The file is larger than %b",
        },
    },
    url: {
        value: "",
        error: "",
    },
    mime: {
        value: "",
        error: "",
    },
    size: {
        value: "",
        error: "",
    },
    permission: {
        value: "",
        error: "",
    },
});

const handleLoad = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    if (route.params.id) {
        try {
            let res = await axios.get(`media/${route.params.id}`);

            console.log(res.data.media);

            formData.file.value = res.data.media.name;
            formData.permission.value = res.data.media.permission;
            formData.url.value = res.data.media.url;
            formData.mime.value = res.data.media.mime;
            formData.size.value = res.data.media.size;
        } catch (err) {
            console.log(err);
            restParseErrors(formData, [formMessage, "message"], err);
        }
    }

    formLoading.value = false;
};

const handleSubmit = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    try {
        if (isValidated(formData)) {
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
            if (formData.file.value instanceof File) {
                submitFormData.append("file", formData.file.value);
            }

            submitFormData.append("permission", formData.permission.value);

            await axios.post("media", submitFormData, {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
                onUploadProgress: (progressEvent) =>
                    (formLoadingMessage.value = `Uploading Files ${Math.floor(
                        (progressEvent.loaded / progressEvent.total) * 100
                    )}%`),
            });

            formMessage.type = "success";
            formMessage.message = "Your details have been updated";
        }
    } catch (err) {
        restParseErrors(formData, [formMessage, "message"], err);
    }

    formLoading.value = false;
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
            await axios.delete(`media/${item.id}`);
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
    return bytesReadable(formData.size.value);
});

useValidation(formData);
handleLoad();
</script>
