<template>
    <SMPage
        class="sm-page-post-edit"
        :page-error="pageError"
        permission="admin/posts">
        <template #container>
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
                        <SMInput
                            type="datetime"
                            control="publish_at"
                            label="Publish Date" />
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
                        <SMInput
                            control="user_id"
                            label="Created By"
                            type="select"
                            :options="authors" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMEditor
                            v-model:model-value="form.controls.content.value" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMColumn>
                        <SMInputAttachments :model-value="attachments" />
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
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import SMButton from "../../components/SMButton.vue";
import SMEditor from "../../components/SMEditor.vue";
import SMForm from "../../components/SMForm.vue";
import SMFormFooter from "../../components/SMFormFooter.vue";
import SMInput from "../../components/SMInput.vue";
import SMInputAttachments from "../../components/SMInputAttachments.vue";

import { api } from "../../helpers/api";
import { PostResponse, UserCollection } from "../../helpers/api.types";
import { SMDate } from "../../helpers/datetime";
import { Form, FormControl } from "../../helpers/form";
import { And, DateTime, Min, Required } from "../../helpers/validate";
import { useToastStore } from "../../store/ToastStore";
import { useUserStore } from "../../store/UserStore";

const route = useRoute();
const userStore = useUserStore();
const page_title = route.params.id ? "Edit Post" : "Create New Post";
let pageError = ref(200);
const authors = ref({});
const attachments = ref([]);

const form = reactive(
    Form({
        title: FormControl("", And([Required(), Min(8)])),
        slug: FormControl("", And([Required(), Min(6)])),
        publish_at: FormControl("", DateTime()),
        hero: FormControl(),
        user_id: FormControl(userStore.id),
        content: FormControl(),
    })
);

const updateSlug = async () => {
    if (form.controls.slug.value == "" && form.controls.title.value != "") {
        let idx = 0;
        let pre_slug = form.controls.title.value
            .toLowerCase()
            .replace(/[^a-z0-9]/gim, "-")
            .replace(/-+/g, "-")
            .replace(/^-*(.+?)-*$/, "$1");

        // eslint-disable-next-line no-constant-condition
        while (true) {
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
                    if (form.controls.slug.value == "") {
                        form.controls.slug.value = slug;
                    }
                }

                return;
            }
        }
    }
};

/**
 * Load the page data.
 */
const loadData = async () => {
    try {
        form.loading(true);
        if (route.params.id) {
            let result = await api.get({
                url: "/posts/{id}",
                params: {
                    id: route.params.id,
                },
            });

            const data = result.data as PostResponse;

            if (data && data.post) {
                form.controls.title.value = data.post.title;
                form.controls.slug.value = data.post.slug;
                form.controls.user_id.value = data.post.user_id;
                form.controls.content.value = data.post.content;
                form.controls.publish_at.value = data.post.publish_at
                    ? new SMDate(data.post.publish_at, {
                          format: "yMd",
                          utc: true,
                      }).format("dd/MM/yyyy HH:mm")
                    : "";
                form.controls.content.value = data.post.content;
                form.controls.hero.value = data.post.hero;
            } else {
                pageError.value = 404;
            }
        } else {
            pageError.value = 404;
        }
    } catch (error) {
        pageError.value = error.status;
    } finally {
        form.loading(false);
    }
};

const handleSubmit = async () => {
    try {
        let data = {
            title: form.controls.title.value,
            slug: form.controls.slug.value,
            publish_at: new SMDate(form.controls.publish_at.value).format(
                "yyyy/MM/dd HH:mm:ss",
                { utc: true }
            ),
            user_id: form.controls.user_id.value,
            content: form.controls.content.value,
            hero: form.controls.hero.value,
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

        useToastStore().addToast({
            title: route.params.id ? "Post Updated" : "Post Created",
            content: route.params.id
                ? "The post has been updated."
                : "The post has been created.",
            type: "success",
        });

        useRouter().push({ name: "dashboard-post-list" });
    } catch (error) {
        form.apiErrors(error);
    }
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
    api.get({
        url: "/users",
        params: {
            fields: "id,username,first_name,last_name",
            limit: 100,
        },
    })
        .then((result) => {
            const data = result.data as UserCollection;

            if (data && data.users) {
                authors.value = {};

                data.users.forEach((item) => {
                    authors.value[item.id] = `${item.username}`;
                });
            }
        })
        .catch((error) => {
            form.apiError(error);
        });
};

loadOptionsAuthors();
loadData();
</script>

<style lang="scss">
.sm-page-post-edit {
    background-color: #f8f8f8;
}
</style>
