<template>
    <SMPage permission="admin/posts" class="sm-page-post-list">
        <template #container>
            <SMHeading heading="Posts" />
            <SMMessage
                v-if="formMessage.message"
                :icon="formMessage.icon"
                :type="formMessage.type"
                :message="formMessage.message" />
            <SMToolbar>
                <template #left>
                    <SMButton
                        type="primary"
                        label="Create Post"
                        @click="handleCreate" />
                </template>
                <template #right>
                    <input v-model="search" placeholder="Search" />
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
                        :to="{ name: 'post-edit', params: { id: item.id } }"
                        >{{ item.title }}</router-link
                    >
                </template>
                <template #item-actions="item">
                    <div class="action-wrapper">
                        <SMButton
                            label="Edit"
                            :dropdown="{
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
import { ref, watch, reactive } from "vue";
import { SMDate } from "../../helpers/datetime";
import { useRouter } from "vue-router";
import { openDialog } from "vue3-promise-dialog";
import { api } from "../../helpers/api";
import { debounce } from "../../helpers/debounce";
import EasyDataTable from "vue3-easy-data-table";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMToolbar from "../../components/SMToolbar.vue";
import SMButton from "../../components/SMButton.vue";
import SMHeading from "../../components/SMHeading.vue";
import SMMessage from "../../components/SMMessage.vue";
import SMLoadingIcon from "../../components/SMLoadingIcon.vue";
import SMPage from "../../components/SMPage.vue";

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
const formMessage = reactive({
    icon: "",
    type: "",
    message: "",
});

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
    } else if (extra.toLowerCase() == "delete") {
        handleDelete(item);
    }
};

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

        let res = await api.get({
            url: "/posts",
            params: params,
        });
        if (!res.data.posts) {
            throw new Error("The server is currently not available");
        }

        items.value = res.data.posts;

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

        serverItemsLength.value = res.data.total;
    } catch (err) {
        console.log(err);
        // formMessage.icon = ''
        // formMessage.type = 'error'
        // formMessage.message = ''
        // restParseErrors(formData, [formMessage, 'message'], err)
    }

    formLoading.value = false;
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
    router.push({ name: "post-edit", params: { id: item.id } });
};

const handleCreate = () => {
    router.push({ name: "post-create" });
};

const handleEdit = (item) => {
    router.push({ name: "post-edit", params: { id: item.id } });
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

            formMessage.message = "Post deleted successfully";
            formMessage.type = "success";
        } catch (err) {
            formMessage.message = err.response?.data?.message;
        }
    }
};
</script>

<style lang="scss">
.sm-page-post-list {
    background-color: #f8f8f8;
}
</style>
