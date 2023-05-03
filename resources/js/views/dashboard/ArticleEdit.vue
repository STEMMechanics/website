<template>
    <SMPage :page-error="pageError" permission="admin/articles">
        <SMMastHead
            :title="pageHeading"
            :back-link="{ name: 'dashboard-article-list' }"
            back-title="Back to Articles" />
        <SMContainer class="flex-grow-1">
            <SMForm
                :model-value="form"
                @submit="handleSubmit"
                @failed-validation="handleFailValidation">
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
                        <SMInputAttachments v-model:model-value="attachments" />
                    </SMColumn>
                </SMRow>
                <SMRow>
                    <SMButtonRow>
                        <template #right>
                            <SMButton type="submit" label="Save" />
                        </template>
                    </SMButtonRow>
                </SMRow>
            </SMForm>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import SMButton from "../../components/SMButton.vue";
import SMEditor from "../../components/SMEditor.vue";
import SMForm from "../../components/SMForm.vue";
import SMButtonRow from "../../components/SMButtonRow.vue";
import SMInput from "../../components/SMInput.vue";
import SMInputAttachments from "../../components/SMInputAttachments.vue";
import { api } from "../../helpers/api";
import { ArticleResponse, UserCollection } from "../../helpers/api.types";
import { SMDate } from "../../helpers/datetime";
import { Form, FormControl } from "../../helpers/form";
import { And, DateTime, Min, Required } from "../../helpers/validate";
import { useToastStore } from "../../store/ToastStore";
import { useUserStore } from "../../store/UserStore";
import SMMastHead from "../../components/SMMastHead.vue";

const route = useRoute();
const router = useRouter();
const userStore = useUserStore();
let pageError = ref(200);
const authors = ref({});
const attachments = ref([]);
const pageHeading = route.params.id ? "Edit Article" : "Create Article";

let form = reactive(
    Form({
        title: FormControl("", And([Required(), Min(8)])),
        slug: FormControl("", And([Required(), Min(6)])),
        publish_at: FormControl(
            route.params.id ? "" : new SMDate("now").format("d/M/yy h:mm aa"),
            DateTime()
        ),
        hero: FormControl(),
        user_id: FormControl(userStore.id),
        content: FormControl(),
    })
);

const updateSlug = async () => {
    if (form.controls.slug.value == "" && form.controls.title.value != "") {
        let idx = 0;
        let pre_slug = (form.controls.title.value as string)
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
                    url: "/articles",
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
        if (route.params.id) {
            form.loading(true);
            let result = await api.get({
                url: "/articles/{id}",
                params: {
                    id: route.params.id,
                },
            });

            const data = result.data as ArticleResponse;

            if (data && data.article) {
                form.controls.title.value = data.article.title;
                form.controls.slug.value = data.article.slug;
                form.controls.user_id.value = data.article.user.id;
                form.controls.content.value = data.article.content;
                form.controls.publish_at.value = data.article.publish_at
                    ? new SMDate(data.article.publish_at, {
                          format: "yMd",
                          utc: true,
                      }).format("dd/MM/yyyy HH:mm")
                    : "";
                form.controls.content.value = data.article.content;
                form.controls.hero.value = data.article.hero;

                attachments.value = (data.article.attachments || []).map(
                    function (attachment) {
                        return attachment.id.toString();
                    }
                );
            } else {
                pageError.value = 404;
            }
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
            publish_at: new SMDate(
                form.controls.publish_at.value as string
            ).format("yyyy/MM/dd HH:mm:ss", { utc: true }),
            user_id: form.controls.user_id.value,
            content: form.controls.content.value,
            hero: form.controls.hero.value.id,
        };

        let article_id = "";

        if (route.params.id) {
            article_id = route.params.id as string;
            await api.put({
                url: `/articles/{id}`,
                params: {
                    id: route.params.id,
                },
                body: data,
            });
        } else {
            let result = await api.post({
                url: "/articles",
                body: data,
            });

            if (result.data) {
                const data = result.data as ArticleResponse;
                article_id = data.article.id;
            }
        }

        await api.put({
            url: `/articles/${article_id}/attachments`,
            body: {
                attachments: attachments.value,
            },
        });

        useToastStore().addToast({
            title: route.params.id ? "Article Updated" : "Article Created",
            content: route.params.id
                ? "The article has been updated."
                : "The article has been created.",
            type: "success",
        });

        router.push({ name: "dashboard-article-list" });
    } catch (error) {
        form.apiErrors(error);
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
            form.apiErrors(error);
        });
};

loadOptionsAuthors();
loadData();
</script>

<style lang="scss"></style>
