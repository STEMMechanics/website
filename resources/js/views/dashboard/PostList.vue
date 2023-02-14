<template>
    <SMContainer permission="admin/posts">
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
                    <!-- <font-awesome-icon
                        icon="fa-solid fa-pen-to-square"
                        @click="handleEdit(item)" />
                    <font-awesome-icon
                        icon="fa-regular fa-trash-can"
                        @click="handleDelete(item)" /> -->
                </div>
            </template>
        </EasyDataTable>
    </SMContainer>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from "vue";
import EasyDataTable from "vue3-easy-data-table";
import axios from "axios";
import { relativeDate, toParamString } from "../../helpers/common";
import { useRouter } from "vue-router";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import { openDialog } from "vue3-promise-dialog";

import SMToolbar from "../../components/SMToolbar.vue";
import SMButton from "../../components/SMButton.vue";
import { debounce } from "../../helpers/common";
import SMHeading from "../../components/SMHeading.vue";
import SMMessage from "../../components/SMMessage.vue";
import SMLoadingIcon from "../../components/SMLoadingIcon.vue";

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

        let res = await axios.get(`posts${toParamString(params)}`, {
            redirect: false,
        });
        if (!res.data.posts) {
            throw new Error("The server is currently not available");
        }

        items.value = res.data.posts;

        items.value.forEach((row) => {
            if (row.created_at !== "undefined") {
                row.created_at = relativeDate(
                    timestampUtcToLocal(row.created_at)
                );
            }
            if (row.updated_at !== "undefined") {
                row.updated_at = relativeDate(
                    timestampUtcToLocal(row.updated_at)
                );
            }
            if (row.publish_at !== "undefined") {
                row.publish_at = relativeDate(
                    timestampUtcToLocal(row.publish_at)
                );
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
            await axios.delete(`posts${item.id}`);
            loadFromServer();

            formMessage.message = "Post deleted successfully";
            formMessage.type = "success";
        } catch (err) {
            formMessage.message = err.response?.data?.message;
        }
    }
};
</script>

<style lang="scss"></style>
