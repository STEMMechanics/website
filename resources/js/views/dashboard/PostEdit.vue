<template>
    <SMContainer :page-error="pageError" permission="admin/posts">
        <SMRow>
            <SMDialog :loading="formLoading">
                <h1>{{ page_title }}</h1>
                <SMMessage
                    v-if="formMessage.message"
                    :icon="formMessage.icon"
                    :type="formMessage.type"
                    :message="formMessage.message" />
                <form @submit.prevent="submit">
                    <SMRow>
                        <SMColumn
                            ><SMInput
                                v-model="formData.title.value"
                                label="Title"
                                required
                                :error="formData.title.error"
                                @blur="
                                    fieldValidate(formData.title);
                                    updateSlug();
                                "
                        /></SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn
                            ><SMInput
                                v-model="formData.slug.value"
                                label="Slug"
                                required
                                :error="formData.slug.error"
                                @blur="fieldValidate(formData.slug)"
                        /></SMColumn>
                        <SMColumn>
                            <SMDatepicker
                                v-model="formData.publish_at.value"
                                label="Publish Date"
                                :error="formData.publish_at.error"
                                @blur="
                                    fieldValidate(formData.publish_at)
                                "></SMDatepicker>
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                v-model="formData.hero.value"
                                type="media"
                                label="Hero image"
                                required />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMSelect
                                v-model="formData.user_id.value"
                                label="Created By"
                                required
                                :options="formData.user_id.options"></SMSelect>
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMEditor
                                v-model:srcContent="formData.content.value"
                                :mime-types="[
                                    'image/png',
                                    'image/jpeg',
                                    'image/gif',
                                ]"
                                @trix-attachment-add="attachmentAdd" />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMFormFooter>
                            <template #right>
                                <SMButton type="submit" label="Save" />
                            </template>
                        </SMFormFooter>
                    </SMRow>
                </form>
            </SMDialog>
        </SMRow>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import SMInput from "../../components/SMInput.vue";
import SMButton from "../../components/SMButton.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMSelect from "../../components/SMSelect.vue";
import SMDatepicker from "../../components/SMDatePicker.vue";
import SMEditor from "../../components/SMEditor.vue";
import SMMessage from "../../components/SMMessage.vue";
import axios from "axios";
import {
    useValidation,
    isValidated,
    fieldValidate,
    restParseErrors,
} from "../../helpers/validation";
import { useRoute } from "vue-router";
import { useUserStore } from "../../store/UserStore";
import SMFormFooter from "../../components/SMFormFooter.vue";
import { timestampLocalToUtc, timestampUtcToLocal } from "../../helpers/common";

const route = useRoute();
const formLoading = ref(false);
const userStore = useUserStore();
const page_title = route.params.id ? "Edit Post" : "Create New Post";
const pageError = ref(200);

const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});
const formData = reactive({
    title: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A post title is required",
            min: 8,
            min_message: "Your post title should be at least 8 letters long",
        },
    },
    slug: {
        value: "",
        error: "",
        rules: {
            required: true,
            required_message: "A slug is required",
            min: 6,
            min_message: "The slug least 6 letters long",
        },
    },
    publish_at: {
        value: null,
        error: "",
        rules: {
            datetime: true,
        },
    },
    hero: {
        value: "",
        error: "",
    },
    user_id: {
        options: {},
        value: userStore.id,
        error: "",
    },
    content: {
        value: "",
        error: "",
    },
});

useValidation(formData);

const updateSlug = async () => {
    if (formData.slug.value == "" && formData.title.value != "") {
        let idx = 0;
        let pre_slug = formData.title.value
            .toLowerCase()
            .replace(/[^a-z0-9]/gim, "-")
            .replace(/-+/g, "-")
            .replace(/^-*(.+?)-*$/, "$1");

        while (1) {
            let slug = pre_slug;

            try {
                if (idx > 1) {
                    slug += "-" + idx;
                }

                await axios.get(`posts?slug=${slug}`);
                idx++;
            } catch (error) {
                if (error.response.status == 404) {
                    if (formData.slug.value == "") {
                        formData.slug.value = slug;
                    }
                }

                return;
            }
        }
    }
};

const loadData = async () => {
    formLoading.value = true;
    formMessage.type = "error";
    formMessage.icon = "fa-solid fa-circle-exclamation";
    formMessage.message = "";

    if (route.params.id) {
        try {
            let res = await axios.get("posts/" + route.params.id);
            if (!res.data.post) {
                throw new Error("The server is currently not available");
            }

            formData.title.value = res.data.post.title;
            formData.slug.value = res.data.post.slug;
            formData.user_id.value = res.data.post.user_id;
            formData.content.value = res.data.post.content;
            formData.publish_at.value = res.data.post.publish_at
                ? timestampUtcToLocal(res.data.post.publish_at)
                : "";
            formData.content.value = res.data.post.content;
            formData.hero.value = res.data.post.hero;
        } catch (err) {
            pageError.value = err.response.status;
        }
    }

    formLoading.value = false;
};

const submit = async () => {
    try {
        if (isValidated(formData)) {
            let data = {
                title: formData.title.value,
                slug: formData.slug.value,
                publish_at: timestampLocalToUtc(formData.publish_at.value),
                user_id: formData.user_id.value,
                content: formData.content.value,
                hero: formData.hero.value,
            };

            if (route.params.id) {
                await axios.put(`posts/${route.params.id}`, data);
            } else {
                await axios.post(`posts`, data);
            }

            formMessage.type = "success";
            formMessage.message = "Your details have been updated";
        }
    } catch (err) {
        console.log(err);
        formMessage.icon = "";
        formMessage.type = "error";
        formMessage.message = "";
        restParseErrors(formData, [formMessage, "message"], err);
    }

    window.scrollTo({
        top: 0,
        left: 0,
        behavior: "smooth",
    });
};

const createStorageKey = (file) => {
    var date = new Date();
    var day = date.toISOString().slice(0, 10);
    var name = date.getTime() + "-" + file.name;
    return ["tmp", day, name].join("/");
};

const attachmentAdd = async (event) => {
    if (event.attachment.file) {
        const key = createStorageKey(event.attachment.file);

        var fileFormData = new FormData();
        fileFormData.append("key", key);
        fileFormData.append("Content-Type", event.attachment.file.type);
        fileFormData.append("file", event.attachment.file);

        try {
            let res = await axios.post("media", fileFormData, {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
                onUploadProgress: (progressEvent) =>
                    event.attachment.setUploadProgress(
                        (progressEvent.loaded * progressEvent.total) / 100
                    ),
            });

            event.attachment.setAttributes({
                url: res.data.media.url,
                href: res.data.media.url,
            });
        } catch (err) {
            event.preventDefault();
            alert(
                err.response?.data?.message ||
                    "An unexpected server error occurred"
            );
        }
    }
};

const loadOptionsAuthors = async () => {
    try {
        let res = await axios.get(
            "users?fields=id,username,first_name,last_name&limit=100",
            { redirect: false }
        );

        if (!res.data.users) {
            throw new Error("The server is currently not available");
        }

        formData.user_id.options = {};

        res.data.users.forEach((item) => {
            formData.user_id.options[item.id] = `${item.username}`;
        });
    } catch (err) {
        formMessage.icon = "";
        formMessage.type = "error";
        formMessage.message = "";
        restParseErrors(formData, [formMessage, "message"], err);
    }
};

loadOptionsAuthors();
loadData();
</script>
