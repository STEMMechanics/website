<template>
    <SMPage permission="admin/users">
        <SMHeading heading="Users" />
        <SMMessage
            v-if="formMessage.message"
            :icon="formMessage.icon"
            :type="formMessage.type"
            :message="formMessage.message" />
        <EasyDataTable
            v-model:server-options="serverOptions"
            :server-items-length="serverItemsLength"
            :loading="formLoading"
            :headers="headers"
            :items="items"
            :search-value="searchValue"
            :header-item-class-name="headerItemClassNameFunction"
            :body-item-class-name="bodyItemClassNameFunction">
            <template #loading>
                <SMLoadingIcon />
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
import { openDialog } from "../../components/SMDialog";
import DialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import SMHeading from "../../components/SMHeading.vue";
import SMLoadingIcon from "../../components/SMLoadingIcon.vue";
import SMMessage from "../../components/SMMessage.vue";
import { api } from "../../helpers/api";
import { SMDate } from "../../helpers/datetime";

const router = useRouter();
const searchValue = ref("");

const headers = [
    { text: "Username", value: "username", sortable: true },
    { text: "First name", value: "first_name", sortable: true },
    { text: "Last name", value: "last_name", sortable: true },
    { text: "Email", value: "email", sortable: true },
    { text: "Phone", value: "phone", sortable: true },
    { text: "Joined", value: "created_at", sortable: true },
    // { text: "Last logged in", value: "lastAttended", width: 200},
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
    formMessage.type = "error";
    formMessage.icon = "alert-circle-outline";
    formMessage.message = "";

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

        let res = await api.get({
            url: "/users",
            params: params,
        });
        items.value = res.data.users;

        items.value.forEach((row) => {
            if (row.created_at !== "undefined") {
                row.created_at = new SMDate(row.created_at, {
                    format: "yMd",
                    utc: true,
                }).relative();
            }
        });

        serverItemsLength.value = res.data.total;
    } catch (err) {
        /* empty */
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

const headerItemClassNameFunction = (header) => {
    if (["position", "actions"].includes(header.value))
        return "easy-data-table-cell-center";
    return "";
};

const bodyItemClassNameFunction = (column) => {
    if (["position", "actions"].includes(column))
        return "easy-data-table-cell-center";
    return "";
};

const handleEdit = (user) => {
    router.push({ name: "dashboard-user-edit", params: { id: user.id } });
};

const handleDelete = async (user) => {
    let result = await openDialog(DialogConfirm, {
        title: "Delete User?",
        text: `Are you sure you want to delete the user <strong>${user.username}</strong>?`,
        cancel: {
            type: "secondary",
            label: "Cancel",
        },
        confirm: {
            type: "danger",
            label: "Delete User",
        },
    });

    if (result == true) {
        try {
            await api.delete(`users${user.id}`);
            loadFromServer();

            formMessage.message = "User deleted successfully";
            formMessage.type = "success";
        } catch (err) {
            formMessage.message = err.response?.data?.message;
        }
    }
};
</script>

<style lang="scss"></style>
