<template>
    <SMPage permission="admin/events">
        <SMHeading heading="Events" />
        <SMMessage
            v-if="formMessage.message"
            :icon="formMessage.icon"
            :type="formMessage.type"
            :message="formMessage.message" />
        <SMToolbar>
            <template #left>
                <SMButton
                    type="primary"
                    label="Create Event"
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
                    :to="{ name: 'event-edit', params: { id: item.id } }"
                    >{{ item.title }}</router-link
                >
            </template>
            <template #item-actions="item">
                <div class="action-wrapper"></div>
            </template>
        </EasyDataTable>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import EasyDataTable from "vue3-easy-data-table";
import { openDialog } from "vue3-promise-dialog";
import SMDialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMButton from "../../components/SMButton.vue";
import SMHeading from "../../components/SMHeading.vue";
import SMLoadingIcon from "../../components/SMLoadingIcon.vue";
import SMMessage from "../../components/SMMessage.vue";
import SMToolbar from "../../components/SMToolbar.vue";
import { api } from "../../helpers/api";
import { SMDate } from "../../helpers/datetime";
import { debounce } from "../../helpers/debounce";

const router = useRouter();
const search = ref("");

const headers = [
    { text: "Title", value: "title", sortable: true },
    { text: "Starts", value: "start_at", sortable: true },
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
    formMessage.icon = "";
    formMessage.type = "error";
    formMessage.message = "";
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
            url: "/events",
            params: params,
        });

        if (!res.data.events) {
            throw new Error("The server is currently not available");
        }

        items.value = res.data.events;

        items.value.forEach((row) => {
            if (row.start_at !== "undefined") {
                row.start_at = new SMDate(row.start_at, {
                    format: "ymd",
                    utc: true,
                }).relative();
            }
            if (row.created_at !== "undefined") {
                row.created_at = new SMDate(row.creative_at, {
                    format: "ymd",
                    utc: true,
                }).relative();
            }
            if (row.updated_at !== "undefined") {
                row.updated_at = new SMDate(row.updated_at, {
                    format: "ymd",
                    utc: true,
                }).relative();
            }
        });

        serverItemsLength.value = res.data.total;
    } catch (err) {
        // restParseErrors(formData, [formMessage, "message"], err);
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
    router.push({ name: "event-edit", params: { id: item.id } });
};

const handleCreate = () => {
    router.push({ name: "event-create" });
};

const handleEdit = (item) => {
    router.push({ name: "event-edit", params: { id: item.id } });
};

const handleDelete = async (item) => {
    let result = await openDialog(SMDialogConfirm, {
        title: "Delete User?",
        text: `Are you sure you want to delete the event <strong>${item.title}</strong>?`,
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
            await api.delete(`events${item.id}`);
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
