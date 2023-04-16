<template>
    <SMPage permission="admin/posts" class="sm-page-post-list">
        <template #container>
            <SMHeading heading="Posts" />
            <SMMessage v-if="formMessage" type="error" :message="formMessage" />
            <SMToolbar>
                <template #left>
                    <SMButton
                        type="primary"
                        label="Create Post"
                        :small="true"
                        @click="handleCreate" />
                </template>
                <template #right>
                    <SMInput
                        v-model="search"
                        label="Search"
                        :small="true"
                        style="max-width: 250px" />
                </template>
            </SMToolbar>

            <EasyDataTable
                v-model:server-options="serverOptions"
                :server-items-length="serverItemsLength"
                :loading="formLoading"
                :headers="headers"
                :items="items"
                :search-value="search">
                <template #loading>
                    <SMLoadingIcon />
                </template>
                <template #item-title="item">
                    <router-link
                        :to="{
                            name: 'dashboard-post-edit',
                            params: { id: item.id },
                        }"
                        >{{ item.title }}</router-link
                    >
                </template>
                <template #item-actions="item">
                    <div class="action-wrapper">
                        <SMButton
                            label="Edit"
                            :dropdown="{
                                duplicate: 'Duplicate',
                                delete: 'Delete',
                            }"
                            @click="handleClick(item, $event)"></SMButton>
                    </div>
                </template>
            </EasyDataTable>
        </template>
    </SMPage>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import { useRouter } from "vue-router";
import EasyDataTable from "vue3-easy-data-table";
import { openDialog } from "../../components/SMDialog";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMButton from "../../components/SMButton.vue";
import SMHeading from "../../components/SMHeading.vue";
import SMInput from "../../depreciated/SMInput-old.vue";
import SMLoadingIcon from "../../components/SMLoadingIcon.vue";
import SMMessage from "../../components/SMMessage.vue";
import SMToolbar from "../../components/SMToolbar.vue";
import { api } from "../../helpers/api";
import { PostCollection, PostResponse } from "../../helpers/api.types";
import { SMDate } from "../../helpers/datetime";
import { debounce } from "../../helpers/debounce";
import { useToastStore } from "../../store/ToastStore";

const router = useRouter();
const search = ref("");

const headers = [
    { text: "Title", value: "title", sortable: true },
    { text: "Published", value: "publish_at", sortable: true },
    { text: "Created", value: "created_at", sortable: true },
    { text: "Updated", value: "updated_at", sortable: true },
    { text: "Actions", value: "actions" },
];

const items = ref([]);
const formMessage = ref("");

const formLoading = ref(false);
const serverItemsLength = ref(0);
const serverOptions = ref({
    page: 1,
    rowsPerPage: 25,
    sortBy: null,
    sortType: null,
});

const handleClick = (item, extra: string): void => {
    if (extra.length == 0) {
        handleEdit(item);
    } else if (extra.toLowerCase() == "duplicate") {
        handleDuplicate(item);
    } else if (extra.toLowerCase() == "delete") {
        handleDelete(item);
    }
};

/**
 * Load the post data from the server.
 */
const loadFromServer = async () => {
    formLoading.value = true;

    try {
        let params = {};
        if (serverOptions.value.sortBy) {
            params["sort"] = serverOptions.value.sortBy;
            if (
                serverOptions.value.sortType &&
                serverOptions.value.sortType === "desc"
            ) {
                params["sort"] = "-" + params["sort"];
            }
        }

        params["page"] = serverOptions.value.page;
        params["limit"] = serverOptions.value.rowsPerPage;

        if (search.value.length > 0) {
            params["title"] = search.value;
        }

        const result = await api.get({
            url: "/posts",
            params: params,
        });

        const data = result.data as PostCollection;

        if (!data || !data.posts) {
            throw new Error("The server is currently not available");
        }

        items.value = data.posts;

        items.value.forEach((row) => {
            if (row.created_at !== "undefined") {
                row.created_at = new SMDate(row.created_at, {
                    format: "yMd",
                    utc: true,
                }).relative();
            }
            if (row.updated_at !== "undefined") {
                row.updated_at = new SMDate(row.updated_at, {
                    format: "yMd",
                    utc: true,
                }).relative();
            }
            if (row.publish_at !== "undefined") {
                row.publish_at = new SMDate(row.publish_at, {
                    format: "yMd",
                    utc: true,
                }).relative();
            }
        });

        serverItemsLength.value = data.total;
    } catch (error) {
        formMessage.value = error.data.message || "An unknown error occurred";
    } finally {
        formLoading.value = false;
    }
};

loadFromServer();

watch(
    serverOptions,
    () => {
        loadFromServer();
    },
    { deep: true }
);

const debouncedFilter = debounce(loadFromServer, 1000);
watch(search, () => {
    debouncedFilter();
});

const handleClickRow = (item) => {
    router.push({ name: "dashboard-post-edit", params: { id: item.id } });
};

const handleCreate = () => {
    router.push({ name: "dashboard-post-create" });
};

const handleEdit = (item) => {
    router.push({ name: "dashboard-post-edit", params: { id: item.id } });
};

const handleDuplicate = async (item) => {
    try {
        let tries = 1;
        let number = 2;

        let originalSlug = item.slug;
        let originalTitle = item.title;

        const slugMatch = originalSlug.match(/-(\d+)$/);
        if (slugMatch == true) {
            number = parseInt(slugMatch[1], 10);

            originalSlug = originalSlug.replace(new RegExp(`-${number}$`), "");
            originalTitle = originalTitle.replace(
                new RegExp(`[- ]${number}$`),
                ""
            );
        }

        delete item.id;
        delete item.created_at;
        delete item.updated_at;

        while (tries < 25) {
            const slug = `${originalSlug}-${number}`;
            try {
                await api.get({
                    url: `/posts/?slug=${slug}`,
                });
            } catch (err) {
                if (err.status === 404) {
                    item.slug = slug;
                    item.title = `${originalTitle} ${number}`;
                    break;
                } else {
                    useToastStore().addToast({
                        title: "Server error",
                        content: "The post could not be duplicated.",
                        type: "danger",
                    });
                    return;
                }
            }

            ++tries;
            ++number;
        }

        const result = await api.post({
            url: "/posts",
            body: item,
        });

        const data = result.data as PostResponse;

        loadFromServer();

        useToastStore().addToast({
            title: "Post duplicated",
            content: "The post was duplicated successfully.",
            type: "success",
        });

        router.push({
            name: "dashboard-post-edit",
            params: { id: data.post.id },
        });
    } catch (err) {
        useToastStore().addToast({
            title: "Server error",
            content: "The post could not be duplicated.",
            type: "danger",
        });
    }
};

const handleDelete = async (item) => {
    let result = await openDialog(SMDialogConfirm, {
        title: "Delete Post?",
        text: `Are you sure you want to delete the post <strong>${item.title}</strong>?`,
        cancel: {
            type: "secondary",
            label: "Cancel",
        },
        confirm: {
            type: "danger",
            label: "Delete Post",
        },
    });

    if (result == true) {
        try {
            await api.delete(`posts${item.id}`);
            loadFromServer();

            formMessage.value.message = "Post deleted successfully";
            formMessage.value.type = "success";
        } catch (err) {
            formMessage.value.message = err.response?.data?.message;
        }
    }
};
</script>

<style lang="scss">
.sm-page-post-list {
    background-color: #f8f8f8;
}
</style>
