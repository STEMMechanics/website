<template>
    <SMPage permission="admin/users" :page-error="pageError">
        <SMMastHead
            title="Users"
            :back-link="{ name: 'dashboard' }"
            back-title="Return to Dashboard" />
        <SMContainer>
            <SMTable
                :headers="headers"
                :items="items"
                @row-click="handleRowClick">
                <template #item-actions="item">
                    <SMButton
                        label="Edit"
                        :dropdown="{
                            download: 'Download',
                            delete: 'Delete',
                        }"
                        size="medium" />
                </template>
            </SMTable>
        </SMContainer>
    </SMPage>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { openDialog } from "../../components/SMDialog";
import DialogConfirm from "../../components/dialogs/SMDialogConfirm.vue";
import { api } from "../../helpers/api";
import { SMDate } from "../../helpers/datetime";
import SMTable from "../../components/SMTable.vue";
import SMMastHead from "../../components/SMMastHead.vue";
import { useToastStore } from "../../store/ToastStore";

const router = useRouter();
const searchValue = ref("");
const pageError = ref(0);

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
const formLoading = ref(false);
const serverItemsLength = ref(0);
const serverOptions = ref({
    page: 1,
    rowsPerPage: 25,
    sortBy: null,
    sortType: null,
});

const handleRowClick = (item) => {
    router.push({ name: "dashboard-user-edit", params: { id: item.id } });
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

            useToastStore().addToast({
                title: "User Deleted",
                content: "User deleted successfully.",
                type: "success",
            });
        } catch (err) {
            useToastStore().addToast({
                title: "Server Error",
                content: "User could not be deleted because an error occurred.",
                type: "danger",
            });
        }
    }
};
</script>

<style lang="scss"></style>
