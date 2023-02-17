<template>
    <SMPage :page-error="pageError" permission="admin/posts">
        <SMRow>
            <SMDialog>
                <h1>{{ page_title }}</h1>
                <SMForm :model-value="form" @submit="handleSubmit">
                    <SMRow>
                        <SMColumn
                            ><SMInput control="title" @blur="updateSlug()"
                        /></SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn><SMInput control="slug" /></SMColumn>
                        <SMColumn>
                            <SMDatepicker
                                control="publish_at"
                                label="Publish Date"></SMDatepicker>
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMInput
                                control="hero"
                                type="media"
                                label="Hero image"
                                required />
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMSelect
                                control="user_id"
                                label="Created By"
                                :options="authors"></SMSelect>
                        </SMColumn>
                    </SMRow>
                    <SMRow>
                        <SMColumn>
                            <SMEditor
                                v-model:srcContent="form.content.value"
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
                </SMForm>
            </SMDialog>
        </SMRow>
    </SMPage>
</template>

<script setup lang="ts">
import { ref, reactive } from "vue";
import { api } from "../../helpers/api";
import { FormObject, FormControl } from "../../helpers/form";
import { And, Required, Min, DateTime } from "../../helpers/validate";
import {
    timestampLocalToUtc,
    timestampUtcToLocal,
} from "../../helpers/datetime";
import { useUserStore } from "../../store/UserStore";
import { useRoute } from "vue-router";
import SMInput from "../../components/SMInput.vue";
import SMButton from "../../components/SMButton.vue";
import SMDialog from "../../components/SMDialog.vue";
import SMSelect from "../../components/SMSelect.vue";
import SMDatepicker from "../../components/SMDatePicker.vue";
import SMEditor from "../../components/SMEditor.vue";
import SMPage from "../../components/SMPage.vue";
import SMForm from "../../components/SMForm.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";

const route = useRoute();
const userStore = useUserStore();
const page_title = route.params.id ? "Edit Post" : "Create New Post";
const pageError = ref(200);
const authors = ref({});

const form = reactive(
    FormObject({
        title: FormControl("", And([Required(), Min(8)])),
        slug: FormControl("", And([Required(), Min(6)])),
        publish_at: FormControl("", DateTime()),
        hero: FormControl(),
        user_id: FormControl(userStore.id),
        content: FormControl(),
    })
);

const updateSlug = async () => {
    if (form.slug.value == "" && form.title.value != "") {
        let idx = 0;
        let pre_slug = form.title.value
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

                await api.get({
                    url: "/posts",
                    params: {
                        slug: slug,
                    },
                });
                idx++;
            } catch (error) {
                if (error.status == 404) {
                    if (form.slug.value == "") {
                        form.slug.value = slug;
                    }
                }

                return;
            }
        }
    }
};

const loadData = async () => {
    form.loading(true);

    if (route.params.id) {
        try {
            let res = await api.get("/posts/" + route.params.id);
            if (!res.data.post) {
                throw new Error("The server is currently not available");
            }

            form.title.value = res.data.post.title;
            form.slug.value = res.data.post.slug;
            form.user_id.value = res.data.post.user_id;
            form.content.value = res.data.post.content;
            form.publish_at.value = res.data.post.publish_at
                ? timestampUtcToLocal(res.data.post.publish_at)
                : "";
            form.content.value = res.data.post.content;
            form.hero.value = res.data.post.hero;
        } catch (err) {
            pageError.value = err.response.status;
        }
    }

    formLoading.value = false;
};

const handleSubmit = async () => {
    try {
        let data = {
            title: form.title.value,
            slug: form.slug.value,
            publish_at: timestampLocalToUtc(form.publish_at.value),
            user_id: form.user_id.value,
            content: form.content.value,
            hero: form.hero.value,
        };

        if (route.params.id) {
            await api.put({
                url: `/posts/${route.params.id}`,
                body: data,
            });
        } else {
            await api.post({
                url: "/posts",
                body: data,
            });
        }

        form.message("Your details have been updated", "success");
    } catch (err) {
        form.apiError(err);
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
            let res = await api.post({
                url: "/media",
                body: fileFormData,
                headers: {
                    "Content-Type": "multipart/form-data",
                },
                progress: (progressEvent) =>
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
        let res = await api.get({
            url: "/users",
            params: {
                fields: "id,username,first_name,last_name",
                limit: 100,
            },
        });

        if (!res.data.users) {
            throw new Error("The server is currently not available");
        }

        authors.value = {};

        res.data.users.forEach((item) => {
            authors.value[item.id] = `${item.username}`;
        });
    } catch (err) {
        form.apiError(err);
    }
};

loadOptionsAuthors();
loadData();
</script>
